<?php
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// Validate required params
if (!isset($_GET['field_id']) || !isset($_GET['service_id'])) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Missing required parameters to manage options.'];
    header('Location: services_management.php');
    exit;
}

$field_id = (int)$_GET['field_id'];
$service_id = (int)$_GET['service_id'];

// AJAX detection
$isAjax = isset($_GET['ajax']) || isset($_POST['ajax']);
if ($isAjax) {
    header('Content-Type: application/json');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_option') {
        $label = trim($_POST['option_label'] ?? '');
        $value = trim($_POST['option_value'] ?? '');
        $order = (int)($_POST['display_order'] ?? 0);

        if ($label === '' || $value === '') {
            if ($isAjax) {
                echo json_encode(['ok' => false, 'error' => 'Option Label and Value cannot be empty.']);
                exit;
            }
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Option Label and Value cannot be empty.'];
        } else {
            $stmt = $conn->prepare('INSERT INTO services_field_options (field_id, option_label, option_value, display_order) VALUES (?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('issi', $field_id, $label, $value, $order);
                if ($stmt->execute()) {
                    if ($isAjax) {
                        echo json_encode(['ok' => true, 'option_id' => $stmt->insert_id]);
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
        if ($isAjax) {
            echo json_encode(['ok' => true]);
            $conn->close();
            exit;
        }
    } elseif ($action === 'update_options') {
        $option_ids = $_POST['option_id'] ?? [];
        $labels = $_POST['option_label'] ?? [];
        $values = $_POST['option_value'] ?? [];
        $orders = $_POST['display_order'] ?? [];
        $errors = 0;

        $stmt = $conn->prepare('UPDATE services_field_options SET option_label = ?, option_value = ?, display_order = ? WHERE option_id = ? AND field_id = ?');
        if ($stmt) {
            for ($i = 0; $i < count($option_ids); $i++) {
                $id = (int)$option_ids[$i];
                $label = trim($labels[$i] ?? '');
                $value = trim($values[$i] ?? '');
                $order = (int)($orders[$i] ?? 0);
                if ($label === '' || $value === '') { $errors++; continue; }
                $stmt->bind_param('ssiii', $label, $value, $order, $id, $field_id);
                if (!$stmt->execute()) { $errors++; }
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
    }

    if (!$isAjax) {
        header("Location: manage_service_options.php?field_id={$field_id}&service_id={$service_id}");
        exit;
    }
}

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete_option' && isset($_GET['id'])) {
    $option_id = (int)$_GET['id'];
    $stmt = $conn->prepare('DELETE FROM services_field_options WHERE option_id = ? AND field_id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $option_id, $field_id);
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
        header("Location: manage_service_options.php?field_id={$field_id}&service_id={$service_id}");
        exit;
    }
}

// Fetch field label (verify belongs to this service)
$field_label = null;
$stmt = $conn->prepare('SELECT label FROM services_fields WHERE field_id = ? AND service_id = ?');
if ($stmt) {
    $stmt->bind_param('ii', $field_id, $service_id);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $field_label = $res->fetch_assoc()['label'];
        } else {
            die('Error: Field not found for this service.');
        }
        $res->close();
    } else {
        die('Execute failed: ' . $stmt->error);
    }
    $stmt->close();
} else {
    die('Prepare failed: ' . $conn->error);
}

// Fetch options
$options = [];
$stmt = $conn->prepare('SELECT option_id, option_label, option_value, display_order FROM services_field_options WHERE field_id = ? ORDER BY display_order, option_id');
if ($stmt) {
    $stmt->bind_param('i', $field_id);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $options[] = $row; }
        $res->close();
    } else {
        die('Execute failed: ' . $stmt->error);
    }
    $stmt->close();
} else {
    die('Prepare failed: ' . $conn->error);
}

// If AJAX request to get data, return JSON and exit
if ($isAjax) {
    echo json_encode([
        'ok' => true,
        'field' => ['field_id' => $field_id, 'service_id' => $service_id, 'label' => $field_label],
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
                    <p class="table-container__subtitle">Field: <?php echo htmlspecialchars($field_label); ?></p>
                </div>
                <div class="header__actions">
                    <button onclick="window.location.href='manage_services.php?service_id=<?php echo (int)$service_id; ?>'" class="btn btn--secondary">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Service Fields
                    </button>
                </div>
            </header>

            <section class="section active" style="margin: 0 20px;">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($_SESSION['message']['type']); ?>" style="margin-bottom:12px;">
                        <?php echo htmlspecialchars($_SESSION['message']['text']); unset($_SESSION['message']); ?>
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
                        <form action="manage_service_options.php?field_id=<?php echo (int)$field_id; ?>&service_id=<?php echo (int)$service_id; ?>" method="post">
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
                                        <input type="hidden" name="option_id[]" value="<?php echo (int)$opt['option_id']; ?>">
                                        <td><input type="text" name="option_label[]" value="<?php echo htmlspecialchars($opt['option_label']); ?>" class="form-group" required></td>
                                        <td><input type="text" name="option_value[]" value="<?php echo htmlspecialchars($opt['option_value']); ?>" class="form-group" required></td>
                                        <td><input type="number" name="display_order[]" value="<?php echo (int)$opt['display_order']; ?>" class="form-group" style="width: 80px;" required></td>
                                        <td>
                                            <a href="manage_service_options.php?action=delete_option&id=<?php echo (int)$opt['option_id']; ?>&field_id=<?php echo (int)$field_id; ?>&service_id=<?php echo (int)$service_id; ?>" class="table__btn table__btn--delete" onclick="return confirm('Delete this option?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <button type="submit" class="btn btn--primary" style="margin-top: 12px;">Update All Options</button>
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
                    <form action="manage_service_options.php?field_id=<?php echo (int)$field_id; ?>&service_id=<?php echo (int)$service_id; ?>" method="post" style="display:flex; gap:12px; align-items:flex-end;">
                        <input type="hidden" name="action" value="add_option">
                        <div class="form-group" style="flex:2;">
                            <label for="add_option_label">Label</label>
                            <input type="text" id="add_option_label" name="option_label" placeholder="e.g., Male" required>
                        </div>
                        <div class="form-group" style="flex:2;">
                            <label for="add_option_value">Value</label>
                            <input type="text" id="add_option_value" name="option_value" placeholder="e.g., Male" required>
                        </div>
                        <div class="form-group" style="flex:1;">
                            <label for="add_display_order">Order</label>
                            <input type="number" id="add_display_order" name="display_order" value="0" required>
                        </div>
                        <button type="submit" class="btn btn--success">Add Option</button>
                    </form>
                </div>
            </section>
        </main>
    </div>

    <script src="theme-init.js"></script>
    </body>
    </html>