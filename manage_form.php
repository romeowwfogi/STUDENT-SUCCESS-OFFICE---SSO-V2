<?php
require_once 'middleware/auth.php';
require_once 'connection/db_connect.php';

// Get applicant type context
$applicant_type_id = (int)($_GET['applicant_type_id'] ?? 0);
// Allow AJAX preview requests to proceed without applicant_type_id
if ($applicant_type_id <= 0 && !(
  isset($_GET['ajax']) && $_GET['ajax'] === '1'
)) {
  $_SESSION['message'] = ['type' => 'error', 'text' => 'Missing or invalid applicant type'];
  header('Location: manage_applicant_types.php');
  exit;
}

$type_name = '';
$cycle_name = '';
$cycle_id = 0;
if ($stmt = $conn->prepare('SELECT at.name AS type_name, ac.academic_year_start, ac.academic_year_end, ac.id AS cycle_id FROM applicant_types at INNER JOIN admission_cycles ac ON ac.id = at.admission_cycle_id WHERE at.id = ?')) {
  $stmt->bind_param('i', $applicant_type_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    $type_name = $row['type_name'];
    $ayStart = $row['academic_year_start'] ?? null;
    $ayEnd = $row['academic_year_end'] ?? null;
    $cycle_name = ($ayStart && $ayEnd) ? ('Academic Year ' . $ayStart . '–' . $ayEnd) : '';
    $cycle_id = (int)$row['cycle_id'];
  }
  $stmt->close();
}

// Handle create actions (Field/Step)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Optional redirect override for specific actions
  $post_redirect_url = null;
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'add_field') {
      $step_id = (int)($_POST['step_id'] ?? 0);
      $label = trim($_POST['label'] ?? '');
      $name = trim($_POST['name'] ?? '');
      $input_type = trim($_POST['input_type'] ?? 'text');
      $placeholder_text = trim($_POST['placeholder_text'] ?? '');
      $is_required = isset($_POST['is_required']) ? 1 : 0;

      if ($step_id <= 0 || $label === '' || $name === '') {
        throw new Exception('Please fill all required field details');
      }

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

      if ($ins = $conn->prepare('INSERT INTO form_fields(step_id, name, label, input_type, placeholder_text, is_required, field_order, is_archived) VALUES(?,?,?,?,?,?,?,0)')) {
        $nextOrder = $maxOrder + 1;
        $ins->bind_param('issssii', $step_id, $name, $label, $input_type, $placeholder_text, $is_required, $nextOrder);
        if (!$ins->execute()) {
          throw new Exception('Error adding field: ' . $ins->error);
        }
        $ins->close();
      }

      $_SESSION['message'] = ['type' => 'success', 'text' => 'Field created'];
    } elseif ($action === 'add_step') {
      $title = trim($_POST['title'] ?? '');
      $description = trim($_POST['description'] ?? '');
      $givenOrder = (int)($_POST['step_order'] ?? 0);
      if ($title === '') {
        throw new Exception('Step title is required');
      }

      $maxS = 0;
      if ($ms = $conn->prepare('SELECT COALESCE(MAX(step_order),0) AS maxo FROM form_steps WHERE applicant_type_id = ? AND is_archived = 0')) {
        $ms->bind_param('i', $applicant_type_id);
        $ms->execute();
        $r = $ms->get_result();
        if ($rw = $r->fetch_assoc()) {
          $maxS = (int)$rw['maxo'];
        }
        $ms->close();
      }

      // Insert with optional description if column exists
      $hasDescInsert = false;
      if ($ck = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_steps' AND COLUMN_NAME = 'description'")) {
        $ck->execute();
        $cr = $ck->get_result();
        if ($crow = $cr->fetch_assoc()) {
          $hasDescInsert = ((int)$crow['cnt'] > 0);
        }
        $ck->close();
      }
      $nextOrder = $maxS + 1;
      $desiredOrder = $givenOrder > 0 ? $givenOrder : $nextOrder;

      // If desired order collides with existing, shift subsequent orders to make room
      if ($desiredOrder <= $maxS) {
        $conn->begin_transaction();
        try {
          if ($sh = $conn->prepare('UPDATE form_steps SET step_order = step_order + 1 WHERE applicant_type_id = ? AND is_archived = 0 AND step_order >= ?')) {
            $sh->bind_param('ii', $applicant_type_id, $desiredOrder);
            if (!$sh->execute()) {
              throw new Exception('Error reordering steps: ' . $sh->error);
            }
            $sh->close();
          }
          // Commit; actual insert happens below within same transaction block if we keep it open
          $conn->commit();
        } catch (Exception $re) {
          $conn->rollback();
          throw $re;
        }
      }
      if ($hasDescInsert) {
        if ($ins = $conn->prepare('INSERT INTO form_steps(applicant_type_id, title, description, step_order, is_archived) VALUES(?,?,?,?,0)')) {
          $ins->bind_param('issi', $applicant_type_id, $title, $description, $desiredOrder);
          if (!$ins->execute()) {
            throw new Exception('Error adding step: ' . $ins->error);
          }
          $ins->close();
        }
      } else {
        if ($ins = $conn->prepare('INSERT INTO form_steps(applicant_type_id, title, step_order, is_archived) VALUES(?,?,?,0)')) {
          $ins->bind_param('isi', $applicant_type_id, $title, $desiredOrder);
          if (!$ins->execute()) {
            throw new Exception('Error adding step: ' . $ins->error);
          }
          $ins->close();
        }
      }

      $safeTitle = htmlspecialchars($title, ENT_QUOTES);
      $safeDesc = htmlspecialchars($description, ENT_QUOTES);
      $msg = 'Successfully added a new step <strong>' . $safeTitle . '</strong>';
      if ($description !== '') {
        $msg .= ' with a description of <strong>' . $safeDesc . '</strong>';
      }
      $_SESSION['message'] = ['type' => 'success', 'text' => $msg];
    } elseif ($action === 'update_step') {
      $step_id = (int)($_POST['step_id'] ?? 0);
      $title = trim($_POST['title'] ?? '');
      $description = trim($_POST['description'] ?? '');
      $newOrder = (int)($_POST['step_order'] ?? 0);
      if ($step_id <= 0) {
        throw new Exception('Invalid step');
      }
      if ($title === '') {
        throw new Exception('Step title is required');
      }

      // Update title and description
      if ($up = $conn->prepare('UPDATE form_steps SET title = ?, description = ? WHERE id = ?')) {
        $up->bind_param('ssi', $title, $description, $step_id);
        if (!$up->execute()) {
          throw new Exception('Error updating step: ' . $up->error);
        }
        $up->close();
      }

      // Handle reordering when requested and step is active
      if ($newOrder > 0) {
        $curOrder = 0;
        $isArchived = 0;
        if ($cs = $conn->prepare('SELECT step_order, is_archived FROM form_steps WHERE id = ? AND applicant_type_id = ?')) {
          $cs->bind_param('ii', $step_id, $applicant_type_id);
          $cs->execute();
          $cr = $cs->get_result();
          if ($crow = $cr->fetch_assoc()) {
            $curOrder = (int)($crow['step_order'] ?? 0);
            $isArchived = (int)($crow['is_archived'] ?? 0);
          }
          $cs->close();
        }
        if ($isArchived === 0) {
          $maxOrder = 0;
          if ($ms = $conn->prepare('SELECT COALESCE(MAX(step_order),0) AS maxo FROM form_steps WHERE applicant_type_id = ? AND is_archived = 0')) {
            $ms->bind_param('i', $applicant_type_id);
            $ms->execute();
            $mr = $ms->get_result();
            if ($mrow = $mr->fetch_assoc()) {
              $maxOrder = (int)$mrow['maxo'];
            }
            $ms->close();
          }
          // Clamp new order within bounds
          if ($newOrder < 1) $newOrder = 1;
          if ($newOrder > $maxOrder) $newOrder = $maxOrder;

          if ($curOrder > 0 && $newOrder > 0 && $newOrder !== $curOrder) {
            $conn->begin_transaction();
            try {
              if ($newOrder < $curOrder) {
                // Shift up: move range [newOrder, curOrder-1] up by 1
                if ($sh = $conn->prepare('UPDATE form_steps SET step_order = step_order + 1 WHERE applicant_type_id = ? AND is_archived = 0 AND step_order >= ? AND step_order < ? AND id <> ?')) {
                  $sh->bind_param('iiii', $applicant_type_id, $newOrder, $curOrder, $step_id);
                  if (!$sh->execute()) {
                    throw new Exception('Error shifting steps up: ' . $sh->error);
                  }
                  $sh->close();
                }
              } else {
                // Shift down: move range [curOrder+1, newOrder] down by 1
                if ($sh = $conn->prepare('UPDATE form_steps SET step_order = step_order - 1 WHERE applicant_type_id = ? AND is_archived = 0 AND step_order <= ? AND step_order > ? AND id <> ?')) {
                  $sh->bind_param('iiii', $applicant_type_id, $newOrder, $curOrder, $step_id);
                  if (!$sh->execute()) {
                    throw new Exception('Error shifting steps down: ' . $sh->error);
                  }
                  $sh->close();
                }
              }
              // Set this step to the desired order
              if ($us = $conn->prepare('UPDATE form_steps SET step_order = ? WHERE id = ?')) {
                $us->bind_param('ii', $newOrder, $step_id);
                if (!$us->execute()) {
                  throw new Exception('Error placing step: ' . $us->error);
                }
                $us->close();
              }
              $conn->commit();
            } catch (Exception $re) {
              $conn->rollback();
              throw $re;
            }
          }
        }
      }

      $safeTitle = htmlspecialchars($title, ENT_QUOTES);
      $_SESSION['message'] = ['type' => 'success', 'text' => 'Successfully updated step <strong>' . $safeTitle . '</strong>'];
    } elseif ($action === 'copy_step') {
      // Copy a step and its fields (and options) to another applicant type (possibly different cycle)
      $source_step_id = (int)($_POST['step_id'] ?? 0);
      $target_applicant_type_id = (int)($_POST['target_applicant_type_id'] ?? 0);
      if ($source_step_id <= 0 || $target_applicant_type_id <= 0) {
        throw new Exception('Invalid source step or Applicant Type');
      }

      // Fetch source step
      $src_step = null;
      if ($st = $conn->prepare('SELECT id, title, description, is_archived FROM form_steps WHERE id = ?')) {
        $st->bind_param('i', $source_step_id);
        $st->execute();
        $rs = $st->get_result();
        $src_step = $rs->fetch_assoc() ?: null;
        $st->close();
      }
      if (!$src_step) {
        throw new Exception('Source step not found');
      }

      // Validate Applicant Type exists
      if ($dt = $conn->prepare('SELECT id FROM applicant_types WHERE id = ?')) {
        $dt->bind_param('i', $target_applicant_type_id);
        $dt->execute();
        $dr = $dt->get_result();
        if (!$dr->fetch_assoc()) {
          $dt->close();
          throw new Exception('Applicant Type not found');
        }
        $dt->close();
      }

      // Determine next step order at destination
      $nextOrder = 1;
      if ($ms = $conn->prepare('SELECT COALESCE(MAX(step_order),0) AS maxo FROM form_steps WHERE applicant_type_id = ? AND is_archived = 0')) {
        $ms->bind_param('i', $target_applicant_type_id);
        $ms->execute();
        $r = $ms->get_result();
        if ($rw = $r->fetch_assoc()) {
          $nextOrder = ((int)$rw['maxo']) + 1;
        }
        $ms->close();
      }

      $conn->begin_transaction();
      try {
        // Create destination step
        $new_step_id = 0;
        // Check if description column exists for form_steps
        $hasDescCol = false;
        if ($ck = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_steps' AND COLUMN_NAME = 'description'")) {
          $ck->execute();
          $cr = $ck->get_result();
          if ($crow = $cr->fetch_assoc()) {
            $hasDescCol = ((int)$crow['cnt'] > 0);
          }
          $ck->close();
        }
        if ($hasDescCol) {
          if ($ins = $conn->prepare('INSERT INTO form_steps(applicant_type_id, title, description, is_archived, step_order) VALUES(?, ?, ?, 0, ?)')) {
            $title = (string)($src_step['title'] ?? '');
            $desc  = (string)($src_step['description'] ?? '');
            $ins->bind_param('issi', $target_applicant_type_id, $title, $desc, $nextOrder);
            if (!$ins->execute()) {
              throw new Exception('Error creating destination step: ' . $ins->error);
            }
            $new_step_id = (int)$ins->insert_id;
            $ins->close();
          }
        } else {
          if ($ins = $conn->prepare('INSERT INTO form_steps(applicant_type_id, title, is_archived, step_order) VALUES(?, ?, 0, ?)')) {
            $title = (string)($src_step['title'] ?? '');
            $ins->bind_param('isi', $target_applicant_type_id, $title, $nextOrder);
            if (!$ins->execute()) {
              throw new Exception('Error creating destination step: ' . $ins->error);
            }
            $new_step_id = (int)$ins->insert_id;
            $ins->close();
          }
        }
        if ($new_step_id <= 0) {
          throw new Exception('Failed to create destination step');
        }

        // Copy fields
        $fields_map = []; // old_field_id => new_field_id
        if ($ff = $conn->prepare('SELECT id, name, label, input_type, placeholder_text, is_required, field_order FROM form_fields WHERE step_id = ? AND is_archived = 0 ORDER BY field_order ASC')) {
          $ff->bind_param('i', $source_step_id);
          $ff->execute();
          $res = $ff->get_result();
          while ($row = $res->fetch_assoc()) {
            if ($insf = $conn->prepare('INSERT INTO form_fields(step_id, name, label, input_type, placeholder_text, is_required, field_order, is_archived) VALUES(?,?,?,?,?,?,?,0)')) {
              $nm = (string)($row['name'] ?? '');
              $lbl = (string)($row['label'] ?? '');
              $typ = (string)($row['input_type'] ?? 'text');
              $ph  = (string)($row['placeholder_text'] ?? '');
              $req = (int)($row['is_required'] ?? 0);
              $ord = (int)($row['field_order'] ?? 0);
              $insf->bind_param('issssii', $new_step_id, $nm, $lbl, $typ, $ph, $req, $ord);
              if (!$insf->execute()) {
                throw new Exception('Error copying field: ' . $insf->error);
              }
              $new_field_id = (int)$insf->insert_id;
              $fields_map[(int)$row['id']] = $new_field_id;
              $insf->close();
            }
          }
          $ff->close();
        }

        // Copy options for each field when applicable
        foreach ($fields_map as $old_id => $new_id) {
          if ($opt = $conn->prepare('SELECT option_label, option_value, option_order FROM form_field_options WHERE field_id = ? ORDER BY option_order ASC')) {
            $opt->bind_param('i', $old_id);
            $opt->execute();
            $or = $opt->get_result();
            while ($o = $or->fetch_assoc()) {
              if ($ino = $conn->prepare('INSERT INTO form_field_options(field_id, option_label, option_value, option_order) VALUES(?,?,?,?)')) {
                $lbl = (string)($o['option_label'] ?? '');
                $val = (string)($o['option_value'] ?? '');
                $ord = (int)($o['option_order'] ?? 0);
                $ino->bind_param('issi', $new_id, $lbl, $val, $ord);
                if (!$ino->execute()) {
                  throw new Exception('Error copying field option: ' . $ino->error);
                }
                $ino->close();
              }
            }
            $opt->close();
          }
        }

        $conn->commit();

        $safeTitle = htmlspecialchars((string)($src_step['title'] ?? ''), ENT_QUOTES);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Copied step <strong>' . $safeTitle . '</strong> with its fields to the selected cycle.'];

        // After successful copy, redirect to Applicant Type manage page
        $post_redirect_url = 'manage_form.php?applicant_type_id=' . urlencode((string)$target_applicant_type_id);
      } catch (Exception $cx) {
        $conn->rollback();
        throw $cx;
      }
    }
  } catch (Exception $e) {
    // Provide clearer failed message for add_step with context
    $action = $_POST['action'] ?? $action ?? '';
    if ($action === 'add_step') {
      $title = trim($_POST['title'] ?? '');
      $description = trim($_POST['description'] ?? '');
      $safeTitle = htmlspecialchars(($title !== '' ? $title : '(untitled)'), ENT_QUOTES);
      $safeDesc = htmlspecialchars($description, ENT_QUOTES);
      $msg = 'Failed to add a new step <strong>' . $safeTitle . '</strong>';
      if ($description !== '') {
        $msg .= ' with a description of <strong>' . $safeDesc . '</strong>';
      }
      // Append concise error detail
      $detail = trim($e->getMessage());
      if ($detail !== '') {
        $msg .= ' — ' . $detail;
      }
      $_SESSION['message'] = ['type' => 'error', 'text' => $msg];
    } elseif ($action === 'update_step') {
      $title = trim($_POST['title'] ?? '');
      $safeTitle = htmlspecialchars(($title !== '' ? $title : '(untitled)'), ENT_QUOTES);
      $detail = trim($e->getMessage());
      $msg = 'Failed to update step <strong>' . $safeTitle . '</strong>';
      if ($detail !== '') {
        $msg .= ' — ' . $detail;
      }
      $_SESSION['message'] = ['type' => 'error', 'text' => $msg];
    } else {
      $_SESSION['message'] = ['type' => 'error', 'text' => $e->getMessage()];
    }
  }
  $redirectTo = $post_redirect_url ?: ('manage_form.php?applicant_type_id=' . urlencode((string)$applicant_type_id));
  header('Location: ' . $redirectTo);
  exit;
}

// Handle archive actions
// Lightweight AJAX endpoints
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === '1') {
  header('Content-Type: application/json');
  $action = $_GET['action'] ?? '';
  try {
    if ($action === 'preview_step') {
      $step_id = (int)($_GET['step_id'] ?? 0);
      if ($step_id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid step']);
        exit;
      }
      $step = null;
      if ($st = $conn->prepare('SELECT id, title, description FROM form_steps WHERE id = ?')) {
        $st->bind_param('i', $step_id);
        $st->execute();
        $rs = $st->get_result();
        $step = $rs->fetch_assoc() ?: null;
        $st->close();
      }
      if (!$step) {
        echo json_encode(['ok' => false, 'error' => 'Step not found']);
        exit;
      }
      $fields = [];
      if ($ff = $conn->prepare('SELECT id, label, input_type, is_required, field_order FROM form_fields WHERE step_id = ? AND is_archived = 0 ORDER BY field_order ASC')) {
        $ff->bind_param('i', $step_id);
        $ff->execute();
        $fr = $ff->get_result();
        while ($row = $fr->fetch_assoc()) {
          $optCount = 0;
          if ($oc = $conn->prepare('SELECT COUNT(*) AS cnt FROM form_field_options WHERE field_id = ?')) {
            $fid = (int)($row['id'] ?? 0);
            $oc->bind_param('i', $fid);
            $oc->execute();
            $cr = $oc->get_result();
            if ($crow = $cr->fetch_assoc()) $optCount = (int)$crow['cnt'];
            $oc->close();
          }
          $fields[] = [
            'id' => (int)($row['id'] ?? 0),
            'label' => (string)($row['label'] ?? ''),
            'input_type' => (string)($row['input_type'] ?? ''),
            'is_required' => (int)($row['is_required'] ?? 0),
            'field_order' => (int)($row['field_order'] ?? 0),
            'options_count' => $optCount,
          ];
        }
        $ff->close();
      }
      echo json_encode(['ok' => true, 'step' => $step, 'fields' => $fields]);
      exit;
    }
  } catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
  $action = $_GET['action'];
  try {
    if ($action === 'archive_field') {
      $field_id = (int)($_GET['field_id'] ?? 0);
      if ($field_id <= 0) {
        throw new Exception('Invalid field');
      }
      if ($stmt = $conn->prepare('UPDATE form_fields SET is_archived = 1 WHERE id = ?')) {
        $stmt->bind_param('i', $field_id);
        if (!$stmt->execute()) {
          throw new Exception('Error archiving field: ' . $stmt->error);
        }
        $stmt->close();
      }
      $_SESSION['message'] = ['type' => 'success', 'text' => 'Field archived'];
    } elseif ($action === 'archive_step') {
      $step_id = (int)($_GET['step_id'] ?? 0);
      if ($step_id <= 0) {
        throw new Exception('Invalid step');
      }
      // Fetch step title for messaging
      $step_title = '';
      if ($st = $conn->prepare('SELECT title FROM form_steps WHERE id = ?')) {
        $st->bind_param('i', $step_id);
        $st->execute();
        $rr = $st->get_result();
        if ($rw = $rr->fetch_assoc()) {
          $step_title = (string)($rw['title'] ?? '');
        }
        $st->close();
      }
      $conn->begin_transaction();
      try {
        if ($s1 = $conn->prepare('UPDATE form_steps SET is_archived = 1 WHERE id = ?')) {
          $s1->bind_param('i', $step_id);
          if (!$s1->execute()) {
            throw new Exception('Error archiving step: ' . $s1->error);
          }
          $s1->close();
        }
        if ($s2 = $conn->prepare('UPDATE form_fields SET is_archived = 1 WHERE step_id = ?')) {
          $s2->bind_param('i', $step_id);
          if (!$s2->execute()) {
            throw new Exception('Error archiving fields: ' . $s2->error);
          }
          $s2->close();
        }
        $conn->commit();
        $safeTitle = htmlspecialchars($step_title !== '' ? $step_title : '(untitled)', ENT_QUOTES);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Successfully archiving ' . $safeTitle . ' and its all field'];
      } catch (Exception $ex) {
        $conn->rollback();
        throw $ex;
      }
    } elseif ($action === 'unarchive_step') {
      $step_id = (int)($_GET['step_id'] ?? 0);
      if ($step_id <= 0) {
        throw new Exception('Invalid step');
      }
      // Fetch step title for messaging
      $step_title = '';
      if ($st = $conn->prepare('SELECT title FROM form_steps WHERE id = ?')) {
        $st->bind_param('i', $step_id);
        $st->execute();
        $rr = $st->get_result();
        if ($rw = $rr->fetch_assoc()) {
          $step_title = (string)($rw['title'] ?? '');
        }
        $st->close();
      }
      $conn->begin_transaction();
      try {
        if ($s1 = $conn->prepare('UPDATE form_steps SET is_archived = 0 WHERE id = ?')) {
          $s1->bind_param('i', $step_id);
          if (!$s1->execute()) {
            throw new Exception('Error unarchiving step: ' . $s1->error);
          }
          $s1->close();
        }
        if ($s2 = $conn->prepare('UPDATE form_fields SET is_archived = 0 WHERE step_id = ?')) {
          $s2->bind_param('i', $step_id);
          if (!$s2->execute()) {
            throw new Exception('Error unarchiving fields: ' . $s2->error);
          }
          $s2->close();
        }
        $conn->commit();
        $safeTitle = htmlspecialchars($step_title !== '' ? $step_title : '(untitled)', ENT_QUOTES);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Successfully unarchive ' . $safeTitle . ' and its all field'];
      } catch (Exception $ex) {
        $conn->rollback();
        throw $ex;
      }
    }
  } catch (Exception $e) {
    // Provide failure message matching requested phrasing
    $act = $_GET['action'] ?? '';
    if ($act === 'archive_step' || $act === 'unarchive_step') {
      $step_id = (int)($_GET['step_id'] ?? 0);
      $step_title = '';
      if ($step_id > 0 && ($st = $conn->prepare('SELECT title FROM form_steps WHERE id = ?'))) {
        $st->bind_param('i', $step_id);
        $st->execute();
        $rr = $st->get_result();
        if ($rw = $rr->fetch_assoc()) {
          $step_title = (string)($rw['title'] ?? '');
        }
        $st->close();
      }
      $safeTitle = htmlspecialchars($step_title !== '' ? $step_title : '(untitled)', ENT_QUOTES);
      $text = ($act === 'archive_step') ? ('Failed archiving ' . $safeTitle . ' and its all field') : ('Failed unarchive ' . $safeTitle . ' and its all field');
      $_SESSION['message'] = ['type' => 'error', 'text' => $text];
    } else {
      $_SESSION['message'] = ['type' => 'error', 'text' => $e->getMessage()];
    }
  }
  header('Location: manage_form.php?applicant_type_id=' . urlencode((string)$applicant_type_id));
  exit;
}

// Fetch steps (with optional description if present in schema)
$steps = [];
$hasDescription = false;
if ($ck = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'form_steps' AND COLUMN_NAME = 'description'")) {
  $ck->execute();
  $cr = $ck->get_result();
  if ($crow = $cr->fetch_assoc()) {
    $hasDescription = ((int)$crow['cnt'] > 0);
  }
  $ck->close();
}

if ($hasDescription) {
  if ($st = $conn->prepare('SELECT id, title, step_order, description, is_archived FROM form_steps WHERE applicant_type_id = ? ORDER BY is_archived ASC, step_order ASC')) {
    $st->bind_param('i', $applicant_type_id);
    $st->execute();
    $rs = $st->get_result();
    while ($row = $rs->fetch_assoc()) {
      $steps[] = $row;
    }
    $st->close();
  }
} else {
  if ($st = $conn->prepare('SELECT id, title, step_order, is_archived FROM form_steps WHERE applicant_type_id = ? ORDER BY is_archived ASC, step_order ASC')) {
    $st->bind_param('i', $applicant_type_id);
    $st->execute();
    $rs = $st->get_result();
    while ($row = $rs->fetch_assoc()) {
      $row['description'] = '';
      $steps[] = $row;
    }
    $st->close();
  }
}

// Suggested next order value for the New Step modal
$suggested_next_order = 1;
if ($ms = $conn->prepare('SELECT COALESCE(MAX(step_order),0) AS maxo FROM form_steps WHERE applicant_type_id = ? AND is_archived = 0')) {
  $ms->bind_param('i', $applicant_type_id);
  $ms->execute();
  $r = $ms->get_result();
  if ($rw = $r->fetch_assoc()) {
    $suggested_next_order = ((int)$rw['maxo']) + 1;
  }
  $ms->close();
}

// Fetch cycles and applicant types for copy modal destination selection
$cycles = [];
if ($ac = $conn->query('SELECT id, academic_year_start, academic_year_end FROM admission_cycles ORDER BY academic_year_start DESC')) {
  while ($row = $ac->fetch_assoc()) {
    $cycles[] = [
      'id' => (int)$row['id'],
      'name' => 'Academic Year ' . (string)$row['academic_year_start'] . '–' . (string)$row['academic_year_end']
    ];
  }
  $ac->close();
}
$types_by_cycle = [];
if ($ats = $conn->query('SELECT id, name, admission_cycle_id FROM applicant_types ORDER BY admission_cycle_id DESC, name ASC')) {
  while ($row = $ats->fetch_assoc()) {
    $cid = (int)$row['admission_cycle_id'];
    if (!isset($types_by_cycle[$cid])) $types_by_cycle[$cid] = [];
    $types_by_cycle[$cid][] = [
      'id' => (int)$row['id'],
      'name' => (string)$row['name']
    ];
  }
  $ats->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Fields</title>
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

    .section .card {
      margin-top: 12px;
    }

    /* Action buttons (match Applicant Types) */
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
      color: var(--color-white);
      border-color: var(--color-accent);
    }

    /* Status pill (match Applicant Types) */
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 600;
      font-size: 0.85rem;
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

    /* Loader overlay (match Applicant Types) */
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
      z-index: 4000;
      backdrop-filter: blur(4px);
    }

    .spinner {
      width: 56px;
      height: 56px;
      border: 5px solid rgba(255, 255, 255, 0.25);
      border-top-color: #18a558;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
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
        </div>
        <div class="header__actions">
          <a href="manage_applicant_types.php?cycle_id=<?php echo (int)$cycle_id; ?>" class="btn btn--secondary" title="Back to Applicant Types">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Applicant Types
          </a>
        </div>
      </header>

      <!-- Loader Overlay (match Applicant Types) -->
      <div id="loadingOverlay" class="loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: none; justify-content: center; align-items: center; z-index: 3000; backdrop-filter: blur(4px);">
        <div class="loading-spinner" style="text-align:center;">
          <div class="spinner" style="margin: 0 auto;"></div>
          <div class="loading-text" style="color:#fff; margin-top:12px; font-weight:600; letter-spacing:0.02em;">Processing...</div>
        </div>
      </div>

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

      <!-- Steps Table -->
      <section class="section active" style="margin:0 20px;">
        <div class="card">
          <div class="card__header">
            <div>
              <h2 class="card__title">Steps</h2>
              <p class="table-container__subtitle">List of steps for this applicant type</p>
            </div>
            <div>
              <button class="btn btn--primary" type="button" id="openNewStepModalBtn">Create New Step</button>
            </div>
          </div>
          <table class="table" id="stepsTable_all">
            <thead>
              <tr>
                <th style="width:50px;">ID</th>
                <th style="width:50px;">Order</th>
                <th style="width:200px;">Title</th>
                <th style="width:200px;">Description</th>
                <th style="width:160px;">Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($steps)): foreach ($steps as $step): ?>
                  <tr>
                    <td><?php echo (int)$step['id']; ?></td>
                    <td>#<?php echo (int)$step['step_order']; ?></td>
                    <td><?php echo htmlspecialchars($step['title']); ?></td>
                    <td>
                      <?php
                      $fullDesc = isset($step['description']) ? trim((string)$step['description']) : '';
                      $words = preg_split('/\s+/', $fullDesc, -1, PREG_SPLIT_NO_EMPTY);
                      $needsMore = count($words) > 50;
                      $truncated = $needsMore ? implode(' ', array_slice($words, 0, 50)) . '...' : $fullDesc;
                      echo htmlspecialchars($truncated, ENT_QUOTES);
                      if ($needsMore) {
                        $safeFull = htmlspecialchars($fullDesc, ENT_QUOTES);
                        echo ' <a href="#" class="js-see-more-desc" data-full-desc="' . $safeFull . '" style="margin-left:8px; color:#3182ce; text-decoration:underline;">See more</a>';
                      }
                      ?>
                    </td>
                    <td>
                      <?php $isArchived = ((int)($step['is_archived'] ?? 0) === 1); ?>
                      <?php if (!$isArchived): ?>
                        <span class="status-pill pill-open"><span class="pill-dot"></span> Active</span>
                      <?php else: ?>
                        <span class="status-pill pill-closed"><span class="pill-dot"></span> Archived</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="table__actions">
                        <a class="table__btn table__btn--update js-update-step" href="edit_step.php?step_id=<?php echo urlencode((string)$step['id']); ?>&applicant_type_id=<?php echo urlencode((string)$applicant_type_id); ?>" data-step-id="<?php echo (int)$step['id']; ?>" data-title="<?php echo htmlspecialchars($step['title'] ?? '', ENT_QUOTES); ?>" data-description="<?php echo htmlspecialchars($step['description'] ?? '', ENT_QUOTES); ?>" data-step-order="<?php echo (int)$step['step_order']; ?>">Update</a>
                        <a class="table__btn table__btn--update" href="manage_step_fields.php?step_id=<?php echo urlencode((string)$step['id']); ?>" onclick="showLoader()">Manage Fields</a>
                        <a class="table__btn table__btn--update js-copy-step" href="#" data-step-id="<?php echo (int)$step['id']; ?>" data-step-title="<?php echo htmlspecialchars($step['title'] ?? '', ENT_QUOTES); ?>">Copy</a>
                        <?php if (!$isArchived): ?>
                          <a class="table__btn table__btn--update" href="manage_form.php?applicant_type_id=<?php echo urlencode((string)$applicant_type_id); ?>&action=archive_step&step_id=<?php echo urlencode((string)$step['id']); ?>">Archive</a>
                        <?php else: ?>
                          <a class="table__btn table__btn--update" href="manage_form.php?applicant_type_id=<?php echo urlencode((string)$applicant_type_id); ?>&action=unarchive_step&step_id=<?php echo urlencode((string)$step['id']); ?>">Unarchive</a>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach;
              else: ?>
                <tr>
                  <td colspan="6">No steps yet. Create one to start adding fields.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>




    </main>
  </div>

  <!-- Removed Create Field modal on steps-only page -->

  <!-- New Step Modal (styled like Applicant Types) -->
  <div id="newStepModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color: rgba(0,0,0,0.4); z-index:1001; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div role="dialog" aria-modal="true" aria-labelledby="newStepModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 520px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text);">
      <!-- Close Button -->
      <button type="button" id="closeNewStepModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

      <!-- Modal Header -->
      <div style="padding: 40px 32px 24px 32px; text-align: center;">
        <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
          <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
          </svg>
        </div>
        <h3 id="newStepModalTitle" style="margin:0 0 8px 0; color:#1a202c; font-size:1.6rem; font-weight:700; letter-spacing:-0.025em;">Create New Step</h3>
        <p style="color:#718096; margin:0; line-height:1.5; font-size:0.95rem;">Add a step for this applicant type</p>
      </div>

      <!-- Modal Body -->
      <div style="padding: 0 32px 24px 32px;">
        <form id="newStepForm" method="POST" action="manage_form.php?applicant_type_id=<?php echo (int)$applicant_type_id; ?>">
          <input type="hidden" name="action" value="add_step">
          <div style="margin-bottom: 16px;">
            <label for="newStepTitle" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Step Title</label>
            <input type="text" id="newStepTitle" name="title" placeholder="e.g., Personal Information" required style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
          </div>
          <div style="margin-bottom: 16px;">
            <label for="newStepOrder" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Step Order</label>
            <input type="number" id="newStepOrder" name="step_order" min="1" value="<?php echo (int)$suggested_next_order; ?>" style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
            <p style="margin:6px 0 0 0; color:#718096; font-size:0.85rem;">Defaults to next number. If you set a lower value, later steps shift down automatically.</p>
          </div>
          <div style="margin-bottom: 8px;">
            <label for="newStepDescription" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Description (optional)</label>
            <textarea id="newStepDescription" name="description" rows="3" placeholder="Short details for this step" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;"></textarea>
          </div>
        </form>
      </div>

      <!-- Modal Footer -->
      <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
        <button type="button" id="cancelNewStepBtn" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
        <button type="button" id="confirmNewStepBtn" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Create</button>
      </div>
    </div>
  </div>

  <!-- Copy Step Modal -->
  <div id="copyStepModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color: rgba(0,0,0,0.4); z-index:1001; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div role="dialog" aria-modal="true" aria-labelledby="copyStepModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 520px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text); display:flex; flex-direction:column; max-height:85vh;">
      <!-- Close Button -->
      <button type="button" id="closeCopyStepModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

      <!-- Modal Header (matching New Step) -->
      <div style="padding: 40px 32px 24px 32px; text-align: center;">
        <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
          <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8M8 11h8M8 15h8M4 7h.01M4 11h.01M4 15h.01"></path>
          </svg>
        </div>
        <h3 id="copyStepModalTitle" style="margin:0 0 8px 0; color:#1a202c; font-size:1.6rem; font-weight:700; letter-spacing:-0.025em;">Copy Step</h3>
        <p style="color:#718096; margin:0; line-height:1.5; font-size:0.95rem;">Preview the step and fields, then choose a destination.</p>
      </div>

      <!-- Modal Body -->
      <div style="padding: 0 32px 24px 32px; flex:1; overflow-y:auto; min-height:0;">
        <div style="border:1px solid #E2E8F0; border-radius:12px; padding:14px; margin-bottom:12px; position:relative;">
          <div style="font-weight:600; margin-bottom:8px;">Step Preview</div>
          <div id="copyPreviewLoading" style="display:none; position:absolute; inset:0; background: rgba(255,255,255,0.7); backdrop-filter: blur(2px); display:flex; align-items:center; justify-content:center; border-radius:12px;">
            <div class="spinner"></div>
          </div>
          <div id="copyStepPreviewTitle" style="margin-bottom:6px; color:#2d3748;"></div>
          <div id="copyStepPreviewDescription" style="margin-bottom:10px; color:#718096;"></div>
          <div style="max-height:220px; overflow:auto; border-top:1px solid #edf2f7; padding-top:10px;">
            <table style="width:100%; border-collapse:collapse;">
              <thead>
                <tr>
                  <th style="text-align:left; padding:6px 8px; border-bottom:1px solid #e2e8f0;">Order</th>
                  <th style="text-align:left; padding:6px 8px; border-bottom:1px solid #e2e8f0;">Label</th>
                  <th style="text-align:left; padding:6px 8px; border-bottom:1px solid #e2e8f0;">Type</th>
                  <th style="text-align:left; padding:6px 8px; border-bottom:1px solid #e2e8f0;">Required</th>
                  <th style="text-align:left; padding:6px 8px; border-bottom:1px solid #e2e8f0;">Options</th>
                </tr>
              </thead>
              <tbody id="copyStepPreviewFields"></tbody>
            </table>
          </div>
        </div>

        <form id="copyStepForm" method="POST" action="manage_form.php?applicant_type_id=<?php echo (int)$applicant_type_id; ?>">
          <input type="hidden" name="action" value="copy_step">
          <input type="hidden" name="step_id" id="copyStepFormStepId" value="">
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div>
              <label style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Academic Year</label>
              <select id="copyDestCycle" style="width:100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;"></select>
            </div>
            <div>
              <label style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Applicant Type</label>
              <select name="target_applicant_type_id" id="copyDestApplicantType" required style="width:100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;"></select>
            </div>
          </div>
          <!-- Modal Footer (match New Step modal styles) -->
          <div style="padding-top: 8px; display: flex; gap: 12px; justify-content: center;">
            <button type="button" id="cancelCopyStepBtn" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
            <button type="button" id="confirmCopyStepBtn" disabled style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4); opacity:0.7; cursor:not-allowed;">Copy</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Embed cycles and applicant types data for destination selection
    window.cyclesData = <?php echo json_encode($cycles, JSON_UNESCAPED_UNICODE); ?>;
    window.typesByCycle = <?php echo json_encode($types_by_cycle, JSON_UNESCAPED_UNICODE); ?>;

    // Safe loader function if not defined
    if (typeof window.showLoader !== 'function') {
      window.showLoader = function(msg) {
        const overlay = document.getElementById('loadingOverlay');
        if (!overlay) return;
        overlay.style.display = 'flex';
        const t = overlay.querySelector('.loading-text');
        if (t) t.textContent = msg || 'Processing...';
      };
    }

    // Copy Step Modal logic
    const copyModal = document.getElementById('copyStepModal');
    const closeCopyModalBtn = document.getElementById('closeCopyStepModalBtn');
    const cancelCopyStepBtn = document.getElementById('cancelCopyStepBtn');
    const confirmCopyStepBtn = document.getElementById('confirmCopyStepBtn');
    const previewTitleEl = document.getElementById('copyStepPreviewTitle');
    const previewDescEl = document.getElementById('copyStepPreviewDescription');
    const previewFieldsTbody = document.getElementById('copyStepPreviewFields');
    const previewLoadingEl = document.getElementById('copyPreviewLoading');
    const copyForm = document.getElementById('copyStepForm');
    const copyStepIdInput = document.getElementById('copyStepFormStepId');
    const destCycleSelect = document.getElementById('copyDestCycle');
    const destTypeSelect = document.getElementById('copyDestApplicantType');
    const curCycleId = <?php echo (int)$cycle_id; ?>;
    const curTypeId = <?php echo (int)$applicant_type_id; ?>;

    function escapeHtml(s) {
      return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function populateCycles() {
      if (!destCycleSelect) return;
      destCycleSelect.innerHTML = '';
      const cycles = (window.cyclesData || []);
      cycles.forEach(c => {
        const opt = document.createElement('option');
        opt.value = String(c.id);
        opt.textContent = String(c.name || ('Cycle ' + c.id));
        destCycleSelect.appendChild(opt);
      });
      // Prefer current cycle if present; otherwise first
      const curOpt = Array.from(destCycleSelect.options).find(o => Number(o.value) === Number(curCycleId));
      if (curOpt) {
        destCycleSelect.value = String(curCycleId);
      } else if (destCycleSelect.options.length > 0) {
        destCycleSelect.value = destCycleSelect.options[0].value;
      }
      populateApplicantTypes();
      // Prefer current applicant type if present in selected cycle
      const tOpt = Array.from(destTypeSelect.options).find(o => Number(o.value) === Number(curTypeId));
      if (tOpt) destTypeSelect.value = String(curTypeId);
    }

    function populateApplicantTypes() {
      if (!destTypeSelect) return;
      destTypeSelect.innerHTML = '';
      const cid = parseInt(destCycleSelect.value || '0', 10);
      const list = (window.typesByCycle && window.typesByCycle[cid]) ? window.typesByCycle[cid] : [];
      list.forEach(t => {
        const opt = document.createElement('option');
        opt.value = String(t.id);
        opt.textContent = String(t.name || ('Applicant Type ' + t.id));
        destTypeSelect.appendChild(opt);
      });
      // Default to first if current type not present
      if (destTypeSelect.options.length > 0 && !Array.from(destTypeSelect.options).some(o => Number(o.value) === Number(curTypeId))) {
        destTypeSelect.value = destTypeSelect.options[0].value;
      }
    }

    destCycleSelect?.addEventListener('change', populateApplicantTypes);

    function openCopyModal(stepId, stepTitle) {
      // Populate destination selectors
      populateCycles();
      copyStepIdInput.value = String(stepId);
      // Fetch preview via AJAX
      setCopyPreviewLoading(true);
      previewTitleEl.textContent = 'Loading preview…';
      previewDescEl.textContent = '';
      previewFieldsTbody.innerHTML = '';
      fetch(`manage_form.php?ajax=1&action=preview_step&step_id=${encodeURIComponent(stepId)}`)
        .then(res => res.json())
        .then(data => {
          if (!data || !data.ok) {
            previewTitleEl.textContent = 'Failed to load preview';
            previewDescEl.textContent = escapeHtml(data?.error || 'Unknown error');
            setCopyPreviewLoading(false);
            return;
          }
          previewTitleEl.textContent = escapeHtml((data.step?.title) || stepTitle || '');
          previewDescEl.textContent = escapeHtml((data.step?.description) || '');
          const rows = (data.fields || []).map(f => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td style="padding:6px 8px; border-bottom:1px solid #edf2f7;">#${Number(f.field_order || 0)}</td>
              <td style="padding:6px 8px; border-bottom:1px solid #edf2f7;">${escapeHtml(f.label)}</td>
              <td style="padding:6px 8px; border-bottom:1px solid #edf2f7;">${escapeHtml(f.input_type)}</td>
              <td style="padding:6px 8px; border-bottom:1px solid #edf2f7;">${(Number(f.is_required) === 1) ? 'Yes' : 'No'}</td>
              <td style="padding:6px 8px; border-bottom:1px solid #edf2f7;">${Number(f.options_count || 0)}</td>
            `;
            return tr;
          });
          rows.forEach(r => previewFieldsTbody.appendChild(r));
          setCopyPreviewLoading(false);
        })
        .catch(err => {
          previewTitleEl.textContent = 'Failed to load preview';
          previewDescEl.textContent = escapeHtml(String(err));
          setCopyPreviewLoading(false);
        });

      copyModal.style.display = 'flex';
    }

    // Hook copy buttons
    document.querySelectorAll('.js-copy-step').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const sid = btn.getAttribute('data-step-id');
        const stitle = btn.getAttribute('data-step-title');
        if (!sid) return;
        openCopyModal(sid, stitle || '');
      });
    });

    // Close/cancel handlers
    closeCopyModalBtn?.addEventListener('click', () => {
      copyModal.style.display = 'none';
    });
    cancelCopyStepBtn?.addEventListener('click', () => {
      copyModal.style.display = 'none';
    });
    copyModal?.addEventListener('click', (e) => {
      if (e.target === copyModal) copyModal.style.display = 'none';
    });

    // Confirm copy
    confirmCopyStepBtn?.addEventListener('click', () => {
      if (!copyForm) return;
      const destTypeId = destTypeSelect?.value || '';
      if (!destTypeId) {
        alert('Please choose a Applicant Type.');
        destTypeSelect?.focus();
        return;
      }
      // Show loader and submit
      showLoader('Copying step…');
      copyModal.style.display = 'none';
      setTimeout(() => copyForm.submit(), 100);
    });

    function setCopyPreviewLoading(isLoading) {
      if (previewLoadingEl) previewLoadingEl.style.display = isLoading ? 'flex' : 'none';
      if (confirmCopyStepBtn) {
        confirmCopyStepBtn.disabled = isLoading;
        confirmCopyStepBtn.style.opacity = isLoading ? '0.7' : '';
        confirmCopyStepBtn.style.cursor = isLoading ? 'not-allowed' : '';
      }
    }
  </script>

  <!-- Status Message Modal (match Applicant Types) -->
  <!-- Edit Step Modal -->
  <div id="editStepModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color: rgba(0,0,0,0.4); z-index:1001; align-items:center; justify-content:center; backdrop-filter: blur(4px);">
    <div role="dialog" aria-modal="true" aria-labelledby="editStepModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 520px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text);">
      <!-- Close Button -->
      <button type="button" id="closeEditStepModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

      <!-- Modal Header -->
      <div style="padding: 40px 32px 24px 32px; text-align: center;">
        <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
          <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h2m2 0h2m-2 0v2m0 2h-2m-2 0H9m2 0V7M6 20h12a2 2 0 002-2V8a2 2 0 00-2-2h-3l-2-2H9L7 6H4a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
        </div>
        <h3 id="editStepModalTitle" style="margin:0 0 8px 0; color:#1a202c; font-size:1.6rem; font-weight:700; letter-spacing:-0.025em;">Update Step</h3>
        <p style="color:#718096; margin:0; line-height:1.5; font-size:0.95rem;">Edit this step’s details</p>
      </div>

      <!-- Modal Body -->
      <div style="padding: 0 32px 24px 32px;">
        <form id="editStepForm" method="POST" action="manage_form.php?applicant_type_id=<?php echo (int)$applicant_type_id; ?>">
          <input type="hidden" name="action" value="update_step">
          <input type="hidden" id="editStepId" name="step_id" value="">
          <div style="margin-bottom: 16px;">
            <label for="editStepTitle" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Step Title</label>
            <input type="text" id="editStepTitle" name="title" placeholder="e.g., Personal Information" required style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
          </div>
          <div style="margin-bottom: 16px;">
            <label for="editStepOrder" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Step Order</label>
            <input type="number" id="editStepOrder" name="step_order" min="1" value="" style="width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
            <p style="margin:6px 0 0 0; color:#718096; font-size:0.85rem;">Changing this will re-order steps automatically.</p>
          </div>
          <div style="margin-bottom: 8px;">
            <label for="editStepDescription" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Description (optional)</label>
            <textarea id="editStepDescription" name="description" rows="3" placeholder="Short details for this step" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;"></textarea>
          </div>
        </form>
      </div>

      <!-- Modal Footer -->
      <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
        <button type="button" id="cancelEditStepBtn" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
        <button type="button" id="confirmEditStepBtn" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Update</button>
      </div>
    </div>
  </div>
  <div id="statusModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div role="dialog" aria-modal="true" aria-labelledby="statusModalTitle" style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 420px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); color: var(--color-text);">
      <div style="padding: 32px 32px 16px 32px;">
        <div id="statusModalIcon" style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;"></div>
        <h3 id="statusModalTitle" style="margin: 0 0 12px 0; color: #1a202c; font-size: 1.5rem; font-weight: 700; letter-spacing: -0.025em;">Action Status</h3>
        <p id="statusModalMessage" style="color: #718096; margin: 0; line-height: 1.6; font-size: 0.95rem;">Done.</p>
      </div>
      <div style="padding: 16px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
        <button id="statusModalCloseBtn" style="flex: 1; padding: 12px 24px; border: none; background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; border: 2px solid var(--color-border);">Close</button>
      </div>
    </div>
  </div>

  <!-- Confirmation Modal (consistent with Applicant Types) -->
  <div id="confirmationModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
    <div role="dialog" aria-modal="true" aria-labelledby="modalTitle" style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 420px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
      <div style="padding: 32px 32px 16px 32px;">
        <div id="modalIcon" style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
          <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
          </svg>
        </div>
        <h3 id="modalTitle" style="margin: 0 0 12px 0; color: #1a202c; font-size: 1.5rem; font-weight: 700; letter-spacing: -0.025em;">Confirm New Step</h3>
        <p id="modalMessage" style="color: #718096; margin: 0; line-height: 1.6; font-size: 0.95rem;">Done.</p>
      </div>
      <div style="padding: 16px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
        <button id="modalCancelBtn" style="flex: 1; padding: 12px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
        <button id="modalConfirmBtn" style="flex: 1; padding: 12px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Confirm</button>
      </div>
    </div>
  </div>

  <!-- Full Description Modal (styled like Update Step; only close button) -->
  <div id="descriptionModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
    <div role="dialog" aria-modal="true" aria-labelledby="descriptionModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 520px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); position: relative; color: var(--color-text);">
      <!-- Close Button (only control) -->
      <button type="button" id="closeDescriptionModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

      <!-- Modal Header (match style) -->
      <div style="padding: 40px 32px 16px 32px; text-align: center;">
        <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 24px auto; display: flex; align-items: center; justify-content: center;">
          <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h10M7 11h10M7 15h6"></path>
          </svg>
        </div>
        <h3 id="descriptionModalTitle" style="margin:0 0 8px 0; color:#1a202c; font-size:1.6rem; font-weight:700; letter-spacing:-0.025em;">Full Description</h3>
        <p style="color:#718096; margin:0; line-height:1.5; font-size:0.95rem;">Expanded details for this step</p>
      </div>

      <!-- Modal Body -->
      <div style="padding: 0 32px 32px 32px;">
        <div id="descriptionModalMessage" style="color: #4a5568; line-height: 1.7; font-size: 0.95rem; white-space: pre-wrap; word-break: break-word;"></div>
      </div>
    </div>
  </div>

  <script>
    // Global loader controls (match Applicant Types)
    window.showLoader = function(text) {
      var loader = document.getElementById('loadingOverlay');
      if (loader) {
        var lt = loader.querySelector('.loading-text');
        if (lt && text) lt.textContent = text;
        document.body.appendChild(loader);
        loader.style.display = 'flex';
      }
    };
    window.hideLoader = function() {
      var loader = document.getElementById('loadingOverlay');
      if (loader) loader.style.display = 'none';
    };

    // Status modal helpers
    // Status modal helpers (match Applicant Types)
    window.showStatusModal = function(title, message, kind) {
      const modal = document.getElementById('statusModal');
      if (!modal) return;
      const t = modal.querySelector('#statusModalTitle');
      const m = modal.querySelector('#statusModalMessage');
      const icon = modal.querySelector('#statusModalIcon');
      t.textContent = title || (kind === 'success' ? 'Success' : 'Failed');
      m.innerHTML = message || '';
      if (kind === 'success') {
        icon.style.background = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
        icon.innerHTML = '<svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"></path></svg>';
      } else {
        icon.style.background = 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)';
        icon.innerHTML = '<svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>';
      }
      modal.style.display = 'flex';
    };
    window.closeStatusModal = function() {
      const modal = document.getElementById('statusModal');
      if (modal) modal.style.display = 'none';
    };

    document.getElementById('statusModal')?.addEventListener('click', (e) => {
      if (e.target === document.getElementById('statusModal')) {
        window.closeStatusModal();
      }
    });
    document.getElementById('statusModalCloseBtn')?.addEventListener('click', () => window.closeStatusModal());

    // Confirmation modal helpers (matching Applicant Types)
    const confirmationModal = document.getElementById('confirmationModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalConfirmBtn = document.getElementById('modalConfirmBtn');
    const modalCancelBtn = document.getElementById('modalCancelBtn');
    let currentAction = '';

    function showConfirmationModal(title, message, actionKey) {
      if (!confirmationModal) return;
      modalTitle.textContent = title;
      modalMessage.innerHTML = message;
      currentAction = actionKey || '';
      confirmationModal.style.display = 'flex';
    }

    function closeConfirmationModal() {
      if (confirmationModal) confirmationModal.style.display = 'none';
    }

    const newStepModal = document.getElementById('newStepModal');
    const openNewStepModalBtn = document.getElementById('openNewStepModalBtn');
    const closeNewStepModalBtn = document.getElementById('closeNewStepModalBtn');
    const cancelNewStepBtn = document.getElementById('cancelNewStepBtn');
    const confirmNewStepBtn = document.getElementById('confirmNewStepBtn');

    openNewStepModalBtn?.addEventListener('click', () => {
      newStepModal.style.display = 'flex';
      document.getElementById('newStepTitle')?.focus();
    });
    closeNewStepModalBtn?.addEventListener('click', () => {
      newStepModal.style.display = 'none';
    });
    cancelNewStepBtn?.addEventListener('click', () => {
      newStepModal.style.display = 'none';
    });
    newStepModal.addEventListener('click', (e) => {
      if (e.target === newStepModal) {
        newStepModal.style.display = 'none';
      }
    });

    // Show confirmation on Create
    confirmNewStepBtn?.addEventListener('click', () => {
      const form = document.querySelector('#newStepModal form');
      if (!form) return;
      const title = (form.querySelector('#newStepTitle')?.value || '').trim();
      const description = (form.querySelector('#newStepDescription')?.value || '').trim();
      const order = (form.querySelector('#newStepOrder')?.value || '').trim();
      if (!title) {
        alert('Step title is required.');
        form.querySelector('#newStepTitle')?.focus();
        return;
      }
      const html = `
        <div style="margin-top:6px; text-align:center;">
          <div><strong>Title:</strong> ${title.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>
          ${order ? `<div><strong>Order:</strong> ${order.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>` : ''}
          ${description ? `<div><strong>Description:</strong> ${description.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>` : ''}
        </div>
      `;
      showConfirmationModal('Confirm New Step', html, 'create_new_step');
    });

    // Edit Step Modal wiring
    const editStepModal = document.getElementById('editStepModal');
    const closeEditStepModalBtn = document.getElementById('closeEditStepModalBtn');
    const cancelEditStepBtn = document.getElementById('cancelEditStepBtn');
    const confirmEditStepBtn = document.getElementById('confirmEditStepBtn');
    const editStepForm = document.getElementById('editStepForm');

    // Open Edit modal from Update links
    document.querySelectorAll('.js-update-step').forEach((link) => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const sid = link.getAttribute('data-step-id') || '';
        const title = link.getAttribute('data-title') || '';
        const desc = link.getAttribute('data-description') || '';
        const order = link.getAttribute('data-step-order') || '';
        document.getElementById('editStepId')?.setAttribute('value', sid);
        const titleInput = document.getElementById('editStepTitle');
        const descInput = document.getElementById('editStepDescription');
        const orderInput = document.getElementById('editStepOrder');
        if (titleInput) titleInput.value = title;
        if (descInput) descInput.value = desc;
        if (orderInput) orderInput.value = order;
        editStepModal.style.display = 'flex';
        titleInput?.focus();
      });
    });

    closeEditStepModalBtn?.addEventListener('click', () => {
      editStepModal.style.display = 'none';
    });
    cancelEditStepBtn?.addEventListener('click', () => {
      editStepModal.style.display = 'none';
    });
    editStepModal?.addEventListener('click', (e) => {
      if (e.target === editStepModal) {
        editStepModal.style.display = 'none';
      }
    });

    // Show confirmation on Update
    confirmEditStepBtn?.addEventListener('click', () => {
      if (!editStepForm) return;
      const title = (editStepForm.querySelector('#editStepTitle')?.value || '').trim();
      const description = (editStepForm.querySelector('#editStepDescription')?.value || '').trim();
      const order = (editStepForm.querySelector('#editStepOrder')?.value || '').trim();
      if (!title) {
        alert('Step title is required.');
        editStepForm.querySelector('#editStepTitle')?.focus();
        return;
      }
      const html = `
        <div style="margin-top:6px; text-align:center;">
          <div><strong>Title:</strong> ${title.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>
          ${order ? `<div><strong>Order:</strong> ${order.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>` : ''}
          ${description ? `<div><strong>Description:</strong> ${description.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>` : ''}
        </div>
      `;
      showConfirmationModal('Confirm Update', html, 'update_step');
    });

    modalCancelBtn?.addEventListener('click', () => {
      closeConfirmationModal();
    });
    confirmationModal?.addEventListener('click', (e) => {
      if (e.target === confirmationModal) closeConfirmationModal();
    });

    // See more: open full description modal
    document.addEventListener('click', (e) => {
      const link = e.target.closest('.js-see-more-desc');
      if (!link) return;
      e.preventDefault();
      const full = link.getAttribute('data-full-desc') || '';
      const msgEl = document.getElementById('descriptionModalMessage');
      if (msgEl) {
        msgEl.textContent = full;
      }
      const dModal = document.getElementById('descriptionModal');
      if (dModal) dModal.style.display = 'flex';
    });
    const dModal = document.getElementById('descriptionModal');
    const closeDescriptionModalBtn = document.getElementById('closeDescriptionModalBtn');
    const descriptionModalCloseBtn = document.getElementById('descriptionModalCloseBtn');
    closeDescriptionModalBtn?.addEventListener('click', () => {
      dModal.style.display = 'none';
    });
    descriptionModalCloseBtn?.addEventListener('click', () => {
      dModal.style.display = 'none';
    });
    dModal?.addEventListener('click', (e) => {
      if (e.target === dModal) dModal.style.display = 'none';
    });

    modalConfirmBtn?.addEventListener('click', () => {
      if (currentAction === 'create_new_step') {
        const form = document.querySelector('#newStepModal form');
        if (!form) return;
        // Show loader and close modals
        showLoader('Creating step…');
        newStepModal.style.display = 'none';
        closeConfirmationModal();
        // Defer submit slightly to allow loader paint
        setTimeout(() => {
          form.submit();
        }, 100);
      } else if (currentAction === 'update_step') {
        const form = document.querySelector('#editStepModal form');
        if (!form) return;
        showLoader('Updating step…');
        editStepModal.style.display = 'none';
        closeConfirmationModal();
        setTimeout(() => {
          form.submit();
        }, 100);
      } else if (currentAction && typeof currentAction === 'string') {
        // Treat currentAction as a URL and navigate after showing loader
        const isUnarchive = currentAction.includes('action=unarchive_step');
        showLoader(isUnarchive ? 'Unarchiving step…' : 'Archiving step…');
        closeConfirmationModal();
        setTimeout(() => {
          window.location.href = currentAction;
        }, 100);
      }
    });

    // Intercept archive and unarchive links to use confirmation modal with loader
    document.querySelectorAll('a[href*="action=archive_step"], a[href*="action=unarchive_step"]').forEach((link) => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const isUnarchive = link.href.includes('action=unarchive_step');
        const title = isUnarchive ? 'Confirm Unarchive' : 'Confirm Archive';
        const msg = isUnarchive ? 'Unarchive this step and all its fields?' : 'Archive this step and all its fields?';
        showConfirmationModal(title, msg, link.href);
      });
    });
  </script>

</body>

</html>