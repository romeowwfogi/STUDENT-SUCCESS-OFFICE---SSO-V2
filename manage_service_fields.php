<?php
// Authentication middleware - protect this endpoint
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

header('Content-Type: application/json');

function json_error($msg, $code = 400)
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function json_ok($data = [])
{
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$isAjax = isset($_POST['ajax']) || isset($_GET['ajax']);

if (!$isAjax) {
    json_error('Invalid request: ajax flag missing');
}

// Detect whether conditional visibility columns exist before using them
$hasConditionalCols = false;
$conditionalMode = 'none'; // 'value' => uses (visible_when_field_id, visible_when_value); 'option_id' => uses (visible_when_option_id)
try {
    $chkFieldId = $conn->query("SHOW COLUMNS FROM services_fields LIKE 'visible_when_field_id'");
    $chkValue    = $conn->query("SHOW COLUMNS FROM services_fields LIKE 'visible_when_value'");
    $chkOptionId = $conn->query("SHOW COLUMNS FROM services_fields LIKE 'visible_when_option_id'");
    if ($chkOptionId && $chkOptionId->num_rows > 0) {
        $hasConditionalCols = true;
        $conditionalMode = 'option_id';
    } elseif ($chkFieldId && $chkFieldId->num_rows > 0 && $chkValue && $chkValue->num_rows > 0) {
        $hasConditionalCols = true;
        $conditionalMode = 'value';
    }
    if ($chkFieldId) {
        $chkFieldId->close();
    }
    if ($chkValue) {
        $chkValue->close();
    }
    if ($chkOptionId) {
        $chkOptionId->close();
    }
} catch (Exception $e) {
    // If SHOW COLUMNS fails, default to false (no conditional columns)
    $hasConditionalCols = false;
    $conditionalMode = 'none';
}

// Detect whether max file size column exists
$hasMaxFileSizeCol = false;
try {
    $chkMax = $conn->query("SHOW COLUMNS FROM services_fields LIKE 'max_file_size_mb'");
    if ($chkMax && $chkMax->num_rows > 0) {
        $hasMaxFileSizeCol = true;
    }
    if ($chkMax) { $chkMax->close(); }
} catch (Exception $e) {
    $hasMaxFileSizeCol = false;
}

if ($method === 'POST' && $action === 'add_field') {
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $label = trim($_POST['label'] ?? '');
    $field_type = strtolower(trim($_POST['field_type'] ?? ''));
    $is_required = isset($_POST['is_required']) ? (int)$_POST['is_required'] : 1;
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    $allowed_file_types = trim($_POST['allowed_file_types'] ?? '');
    $max_file_size_mb_raw = trim($_POST['max_file_size_mb'] ?? '');
    $visible_when_field_id_raw = trim($_POST['visible_when_field_id'] ?? '');
    $visible_when_value = trim($_POST['visible_when_value'] ?? '');

    if ($service_id <= 0) json_error('service_id is required');
    if ($label === '') json_error('label is required');
    $allowed_types = ['text', 'textarea', 'date', 'number', 'email', 'select', 'checkbox', 'radio', 'file'];
    if (!in_array($field_type, $allowed_types, true)) json_error('Invalid field_type');
    if ($is_required !== 0 && $is_required !== 1) $is_required = 1;

    // If file type, validate allowed_file_types pattern (simple check: only commas and leading dots, no spaces)
    if ($field_type === 'file' && $allowed_file_types !== '') {
        if (preg_match('/\s/', $allowed_file_types)) {
            json_error('allowed_file_types must be comma-separated without spaces, e.g., .pdf,.jpg');
        }
        if (!preg_match('/^(\.[A-Za-z0-9]+)(,\.[A-Za-z0-9]+)*$/', $allowed_file_types)) {
            json_error('allowed_file_types format invalid. Example: .pdf,.jpg,.png');
        }
    } else {
        $allowed_file_types = null; // store as NULL when not applicable
    }

    // If file type, validate max_file_size_mb (optional)
    $max_file_size_mb = null;
    if ($field_type === 'file' && $max_file_size_mb_raw !== '') {
        if (!preg_match('/^\d+$/', $max_file_size_mb_raw)) {
            json_error('max_file_size_mb must be a positive integer');
        }
        $max_file_size_mb = (int)$max_file_size_mb_raw;
        if ($max_file_size_mb <= 0 || $max_file_size_mb > 2048) { // cap at 2 GB for safety
            json_error('max_file_size_mb must be between 1 and 2048');
        }
    } else {
        $max_file_size_mb = null; // not applicable or empty
    }

    // Conditional visibility normalization (only if columns exist)
    $visible_when_field_id = null;
    $visible_when_option_id = null;
    if ($hasConditionalCols) {
        if ($conditionalMode === 'value') {
            if ($visible_when_field_id_raw !== '') {
                $visible_when_field_id = (int)$visible_when_field_id_raw;
                if ($visible_when_field_id <= 0) {
                    $visible_when_field_id = null;
                }
            }
            if ($visible_when_field_id !== null && $visible_when_value === '') {
                json_error('visible_when_value is required when controller field is set');
            }
            if ($visible_when_field_id !== null) {
                // Validate controller field belongs to the same service and is of allowed type
                $stmtCtl = $conn->prepare('SELECT field_type FROM services_fields WHERE field_id = ? AND service_id = ?');
                $stmtCtl->bind_param('ii', $visible_when_field_id, $service_id);
                if (!$stmtCtl->execute()) {
                    $stmtCtl->close();
                    json_error('Failed to validate controller field: ' . $conn->error, 500);
                }
                $resCtl = $stmtCtl->get_result();
                if (!$resCtl || $resCtl->num_rows === 0) {
                    $stmtCtl->close();
                    json_error('Invalid controller field for this service');
                }
                $rowCtl = $resCtl->fetch_assoc();
                $resCtl->close();
                $stmtCtl->close();
                $ctl_type = strtolower($rowCtl['field_type'] ?? '');
                $allowed_ctl = ['select', 'checkbox', 'radio'];
                if (!in_array($ctl_type, $allowed_ctl, true)) {
                    json_error('Controller field type must be select, checkbox, or radio');
                }
            } else {
                $visible_when_value = null; // no controller => clear value
            }
        } elseif ($conditionalMode === 'option_id') {
            // Resolve option_id based on provided controller field and trigger value
            if ($visible_when_field_id_raw !== '') {
                $visible_when_field_id = (int)$visible_when_field_id_raw;
                if ($visible_when_field_id <= 0) {
                    $visible_when_field_id = null;
                }
            }
            if ($visible_when_field_id !== null && $visible_when_value === '') {
                json_error('visible_when_value is required when controller field is set');
            }
            if ($visible_when_field_id !== null) {
                // Validate controller field belongs to the same service and is of allowed type
                $stmtCtl = $conn->prepare('SELECT field_type FROM services_fields WHERE field_id = ? AND service_id = ?');
                $stmtCtl->bind_param('ii', $visible_when_field_id, $service_id);
                if (!$stmtCtl->execute()) {
                    $stmtCtl->close();
                    json_error('Failed to validate controller field: ' . $conn->error, 500);
                }
                $resCtl = $stmtCtl->get_result();
                if (!$resCtl || $resCtl->num_rows === 0) {
                    $stmtCtl->close();
                    json_error('Invalid controller field for this service');
                }
                $rowCtl = $resCtl->fetch_assoc();
                $resCtl->close();
                $stmtCtl->close();
                $ctl_type = strtolower($rowCtl['field_type'] ?? '');
                $allowed_ctl = ['select', 'radio', 'checkbox'];
                if (!in_array($ctl_type, $allowed_ctl, true)) {
                    json_error('Controller field must be select, radio, or checkbox when using option_id schema');
                }
                // Map value to option_id
                $stmtOpt = $conn->prepare('SELECT option_id FROM services_field_options WHERE field_id = ? AND option_value = ? LIMIT 1');
                $stmtOpt->bind_param('is', $visible_when_field_id, $visible_when_value);
                if (!$stmtOpt->execute()) {
                    $stmtOpt->close();
                    json_error('Failed to resolve controller option: ' . $conn->error, 500);
                }
                $resOpt = $stmtOpt->get_result();
                if (!$resOpt || $resOpt->num_rows === 0) {
                    $stmtOpt->close();
                    json_error('Trigger value must match an existing option for the selected controller field');
                }
                $rowOpt = $resOpt->fetch_assoc();
                $visible_when_option_id = (int)$rowOpt['option_id'];
                $resOpt->close();
                $stmtOpt->close();
            }
            // In option_id mode, we do not store visible_when_field_id or visible_when_value
        }
    } else {
        // Columns missing; ignore provided conditional values
        $visible_when_value = null;
        $visible_when_field_id = null;
        $visible_when_option_id = null;
    }

    // If display_order is 0, compute next order
    if ($display_order === 0) {
        $stmtOrder = $conn->prepare('SELECT COALESCE(MAX(display_order), 0) AS max_order FROM services_fields WHERE service_id = ?');
        $stmtOrder->bind_param('i', $service_id);
        if ($stmtOrder->execute()) {
            $resOrder = $stmtOrder->get_result();
            $rowOrder = $resOrder->fetch_assoc();
            $display_order = (int)($rowOrder['max_order'] ?? 0) + 1;
            $resOrder->close();
        }
        $stmtOrder->close();
    }

    // Insert new field
    if ($hasConditionalCols && $conditionalMode === 'value') {
        if ($hasMaxFileSizeCol) {
            $sql = 'INSERT INTO services_fields (service_id, label, field_type, is_required, display_order, allowed_file_types, max_file_size_mb, visible_when_field_id, visible_when_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
        } else {
            $sql = 'INSERT INTO services_fields (service_id, label, field_type, is_required, display_order, allowed_file_types, visible_when_field_id, visible_when_value) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        }
        $stmt = $conn->prepare($sql);
    } elseif ($hasConditionalCols && $conditionalMode === 'option_id') {
        if ($hasMaxFileSizeCol) {
            $sql = 'INSERT INTO services_fields (service_id, label, field_type, is_required, display_order, allowed_file_types, max_file_size_mb, visible_when_option_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        } else {
            $sql = 'INSERT INTO services_fields (service_id, label, field_type, is_required, display_order, allowed_file_types, visible_when_option_id) VALUES (?, ?, ?, ?, ?, ?, ?)';
        }
        $stmt = $conn->prepare($sql);
    } else {
        if ($hasMaxFileSizeCol) {
            $sql = 'INSERT INTO services_fields (service_id, label, field_type, is_required, display_order, allowed_file_types, max_file_size_mb) VALUES (?, ?, ?, ?, ?, ?, ?)';
        } else {
            $sql = 'INSERT INTO services_fields (service_id, label, field_type, is_required, display_order, allowed_file_types) VALUES (?, ?, ?, ?, ?, ?)';
        }
        $stmt = $conn->prepare($sql);
    }
    if (!$stmt) {
        json_error('Failed to prepare statement: ' . $conn->error, 500);
    }
    // Bind nullable values
    if ($hasConditionalCols && $conditionalMode === 'value') {
        if ($hasMaxFileSizeCol) {
            $stmt->bind_param('issiisiis', $service_id, $label, $field_type, $is_required, $display_order, $allowed_file_types, $max_file_size_mb, $visible_when_field_id, $visible_when_value);
        } else {
            $stmt->bind_param('issiisis', $service_id, $label, $field_type, $is_required, $display_order, $allowed_file_types, $visible_when_field_id, $visible_when_value);
        }
    } elseif ($hasConditionalCols && $conditionalMode === 'option_id') {
        if ($hasMaxFileSizeCol) {
            $stmt->bind_param('issiisii', $service_id, $label, $field_type, $is_required, $display_order, $allowed_file_types, $max_file_size_mb, $visible_when_option_id);
        } else {
            $stmt->bind_param('issiisi', $service_id, $label, $field_type, $is_required, $display_order, $allowed_file_types, $visible_when_option_id);
        }
    } else {
        if ($hasMaxFileSizeCol) {
            $stmt->bind_param('issiisi', $service_id, $label, $field_type, $is_required, $display_order, $allowed_file_types, $max_file_size_mb);
        } else {
            $stmt->bind_param('issiis', $service_id, $label, $field_type, $is_required, $display_order, $allowed_file_types);
        }
    }
    if (!$stmt->execute()) {
        $stmt->close();
        json_error('Failed to add field: ' . $conn->error, 500);
    }
    $new_id = $stmt->insert_id;
    $stmt->close();

    json_ok(['field_id' => $new_id, 'display_order' => $display_order]);
}

if ($method === 'POST' && $action === 'update_field') {
    $service_id = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $field_id = isset($_POST['field_id']) ? (int)$_POST['field_id'] : 0;
    $label = trim($_POST['label'] ?? '');
    $field_type = strtolower(trim($_POST['field_type'] ?? ''));
    $is_required = isset($_POST['is_required']) ? (int)$_POST['is_required'] : 1;
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    $allowed_file_types = trim($_POST['allowed_file_types'] ?? '');
    $max_file_size_mb_raw = trim($_POST['max_file_size_mb'] ?? '');
    $visible_when_field_id_raw = trim($_POST['visible_when_field_id'] ?? '');
    $visible_when_value = trim($_POST['visible_when_value'] ?? '');

    if ($service_id <= 0) json_error('service_id is required');
    if ($field_id <= 0) json_error('field_id is required');
    if ($label === '') json_error('label is required');
    $allowed_types = ['text', 'textarea', 'date', 'number', 'email', 'select', 'checkbox', 'radio', 'file'];
    if (!in_array($field_type, $allowed_types, true)) json_error('Invalid field_type');
    if ($is_required !== 0 && $is_required !== 1) $is_required = 1;

    // Validate field ownership
    $stmtChk = $conn->prepare('SELECT field_id FROM services_fields WHERE field_id = ? AND service_id = ?');
    $stmtChk->bind_param('ii', $field_id, $service_id);
    if (!$stmtChk->execute()) {
        $stmtChk->close();
        json_error('Failed to validate field: ' . $conn->error, 500);
    }
    $resChk = $stmtChk->get_result();
    if (!$resChk || $resChk->num_rows === 0) {
        $stmtChk->close();
        json_error('Field not found for this service', 404);
    }
    $resChk->close();
    $stmtChk->close();

    // If file type, validate allowed_file_types pattern (simple check: only commas and leading dots, no spaces)
    if ($field_type === 'file' && $allowed_file_types !== '') {
        if (preg_match('/\s/', $allowed_file_types)) {
            json_error('allowed_file_types must be comma-separated without spaces, e.g., .pdf,.jpg');
        }
        if (!preg_match('/^(\.[A-Za-z0-9]+)(,\.[A-Za-z0-9]+)*$/', $allowed_file_types)) {
            json_error('allowed_file_types format invalid. Example: .pdf,.jpg,.png');
        }
    } else {
        $allowed_file_types = null; // store as NULL when not applicable
    }

    // If file type, validate max_file_size_mb (optional)
    $max_file_size_mb = null;
    if ($field_type === 'file' && $max_file_size_mb_raw !== '') {
        if (!preg_match('/^\d+$/', $max_file_size_mb_raw)) {
            json_error('max_file_size_mb must be a positive integer');
        }
        $max_file_size_mb = (int)$max_file_size_mb_raw;
        if ($max_file_size_mb <= 0 || $max_file_size_mb > 2048) {
            json_error('max_file_size_mb must be between 1 and 2048');
        }
    } else {
        $max_file_size_mb = null; // not applicable or empty
    }

    // Conditional visibility normalization (only if columns exist)
    $visible_when_field_id = null;
    $visible_when_option_id = null;
    if ($hasConditionalCols) {
        if ($visible_when_field_id_raw !== '') {
            $visible_when_field_id = (int)$visible_when_field_id_raw;
            if ($visible_when_field_id <= 0) {
                $visible_when_field_id = null;
            }
        }
        if ($visible_when_field_id !== null && $visible_when_value === '') {
            json_error('visible_when_value is required when controller field is set');
        }
        if ($visible_when_field_id !== null) {
            // Validate controller field belongs to the same service and is of allowed type
            $stmtCtl = $conn->prepare('SELECT field_type FROM services_fields WHERE field_id = ? AND service_id = ?');
            $stmtCtl->bind_param('ii', $visible_when_field_id, $service_id);
            if (!$stmtCtl->execute()) {
                $stmtCtl->close();
                json_error('Failed to validate controller field: ' . $conn->error, 500);
            }
            $resCtl = $stmtCtl->get_result();
            if (!$resCtl || $resCtl->num_rows === 0) {
                $stmtCtl->close();
                json_error('Invalid controller field for this service');
            }
            $rowCtl = $resCtl->fetch_assoc();
            $resCtl->close();
            $stmtCtl->close();
            $ctl_type = strtolower($rowCtl['field_type'] ?? '');
            if ($conditionalMode === 'value') {
                $allowed_ctl = ['select', 'checkbox', 'radio'];
            } else {
                $allowed_ctl = ['select', 'radio', 'checkbox'];
            }
            if (!in_array($ctl_type, $allowed_ctl, true)) {
                json_error($conditionalMode === 'value' ? 'Controller field type must be select, checkbox, or radio' : 'Controller field must be select, radio, or checkbox when using option_id schema');
            }
            // In option_id mode, map to option_id
            if ($conditionalMode === 'option_id') {
                $stmtOpt = $conn->prepare('SELECT option_id FROM services_field_options WHERE field_id = ? AND option_value = ? LIMIT 1');
                $stmtOpt->bind_param('is', $visible_when_field_id, $visible_when_value);
                if (!$stmtOpt->execute()) {
                    $stmtOpt->close();
                    json_error('Failed to resolve controller option: ' . $conn->error, 500);
                }
                $resOpt = $stmtOpt->get_result();
                if (!$resOpt || $resOpt->num_rows === 0) {
                    $stmtOpt->close();
                    json_error('Trigger value must match an existing option for the selected controller field');
                }
                $rowOpt = $resOpt->fetch_assoc();
                $visible_when_option_id = (int)$rowOpt['option_id'];
                $resOpt->close();
                $stmtOpt->close();
            }
        } else {
            $visible_when_value = null; // no controller => clear value
        }
    } else {
        // Columns missing; ignore provided conditional values
        $visible_when_value = null;
        $visible_when_field_id = null;
        $visible_when_option_id = null;
    }

    // If display_order is 0, keep current order; otherwise update
    if ($display_order === 0) {
        // Query current order to return it in response
        $stmtCur = $conn->prepare('SELECT display_order FROM services_fields WHERE field_id = ?');
        $stmtCur->bind_param('i', $field_id);
        if ($stmtCur->execute()) {
            $resCur = $stmtCur->get_result();
            $rowCur = $resCur->fetch_assoc();
            $display_order = (int)($rowCur['display_order'] ?? 0);
            $resCur->close();
        }
        $stmtCur->close();
    }

    if ($hasConditionalCols && $conditionalMode === 'value') {
        if ($hasMaxFileSizeCol) {
            $sql = 'UPDATE services_fields SET label = ?, field_type = ?, is_required = ?, display_order = ?, allowed_file_types = ?, max_file_size_mb = ?, visible_when_field_id = ?, visible_when_value = ? WHERE field_id = ? AND service_id = ?';
        } else {
            $sql = 'UPDATE services_fields SET label = ?, field_type = ?, is_required = ?, display_order = ?, allowed_file_types = ?, visible_when_field_id = ?, visible_when_value = ? WHERE field_id = ? AND service_id = ?';
        }
        $stmt = $conn->prepare($sql);
    } elseif ($hasConditionalCols && $conditionalMode === 'option_id') {
        if ($hasMaxFileSizeCol) {
            $sql = 'UPDATE services_fields SET label = ?, field_type = ?, is_required = ?, display_order = ?, allowed_file_types = ?, max_file_size_mb = ?, visible_when_option_id = ? WHERE field_id = ? AND service_id = ?';
        } else {
            $sql = 'UPDATE services_fields SET label = ?, field_type = ?, is_required = ?, display_order = ?, allowed_file_types = ?, visible_when_option_id = ? WHERE field_id = ? AND service_id = ?';
        }
        $stmt = $conn->prepare($sql);
    } else {
        if ($hasMaxFileSizeCol) {
            $sql = 'UPDATE services_fields SET label = ?, field_type = ?, is_required = ?, display_order = ?, allowed_file_types = ?, max_file_size_mb = ? WHERE field_id = ? AND service_id = ?';
        } else {
            $sql = 'UPDATE services_fields SET label = ?, field_type = ?, is_required = ?, display_order = ?, allowed_file_types = ? WHERE field_id = ? AND service_id = ?';
        }
        $stmt = $conn->prepare($sql);
    }
    if (!$stmt) {
        json_error('Failed to prepare statement: ' . $conn->error, 500);
    }
    if ($hasConditionalCols && $conditionalMode === 'value') {
        if ($hasMaxFileSizeCol) {
            $stmt->bind_param('ssiisiisii', $label, $field_type, $is_required, $display_order, $allowed_file_types, $max_file_size_mb, $visible_when_field_id, $visible_when_value, $field_id, $service_id);
        } else {
            $stmt->bind_param('ssiisisii', $label, $field_type, $is_required, $display_order, $allowed_file_types, $visible_when_field_id, $visible_when_value, $field_id, $service_id);
        }
    } elseif ($hasConditionalCols && $conditionalMode === 'option_id') {
        if ($hasMaxFileSizeCol) {
            $stmt->bind_param('ssiisiiii', $label, $field_type, $is_required, $display_order, $allowed_file_types, $max_file_size_mb, $visible_when_option_id, $field_id, $service_id);
        } else {
            $stmt->bind_param('ssiisiii', $label, $field_type, $is_required, $display_order, $allowed_file_types, $visible_when_option_id, $field_id, $service_id);
        }
    } else {
        if ($hasMaxFileSizeCol) {
            $stmt->bind_param('ssiisiii', $label, $field_type, $is_required, $display_order, $allowed_file_types, $max_file_size_mb, $field_id, $service_id);
        } else {
            $stmt->bind_param('ssiisii', $label, $field_type, $is_required, $display_order, $allowed_file_types, $field_id, $service_id);
        }
    }
    if (!$stmt->execute()) {
        $stmt->close();
        json_error('Failed to update field: ' . $conn->error, 500);
    }
    $stmt->close();

    json_ok(['field_id' => $field_id, 'display_order' => $display_order]);
}

json_error('Unsupported action', 400);