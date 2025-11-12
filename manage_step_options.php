<?php
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// Validate required params
if (!isset($_GET['field_id']) || !isset($_GET['step_id'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Missing required parameters to manage options.'];
    header('Location: manage_step_fields.php');
    exit;
}

$field_id = (int)($_GET['field_id'] ?? 0);
$step_id = (int)($_GET['step_id'] ?? 0);

// AJAX detection
$isAjax = isset($_GET['ajax']) || isset($_POST['ajax']);
if ($isAjax) {
    header('Content-Type: application/json');
}

// Confirm field belongs to this step and is of an option-capable type
$field_label = null;
$field_type = null;
$stmt = $conn->prepare('SELECT label, input_type FROM form_fields WHERE id = ? AND step_id = ?');
if ($stmt) {
    $stmt->bind_param('ii', $field_id, $step_id);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $field_label = $row['label'];
            $field_type = strtolower($row['input_type']);
        } else {
            $stmt->close();
            $conn->close();
            die('Error: Field not found for this step.');
        }
        $res->close();
    } else {
        $stmt->close();
        $conn->close();
        die('Execute failed: ' . $stmt->error);
    }
    $stmt->close();
} else {
    $conn->close();
    die('Prepare failed: ' . $conn->error);
}

$optionTypes = ['select', 'radio', 'checkbox'];
if (!in_array($field_type, $optionTypes, true)) {
    $conn->close();
    die('This field type does not support options.');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_option') {
        $label = trim($_POST['option_label'] ?? '');
        $value = trim($_POST['option_value'] ?? '');
        $order = (int)($_POST['option_order'] ?? 0);

        if ($label === '' || $value === '') {
            if ($isAjax) {
                echo json_encode(['ok' => false, 'error' => 'Option Label and Value cannot be empty.']);
                exit;
            }
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Option Label and Value cannot be empty.'];
        } else {
            $stmt = $conn->prepare('INSERT INTO form_field_options (field_id, option_label, option_value, option_order) VALUES (?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('issi', $field_id, $label, $value, $order);
                if ($stmt->execute()) {
                    if ($isAjax) {
                        echo json_encode(['ok' => true, 'id' => $stmt->insert_id]);
                        $stmt->close();
                        $conn->close();
                        exit;
                    }
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Option added successfully.'];
                } else {
                    if ($isAjax) {
                        echo json_encode(['ok' => false, 'error' => 'Error adding option: ' . $stmt->error]);
                        $stmt->close();
                        $conn->close();
                        exit;
                    }
                    $_SESSION['message'] = ['type' => 'error', 'text' => 'Error adding option: ' . $stmt->error];
                }
                $stmt->close();
            } else {
                if ($isAjax) {
                    echo json_encode(['ok' => false, 'error' => 'Database prepare error: ' . $conn->error]);
                    $conn->close();
                    exit;
                }
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Database prepare error: ' . $conn->error];
            }
        }
        if (!$isAjax) {
            header('Location: manage_step_options.php?field_id=' . (int)$field_id . '&step_id=' . (int)$step_id);
            exit;
        }
    } elseif ($action === 'update_options') {
        $ids = $_POST['id'] ?? [];
        $labels = $_POST['option_label'] ?? [];
        $values = $_POST['option_value'] ?? [];
        $orders = $_POST['option_order'] ?? [];
        $errors = 0;

        $stmt = $conn->prepare('UPDATE form_field_options SET option_label = ?, option_value = ?, option_order = ? WHERE id = ? AND field_id = ?');
        if ($stmt) {
            for ($i = 0; $i < count($ids); $i++) {
                $id = (int)($ids[$i] ?? 0);
                $label = trim($labels[$i] ?? '');
                $value = trim($values[$i] ?? '');
                $order = (int)($orders[$i] ?? 0);
                if ($label === '' || $value === '') {
                    $errors++;
                    continue;
                }
                $stmt->bind_param('ssiii', $label, $value, $order, $id, $field_id);
                if (!$stmt->execute()) {
                    $errors++;
                }
            }
            $stmt->close();
            if ($isAjax) {
                echo json_encode(['ok' => $errors === 0, 'errors' => $errors]);
                $conn->close();
                exit;
            }
            $_SESSION['message'] = $errors > 0
                ? ['type' => 'warning', 'text' => 'Some options failed to update.']
                : ['type' => 'success', 'text' => 'Options updated successfully.'];
        } else {
            if ($isAjax) {
                echo json_encode(['ok' => false, 'error' => 'Database prepare error: ' . $conn->error]);
                $conn->close();
                exit;
            }
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Database prepare error: ' . $conn->error];
        }
        if (!$isAjax) {
            header('Location: manage_step_options.php?field_id=' . (int)$field_id . '&step_id=' . (int)$step_id);
            exit;
        }
    }
}

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete_option' && isset($_GET['id'])) {
    $optId = (int)$_GET['id'];
    $stmt = $conn->prepare('DELETE FROM form_field_options WHERE id = ? AND field_id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $optId, $field_id);
        if ($stmt->execute()) {
            if ($isAjax) {
                echo json_encode(['ok' => true]);
                $stmt->close();
                $conn->close();
                exit;
            }
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Option deleted successfully.'];
        } else {
            if ($isAjax) {
                echo json_encode(['ok' => false, 'error' => 'Error deleting option: ' . $stmt->error]);
                $stmt->close();
                $conn->close();
                exit;
            }
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error deleting option: ' . $stmt->error];
        }
        $stmt->close();
    } else {
        if ($isAjax) {
            echo json_encode(['ok' => false, 'error' => 'Database prepare error: ' . $conn->error]);
            $conn->close();
            exit;
        }
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Database prepare error: ' . $conn->error];
    }
    if (!$isAjax) {
        header('Location: manage_step_options.php?field_id=' . (int)$field_id . '&step_id=' . (int)$step_id);
        exit;
    }
}

// Fetch options
$options = [];
$stmt = $conn->prepare('SELECT id, option_label, option_value, option_order FROM form_field_options WHERE field_id = ? ORDER BY option_order, id');
if ($stmt) {
    $stmt->bind_param('i', $field_id);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $options[] = $row;
        }
        $res->close();
    } else {
        $stmt->close();
        $conn->close();
        die('Execute failed: ' . $stmt->error);
    }
    $stmt->close();
} else {
    $conn->close();
    die('Prepare failed: ' . $conn->error);
}

if ($isAjax) {
    echo json_encode([
        'ok' => true,
        'field' => ['field_id' => $field_id, 'step_id' => $step_id, 'label' => $field_label, 'type' => $field_type],
        'options' => $options
    ]);
    $conn->close();
    exit;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Options for <?php echo htmlspecialchars($field_label); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
</head>

<body>
    <?php include 'includes/mobile_navbar.php'; ?>
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <header class="header">
                <div class="header__left">
                    <h1 class="header__title">Manage Options</h1>
                    <p class="table-container__subtitle">Field: <?php echo htmlspecialchars($field_label); ?> (<?php echo htmlspecialchars($field_type); ?>)</p>
                </div>
                <div class="header__actions">
                    <button onclick="window.location.href='manage_step_fields.php?step_id=<?php echo (int)$step_id; ?>'" class="btn btn--secondary">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Step Fields
                    </button>
                </div>
            </header>

            <section class="section active" style="margin: 0 20px;">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($_SESSION['message']['type']); ?>" style="margin-bottom:12px;">
                        <?php echo htmlspecialchars($_SESSION['message']['text']);
                        unset($_SESSION['message']); ?>
                    </div>
                <?php endif; ?>

                <div class="table-container" style="margin-bottom: 20px;">
                    <div class="table-container__header" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                        <div>
                            <h2 class="table-container__title">Existing Options</h2>
                            <p class="table-container__subtitle">Update or delete options for this field</p>
                        </div>
                    </div>
                    <?php if (empty($options)): ?>
                        <p>No options found. Add one below.</p>
                    <?php else: ?>
                        <form id="updateOptionsForm" action="manage_step_options.php?field_id=<?php echo (int)$field_id; ?>&step_id=<?php echo (int)$step_id; ?>" method="post">
                            <input type="hidden" name="action" value="update_options">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Label</th>
                                        <th>Value</th>
                                        <th style="width:100px;">Order</th>
                                        <th style="width:120px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($options as $opt): ?>
                                        <tr>
                                            <input type="hidden" name="id[]" value="<?php echo (int)$opt['id']; ?>">
                                            <td><input type="text" name="option_label[]" value="<?php echo htmlspecialchars($opt['option_label']); ?>" class="form-group" required></td>
                                            <td><input type="text" name="option_value[]" value="<?php echo htmlspecialchars($opt['option_value']); ?>" class="form-group" required></td>
                                            <td><input type="number" name="option_order[]" value="<?php echo (int)$opt['option_order']; ?>" class="form-group" style="width: 80px;" required></td>
                                            <td>
                                                <a href="manage_step_options.php?action=delete_option&id=<?php echo (int)$opt['id']; ?>&field_id=<?php echo (int)$field_id; ?>&step_id=<?php echo (int)$step_id; ?>" class="table__btn table__btn--delete" onclick="return confirm('Delete this option?');">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <button id="updateOptionsBtn" type="submit" class="btn btn--primary" style="margin-top: 12px;">Update All Options</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="table-container">
                    <div class="table-container__header" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                        <div>
                            <h2 class="table-container__title">Add New Option</h2>
                            <p class="table-container__subtitle">Create an option for this field</p>
                        </div>
                    </div>
                    <form action="manage_step_options.php?field_id=<?php echo (int)$field_id; ?>&step_id=<?php echo (int)$step_id; ?>" method="post" style="display:flex; gap:12px; align-items:flex-end;">
                        <input type="hidden" name="action" value="add_option">
                        <div class="form-group" style="flex:2;">
                            <label for="add_option_label">Label</label>
                            <input type="text" id="add_option_label" name="option_label" placeholder="e.g., Yes" required>
                        </div>
                        <div class="form-group" style="flex:2;">
                            <label for="add_option_value">Value</label>
                            <input type="text" id="add_option_value" name="option_value" placeholder="e.g., yes" required>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label for="add_option_order">Order</label>
                            <input type="number" id="add_option_order" name="option_order" value="0" required>
                        </div>
                        <button type="submit" class="btn btn--success">Add Option</button>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <!-- Loader Overlay -->
    <div id="optionsLoaderOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.35); z-index:1000; align-items:center; justify-content:center;">
        <div style="background: var(--color-card); padding:16px 20px; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.2); border:1px solid var(--color-border); display:flex; align-items:center; gap:12px; color:var(--color-text);">
            <div class="spinner" style="width:20px; height:20px; border:3px solid var(--color-border); border-top-color: var(--primary-color); border-radius:50%; animation: spin 0.9s linear infinite;"></div>
            <span>Saving option changes…</span>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="optionsConfirmModal" style="display:none; position:fixed; left:0; top:0; width:100%; height:100%; background-color: rgba(0,0,0,0.5); z-index:1001; align-items:center; justify-content:center; padding:16px;">
        <div style="background: var(--color-card); padding:24px; border-radius:12px; max-width:480px; width:95%; box-shadow:0 10px 30px rgba(0,0,0,0.25); border:1px solid var(--color-border); color:var(--color-text);">
            <h3 style="margin:0 0 8px; color: var(--primary-color);">Update options?</h3>
            <p style="margin:0 0 16px;">This will save changes to all listed options. Proceed?</p>
            <div style="display:flex; gap:8px;">
                <button id="confirmUpdateOptions" class="btn btn--primary" style="flex:1;">Confirm</button>
                <button id="cancelUpdateOptions" class="btn btn--secondary" style="flex:1;">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="optionsMessageModal" style="display:none; position:fixed; left:0; top:0; width:100%; height:100%; background-color: rgba(0,0,0,0.5); z-index:1002; align-items:center; justify-content:center; padding:16px;">
        <div style="background: var(--color-card); padding:24px; border-radius:12px; max-width:520px; width:95%; box-shadow:0 10px 30px rgba(0,0,0,0.25); border:1px solid var(--color-border); color:var(--color-text);">
            <h3 id="optionsMessageTitle" style="margin:0 0 8px; color: var(--primary-color);">Update Result</h3>
            <p id="optionsMessageBody" style="margin:0 0 16px;">Options updated.</p>
            <div style="display:flex; gap:8px;">
                <button id="optionsMessageOk" class="btn btn--primary" style="flex:1;">OK</button>
            </div>
        </div>
    </div>

    <script src="theme-init.js"></script>
    <script>
        // Simple helpers
        const loaderEl = document.getElementById('optionsLoaderOverlay');
        const confirmModalEl = document.getElementById('optionsConfirmModal');
        const messageModalEl = document.getElementById('optionsMessageModal');
        const messageTitleEl = document.getElementById('optionsMessageTitle');
        const messageBodyEl = document.getElementById('optionsMessageBody');
        const messageOkBtn = document.getElementById('optionsMessageOk');
        const confirmBtn = document.getElementById('confirmUpdateOptions');
        const cancelBtn = document.getElementById('cancelUpdateOptions');
        const updateForm = document.getElementById('updateOptionsForm');
        const updateBtn = document.getElementById('updateOptionsBtn');

        function show(el) {
            el.style.display = 'flex';
        }

        function hide(el) {
            el.style.display = 'none';
        }

        function showLoader() {
            show(loaderEl);
            if (updateBtn) {
                updateBtn.disabled = true;
                updateBtn.style.opacity = '0.7';
                updateBtn.style.cursor = 'not-allowed';
            }
        }

        function hideLoader() {
            hide(loaderEl);
            if (updateBtn) {
                updateBtn.disabled = false;
                updateBtn.style.opacity = '';
                updateBtn.style.cursor = '';
            }
        }

        function openConfirm() {
            show(confirmModalEl);
        }

        function closeConfirm() {
            hide(confirmModalEl);
        }

        function openMessage(title, body) {
            messageTitleEl.textContent = title;
            messageBodyEl.textContent = body;
            show(messageModalEl);
        }

        function closeMessage() {
            hide(messageModalEl);
        }

        // Intercept Update All Options submit
        if (updateForm) {
            updateForm.addEventListener('submit', function(e) {
                e.preventDefault();
                openConfirm();
            });
        }

        // Confirm flow -> AJAX POST
        if (confirmBtn) {
            confirmBtn.addEventListener('click', async function() {
                closeConfirm();
                showLoader();
                try {
                    const url = updateForm.getAttribute('action') + (updateForm.getAttribute('action').includes('?') ? '&' : '?') + 'ajax=1';
                    const fd = new FormData(updateForm);
                    fd.append('ajax', '1');
                    const resp = await fetch(url, {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await resp.json();
                    hideLoader();
                    if (data && data.ok) {
                        const msg = (data.errors && data.errors > 0) ?
                            `Updated with ${data.errors} issue(s).` :
                            'Options updated successfully.';
                        openMessage('Success', msg);
                    } else {
                        const err = (data && data.error) ? data.error : 'Failed to update options.';
                        openMessage('Error', err);
                    }
                } catch (err) {
                    hideLoader();
                    openMessage('Error', 'Network or server error while updating options.');
                }
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                closeConfirm();
            });
        }

        if (messageOkBtn) {
            messageOkBtn.addEventListener('click', function() {
                closeMessage();
                // Optional: refresh to reflect any server-side transforms
                window.location.reload();
            });
        }

        // Spinner animation keyframes
        const styleEl = document.createElement('style');
        styleEl.textContent = '@keyframes spin { from { transform: rotate(0deg);} to { transform: rotate(360deg);} }';
        document.head.appendChild(styleEl);
    </script>
</body>

</html>