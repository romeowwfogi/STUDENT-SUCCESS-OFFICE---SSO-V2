<?php
require_once 'middleware/auth.php';
require_once 'connection/db_connect.php';

// Validate step context
$step_id = (int)($_GET['step_id'] ?? 0);
if ($step_id <= 0) {
  $_SESSION['message'] = ['type' => 'error', 'text' => 'Missing or invalid step'];
  header('Location: application_management.php');
  exit;
}

// Fetch step info and applicant type context
$step = null;
$applicant_type_id = 0;
$type_name = '';
$cycle_name = '';
$cycle_id = 0;
if ($st = $conn->prepare('SELECT fs.id, fs.title, fs.step_order, fs.description, fs.is_archived, fs.applicant_type_id, at.name AS type_name, ac.academic_year_start AS ay_start, ac.academic_year_end AS ay_end, ac.id AS cycle_id FROM form_steps fs INNER JOIN applicant_types at ON at.id = fs.applicant_type_id INNER JOIN admission_cycles ac ON ac.id = at.admission_cycle_id WHERE fs.id = ?')) {
  $st->bind_param('i', $step_id);
  $st->execute();
  $res = $st->get_result();
  if ($row = $res->fetch_assoc()) {
    $step = $row;
    $applicant_type_id = (int)($row['applicant_type_id'] ?? 0);
    $type_name = (string)($row['type_name'] ?? '');
    $cycle_id = (int)($row['cycle_id'] ?? 0);
    $ayStart = $row['ay_start'] ?? null;
    $ayEnd   = $row['ay_end'] ?? null;
    $cycle_name = ($ayStart && $ayEnd) ? ('Academic Year ' . $ayStart . '–' . $ayEnd) : '';
  }
  $st->close();
}
if (!$step) {
  $_SESSION['message'] = ['type' => 'error', 'text' => 'Step not found'];
  header('Location: manage_form.php?applicant_type_id=' . urlencode((string)$applicant_type_id));
  exit;
}

// Detect optional columns
$hasNotesCol = false;
try {
  $ck = $conn->query("SHOW COLUMNS FROM form_fields LIKE 'notes'");
  $hasNotesCol = ($ck && $ck->num_rows > 0);
  if ($ck) {
    $ck->close();
  }
} catch (Exception $e) {
  $hasNotesCol = false;
}
// Optional file-related columns
$hasAllowedCol = false;
$hasMaxCol = false;
// Optional conditional visibility columns
$hasVisibleFieldCol = false;
$hasVisibleValueCol = false;
try {
  $ckAllowed = $conn->query("SHOW COLUMNS FROM form_fields LIKE 'allowed_file_types'");
  $hasAllowedCol = ($ckAllowed && $ckAllowed->num_rows > 0);
  if ($ckAllowed) {
    $ckAllowed->close();
  }
} catch (Exception $e) {
  $hasAllowedCol = false;
}
try {
  $ckMax = $conn->query("SHOW COLUMNS FROM form_fields LIKE 'max_file_size_mb'");
  $hasMaxCol = ($ckMax && $ckMax->num_rows > 0);
  if ($ckMax) {
    $ckMax->close();
  }
} catch (Exception $e) {
  $hasMaxCol = false;
}
// Detect conditional visibility columns
try {
  $ckVisField = $conn->query("SHOW COLUMNS FROM form_fields LIKE 'visible_when_field_id'");
  $hasVisibleFieldCol = ($ckVisField && $ckVisField->num_rows > 0);
  if ($ckVisField) {
    $ckVisField->close();
  }
} catch (Exception $e) {
  $hasVisibleFieldCol = false;
}
try {
  $ckVisVal = $conn->query("SHOW COLUMNS FROM form_fields LIKE 'visible_when_value'");
  $hasVisibleValueCol = ($ckVisVal && $ckVisVal->num_rows > 0);
  if ($ckVisVal) {
    $ckVisVal->close();
  }
} catch (Exception $e) {
  $hasVisibleValueCol = false;
}

// Lightweight AJAX: fetch current field data for modal population
if ((isset($_GET['ajax']) || isset($_POST['ajax'])) && isset($_GET['action']) && $_GET['action'] === 'get_field') {
  header('Content-Type: application/json');
  $fid = (int)($_GET['field_id'] ?? 0);
  $out = ['ok' => false];
  if ($fid > 0) {
    if ($ff = $conn->prepare('SELECT id, name, label, input_type, placeholder_text, is_required, field_order, is_archived'
      . ($hasNotesCol ? ', notes' : '')
      . ($hasAllowedCol ? ', allowed_file_types' : '')
      . ($hasMaxCol ? ', max_file_size_mb' : '')
      . ($hasVisibleFieldCol ? ', visible_when_field_id' : '')
      . ($hasVisibleValueCol ? ', visible_when_value' : '')
      . ' FROM form_fields WHERE id = ? AND step_id = ? LIMIT 1')) {
      $ff->bind_param('ii', $fid, $step_id);
      if ($ff->execute()) {
        $res = $ff->get_result();
        if ($row = $res->fetch_assoc()) {
          $out = ['ok' => true, 'field' => $row];
        } else {
          $out = ['ok' => false, 'error' => 'Field not found'];
        }
        $res->close();
      } else {
        $out = ['ok' => false, 'error' => $ff->error];
      }
      $ff->close();
    } else {
      $out = ['ok' => false, 'error' => $conn->error];
    }
  } else {
    $out = ['ok' => false, 'error' => 'Invalid field id'];
  }
  echo json_encode($out);
  exit;
}

// Normalize a label/name to a safe machine key: lowercase, underscores only
function normalize_field_name(string $raw): string
{
  $raw = strtolower($raw);
  // Replace any non a-z0-9 with underscore
  $n = preg_replace('/[^a-z0-9]+/i', '_', $raw);
  // Collapse multiple underscores and trim
  $n = preg_replace('/_+/', '_', $n);
  $n = trim($n, '_');
  if ($n === '') {
    $n = 'field';
  }
  // Ensure it does not start with a digit
  if (preg_match('/^[0-9]/', $n)) {
    $n = 'field_' . $n;
  }
  return $n;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'add_field') {
      $label = trim($_POST['label'] ?? '');
      $name = trim($_POST['name'] ?? '');
      $input_type = trim($_POST['input_type'] ?? 'text');
      $placeholder_text = trim($_POST['placeholder_text'] ?? '');
      $is_required = isset($_POST['is_required']) ? 1 : 0;
      $field_order_raw = trim($_POST['field_order'] ?? '');
      $notes = trim($_POST['notes'] ?? '');
      $allowed_file_types = trim($_POST['allowed_file_types'] ?? '');
      $max_file_size_mb_raw = trim($_POST['max_file_size_mb'] ?? '');
      // Conditional visibility inputs
      $visible_when_field_id_raw = $_POST['visible_when_field_id'] ?? '';
      $visible_when_value = trim($_POST['visible_when_value'] ?? '');

      // Auto-generate/sanitize name from label when needed, and always normalize
      $name = normalize_field_name($name !== '' ? $name : $label);

      if ($label === '' || $name === '') {
        throw new Exception('Please fill all required field details');
      }

      // Validate file settings if type=file and columns exist
      $allowed_file_types_db = null;
      $max_file_size_mb_db = null;
      if ($input_type === 'file') {
        if ($hasAllowedCol) {
          if ($allowed_file_types === '') {
            throw new Exception('Allowed file extensions are required for file fields');
          }
          if (preg_match('/\s/', $allowed_file_types)) {
            throw new Exception('Allowed extensions must be comma-separated without spaces, e.g., .pdf,.jpg');
          }
          if (!preg_match('/^(\.[A-Za-z0-9]+)(,\.[A-Za-z0-9]+)*$/', $allowed_file_types)) {
            throw new Exception('Allowed extensions format invalid. Example: .pdf,.jpg,.png');
          }
          $allowed_file_types_db = $allowed_file_types;
        }
        if ($hasMaxCol) {
          if ($max_file_size_mb_raw === '') {
            throw new Exception('Max file size (MB) is required for file fields');
          }
          if (!preg_match('/^\d+$/', $max_file_size_mb_raw)) {
            throw new Exception('Max file size (MB) must be a positive integer');
          }
          $max_file_size_mb_db = (int)$max_file_size_mb_raw;
          if ($max_file_size_mb_db <= 0 || $max_file_size_mb_db > 2048) {
            throw new Exception('Max file size (MB) must be between 1 and 2048');
          }
        }
      }

      // Validate conditional visibility if columns exist
      $visible_when_field_id_db = null;
      $visible_when_value_db = null;
      if ($hasVisibleFieldCol && $hasVisibleValueCol) {
        if ($visible_when_field_id_raw !== '') {
          $visible_when_field_id_db = (int)$visible_when_field_id_raw;
          if ($visible_when_field_id_db <= 0) {
            $visible_when_field_id_db = null;
          }
        }
        if ($visible_when_field_id_db !== null && $visible_when_value === '') {
          throw new Exception('Trigger value is required when a controller field is selected');
        }
        if ($visible_when_field_id_db !== null) {
          // Validate controller field belongs to same step and is allowed type
          if ($ctl = $conn->prepare('SELECT input_type FROM form_fields WHERE id = ? AND step_id = ?')) {
            $ctl->bind_param('ii', $visible_when_field_id_db, $step_id);
            if (!$ctl->execute()) {
              $ctl->close();
              throw new Exception('Failed to validate controller field');
            }
            $rctl = $ctl->get_result();
            if (!$rctl || $rctl->num_rows === 0) {
              $ctl->close();
              throw new Exception('Invalid controller field for this step');
            }
            $rowCtl = $rctl->fetch_assoc();
            $ctl->close();
            $ctlType = strtolower($rowCtl['input_type'] ?? '');
            $allowedCtl = ['select', 'checkbox', 'radio'];
            if (!in_array($ctlType, $allowedCtl, true)) {
              throw new Exception('Controller field must be select, checkbox, or radio');
            }
            $visible_when_value_db = $visible_when_value !== '' ? $visible_when_value : null;
          }
        }
      }

      // next display order
      $maxOrder = 0;
      if ($ms = $conn->prepare('SELECT COALESCE(MAX(field_order),0) AS maxo FROM form_fields WHERE step_id = ? AND is_archived = 0')) {
        $ms->bind_param('i', $step_id);
        $ms->execute();
        $r = $ms->get_result();
        if ($rw = $r->fetch_assoc()) {
          $maxOrder = (int)$rw['maxo'];
        }
        $ms->close();
      }

      // Build INSERT dynamically with optional columns
      // Decide final order; shift existing orders if inserting into middle
      $providedOrder = null;
      if ($field_order_raw !== '') {
        if (!preg_match('/^\d+$/', $field_order_raw) || (int)$field_order_raw < 1) {
          throw new Exception('Order must be a positive integer');
        }
        $providedOrder = (int)$field_order_raw;
      }
      $nextOrder = $maxOrder + 1;
      $finalOrder = $providedOrder !== null ? $providedOrder : $nextOrder;
      if ($providedOrder !== null) {
        if ($sh = $conn->prepare('UPDATE form_fields SET field_order = field_order + 1 WHERE step_id = ? AND is_archived = 0 AND field_order >= ?')) {
          $sh->bind_param('ii', $step_id, $providedOrder);
          if (!$sh->execute()) {
            $sh->close();
            throw new Exception('Failed to shift existing field orders: ' . $sh->error);
          }
          $sh->close();
        }
      }
      $cols = ['step_id', 'name', 'label', 'input_type', 'placeholder_text', 'is_required', 'field_order', 'is_archived'];
      $place = ['?', '?', '?', '?', '?', '?', '?', '0'];
      $types = 'issssii';
      $params = [$step_id, $name, $label, $input_type, $placeholder_text, $is_required, $finalOrder];
      if ($hasNotesCol) {
        $cols[] = 'notes';
        $place[] = '?';
        $types .= 's';
        $params[] = $notes;
      }
      if ($hasAllowedCol) {
        $cols[] = 'allowed_file_types';
        $place[] = '?';
        $types .= 's';
        $params[] = $allowed_file_types_db;
      }
      if ($hasMaxCol) {
        $cols[] = 'max_file_size_mb';
        $place[] = '?';
        $types .= 'i';
        $params[] = $max_file_size_mb_db;
      }
      if ($hasVisibleFieldCol && $hasVisibleValueCol) {
        $cols[] = 'visible_when_field_id';
        $place[] = '?';
        $types .= 'i';
        $params[] = $visible_when_field_id_db;
        $cols[] = 'visible_when_value';
        $place[] = '?';
        $types .= 's';
        $params[] = $visible_when_value_db;
      }
      $sql = 'INSERT INTO form_fields(' . implode(',', $cols) . ') VALUES(' . implode(',', $place) . ')';
      $ins = $conn->prepare($sql);
      if (!$ins) {
        throw new Exception('Failed to prepare insert: ' . $conn->error);
      }
      $ins->bind_param($types, ...$params);
      if (!$ins->execute()) {
        throw new Exception('Error adding field: ' . $ins->error);
      }
      $ins->close();
      $safeLabel = htmlspecialchars($label, ENT_QUOTES);
      $safeType = htmlspecialchars($input_type, ENT_QUOTES);
      $reqText = $is_required ? 'Yes' : 'No';
      $_SESSION['message'] = [
        'type' => 'success',
        'text' => "Added field \"$safeLabel\" (Type: $safeType • Required: $reqText • Order: $finalOrder)"
      ];
    } elseif ($action === 'update_field') {
      $field_id = (int)($_POST['field_id'] ?? 0);
      $label = trim($_POST['label'] ?? '');
      $name = trim($_POST['name'] ?? '');
      $input_type = trim($_POST['input_type'] ?? 'text');
      $placeholder_text = trim($_POST['placeholder_text'] ?? '');
      $is_required = isset($_POST['is_required']) ? 1 : 0;
      $notes = trim($_POST['notes'] ?? '');
      $field_order = (int)($_POST['field_order'] ?? 0);
      $allowed_file_types = trim($_POST['allowed_file_types'] ?? '');
      $max_file_size_mb_raw = trim($_POST['max_file_size_mb'] ?? '');
      if ($field_id <= 0) throw new Exception('Invalid field');
      // Always normalize name; if empty, derive from label
      $name = normalize_field_name($name !== '' ? $name : $label);
      if ($label === '' || $name === '') throw new Exception('Please fill all required field details');

      // Validate file settings for update
      $allowed_file_types_db = null;
      $max_file_size_mb_db = null;
      if ($input_type === 'file') {
        if ($hasAllowedCol) {
          if ($allowed_file_types === '') {
            throw new Exception('Allowed file extensions are required for file fields');
          }
          if (preg_match('/\s/', $allowed_file_types)) {
            throw new Exception('Allowed extensions must be comma-separated without spaces, e.g., .pdf,.jpg');
          }
          if (!preg_match('/^(\.[A-Za-z0-9]+)(,\.[A-Za-z0-9]+)*$/', $allowed_file_types)) {
            throw new Exception('Allowed extensions format invalid. Example: .pdf,.jpg,.png');
          }
          $allowed_file_types_db = $allowed_file_types;
        }
        if ($hasMaxCol) {
          if ($max_file_size_mb_raw === '') {
            throw new Exception('Max file size (MB) is required for file fields');
          }
          if (!preg_match('/^\d+$/', $max_file_size_mb_raw)) {
            throw new Exception('Max file size (MB) must be a positive integer');
          }
          $max_file_size_mb_db = (int)$max_file_size_mb_raw;
          if ($max_file_size_mb_db <= 0 || $max_file_size_mb_db > 2048) {
            throw new Exception('Max file size (MB) must be between 1 and 2048');
          }
        }
      }

      // Build UPDATE dynamically with optional columns
      $setCols = [
        'name = ?',
        'label = ?',
        'input_type = ?',
        'placeholder_text = ?',
        'is_required = ?',
        'field_order = ?'
      ];
      $types = 'ssssii';
      $params = [$name, $label, $input_type, $placeholder_text, $is_required, $field_order];
      if ($hasNotesCol) {
        $setCols[] = 'notes = ?';
        $types .= 's';
        $params[] = $notes;
      }
      if ($hasAllowedCol) {
        $setCols[] = 'allowed_file_types = ?';
        $types .= 's';
        $params[] = $allowed_file_types_db;
      }
      if ($hasMaxCol) {
        $setCols[] = 'max_file_size_mb = ?';
        $types .= 'i';
        $params[] = $max_file_size_mb_db;
      }
      if ($hasVisibleFieldCol && $hasVisibleValueCol) {
        // Same validation as add_field
        $visible_when_field_id_raw = $_POST['visible_when_field_id'] ?? '';
        $visible_when_value = trim($_POST['visible_when_value'] ?? '');
        $visible_when_field_id_db = null;
        $visible_when_value_db = null;
        if ($visible_when_field_id_raw !== '') {
          $visible_when_field_id_db = (int)$visible_when_field_id_raw;
          if ($visible_when_field_id_db <= 0) {
            $visible_when_field_id_db = null;
          }
        }
        if ($visible_when_field_id_db !== null && $visible_when_value === '') {
          throw new Exception('Trigger value is required when a controller field is selected');
        }
        if ($visible_when_field_id_db !== null) {
          if ($ctl = $conn->prepare('SELECT input_type FROM form_fields WHERE id = ? AND step_id = ?')) {
            $ctl->bind_param('ii', $visible_when_field_id_db, $step_id);
            if (!$ctl->execute()) {
              $ctl->close();
              throw new Exception('Failed to validate controller field');
            }
            $rctl = $ctl->get_result();
            if (!$rctl || $rctl->num_rows === 0) {
              $ctl->close();
              throw new Exception('Invalid controller field for this step');
            }
            $rowCtl = $rctl->fetch_assoc();
            $ctl->close();
            $ctlType = strtolower($rowCtl['input_type'] ?? '');
            $allowedCtl = ['select', 'checkbox', 'radio'];
            if (!in_array($ctlType, $allowedCtl, true)) {
              throw new Exception('Controller field must be select, checkbox, or radio');
            }
            $visible_when_value_db = $visible_when_value !== '' ? $visible_when_value : null;
          }
        }
        $setCols[] = 'visible_when_field_id = ?';
        $types .= 'i';
        $params[] = $visible_when_field_id_db;
        $setCols[] = 'visible_when_value = ?';
        $types .= 's';
        $params[] = $visible_when_value_db;
      }
      $sql = 'UPDATE form_fields SET ' . implode(', ', $setCols) . ' WHERE id = ? AND step_id = ?';
      $types .= 'ii';
      $params[] = $field_id;
      $params[] = $step_id;
      $up = $conn->prepare($sql);
      if (!$up) {
        throw new Exception('Failed to prepare update statement: ' . $conn->error);
      }
      $up->bind_param($types, ...$params);
      if (!$up->execute()) {
        throw new Exception('Error updating field: ' . $up->error);
      }
      $up->close();
      $safeLabel = htmlspecialchars($label, ENT_QUOTES);
      $safeType = htmlspecialchars($input_type, ENT_QUOTES);
      $reqText = $is_required ? 'Yes' : 'No';
      $_SESSION['message'] = [
        'type' => 'success',
        'text' => "Updated field \"$safeLabel\" (Type: $safeType • Required: $reqText • Order: $field_order)"
      ];
    }
  } catch (Exception $e) {
    $_SESSION['message'] = ['type' => 'error', 'text' => $e->getMessage()];
  }
  header('Location: manage_step_fields.php?step_id=' . urlencode((string)$step_id));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
  $action = $_GET['action'] ?? '';
  // Preload label for more informative messages
  $fieldLabel = '';
  try {
    if ($action === 'archive_field' || $action === 'unarchive_field') {
      $field_id = (int)($_GET['field_id'] ?? 0);
      if ($field_id <= 0) throw new Exception('Invalid field');

      // Fetch label for messaging
      if ($stmtLbl = $conn->prepare('SELECT label FROM form_fields WHERE id = ? AND step_id = ?')) {
        $stmtLbl->bind_param('ii', $field_id, $step_id);
        if ($stmtLbl->execute()) {
          $resLbl = $stmtLbl->get_result();
          if ($rowLbl = $resLbl->fetch_assoc()) {
            $fieldLabel = (string)($rowLbl['label'] ?? '');
          }
        }
        $stmtLbl->close();
      }

      $to = ($action === 'archive_field') ? 1 : 0;
      if ($stmt = $conn->prepare('UPDATE form_fields SET is_archived = ? WHERE id = ? AND step_id = ?')) {
        $stmt->bind_param('iii', $to, $field_id, $step_id);
        if (!$stmt->execute()) {
          throw new Exception('Error updating field status: ' . $stmt->error);
        }
        $stmt->close();
      }
      $safeLabel = htmlspecialchars($fieldLabel !== '' ? $fieldLabel : 'this field', ENT_QUOTES);
      $_SESSION['message'] = ['type' => 'success', 'text' => $to ? ('Successfully archived ' . $safeLabel) : ('Successfully unarchived ' . $safeLabel)];
    }
  } catch (Exception $e) {
    $safeLabel = htmlspecialchars($fieldLabel !== '' ? $fieldLabel : 'this field', ENT_QUOTES);
    if ($action === 'archive_field' || $action === 'unarchive_field') {
      $verb = ($action === 'archive_field') ? 'archive' : 'unarchive';
      $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to ' . $verb . ' ' . $safeLabel . ': ' . $e->getMessage()];
    } else {
      $_SESSION['message'] = ['type' => 'error', 'text' => $e->getMessage()];
    }
  }
  header('Location: manage_step_fields.php?step_id=' . urlencode((string)$step_id));
  exit;
}

// Fetch fields for table
$fields = [];
if ($ff = $conn->prepare('SELECT id, name, label, input_type, placeholder_text, is_required, field_order, is_archived'
  . ($hasNotesCol ? ', notes' : '')
  . ($hasAllowedCol ? ', allowed_file_types' : '')
  . ($hasMaxCol ? ', max_file_size_mb' : '')
  . ($hasVisibleFieldCol ? ', visible_when_field_id' : '')
  . ($hasVisibleValueCol ? ', visible_when_value' : '')
  . ' FROM form_fields WHERE step_id = ? ORDER BY is_archived ASC, field_order ASC')) {
  $ff->bind_param('i', $step_id);
  $ff->execute();
  $resF = $ff->get_result();
  while ($row = $resF->fetch_assoc()) {
    $fields[] = $row;
  }
  $ff->close();
}
$currentMaxOrder = 0;
if (!empty($fields)) {
  foreach ($fields as $f) {
    $fo = (int)($f['field_order'] ?? 0);
    if ($fo > $currentMaxOrder) {
      $currentMaxOrder = $fo;
    }
  }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Step Fields</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="dashboard.css">
  <style>
    .card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 1px 2px rgba(16, 24, 40, .05);
      padding: 18px;
    }

    .card__header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 12px;
    }

    .card__title {
      font-weight: 600;
      margin: 0;
    }

    .table {
      border: 1px solid #E2E8F0;
      background: #fff;
    }

    .table thead th {
      background: #E6F1E6;
    }

    .table td,
    .table th {
      border-right: 1px solid #EDF2F7;
    }

    .table tr:last-child td {
      border-bottom: 1px solid #E2E8F0;
    }

    .table__actions {
      display: inline-flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .table__btn,
    .table__btn:link,
    .table__btn:visited,
    .table__btn:hover {
      text-decoration: none !important;
      font-weight: 150;
    }

    .table__btn--update {
      background-color: var(--color-card);
      color: var(--color-accent);
      border: 1.5px solid rgba(16, 185, 129, 0.35);
    }

    .table__btn--update:hover {
      background-color: var(--color-accent);
      color: #fff;
      border-color: var(--color-accent);
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 600;
      font-size: .85rem;
    }

    .pill-open {
      background: #ecfdf5;
      color: #065f46;
      border: 1px solid #10b981;
    }

    .pill-closed {
      background: #fff7ed;
      color: #9a3412;
      border: 1px solid #fb923c;
    }

    .pill-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      display: inline-block;
    }

    .pill-open .pill-dot {
      background: #10b981;
    }

    .pill-closed .pill-dot {
      background: #fb923c;
    }

    #loadingOverlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 3000;
      backdrop-filter: blur(4px);
    }

    .spinner {
      width: 56px;
      height: 56px;
      border: 5px solid rgba(255, 255, 255, 0.25);
      border-top-color: #18a558;
      border-radius: 50%;
      animation: spin .8s linear infinite;
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.4);
      z-index: 1001;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(4px);
    }

    .modal-card {
      background: var(--color-card);
      border-radius: 20px;
      max-width: 560px;
      width: 90%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1), 0 8px 25px rgba(0, 0, 0, 0.08);
      margin: auto;
      overflow: hidden;
      border: 1px solid var(--color-border);
      position: relative;
      color: var(--color-text);
    }

    .modal-card .modal-body {
      padding: 0 32px 24px 32px;
    }

    .form-group {
      margin-bottom: 12px;
    }

    .form-group label {
      display: block;
      font-weight: 500;
      margin-bottom: 6px;
    }

    .form-group input[type="text"],
    .form-group input[type="number"],
    .form-group select {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
  </style>
</head>

<body>
  <?php include 'includes/mobile_navbar.php'; ?>
  <div class="layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
      <header class="header">
        <div class="header__left">
          <h1 class="header__title">Manage Fields</h1>
          <p style="color:#718096; margin:4px 0 0 0; font-size:0.95rem;">
            <?php echo htmlspecialchars($cycle_name ?? 'N/A'); ?> — <?php echo htmlspecialchars($type_name ?? ''); ?>
          </p>
          <p class="header__subtitle">Step <?php echo '#' . (int)($step['step_order'] ?? 0); ?> • <?php echo htmlspecialchars($step['title'] ?? ''); ?></p>
        </div>
        <div class="header__actions">
          <a href="manage_form.php?applicant_type_id=<?php echo (int)$applicant_type_id; ?>" class="btn btn--secondary" title="Back to Steps" onclick="showLoader()">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Steps
          </a>
        </div>
      </header>

      <div id="loadingOverlay" class="loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); display: none; justify-content: center; align-items: center; z-index: 3000; backdrop-filter: blur(4px);">
        <div class="spinner" style="width: 56px; height: 56px; border: 5px solid rgba(255,255,255,0.25); border-top-color: #18a558; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto;"></div>
      </div>
      <style>
        @keyframes spin {
          from {
            transform: rotate(0deg);
          }

          to {
            transform: rotate(360deg);
          }
        }
      </style>

      <?php if (!empty($_SESSION['message'])): $m = $_SESSION['message'];
        unset($_SESSION['message']); ?>
        <script>
          window.addEventListener('DOMContentLoaded', function() {
            var kind = '<?php echo (($m['type'] ?? '') === 'success') ? 'success' : 'error'; ?>';
            var text = <?php echo json_encode($m['text']); ?>;
            window.showStatusModal('', text, kind);
          });
        </script>
      <?php endif; ?>

      <!-- Ultra-basic table block (no theme classes) -->
      <div style="margin: 0 20px 8px 20px; display:none;">
        <h2 style="font-weight:600; margin:0 0 8px 0;">Fields (Plain View)</h2>
        <div style="overflow:auto; border:1px solid #e2e8f0; border-radius:8px;">
          <table style="width:100%; border-collapse:collapse; background:#fff;">
            <thead>
              <tr style="background:#f7fafc;">
                <th style="border:1px solid #e2e8f0; padding:8px;">Order</th>
                <th style="border:1px solid #e2e8f0; padding:8px;">Label</th>
                <th style="border:1px solid #e2e8f0; padding:8px;">Type</th>
                <th style="border:1px solid #e2e8f0; padding:8px;">Required</th>
                <?php if ($hasNotesCol): ?>
                  <th style="border:1px solid #e2e8f0; padding:8px;">Notes</th>
                <?php endif; ?>
                <th style="border:1px solid #e2e8f0; padding:8px;">Status</th>
                <th style="border:1px solid #e2e8f0; padding:8px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($fields)): foreach ($fields as $f): ?>
                  <tr>
                    <td style="border:1px solid #e2e8f0; padding:8px;">#<?php echo (int)$f['field_order']; ?></td>
                    <td style="border:1px solid #e2e8f0; padding:8px;"><?php echo htmlspecialchars($f['label'] ?? ''); ?></td>
                    <td style="border:1px solid #e2e8f0; padding:8px;"><?php echo htmlspecialchars($f['input_type'] ?? ''); ?></td>
                    <td style="border:1px solid #e2e8f0; padding:8px;"><?php echo ((int)($f['is_required'] ?? 0) === 1) ? 'Yes' : 'No'; ?></td>
                    <?php if ($hasNotesCol): ?>
                      <td style="border:1px solid #e2e8f0; padding:8px;"><?php echo htmlspecialchars($f['notes'] ?? ''); ?></td>
                    <?php endif; ?>
                    <td style="border:1px solid #e2e8f0; padding:8px;">
                      <?php $isArchived = ((int)($f['is_archived'] ?? 0) === 1); ?>
                      <?php echo $isArchived ? 'Archived' : 'Active'; ?>
                    </td>
                    <td style="border:1px solid #e2e8f0; padding:8px;">
                      <button type="button" class="js-edit-field" style="padding:6px 10px; border:1px solid #CBD5E0; background:#EDF2F7; border-radius:6px;">Update</button>
                      <?php $ftLower = strtolower($f['input_type'] ?? '');
                      if (in_array($ftLower, ['select', 'radio', 'checkbox'], true)): ?>
                        <a href="manage_step_options.php?field_id=<?php echo urlencode((string)$f['id']); ?>&step_id=<?php echo urlencode((string)$step_id); ?>" class="table__btn table__btn--update" title="Manage Options" style="margin-left:6px;">Manage Options</a>
                      <?php endif; ?>
                      <?php if (!$isArchived): ?>
                        <a href="manage_step_fields.php?step_id=<?php echo urlencode((string)$step_id); ?>&action=archive_field&field_id=<?php echo urlencode((string)$f['id']); ?>" style="margin-left:6px;" data-label="<?php echo htmlspecialchars($f['label'] ?? '', ENT_QUOTES); ?>">Archive</a>
                      <?php else: ?>
                        <a href="manage_step_fields.php?step_id=<?php echo urlencode((string)$step_id); ?>&action=unarchive_field&field_id=<?php echo urlencode((string)$f['id']); ?>" style="margin-left:6px;" data-label="<?php echo htmlspecialchars($f['label'] ?? '', ENT_QUOTES); ?>">Unarchive</a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach;
              else: ?>
                <tr>
                  <td colspan="<?php echo $hasNotesCol ? 7 : 6; ?>" style="border:1px solid #e2e8f0; padding:12px; text-align:center; color:#718096;">No fields yet. Click "Create New Field" to add one.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Always-visible basic table (ensures a table renders even if card styles clash) -->
      <section class="section" style="margin:0 20px; display:none;">
        <h2 style="font-weight:600; margin:0 0 8px 0;">Fields (Basic View)</h2>
        <p style="color:#718096; margin:0 0 12px 0;">Simple table rendering of fields for this step.</p>
        <div style="overflow:auto;">
          <table id="fieldsTable_basic" style="width:100%; border-collapse:collapse; background:#fff;">
            <thead>
              <tr style="background:#f7fafc;">
                <th style="border:1px solid #e2e8f0; padding:8px;">Order</th>
                <th style="border:1px solid #e2e8f0; padding:8px;">Label</th>
                <th style="border:1px solid #e2e8f0; padding:8px;">Type</th>
                <th style="border:1px solid #e2e8f0; padding:8px;">Required</th>
                <?php if ($hasNotesCol): ?>
                  <th style="border:1px solid #e2e8f0; padding:8px;">Notes</th>
                <?php endif; ?>
                <th style="border:1px solid #e2e8f0; padding:8px;">Status</th>
                <th style="border:1px solid #e2e8f0; padding:8px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($fields)): foreach ($fields as $f): ?>
                  <tr>
                    <td style="border:1px solid #e2e8f0; padding:8px;">#<?php echo (int)$f['field_order']; ?></td>
                    <td style="border:1px solid #e2e8f0; padding:8px;"><?php echo htmlspecialchars($f['label'] ?? ''); ?></td>
                    <td style="border:1px solid #e2e8f0; padding:8px;"><?php echo htmlspecialchars($f['input_type'] ?? ''); ?></td>
                    <td style="border:1px solid #e2e8f0; padding:8px;"><?php echo ((int)($f['is_required'] ?? 0) === 1) ? 'Yes' : 'No'; ?></td>
                    <?php if ($hasNotesCol): ?>
                      <td style="border:1px solid #e2e8f0; padding:8px;"><?php echo htmlspecialchars($f['notes'] ?? ''); ?></td>
                    <?php endif; ?>
                    <td style="border:1px solid #e2e8f0; padding:8px;">
                      <?php $isArchived = ((int)($f['is_archived'] ?? 0) === 1); ?>
                      <?php echo $isArchived ? 'Archived' : 'Active'; ?>
                    </td>
                    <td style="border:1px solid #e2e8f0; padding:8px;">
                      <button type="button" class="js-edit-field" style="padding:6px 10px; border:1px solid #CBD5E0; background:#EDF2F7; border-radius:6px;">Update</button>
                      <?php $ftLower = strtolower($f['input_type'] ?? '');
                      if (in_array($ftLower, ['select', 'radio', 'checkbox'], true)): ?>
                        <a href="manage_step_options.php?field_id=<?php echo urlencode((string)$f['id']); ?>&step_id=<?php echo urlencode((string)$step_id); ?>" class="table__btn table__btn--update" title="Manage Options" style="margin-left:6px;">Manage Options</a>
                      <?php endif; ?>
                      <?php if (!$isArchived): ?>
                        <a href="manage_step_fields.php?step_id=<?php echo urlencode((string)$step_id); ?>&action=archive_field&field_id=<?php echo urlencode((string)$f['id']); ?>" style="margin-left:6px;" data-label="<?php echo htmlspecialchars($f['label'] ?? '', ENT_QUOTES); ?>">Archive</a>
                      <?php else: ?>
                        <a href="manage_step_fields.php?step_id=<?php echo urlencode((string)$step_id); ?>&action=unarchive_field&field_id=<?php echo urlencode((string)$f['id']); ?>" style="margin-left:6px;" data-label="<?php echo htmlspecialchars($f['label'] ?? '', ENT_QUOTES); ?>">Unarchive</a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach;
              else: ?>
                <tr>
                  <td colspan="<?php echo $hasNotesCol ? 7 : 6; ?>" style="border:1px solid #e2e8f0; padding:12px; text-align:center; color:#718096;">No fields yet. Click "Create New Field" to add one.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Styled card/table view (kept for consistency with the dashboard theme) -->
      <section class="section active" style="margin:0 20px;">
        <div class="card">
          <div class="card__header">
            <div>
              <h2 class="card__title">Fields</h2>
              <p class="table-container__subtitle">List of fields for this step</p>
            </div>
            <div>
              <button class="btn btn--primary" type="button" id="openNewFieldModalBtn">Create New Field</button>
            </div>
          </div>
          <table class="table" id="fieldsTable_all">
            <thead>
              <tr>
                <th style="width:60px;">Order</th>
                <th style="width:200px;">Label</th>
                <th style="width:120px;">Type</th>
                <th style="width:100px;">Required</th>
                <?php if ($hasNotesCol): ?>
                  <th style="width:220px;">Notes</th>
                <?php endif; ?>
                <th style="width:140px;">Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($fields)): foreach ($fields as $f): ?>
                  <tr>
                    <td>#<?php echo (int)$f['field_order']; ?></td>
                    <td><?php echo htmlspecialchars($f['label'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($f['input_type'] ?? ''); ?></td>
                    <td><?php echo ((int)($f['is_required'] ?? 0) === 1) ? 'Yes' : 'No'; ?></td>
                    <?php if ($hasNotesCol): ?>
                      <td><?php echo htmlspecialchars($f['notes'] ?? ''); ?></td>
                    <?php endif; ?>
                    <td>
                      <?php $isArchived = ((int)($f['is_archived'] ?? 0) === 1); ?>
                      <?php if (!$isArchived): ?>
                        <span class="status-pill pill-open"><span class="pill-dot"></span> Active</span>
                      <?php else: ?>
                        <span class="status-pill pill-closed"><span class="pill-dot"></span> Archived</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="table__actions">
                        <button type="button" class="table__btn table__btn--update js-edit-field"
                          data-field-id="<?php echo (int)$f['id']; ?>"
                          data-name="<?php echo htmlspecialchars($f['name'] ?? '', ENT_QUOTES); ?>"
                          data-label="<?php echo htmlspecialchars($f['label'] ?? '', ENT_QUOTES); ?>"
                          data-type="<?php echo htmlspecialchars($f['input_type'] ?? '', ENT_QUOTES); ?>"
                          data-ph="<?php echo htmlspecialchars($f['placeholder_text'] ?? '', ENT_QUOTES); ?>"
                          data-req="<?php echo (int)($f['is_required'] ?? 0); ?>"
                          data-order="<?php echo (int)($f['field_order'] ?? 0); ?>"
                          <?php if ($hasAllowedCol): ?> data-allowed="<?php echo htmlspecialchars($f['allowed_file_types'] ?? '', ENT_QUOTES); ?>" <?php endif; ?>
                          <?php if ($hasMaxCol): ?> data-maxmb="<?php echo htmlspecialchars((string)($f['max_file_size_mb'] ?? ''), ENT_QUOTES); ?>" <?php endif; ?>
                          <?php if ($hasVisibleFieldCol): ?> data-visiblefid="<?php echo htmlspecialchars((string)($f['visible_when_field_id'] ?? ''), ENT_QUOTES); ?>" <?php endif; ?>
                          <?php if ($hasVisibleValueCol): ?> data-visibleval="<?php echo htmlspecialchars($f['visible_when_value'] ?? '', ENT_QUOTES); ?>" <?php endif; ?>
                          <?php if ($hasNotesCol): ?>
                          data-notes="<?php echo htmlspecialchars($f['notes'] ?? '', ENT_QUOTES); ?>"
                          <?php endif; ?>>Update</button>
                        <?php $ftLower = strtolower($f['input_type'] ?? '');
                        if (in_array($ftLower, ['select', 'radio', 'checkbox'], true)): ?>
                          <button type="button" class="table__btn table__btn--update" title="Manage Options"
                            onclick="openStepOptionsModal(<?php echo (int)$f['id']; ?>, <?php echo (int)$step_id; ?>, '<?php echo htmlspecialchars($f['label'] ?? '', ENT_QUOTES); ?>')">Manage Options</button>
                        <?php endif; ?>
                        <?php if (!$isArchived): ?>
                          <a class="table__btn table__btn--update" href="manage_step_fields.php?step_id=<?php echo urlencode((string)$step_id); ?>&action=archive_field&field_id=<?php echo urlencode((string)$f['id']); ?>" data-label="<?php echo htmlspecialchars($f['label'] ?? '', ENT_QUOTES); ?>">Archive</a>
                        <?php else: ?>
                          <a class="table__btn table__btn--update" href="manage_step_fields.php?step_id=<?php echo urlencode((string)$step_id); ?>&action=unarchive_field&field_id=<?php echo urlencode((string)$f['id']); ?>" data-label="<?php echo htmlspecialchars($f['label'] ?? '', ENT_QUOTES); ?>">Unarchive</a>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach;
              else: ?>
                <tr>
                  <td colspan="<?php echo $hasNotesCol ? 7 : 6; ?>">No fields yet. Create one to start.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- New Field Modal -->
      <div id="newFieldModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1001; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
        <div role="dialog" aria-modal="true" aria-labelledby="newFieldModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 520px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text); display: flex; flex-direction: column; max-height: 85vh;">
          <!-- Close Button -->
          <button type="button" id="closeNewFieldModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

          <!-- Modal Header -->
          <div style="padding: 40px 32px 24px 32px; text-align: center;">
            <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
              <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
              </svg>
            </div>
            <h3 id="newFieldModalTitle" style="margin:0 0 8px 0; color:#1a202c; font-size:1.6rem; font-weight:700; letter-spacing:-0.025em;">Add New Field</h3>
            <p style="color:#718096; margin:0; line-height:1.5; font-size:0.95rem;">Create a field for this step</p>
          </div>

          <!-- Modal Body -->
          <div style="padding: 0 32px 24px 32px; overflow-y: auto; flex: 1;">
            <form method="POST" action="manage_step_fields.php?step_id=<?php echo (int)$step_id; ?>" id="newFieldForm">
              <input type="hidden" name="action" value="add_field">
              <div class="form-group">
                <label for="new_label" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Label</label>
                <input type="text" name="label" id="new_label" required style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;" />
              </div>
              <!-- Name is auto-generated; keep hidden field populated from Label -->
              <input type="hidden" name="name" id="new_name" />
              <div class="form-group">
                <label for="new_type" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Type</label>
                <select name="input_type" id="new_type" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                  <option value="text">text</option>
                  <option value="textarea">textarea</option>
                  <option value="number">number</option>
                  <option value="email">email</option>
                  <option value="date">date</option>
                  <option value="select">select</option>
                  <option value="radio">radio</option>
                  <option value="checkbox">checkbox</option>
                  <option value="file">file</option>
                </select>
              </div>
              <!-- File-specific settings (shown when Type = file) -->
              <div class="form-group" id="newAllowedFileTypesGroup" style="margin-top:12px; display:none;">
                <label for="newAllowedFileTypes" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Allowed file extensions</label>
                <input type="text" id="newAllowedFileTypes" name="allowed_file_types" placeholder="e.g., .pdf,.jpg,.png" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                <p style="margin-top:6px; color:#718096; font-size:0.85rem;">Use comma-separated list with leading dots. Example: <code>.pdf,.jpg,.png</code></p>
              </div>
              <div class="form-group" id="newMaxFileSizeMbGroup" style="margin-top:12px; display:none;">
                <label for="newMaxFileSizeMb" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Max file size (MB)</label>
                <input type="number" id="newMaxFileSizeMb" name="max_file_size_mb" placeholder="e.g., 10" min="1" max="2048" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
              </div>
              <div class="form-group">
                <label for="new_ph" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Placeholder</label>
                <input type="text" name="placeholder_text" id="new_ph" style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;" />
              </div>
              <?php if ($hasNotesCol): ?>
                <div class="form-group">
                  <label for="new_notes" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Notes</label>
                  <input type="text" name="notes" id="new_notes" style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;" />
                </div>
              <?php endif; ?>
              <div class="form-group">
                <label style="display:block; font-weight:600; color:#2d3748; font-size:0.9rem;"><input type="checkbox" name="is_required" value="1"> Required</label>
              </div>

              <!-- Field order for new field -->
              <div class="form-group" style="margin-top:12px;">
                <label for="newFieldOrder" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Order</label>
                <input type="number" id="newFieldOrder" name="field_order" min="1" placeholder="<?php echo (int)($currentMaxOrder + 1); ?>" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                <small style="color:#718096; display:block; margin-top:6px;">Leave blank to add to the end (<?php echo (int)($currentMaxOrder + 1); ?>).</small>
              </div>

              <?php if ($hasVisibleFieldCol && $hasVisibleValueCol): ?>
                <!-- Conditional Visibility -->
                <div class="form-group" style="margin-top:12px;">
                  <label for="newVisibleWhenFieldId" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Show this field when another field equals</label>
                  <select id="newVisibleWhenFieldId" name="visible_when_field_id" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    <option value="">None</option>
                    <?php if (!empty($fields)): ?>
                      <?php foreach ($fields as $f): ?>
                        <?php $ft = strtolower($f['input_type']);
                        if (in_array($ft, ['select', 'checkbox', 'radio'], true)): ?>
                          <option value="<?php echo (int)$f['id']; ?>"><?php echo htmlspecialchars($f['label']); ?></option>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </select>
                  <small style="color:#718096; display:block; margin-top:6px;">Only select, checkbox, and radio fields can be controllers.</small>
                </div>
                <div class="form-group" id="newVisibleWhenValueGroup" style="margin-top:12px; display:none;">
                  <label for="newVisibleWhenValue" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Trigger value</label>
                  <input type="text" id="newVisibleWhenValue" name="visible_when_value" placeholder="e.g., yes" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                  <small style="color:#718096; display:block; margin-top:6px;">Enter the option value in the controller that should show this field.</small>
                </div>
              <?php endif; ?>

              <!-- Modal Footer -->
              <div style="padding: 20px; display: flex; gap: 12px; justify-content: center;">
                <button type="button" id="cancelNewFieldBtn" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                <button type="submit" id="confirmAddFieldBtn" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Add</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Edit Field Modal -->
      <div id="editFieldModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1001; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
        <div role="dialog" aria-modal="true" aria-labelledby="editFieldModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 520px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text); display: flex; flex-direction: column; max-height: 85vh;">
          <!-- Close Button -->
          <button type="button" id="closeEditFieldModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

          <!-- Modal Header -->
          <div style="padding: 40px 32px 24px 32px; text-align: center;">
            <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
              <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h2m-2 14h2m-7-7h12" />
              </svg>
            </div>
            <h3 id="editFieldModalTitle" style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Edit Field</h3>
            <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Update field properties. Supports text, textarea, date, select, checkbox, radio, and file.</p>
          </div>

          <!-- Modal Body -->
          <div style="padding: 0 32px 24px 32px; overflow-y: auto; flex: 1;">
            <form method="POST" action="manage_step_fields.php?step_id=<?php echo (int)$step_id; ?>" id="editFieldForm">
              <input type="hidden" name="action" value="update_field">
              <input type="hidden" name="field_id" id="edit_field_id" />
              <div class="form-group">
                <label for="edit_label" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Label</label>
                <input type="text" name="label" id="edit_label" required style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;" />
              </div>
              <div class="form-group">
                <label for="edit_name" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Name</label>
                <input type="text" name="name" id="edit_name" required style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;" />
              </div>
              <div class="form-group">
                <label for="edit_type" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Type</label>
                <select name="input_type" id="edit_type" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                  <option value="text">text</option>
                  <option value="textarea">textarea</option>
                  <option value="number">number</option>
                  <option value="email">email</option>
                  <option value="date">date</option>
                  <option value="select">select</option>
                  <option value="radio">radio</option>
                  <option value="checkbox">checkbox</option>
                  <option value="file">file</option>
                </select>
              </div>
              <!-- File-specific settings (shown when Type = file) -->
              <div class="form-group" id="editAllowedFileTypesGroup" style="margin-top:12px; display:none;">
                <label for="editAllowedFileTypes" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Allowed file extensions</label>
                <input type="text" id="editAllowedFileTypes" name="allowed_file_types" placeholder="e.g., .pdf,.jpg,.png" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                <p style="margin-top:6px; color:#718096; font-size:0.85rem;">Use comma-separated list with leading dots. Example: <code>.pdf,.jpg,.png</code></p>
              </div>
              <div class="form-group" id="editMaxFileSizeMbGroup" style="margin-top:12px; display:none;">
                <label for="editMaxFileSizeMb" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Max file size (MB)</label>
                <input type="number" id="editMaxFileSizeMb" name="max_file_size_mb" placeholder="e.g., 10" min="1" max="2048" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
              </div>
              <div class="form-group">
                <label for="edit_ph" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Placeholder</label>
                <input type="text" name="placeholder_text" id="edit_ph" style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;" />
              </div>
              <?php if ($hasNotesCol): ?>
                <div class="form-group">
                  <label for="edit_notes" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Notes</label>
                  <input type="text" name="notes" id="edit_notes" style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;" />
                </div>
              <?php endif; ?>
              <div class="form-group">
                <label for="edit_order" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Order</label>
                <input type="number" name="field_order" id="edit_order" min="1" style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;" />
              </div>
              <div class="form-group">
                <label style="display:block; font-weight:600; color:#2d3748; font-size:0.9rem;"><input type="checkbox" name="is_required" value="1" id="edit_req"> Required</label>
              </div>

              <?php if ($hasVisibleFieldCol && $hasVisibleValueCol): ?>
                <!-- Conditional Visibility (Edit) -->
                <div class="form-group" style="margin-top:12px;">
                  <label for="editVisibleWhenFieldId" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Show this field when another field equals</label>
                  <select id="editVisibleWhenFieldId" name="visible_when_field_id" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    <option value="">None</option>
                    <?php if (!empty($fields)): ?>
                      <?php foreach ($fields as $f): ?>
                        <?php $ft = strtolower($f['input_type']);
                        if (in_array($ft, ['select', 'checkbox', 'radio'], true)): ?>
                          <option value="<?php echo (int)$f['id']; ?>"><?php echo htmlspecialchars($f['label']); ?></option>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </select>
                  <small style="color:#718096; display:block; margin-top:6px;">Only select, checkbox, and radio fields can be controllers.</small>
                </div>
                <div class="form-group" id="editVisibleWhenValueGroup" style="margin-top:12px; display:none;">
                  <label for="editVisibleWhenValue" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Trigger value</label>
                  <input type="text" id="editVisibleWhenValue" name="visible_when_value" placeholder="e.g., yes" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                  <small style="color:#718096; display:block; margin-top:6px;">Enter the option value in the controller that should show this field.</small>
                </div>
              <?php endif; ?>

              <!-- Modal Footer -->
              <div style="padding: 20px; display: flex; gap: 12px; justify-content: center; align-items: center;">
                <button class="btn" type="button" id="cancelEditFieldBtn" style="min-width: 180px; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; display: inline-flex; align-items: center; justify-content: center; text-align: center; line-height: 1;">Cancel</button>
                <button class="btn" type="submit" id="confirmEditFieldBtn" style="min-width: 180px; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4); display: inline-flex; align-items: center; justify-content: center; text-align: center; line-height: 1;">Update</button>
              </div>
            </form>
          </div>
        </div>
      </div>

    </main>
  </div>

  <!-- Options Modal (styled like services modal) -->
  <div id="optionsModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1001; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div role="dialog" aria-modal="true" aria-labelledby="optionsModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 900px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text); display: flex; flex-direction: column; max-height: 85vh;">
      <!-- Close Button -->
      <button type="button" id="closeOptionsModalBtn" onclick="closeOptionsModal()" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

      <!-- Modal Header -->
      <div style="padding: 40px 32px 24px 32px; text-align: center;">
        <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
          <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
          </svg>
        </div>
        <h3 id="optionsModalTitle" style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Manage Field Options</h3>
        <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Create and edit choices for the selected field</p>
        <p style="color: #718096; margin-top: 6px; font-size: 0.9rem;">Field: <span id="optionsFieldLabel" style="font-weight: 600; color: #2d3748;"></span></p>
      </div>

      <!-- Modal Body -->
      <div style="padding: 0 32px 24px 32px; flex: 1; overflow-y: auto;">
        <div class="table-container" style="margin-bottom: 20px;">
          <div class="table-container__header" style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
            <div>
              <h2 class="table-container__title">Existing Options</h2>
              <p class="table-container__subtitle">Update or delete options for this field</p>
            </div>
          </div>
          <table class="table">
            <thead>
              <tr>
                <th>Label</th>
                <th>Value</th>
                <th style="width:100px;">Order</th>
                <th style="width:120px;">Action</th>
              </tr>
            </thead>
            <tbody id="optionsTableBody"></tbody>
          </table>
        </div>

        <div class="table-container" style="margin-bottom: 8px;">
          <div class="table-container__header" style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
            <div>
              <h2 class="table-container__title">Add New Option</h2>
              <p class="table-container__subtitle">Create an option for this field</p>
            </div>
          </div>
          <div style="display: flex; gap: 12px; align-items: flex-end;">
            <div class="form-group" style="flex: 2;">
              <label for="newOptionLabel" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Label</label>
              <input type="text" id="newOptionLabel" placeholder="e.g., Male" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
            </div>
            <div class="form-group" style="flex: 2;">
              <label for="newOptionValue" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Value</label>
              <input type="text" id="newOptionValue" placeholder="e.g., Male" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
            </div>
            <div class="form-group" style="flex: 1;">
              <label for="newOptionOrder" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; font-size: 0.9rem;">Order</label>
              <input type="number" id="newOptionOrder" value="0" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
            </div>
            <button type="button" onclick="addNewOption()" style="padding: 12px 18px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Add Option</button>
          </div>
          <div id="optionsModalMessage" class="alert" style="display: none; margin-top: 12px;"></div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
        <button id="optionsSuccessOkBtn" type="button" onclick="window.location.reload()" style="display: none; flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Okay</button>
      </div>
    </div>
  </div>
  <!-- Confirmation Modal -->
  <div id="confirmationModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
    <div role="dialog" aria-modal="true" aria-labelledby="confirmationModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 560px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text);">
      <button type="button" id="closeConfirmationModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>
      <div style="padding: 32px; text-align: center;">
        <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center;">
          <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
          </svg>
        </div>
        <h3 id="confirmationModalTitle" style="margin:0 0 8px 0; color:#1a202c; font-size:1.4rem; font-weight:700; letter-spacing:-0.025em;">Confirm New Field</h3>
        <p style="color:#718096; margin:0; line-height:1.5; font-size:0.95rem;">Review details before creating the field.</p>
      </div>
      <div style="padding: 0 32px 24px 32px;">
        <div id="confirmationDetails" style="color:#2d3748; font-size:0.95rem; line-height:1.6; text-align: center;"></div>
        <div style="display:flex; gap:12px; margin-top:20px; justify-content:center;">
          <button type="button" id="cancelConfirmBtn" style="flex:1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Cancel</button>
          <button type="button" id="confirmActionBtn" style="flex:1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease;">Confirm</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Status Message Modal -->
  <div id="statusModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div role="dialog" aria-modal="true" aria-labelledby="statusModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 560px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text);">
      <button type="button" id="closeStatusModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>
      <div style="padding: 32px; text-align: center;">
        <div id="statusModalIcon" style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center;">
          <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h3 id="statusModalTitle" style="margin:0 0 8px 0; color:#1a202c; font-size:1.4rem; font-weight:700; letter-spacing:-0.025em;">Status</h3>
        <p id="statusModalText" style="color:#718096; margin:0; line-height:1.6; font-size:0.95rem;">Message</p>
      </div>
    </div>
  </div>

  <script>
    function showLoader() {
      var ov = document.getElementById('loadingOverlay');
      if (ov) {
        ov.style.display = 'flex';
      }
    }

    function hideLoader() {
      var ov = document.getElementById('loadingOverlay');
      if (ov) {
        ov.style.display = 'none';
      }
    }
    const newFieldModal = document.getElementById('newFieldModal');
    const editFieldModal = document.getElementById('editFieldModal');
    document.getElementById('openNewFieldModalBtn').addEventListener('click', () => {
      newFieldModal.style.display = 'flex';
      newFieldModal.style.visibility = 'visible';
      newFieldModal.style.opacity = '1';
    });
    document.getElementById('closeNewFieldModalBtn').addEventListener('click', () => {
      newFieldModal.style.display = 'none';
      newFieldModal.style.visibility = 'hidden';
      newFieldModal.style.opacity = '0';
    });
    document.getElementById('closeEditFieldModalBtn').addEventListener('click', () => {
      editFieldModal.style.display = 'none';
      editFieldModal.style.visibility = 'hidden';
      editFieldModal.style.opacity = '0';
    });
    document.getElementById('cancelNewFieldBtn')?.addEventListener('click', () => {
      newFieldModal.style.display = 'none';
      newFieldModal.style.visibility = 'hidden';
      newFieldModal.style.opacity = '0';
    });
    document.getElementById('cancelEditFieldBtn')?.addEventListener('click', () => {
      editFieldModal.style.display = 'none';
      editFieldModal.style.visibility = 'hidden';
      editFieldModal.style.opacity = '0';
    });
    // click outside to close
    newFieldModal?.addEventListener('click', (e) => {
      if (e.target === newFieldModal) {
        newFieldModal.style.display = 'none';
        newFieldModal.style.visibility = 'hidden';
        newFieldModal.style.opacity = '0';
      }
    });
    editFieldModal?.addEventListener('click', (e) => {
      if (e.target === editFieldModal) {
        editFieldModal.style.display = 'none';
        editFieldModal.style.visibility = 'hidden';
        editFieldModal.style.opacity = '0';
      }
    });
    document.querySelectorAll('.js-edit-field').forEach(btn => {
      btn.addEventListener('click', function() {
        editFieldModal.style.display = 'flex';
        editFieldModal.style.visibility = 'visible';
        editFieldModal.style.opacity = '1';
        document.getElementById('edit_field_id').value = this.dataset.fieldId;
        document.getElementById('edit_label').value = this.dataset.label;
        document.getElementById('edit_name').value = this.dataset.name;
        document.getElementById('edit_type').value = this.dataset.type;
        document.getElementById('edit_ph').value = this.dataset.ph;
        document.getElementById('edit_order').value = this.dataset.order;
        document.getElementById('edit_req').checked = (parseInt(this.dataset.req, 10) === 1);
        var notesEl = document.getElementById('edit_notes');
        if (notesEl && this.dataset.notes !== undefined) notesEl.value = this.dataset.notes;
        // Toggle file-specific groups based on initial type
        const isFileType = String(this.dataset.type || '').toLowerCase() === 'file';
        const editAllowedGrp = document.getElementById('editAllowedFileTypesGroup');
        const editMaxGrp = document.getElementById('editMaxFileSizeMbGroup');
        if (editAllowedGrp) editAllowedGrp.style.display = isFileType ? 'block' : 'none';
        if (editMaxGrp) editMaxGrp.style.display = isFileType ? 'block' : 'none';
        // Populate values if present
        const editAllowedEl = document.getElementById('editAllowedFileTypes');
        const editMaxEl = document.getElementById('editMaxFileSizeMb');
        if (editAllowedEl && this.dataset.allowed !== undefined) {
          editAllowedEl.value = this.dataset.allowed || '';
        }
        if (editMaxEl && this.dataset.maxmb !== undefined) {
          editMaxEl.value = this.dataset.maxmb || '';
        }
        // Populate conditional visibility
        const editCtlSelect = document.getElementById('editVisibleWhenFieldId');
        const editCtlValGroup = document.getElementById('editVisibleWhenValueGroup');
        const editCtlValInput = document.getElementById('editVisibleWhenValue');
        if (editCtlSelect) {
          const fid = this.dataset.visiblefid || '';
          editCtlSelect.value = fid || '';
          const hasCtl = String(fid).trim() !== '';
          if (editCtlValGroup) editCtlValGroup.style.display = hasCtl ? 'block' : 'none';
        }
        if (editCtlValInput && this.dataset.visibleval !== undefined) {
          editCtlValInput.value = this.dataset.visibleval || '';
        }
        // Fetch freshest values for file settings to avoid stale dataset
        const fidFetch = parseInt(this.dataset.fieldId || '0', 10);
        if (fidFetch > 0) {
          fetch(`manage_step_fields.php?step_id=${pageStepId}&action=get_field&field_id=${fidFetch}&ajax=1`, {
              headers: {
                'X-Requested-With': 'XMLHttpRequest'
              }
            })
            .then(r => r.ok ? r.json() : Promise.reject(new Error('Network error')))
            .then(data => {
              if (!data || !data.ok || !data.field) return;
              const f = data.field;
              const editTypeSel = document.getElementById('edit_type');
              if (editTypeSel) editTypeSel.value = String(f.input_type || '');
              const editAllowedEl2 = document.getElementById('editAllowedFileTypes');
              const editMaxEl2 = document.getElementById('editMaxFileSizeMb');
              if (hasAllowedCol && editAllowedEl2 && Object.prototype.hasOwnProperty.call(f, 'allowed_file_types')) {
                editAllowedEl2.value = String(f.allowed_file_types ?? '');
              }
              if (hasMaxCol && editMaxEl2 && Object.prototype.hasOwnProperty.call(f, 'max_file_size_mb')) {
                editMaxEl2.value = (f.max_file_size_mb ?? '') === null ? '' : String(f.max_file_size_mb);
              }
            })
            .catch(() => {
              /* silent */ });
        }
        // Ensure toggle logic bound elsewhere runs using current type state
        try {
          if (typeof toggleEditFileGroups === 'function') {
            toggleEditFileGroups();
          }
        } catch (e) {
          /* no-op */ }
      });
    });

    // Auto-generate sanitized 'name' from 'label' while typing
    function sanitizeToName(raw) {
      let n = String(raw || '').toLowerCase();
      n = n.replace(/[^a-z0-9]+/g, '_'); // non alnum -> _
      n = n.replace(/_+/g, '_'); // collapse
      n = n.replace(/^_+|_+$/g, ''); // trim
      if (!n) n = 'field';
      if (/^[0-9]/.test(n)) n = 'field_' + n;
      return n;
    }

    // New Field modal wiring
    const newLabelEl = document.getElementById('new_label');
    const newNameEl = document.getElementById('new_name');
    let newNameTouched = false;
    newNameEl?.addEventListener('input', () => {
      newNameTouched = true;
    });
    newLabelEl?.addEventListener('input', () => {
      if (!newNameTouched) {
        newNameEl.value = sanitizeToName(newLabelEl.value);
      }
    });

    // Edit Field modal wiring (auto-update when user edits label unless name was changed)
    const editLabelEl = document.getElementById('edit_label');
    const editNameEl = document.getElementById('edit_name');
    let editNameTouched = false;
    editNameEl?.addEventListener('input', () => {
      editNameTouched = true;
    });
    editLabelEl?.addEventListener('input', () => {
      if (!editNameTouched) {
        editNameEl.value = sanitizeToName(editLabelEl.value);
      }
    });

    // Server column availability flags
    const hasAllowedCol = <?php echo $hasAllowedCol ? 'true' : 'false'; ?>;
    const hasMaxCol = <?php echo $hasMaxCol ? 'true' : 'false'; ?>;
    const hasVisibleCols = <?php echo ($hasVisibleFieldCol && $hasVisibleValueCol) ? 'true' : 'false'; ?>;
    const pageStepId = <?php echo (int)$step_id; ?>;

    // Show/hide file-specific inputs based on Type selection
    const newTypeEl = document.getElementById('new_type');
    const newAllowedGrp = document.getElementById('newAllowedFileTypesGroup');
    const newMaxGrp = document.getElementById('newMaxFileSizeMbGroup');
    const toggleNewFileGroups = () => {
      const isFile = String(newTypeEl?.value || '').toLowerCase() === 'file';
      if (newAllowedGrp) newAllowedGrp.style.display = isFile ? 'block' : 'none';
      if (newMaxGrp) newMaxGrp.style.display = isFile ? 'block' : 'none';
    };
    newTypeEl?.addEventListener('change', toggleNewFileGroups);
    // Ensure correct state when opening the New Field modal
    document.getElementById('openNewFieldModalBtn')?.addEventListener('click', () => {
      // Reset file groups visibility based on current type value
      toggleNewFileGroups();
    });

    const editTypeEl = document.getElementById('edit_type');
    const editAllowedGrp = document.getElementById('editAllowedFileTypesGroup');
    const editMaxGrp = document.getElementById('editMaxFileSizeMbGroup');
    const toggleEditFileGroups = () => {
      const isFile = String(editTypeEl?.value || '').toLowerCase() === 'file';
      if (editAllowedGrp) editAllowedGrp.style.display = isFile ? 'block' : 'none';
      if (editMaxGrp) editMaxGrp.style.display = isFile ? 'block' : 'none';
    };
    editTypeEl?.addEventListener('change', toggleEditFileGroups);

    // Toggle conditional trigger input
    const newCtlSelect = document.getElementById('newVisibleWhenFieldId');
    const newCtlValGroup = document.getElementById('newVisibleWhenValueGroup');
    const newCtlValInput = document.getElementById('newVisibleWhenValue');
    if (newCtlSelect) {
      newCtlSelect.addEventListener('change', function() {
        const hasCtl = String(this.value).trim() !== '';
        if (newCtlValGroup) newCtlValGroup.style.display = hasCtl ? 'block' : 'none';
        if (!hasCtl && newCtlValInput) newCtlValInput.value = '';
      });
    }
    const editCtlSelect = document.getElementById('editVisibleWhenFieldId');
    const editCtlValGroup = document.getElementById('editVisibleWhenValueGroup');
    const editCtlValInput = document.getElementById('editVisibleWhenValue');
    if (editCtlSelect) {
      editCtlSelect.addEventListener('change', function() {
        const hasCtl = String(this.value).trim() !== '';
        if (editCtlValGroup) editCtlValGroup.style.display = hasCtl ? 'block' : 'none';
        if (!hasCtl && editCtlValInput) editCtlValInput.value = '';
      });
    }

    // Basic client-side validation for file inputs on submit
    const validateFileSettings = (typeValue, allowedStr, maxMbStr) => {
      const isFile = String(typeValue || '').toLowerCase() === 'file';
      if (!isFile) return true;
      // Only enforce if columns exist server-side
      const needAllowed = hasAllowedCol;
      const needMax = hasMaxCol;
      const allowed = String(allowedStr || '').trim();
      const maxMb = String(maxMbStr || '').trim();
      if (needAllowed && !allowed) {
        alert('Please specify allowed file extensions, e.g., .pdf,.jpg,.png');
        return false;
      }
      if (!/^\.(?:[A-Za-z0-9]+)(,\.[A-Za-z0-9]+)*$/.test(allowed)) {
        alert('Allowed file extensions must be comma-separated, e.g., .pdf,.jpg,.png');
        return false;
      }
      if (needMax && !maxMb) {
        alert('Please provide a maximum file size in MB');
        return false;
      }
      if (maxMb && !/^\d+$/.test(maxMb)) {
        alert('Max file size (MB) must be a positive integer');
        return false;
      }
      const n = parseInt(maxMb, 10);
      if (maxMb && (n <= 0 || n > 2048)) {
        alert('Max file size (MB) must be between 1 and 2048');
        return false;
      }
      return true;
    };

    // Intercept New Field submit: validate → confirm → show loader → submit
    const confirmationModal = document.getElementById('confirmationModal');
    const closeConfirmationModalBtn = document.getElementById('closeConfirmationModalBtn');
    const cancelConfirmBtn = document.getElementById('cancelConfirmBtn');
    const confirmActionBtn = document.getElementById('confirmActionBtn');
    const confirmationDetails = document.getElementById('confirmationDetails');
    const confirmationModalTitle = document.getElementById('confirmationModalTitle');
    let confirmContext = 'newField';

    function openConfirmation(detailsHtml) {
      if (!confirmationModal) return;
      confirmationDetails.innerHTML = detailsHtml;
      confirmationModal.style.display = 'flex';
      confirmationModal.style.visibility = 'visible';
      confirmationModal.style.opacity = '1';
    }

    function closeConfirmation() {
      if (!confirmationModal) return;
      confirmationModal.style.display = 'none';
      confirmationModal.style.visibility = 'hidden';
      confirmationModal.style.opacity = '0';
    }
    closeConfirmationModalBtn?.addEventListener('click', closeConfirmation);
    cancelConfirmBtn?.addEventListener('click', () => {
      confirmContext = 'newField';
      closeConfirmation();
    });
    confirmationModal?.addEventListener('click', (e) => {
      if (e.target === confirmationModal) closeConfirmation();
    });

    document.getElementById('newFieldForm')?.addEventListener('submit', (e) => {
      const typeVal = newTypeEl?.value || '';
      const allowedVal = document.getElementById('newAllowedFileTypes')?.value || '';
      const maxMbVal = document.getElementById('newMaxFileSizeMb')?.value || '';
      if (!validateFileSettings(typeVal, allowedVal, maxMbVal)) {
        e.preventDefault();
        return;
      }
      // Validate conditional visibility client-side if columns exist
      if (hasVisibleCols) {
        const ctlId = String(newCtlSelect?.value || '').trim();
        const trigVal = String(newCtlValInput?.value || '').trim();
        if (ctlId !== '' && trigVal === '') {
          e.preventDefault();
          alert('Please provide a trigger value for the selected controller field');
          return;
        }
      }
      // Validate order input (optional)
      const ord = document.getElementById('newFieldOrder')?.value || '';
      if (ord !== '') {
        if (!/^\d+$/.test(ord) || parseInt(ord, 10) < 1) {
          e.preventDefault();
          alert('Order must be a positive integer');
          return;
        }
      }
      // Show confirmation instead of direct submit
      e.preventDefault();
      const lbl = document.getElementById('new_label')?.value || '';
      const req = document.querySelector('#newFieldForm input[name="is_required"]')?.checked ? 'Yes' : 'No';
      const ph = document.getElementById('new_ph')?.value || '';
      const notes = document.getElementById('new_notes')?.value || '';
      const visCtl = document.getElementById('newVisibleWhenFieldId')?.selectedOptions?.[0]?.textContent || 'None';
      const visVal = document.getElementById('newVisibleWhenValue')?.value || '';
      const details = `
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
          <div><strong>Label:</strong> ${lbl}</div>
          <div><strong>Type:</strong> ${typeVal}</div>
          <div><strong>Required:</strong> ${req}</div>
          <div><strong>Order:</strong> ${ord || '<?php echo (int)($currentMaxOrder + 1); ?>'}</div>
          <div style="grid-column: 1 / -1;"><strong>Placeholder:</strong> ${ph || '—'}</div>
          ${notes ? `<div style=\"grid-column: 1 / -1;\"><strong>Notes:</strong> ${notes}</div>` : ''}
          ${String(typeVal).toLowerCase() === 'file' ? `<div style=\"grid-column: 1 / -1;\"><strong>Allowed:</strong> ${allowedVal || '—'}</div><div style=\"grid-column: 1 / -1;\"><strong>Max size (MB):</strong> ${maxMbVal || '—'}</div>` : ''}
          ${<?php echo ($hasVisibleFieldCol && $hasVisibleValueCol) ? 'true' : 'false'; ?> ? `<div><strong>Controller:</strong> ${visCtl}</div><div><strong>Trigger value:</strong> ${visVal || '—'}</div>` : ''}
        </div>`;
      openConfirmation(details);
    });

    // Default Confirm handler: only runs for new field creation context
    confirmActionBtn?.addEventListener('click', () => {
      if (confirmContext !== 'newField') return;
      showLoader();
      closeConfirmation();
      document.getElementById('newFieldForm')?.submit();
    });

    // Generic confirmation modal (aligns with other pages)
    function showConfirm(title, messageHtml, onConfirm) {
      if (!confirmationModal || !confirmationDetails || !confirmActionBtn) return;
      if (confirmationModalTitle) {
        confirmationModalTitle.textContent = title || 'Confirm Action';
      }
      confirmationDetails.innerHTML = messageHtml || '';
      confirmContext = 'generic';
      confirmationModal.style.display = 'flex';
      confirmationModal.style.visibility = 'visible';
      confirmationModal.style.opacity = '1';
      const handler = async () => {
        try {
          closeConfirmation();
          if (typeof onConfirm === 'function') await onConfirm();
        } finally {
          confirmContext = 'newField';
        }
      };
      // One-off handler for this confirm
      confirmActionBtn.addEventListener('click', handler, {
        once: true
      });
    }

    // Status Modal close handlers
    const statusModal = document.getElementById('statusModal');
    const closeStatusModalBtn = document.getElementById('closeStatusModalBtn');
    closeStatusModalBtn?.addEventListener('click', () => {
      statusModal.style.display = 'none';
      statusModal.style.visibility = 'hidden';
      statusModal.style.opacity = '0';
    });
    statusModal?.addEventListener('click', (e) => {
      if (e.target === statusModal) {
        statusModal.style.display = 'none';
        statusModal.style.visibility = 'hidden';
        statusModal.style.opacity = '0';
      }
    });

    function showStatusModal(title, message, kind) {
      const tEl = document.getElementById('statusModalTitle');
      const mEl = document.getElementById('statusModalText');
      const iconEl = document.getElementById('statusModalIcon');
      const isSuccess = String(kind || '').toLowerCase() === 'success';
      if (tEl) tEl.textContent = title || (isSuccess ? 'Success' : 'Failed');
      if (mEl) mEl.innerHTML = message || '';
      if (iconEl) {
        if (isSuccess) {
          iconEl.style.background = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
          iconEl.innerHTML = '<svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"></path></svg>';
        } else {
          iconEl.style.background = 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)';
          iconEl.innerHTML = '<svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>';
        }
      }
      statusModal.style.display = 'flex';
      statusModal.style.visibility = 'visible';
      statusModal.style.opacity = '1';
    }

    // Intercept archive/unarchive links to use confirmation modal with loader
    document.querySelectorAll('a[href*="action=archive_field"], a[href*="action=unarchive_field"]').forEach((link) => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const isUnarchive = link.href.includes('action=unarchive_field');
        const title = isUnarchive ? 'Confirm Unarchive' : 'Confirm Archive';
        const labelRaw = link.getAttribute('data-label') || link.closest('tr')?.querySelector('td:nth-child(2)')?.textContent?.trim() || '';
        const labelQuoted = labelRaw ? `"${labelRaw}"` : 'this field';
        const msg = isUnarchive ? `Unarchive ${labelQuoted} and make it active again?` : `Archive ${labelQuoted}? It will be hidden from use.`;
        showConfirm(title, msg, () => {
          showLoader();
          setTimeout(() => {
            window.location.href = link.href;
          }, 100);
        });
      });
    });

    document.getElementById('editFieldForm')?.addEventListener('submit', (e) => {
      // Always intercept to show confirmation modal like manage_form.php
      e.preventDefault();
      const form = document.getElementById('editFieldForm');
      if (!form) return;

      // Validate before showing confirmation
      const typeVal = editTypeEl?.value || '';
      const allowedVal = document.getElementById('editAllowedFileTypes')?.value || '';
      const maxMbVal = document.getElementById('editMaxFileSizeMb')?.value || '';
      if (!validateFileSettings(typeVal, allowedVal, maxMbVal)) {
        return;
      }
      if (hasVisibleCols) {
        const ctlId = String(editCtlSelect?.value || '').trim();
        const trigVal = String(editCtlValInput?.value || '').trim();
        if (ctlId !== '' && trigVal === '') {
          showStatusModal('Missing Trigger Value', 'Please provide a trigger value for the selected controller field.');
          return;
        }
      }

      // Build confirmation details
      const lbl = document.getElementById('edit_label')?.value || '';
      const req = document.querySelector('#editFieldForm input[name="is_required"]')?.checked ? 'Yes' : 'No';
      const ph = document.getElementById('edit_ph')?.value || '';
      const ord = document.getElementById('edit_order')?.value || '';
      const notes = document.getElementById('edit_notes')?.value || '';
      const visCtl = document.getElementById('editVisibleWhenFieldId')?.selectedOptions?.[0]?.textContent || 'None';
      const visVal = document.getElementById('editVisibleWhenValue')?.value || '';
      const allowedText = document.getElementById('editAllowedFileTypes')?.value || '';
      const maxMbText = document.getElementById('editMaxFileSizeMb')?.value || '';

      const details = `
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
          <div><strong>Label:</strong> ${lbl}</div>
          <div><strong>Type:</strong> ${typeVal}</div>
          <div><strong>Required:</strong> ${req}</div>
          <div><strong>Order:</strong> ${ord || '—'}</div>
          <div style="grid-column: 1 / -1;"><strong>Placeholder:</strong> ${ph || '—'}</div>
          ${notes ? `<div style=\"grid-column: 1 / -1;\"><strong>Notes:</strong> ${notes}</div>` : ''}
          ${String(typeVal).toLowerCase() === 'file' ? `<div style=\"grid-column: 1 / -1;\"><strong>Allowed:</strong> ${allowedText || '—'}</div><div style=\"grid-column: 1 / -1;\"><strong>Max size (MB):</strong> ${maxMbText || '—'}</div>` : ''}
          ${<?php echo ($hasVisibleFieldCol && $hasVisibleValueCol) ? 'true' : 'false'; ?> ? `<div><strong>Controller:</strong> ${visCtl}</div><div><strong>Trigger value:</strong> ${visVal || '—'}</div>` : ''}
        </div>`;

      // Use generic confirmation with loader on confirm
      showConfirm('Confirm Update', details, () => {
        showLoader();
        editFieldModal.style.display = 'none';
        // Defer a bit to ensure loader paints
        setTimeout(() => {
          form.submit();
        }, 100);
      });
    });

    // ===== Manage Options Modal (AJAX) =====
    const optionsModal = document.getElementById('optionsModal');
    const closeOptionsModalBtn = document.getElementById('closeOptionsModalBtn');
    const optionsTableBody = document.getElementById('optionsTableBody');
    const optionsFieldLabelEl = document.getElementById('optionsFieldLabel');
    const optionsModalMessage = document.getElementById('optionsModalMessage');
    const optionsSuccessOkBtn = document.getElementById('optionsSuccessOkBtn');

    function openStepOptionsModal(fieldId, stepId, fieldLabel) {
      if (!optionsModal) return;
      optionsModal.dataset.fieldId = String(fieldId);
      optionsModal.dataset.stepId = String(stepId);
      if (optionsFieldLabelEl) optionsFieldLabelEl.textContent = fieldLabel || '';
      loadStepOptions(fieldId, stepId);
      optionsModal.style.display = 'flex';
      optionsModal.style.visibility = 'visible';
      optionsModal.style.opacity = '1';
    }

    function closeOptionsModal() {
      if (!optionsModal) return;
      optionsModal.style.display = 'none';
      optionsModal.style.visibility = 'hidden';
      optionsModal.style.opacity = '0';
      if (optionsTableBody) optionsTableBody.innerHTML = '';
      if (optionsModalMessage) {
        optionsModalMessage.style.display = 'none';
        optionsModalMessage.textContent = '';
      }
      if (optionsSuccessOkBtn) optionsSuccessOkBtn.style.display = 'none';
    }
    closeOptionsModalBtn?.addEventListener('click', closeOptionsModal);
    optionsModal?.addEventListener('click', (e) => {
      if (e.target === optionsModal) closeOptionsModal();
    });

    async function loadStepOptions(fieldId, stepId) {
      showLoader();
      try {
        const res = await fetch(`manage_step_options.php?ajax=1&field_id=${encodeURIComponent(fieldId)}&step_id=${encodeURIComponent(stepId)}`);
        const data = await res.json();
        if (!data || !data.ok) {
          alert('Failed to load options.');
          return;
        }
        renderOptionsRows(data.options || []);
      } catch (err) {
        alert('Error loading options: ' + (err?.message || String(err)));
      } finally {
        hideLoader();
      }
    }

    let saveTimer = null;

    function scheduleAutoSave() {
      if (saveTimer) clearTimeout(saveTimer);
      saveTimer = setTimeout(() => {
        saveAllOptionsChanges();
      }, 600);
    }

    function renderOptionsRows(options) {
      if (!optionsTableBody) return;
      optionsTableBody.innerHTML = '';
      if (!options || options.length === 0) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 4;
        td.textContent = 'No options yet. Add one below.';
        td.style.color = '#718096';
        tr.appendChild(td);
        optionsTableBody.appendChild(tr);
        return;
      }
      for (const opt of options) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <input type="hidden" data-name="option_id" value="${opt.id}">
          <td><input type="text" data-name="option_label" value="${escapeHtml(String(opt.option_label || ''))}" required style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;"></td>
          <td><input type="text" data-name="option_value" value="${escapeHtml(String(opt.option_value || ''))}" required style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;"></td>
          <td style="width:100px;"><input type="number" data-name="option_order" value="${parseInt(opt.option_order || 0, 10)}" required style="width:80px; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;"></td>
          <td style="width:120px;">
            <button type="button" class="table__btn table__btn--delete" data-opt-id="${opt.id}">Delete</button>
          </td>`;
        optionsTableBody.appendChild(tr);
        // autosave on change
        tr.querySelectorAll('input[data-name]').forEach(inp => {
          inp.addEventListener('change', scheduleAutoSave);
          inp.addEventListener('blur', scheduleAutoSave);
        });
      }
      // Hook delete buttons
      optionsTableBody.querySelectorAll('button.table__btn--delete').forEach(btn => {
        btn.addEventListener('click', function() {
          const optId = this.getAttribute('data-opt-id');
          if (!optId) return;
          const row = this.closest('tr');
          let delLbl = '',
            delVal = '';
          try {
            delLbl = row?.querySelector('input[data-name="option_label"]')?.value?.trim() || '';
            delVal = row?.querySelector('input[data-name="option_value"]')?.value?.trim() || '';
          } catch (_) {}
          const fid = optionsModal?.dataset.fieldId;
          const sid = optionsModal?.dataset.stepId;
          const detailsHtml = `<div><strong>Label:</strong> ${escapeHtml(delLbl || '')}</div><div><strong>Value:</strong> ${escapeHtml(delVal || '')}</div>`;
          showConfirm('Delete Option', detailsHtml, async () => {
            try {
              showLoader();
              const res = await fetch(`manage_step_options.php?ajax=1&action=delete_option&id=${encodeURIComponent(optId)}&field_id=${encodeURIComponent(fid)}&step_id=${encodeURIComponent(sid)}`);
              const data = await res.json();
              if (!data || !data.ok) {
                showStatusModal('Couldn’t Delete Option', data?.error || 'Failed to delete option.');
                return;
              }
              showStatusModal('Successfully Deleted!', `Label: ${delLbl}\nValue: ${delVal}`, 'success');
              loadStepOptions(fid, sid);
            } catch (err) {
              showStatusModal('Couldn’t Delete Option', 'Error deleting option: ' + (err?.message || String(err)));
            } finally {
              hideLoader();
            }
          });
        });
      });
    }

    function escapeHtml(str) {
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    async function saveAllOptionsChanges() {
      const fid = optionsModal?.dataset.fieldId;
      const sid = optionsModal?.dataset.stepId;
      if (!fid || !sid) return;
      const rows = Array.from(optionsTableBody.querySelectorAll('tr'));
      const fd = new FormData();
      fd.append('ajax', '1');
      fd.append('action', 'update_options');
      for (const tr of rows) {
        const id = tr.querySelector('input[data-name="option_id"]')?.value;
        const lbl = tr.querySelector('input[data-name="option_label"]')?.value;
        const val = tr.querySelector('input[data-name="option_value"]')?.value;
        const ord = tr.querySelector('input[data-name="option_order"]')?.value;
        if (!id) continue;
        fd.append('id[]', id);
        fd.append('option_label[]', lbl || '');
        fd.append('option_value[]', val || '');
        fd.append('option_order[]', ord || '0');
      }
      try {
        const res = await fetch(`manage_step_options.php?field_id=${encodeURIComponent(fid)}&step_id=${encodeURIComponent(sid)}`, {
          method: 'POST',
          body: fd
        });
        const data = await res.json();
        if (!data || !data.ok) {
          console.warn('Options update failed', data?.error || data);
          optionsModalMessage?.classList?.add('alert-error');
          if (optionsModalMessage) {
            optionsModalMessage.style.display = 'block';
            optionsModalMessage.textContent = data?.error || 'Some options failed to update.';
          }
        } else {
          optionsModalMessage?.classList?.remove('alert-error');
          if (optionsModalMessage) {
            optionsModalMessage.style.display = 'block';
            optionsModalMessage.textContent = 'Options updated.';
          }
        }
      } catch (err) {
        console.error(err);
        if (optionsModalMessage) {
          optionsModalMessage.style.display = 'block';
          optionsModalMessage.textContent = 'Error saving options: ' + (err?.message || String(err));
        }
      }
    }

    // Add Option (using services-style inputs)
    async function addNewOption() {
      const fid = optionsModal?.dataset.fieldId;
      const sid = optionsModal?.dataset.stepId;
      if (!fid || !sid) return;
      const label = document.getElementById('newOptionLabel')?.value?.trim() || '';
      const value = document.getElementById('newOptionValue')?.value?.trim() || '';
      const order = document.getElementById('newOptionOrder')?.value?.trim() || '0';
      if (!label || !value) {
        showStatusModal('Missing Information', 'Label and Value are required.');
        return;
      }
      const detailsHtml = `<div><strong>Label:</strong> ${escapeHtml(label)}</div><div><strong>Value:</strong> ${escapeHtml(value)}</div><div><strong>Order:</strong> ${escapeHtml(order)}</div>`;
      showConfirm('Add Option', detailsHtml, async () => {
        const fd = new FormData();
        fd.append('ajax', '1');
        fd.append('action', 'add_option');
        fd.append('option_label', label);
        fd.append('option_value', value);
        fd.append('option_order', order);
        showLoader();
        try {
          const res = await fetch(`manage_step_options.php?field_id=${encodeURIComponent(fid)}&step_id=${encodeURIComponent(sid)}`, {
            method: 'POST',
            body: fd
          });
          const data = await res.json();
          if (!data || !data.ok) {
            showStatusModal('Couldn’t Add Option', data?.error || 'Failed to add option.');
            return;
          }
          document.getElementById('newOptionLabel').value = '';
          document.getElementById('newOptionValue').value = '';
          document.getElementById('newOptionOrder').value = '0';
          showStatusModal('Successfully Added!', `Label: ${label}\nValue: ${value}`, 'success');
          loadStepOptions(fid, sid);
        } catch (err) {
          showStatusModal('Couldn’t Add Option', 'Error adding option: ' + (err?.message || String(err)));
        } finally {
          hideLoader();
        }
      });
    }
  </script>
</body>

</html>