<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// --- VALIDATION: Now uses applicant_type_id ---
if (!isset($_GET['applicant_type_id'])) {
    die("Error: No applicant type specified.");
}
$applicant_type_id = (int)$_GET['applicant_type_id'];

// Get info for header and "Back" button
$sql_info = "SELECT at.name, at.admission_cycle_id 
             FROM applicant_types at 
             WHERE at.id = $applicant_type_id";
$info = $conn->query($sql_info)->fetch_assoc();
if (!$info) {
    die("Error: Applicant type not found.");
}
$type_name = $info['name'];
$cycle_id = $info['admission_cycle_id'];

// --- ACTION HANDLER: Process form submissions (Add/Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ADD A NEW STEP ---
    if ($action === 'add_step') {
        $title = $conn->real_escape_string($_POST['title']);
        $result = $conn->query("SELECT MAX(step_order) as max_order FROM form_steps WHERE applicant_type_id = $applicant_type_id");
        $max_order = $result->fetch_assoc()['max_order'] ?? 0;
        $new_order = $max_order + 1;

        $sql = "INSERT INTO form_steps (applicant_type_id, title, step_order) VALUES ($applicant_type_id, '$title', $new_order)";
        if ($conn->query($sql)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'New step added.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $conn->error];
        }
        header("Location: manage_form.php?applicant_type_id=$applicant_type_id");
        exit;
    }

    // --- ADD A NEW FIELD ---
    if ($action === 'add_field') {
        $step_id = (int)$_POST['step_id'];
        $name = $conn->real_escape_string($_POST['name']);
        // Server-side validation: Name/ID must start with a letter and contain only letters, numbers, and underscores
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $_POST['name'])) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid Name/ID. Use letters, numbers, underscores; start with a letter.'];
            header("Location: manage_form.php?applicant_type_id=$applicant_type_id");
            exit;
        }
        $label = $conn->real_escape_string($_POST['label']);
        $input_type = $conn->real_escape_string($_POST['input_type']);
        $placeholder = $conn->real_escape_string($_POST['placeholder_text']);
        $is_required = isset($_POST['is_required']) ? 1 : 0;

        $result = $conn->query("SELECT MAX(field_order) as max_order FROM form_fields WHERE step_id = $step_id");
        $max_order = $result->fetch_assoc()['max_order'] ?? 0;
        $new_order = $max_order + 1;

        $sql = "INSERT INTO form_fields (step_id, name, label, input_type, placeholder_text, is_required, field_order)
                VALUES ($step_id, '$name', '$label', '$input_type', '$placeholder', $is_required, $new_order)";
        if ($conn->query($sql)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'New field added.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $conn->error];
        }
        header("Location: manage_form.php?applicant_type_id=$applicant_type_id");
        exit;
    }
}

// --- ACTION HANDLER: Process GET requests (Delete) ---
if (isset($_GET['action'])) {
    // --- DELETE A STEP ---
    if ($_GET['action'] === 'delete_step' && isset($_GET['id'])) {
        $step_id = (int)$_GET['id'];
        $sql = "DELETE FROM form_steps WHERE id = $step_id AND applicant_type_id = $applicant_type_id";
        if ($conn->query($sql)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Step and all its fields deleted.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $conn->error];
        }
        header("Location: manage_form.php?applicant_type_id=$applicant_type_id");
        exit;
    }

    // --- ACTION: Archive a field ---
    if ($_GET['action'] === 'delete_field' && isset($_GET['id'])) { // Keeping action name, but it archives
        $field_id = (int)$_GET['id'];
        $sql = "UPDATE form_fields SET is_archived = 1 WHERE id = $field_id";
        if ($conn->query($sql)) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Field archived.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: ' . $conn->error];
        }
        header("Location: manage_form.php?applicant_type_id=$applicant_type_id");
        exit;
    }
}

// --- DATA FETCHING: Get all steps and fields for this type ---
// --- DATA FETCHING: Get all NON-ARCHIVED steps and fields for this type ---
$steps = [];
$sql_get_data = "SELECT
                    s.id as step_id, s.title as step_title,
                    f.id as field_id, f.name, f.label, f.input_type, f.placeholder_text, f.is_required
                 FROM form_steps s
                 LEFT JOIN form_fields f ON s.id = f.step_id AND f.is_archived = 0 -- Join only non-archived fields
                 WHERE s.applicant_type_id = $applicant_type_id AND s.is_archived = 0 -- Select only non-archived steps
                 ORDER BY s.step_order, f.field_order";
// ... rest of the fetch loop

$result_data = $conn->query($sql_get_data);

while ($row = $result_data->fetch_assoc()) {
    $step_id = $row['step_id'];

    if (!isset($steps[$step_id])) {
        $steps[$step_id] = [
            'title' => $row['step_title'],
            'fields' => []
        ];
    }

    if ($row['field_id'] !== null) {
        $steps[$step_id]['fields'][] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Form</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
    
    <script>
        function confirmDelete(type) {
            return confirm('Are you sure you want to delete this ' + type + '?');
        }

        // Full-screen loader functionality
        function showLoader() {
            const loader = document.getElementById('loadingOverlay');
            if (loader) {
                loader.style.display = 'flex';
            }
        }

        function hideLoader() {
            const loader = document.getElementById('loadingOverlay');
            if (loader) {
                loader.style.display = 'none';
            }
        }

        // Add event listeners when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Confirmation Modal functionality
            const modal = document.getElementById('confirmationModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalConfirmBtn = document.getElementById('modalConfirmBtn');
            const modalCancelBtn = document.getElementById('modalCancelBtn');
            let currentForm = null;

            function showConfirmationModal(title, message, form) {
                modalTitle.textContent = title;
                modalMessage.textContent = message;
                currentForm = form;
                modal.style.display = 'flex';
            }

            // Client-side identifier validation for Name/ID fields
            function isValidIdentifier(str) {
                return /^[A-Za-z][A-Za-z0-9_]*$/.test(str);
            }

            modalConfirmBtn.addEventListener('click', () => {
                if (currentForm) {
                    modal.style.display = 'none';
                    showLoader();
                    currentForm.submit();
                }
            });

            modalCancelBtn.addEventListener('click', () => {
                modal.style.display = 'none';
                currentForm = null;
            });

            // Close modal when clicking outside
            modal.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    currentForm = null;
                }
            });
            // Add Step form
            const addStepForm = document.getElementById('addStepForm');
            if (addStepForm) {
                addStepForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const stepTitle = this.querySelector('input[name="title"]').value.trim();
                    if (stepTitle) {
                        showConfirmationModal(
                            'Add New Step',
                            `Are you sure you want to add the step "${stepTitle}"?`,
                            this
                        );
                    }
                });
            }

            // Add Field forms
            const addFieldForms = document.querySelectorAll('.addFieldForm');
            addFieldForms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const fieldLabel = this.querySelector('input[name="label"]').value.trim();
                    const fieldName = this.querySelector('input[name="name"]').value.trim();
                    const fieldType = this.querySelector('select[name="input_type"]').value;
                    if (!isValidIdentifier(fieldName)) {
                        alert('Invalid Name/ID. Use letters, numbers, underscores; start with a letter.');
                        return;
                    }
                    if (fieldLabel) {
                        showConfirmationModal(
                            'Add New Field',
                            `Are you sure you want to add the ${fieldType} field "${fieldLabel}"?`,
                            this
                        );
                    }
                });
            });

            // Handle confirmation modal for delete/archive actions
            const confirmActionLinks = document.querySelectorAll('.confirm-action');
            confirmActionLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const title = this.getAttribute('data-modal-title') || 'Confirm Action';
                    const message = this.getAttribute('data-modal-message') || 'Are you sure you want to proceed?';
                    const href = this.href;
                    
                    modalTitle.textContent = title;
                    modalMessage.textContent = message;
                    modal.style.display = 'flex';
                    
                    modalConfirmBtn.onclick = function() {
                        modal.style.display = 'none';
                        showLoader();
                        window.location.href = href;
                    };
                });
            });

            // Floating Action Button and Add Step Modal functionality
            const fabAddStep = document.getElementById('fabAddStep');
            const addStepModal = document.getElementById('addStepModal');
            const closeAddStepModal = document.getElementById('closeAddStepModal');
            const cancelAddStep = document.getElementById('cancelAddStep');
            const addStepFormModal = addStepModal.querySelector('#addStepForm');

            // Show modal when FAB is clicked
            fabAddStep.addEventListener('click', function() {
                addStepModal.style.display = 'block';
                
                // Add show class for animation after a brief delay
                setTimeout(() => {
                    addStepModal.classList.add('show');
                }, 10);
                
                // Focus on the title input
                const titleInput = addStepModal.querySelector('#title');
                setTimeout(() => titleInput.focus(), 100);
            });

            // Function to close modal with animation
            function closeModalWithAnimation() {
                addStepModal.classList.remove('show');
                
                // Hide modal after animation completes
                setTimeout(() => {
                    addStepModal.style.display = 'none';
                }, 400);
            }

            // Close modal when close button is clicked (and clear form)
            closeAddStepModal.addEventListener('click', function() {
                closeModalWithAnimation();
                addStepFormModal.reset();
            });

            // Close modal when cancel button is clicked (and clear form)
            cancelAddStep.addEventListener('click', function() {
                closeModalWithAnimation();
                addStepFormModal.reset();
            });

            // Close modal when clicking outside the modal content
            addStepModal.addEventListener('click', function(event) {
                if (event.target === addStepModal) {
                    closeModalWithAnimation();
                    addStepFormModal.reset();
                }
            });

            // Handle form submission with confirmation
            addStepFormModal.addEventListener('submit', function(e) {
                e.preventDefault();
                const stepTitle = this.querySelector('input[name="title"]').value.trim();
                if (stepTitle) {
                    // Hide the add step modal first
                    closeModalWithAnimation();
                    
                    // Show confirmation modal after a brief delay
                    setTimeout(() => {
                        showConfirmationModal(
                            'Add New Step',
                            `Are you sure you want to add the step "${stepTitle}"?`,
                            this
                        );
                    }, 100);
                }
            });

            // Close modal with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    if (addStepModal.classList.contains('show')) {
                        closeModalWithAnimation();
                        addStepFormModal.reset();
                    }
                }
            });

            // Edit Step Modal functionality
            const editStepModal = document.getElementById('editStepModal');
            const editStepForm = document.getElementById('editStepForm');
            const closeEditStepModal = document.getElementById('closeEditStepModal');
            const cancelEditStep = document.getElementById('cancelEditStep');
            const editTitleInput = document.getElementById('edit_title');

            function openEditStepModal(stepId, stepTitle) {
                editTitleInput.value = stepTitle || '';
                editStepForm.action = `edit_step.php?step_id=${stepId}&applicant_type_id=<?php echo $applicant_type_id; ?>`;
                editStepModal.style.display = 'block';
                setTimeout(() => editStepModal.classList.add('show'), 10);
                setTimeout(() => editTitleInput.focus(), 100);
            }

            function closeEditModalWithAnimation() {
                editStepModal.classList.remove('show');
                setTimeout(() => {
                    editStepModal.style.display = 'none';
                }, 400);
            }

            // Bind click handlers for Edit buttons
            const editButtons = document.querySelectorAll('.edit-step-btn');
            editButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const stepId = this.getAttribute('data-step-id');
                    const stepTitle = this.getAttribute('data-step-title');
                    openEditStepModal(stepId, stepTitle);
                });
            });

            // Close behavior
            closeEditStepModal.addEventListener('click', function() {
                closeEditModalWithAnimation();
                editStepForm.reset();
            });
            cancelEditStep.addEventListener('click', function() {
                closeEditModalWithAnimation();
                editStepForm.reset();
            });
            editStepModal.addEventListener('click', function(event) {
                if (event.target === editStepModal) {
                    closeEditModalWithAnimation();
                    editStepForm.reset();
                }
            });

            // Submit with confirmation
            editStepForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const newTitle = editTitleInput.value.trim();
                if (newTitle) {
                    closeEditModalWithAnimation();
                    setTimeout(() => {
                        showConfirmationModal(
                            'Update Step',
                            `Are you sure you want to update the step title to \"${newTitle}\"?`,
                            this
                        );
                    }, 100);
                }
            });

            // Escape key closes edit modal
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    if (editStepModal.classList.contains('show')) {
                        closeEditModalWithAnimation();
                        editStepForm.reset();
                    }
                }
            });

            // Edit Field Modal functionality
            const editFieldModal = document.getElementById('editFieldModal');
            const editFieldForm = document.getElementById('editFieldForm');
            const closeEditFieldModal = document.getElementById('closeEditFieldModal');
            const cancelEditField = document.getElementById('cancelEditField');
            const fieldLabelInput = document.getElementById('field_label');
            const fieldNameInput = document.getElementById('field_name');
            const fieldTypeSelect = document.getElementById('field_type');
            const fieldPlaceholderInput = document.getElementById('field_placeholder');
            const fieldRequiredCheckbox = document.getElementById('field_required');

            function openEditFieldModal(fieldId, fieldData) {
                fieldLabelInput.value = fieldData.label || '';
                fieldNameInput.value = fieldData.name || '';
                fieldTypeSelect.value = fieldData.input_type || 'text';
                fieldPlaceholderInput.value = fieldData.placeholder_text || '';
                fieldRequiredCheckbox.checked = !!Number(fieldData.is_required);
                editFieldForm.action = `edit_field.php?field_id=${fieldId}&applicant_type_id=<?php echo $applicant_type_id; ?>`;
                editFieldModal.style.display = 'block';
                setTimeout(() => editFieldModal.classList.add('show'), 10);
                setTimeout(() => fieldLabelInput.focus(), 100);
            }

            function closeEditFieldWithAnimation() {
                editFieldModal.classList.remove('show');
                setTimeout(() => {
                    editFieldModal.style.display = 'none';
                }, 400);
            }

            // Bind click handlers for field Edit buttons
            const editFieldButtons = document.querySelectorAll('.edit-field-btn');
            editFieldButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const fieldId = this.getAttribute('data-field-id');
                    const fieldData = {
                        label: this.getAttribute('data-label'),
                        name: this.getAttribute('data-name'),
                        input_type: this.getAttribute('data-input-type'),
                        placeholder_text: this.getAttribute('data-placeholder-text'),
                        is_required: this.getAttribute('data-is-required')
                    };
                    openEditFieldModal(fieldId, fieldData);
                });
            });

            // Close behavior
            closeEditFieldModal.addEventListener('click', function() {
                closeEditFieldWithAnimation();
                editFieldForm.reset();
            });
            cancelEditField.addEventListener('click', function() {
                closeEditFieldWithAnimation();
                editFieldForm.reset();
            });
            editFieldModal.addEventListener('click', function(event) {
                if (event.target === editFieldModal) {
                    closeEditFieldWithAnimation();
                    editFieldForm.reset();
                }
            });

            // Submit with confirmation
            editFieldForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const currentLabel = fieldLabelInput.value.trim();
                const currentName = fieldNameInput.value.trim();
                if (!isValidIdentifier(currentName)) {
                    alert('Invalid Name/ID. Use letters, numbers, underscores; start with a letter.');
                    return;
                }
                if (currentLabel) {
                    closeEditFieldWithAnimation();
                    setTimeout(() => {
                        showConfirmationModal(
                            'Update Field',
                            `Are you sure you want to update the field \"${currentLabel}\"?`,
                            this
                        );
                    }, 100);
                }
            });

            // Escape key closes field modal
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    if (editFieldModal.classList.contains('show')) {
                        closeEditFieldWithAnimation();
                        editFieldForm.reset();
                    }
                }
            });
        });
    </script>
</head>

<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div class="loading-text">Processing...</div>
        </div>
    </div>

    <div class="container">
        <a href="applicant_types.php?cycle_id=<?php echo $cycle_id; ?>" class="btn btn-secondary" style="margin-bottom: 20px;">
            &laquo; Back to Applicant Types
        </a>
        <h1>Manage Form: <?php echo htmlspecialchars($type_name); ?></h1>

        <?php
        if (isset($_SESSION['message'])) {
            echo '<div class="alert alert-' . $_SESSION['message']['type'] . '">
                    ' . $_SESSION['message']['text'] . '
                  </div>';
            unset($_SESSION['message']);
        }
        ?>

        <?php foreach ($steps as $step_id => $step): ?>
            <div class="step-box">
                <div class="step-header">
                    <h2><?php echo htmlspecialchars($step['title']); ?></h2>
                    <div class="actions">
                        <a href="#" class="btn btn-warning edit-step-btn" data-step-id="<?php echo $step_id; ?>" data-step-title="<?php echo htmlspecialchars($step['title']); ?>">Edit Step</a>
                        <a href="manage_form.php?action=delete_step&id=<?php echo $step_id; ?>&applicant_type_id=<?php echo $applicant_type_id; ?>"
                            class="btn btn-danger confirm-action"
                            data-modal-title="Confirm Delete"
                            data-modal-message="Are you sure you want to delete this step and all fields within it?">
                            Delete Step
                        </a>
                    </div>
                </div>

                <div class="step-content">
                    <table>
                        <thead>
                            <tr>
                                <th>Label</th>
                                <th>Name/ID</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($step['fields'])): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center;">No fields in this step.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($step['fields'] as $field): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($field['label']); ?></td>
                                        <td><?php echo htmlspecialchars($field['name']); ?></td>
                                        <td><?php echo htmlspecialchars($field['input_type']); ?></td>
                                        <td><?php echo $field['is_required'] ? 'Yes' : 'No'; ?></td>
                                        <td class="actions">
                                            <a href="#" class="btn btn-warning edit-field-btn"
                                               data-field-id="<?php echo $field['field_id']; ?>"
                                               data-step-id="<?php echo $step_id; ?>"
                                               data-label="<?php echo htmlspecialchars($field['label']); ?>"
                                               data-name="<?php echo htmlspecialchars($field['name']); ?>"
                                               data-input-type="<?php echo htmlspecialchars($field['input_type']); ?>"
                                               data-placeholder-text="<?php echo htmlspecialchars($field['placeholder_text']); ?>"
                                               data-is-required="<?php echo (int)$field['is_required']; ?>">Edit</a>

                                            <?php if ($field['input_type'] === 'select'): ?>
                                                <a href="manage_options.php?field_id=<?php echo $field['field_id']; ?>&applicant_type_id=<?php echo $applicant_type_id; ?>" class="btn btn-secondary">
                                                    Manage Options
                                                </a>
                                            <?php endif; ?>

                                            <a href="manage_form.php?action=delete_field&id=<?php echo $field['field_id']; ?>&applicant_type_id=<?php echo $applicant_type_id; ?>"
                                                class="btn btn-danger confirm-action"
                                                data-modal-title="Confirm Delete"
                                                data-modal-message="Are you sure you want to delete this field?">
                                                Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="form-section" style="margin-top: 20px;">
                        <h3>Add New Field to this Step</h3>
                        <form class="addFieldForm" action="manage_form.php?applicant_type_id=<?php echo $applicant_type_id; ?>" method="post">
                            <input type="hidden" name="action" value="add_field">
                            <input type="hidden" name="step_id" value="<?php echo $step_id; ?>">

                            <div class="form-group">
                                <label for="label_<?php echo $step_id; ?>">Label:</label>
                                <input type="text" id="label_<?php echo $step_id; ?>" name="label" placeholder="e.g., First Name" required>
                            </div>
                            <div class="form-group">
                                <label for="name_<?php echo $step_id; ?>">Name/ID:</label>
                                <input type="text" id="name_<?php echo $step_id; ?>" name="name" placeholder="e.g., first_name (no spaces)" required pattern="^[A-Za-z][A-Za-z0-9_]*$" title="Start with a letter; letters, numbers, underscores only; no spaces">
                            </div>
                            <div class="form-group">
                                <label for="input_type_<?php echo $step_id; ?>">Input Type:</label>
                                <select id="input_type_<?php echo $step_id; ?>" name="input_type">
                                    <option value="text">Text</option>
                                    <option value="email">Email</option>
                                    <option value="tel">Telephone (tel)</option>
                                    <option value="date">Date</option>
                                    <option value="file">File Upload</option>
                                    <option value="select">Select (Dropdown)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="placeholder_text_<?php echo $step_id; ?>">Placeholder Text:</label>
                                <input type="text" id="placeholder_text_<?php echo $step_id; ?>" name="placeholder_text" placeholder="For floating label, use a single space: ' '">
                            </div>
                            <div class="form-group">
                                <input type="checkbox" id="is_required_<?php echo $step_id; ?>" name="is_required" value="1">
                                <label for="is_required_<?php echo $step_id; ?>" style="display:inline; font-weight:normal;">This field is required</label>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Field</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <!-- Floating Action Button -->
    <button class="fab" id="fabAddStep" data-tooltip="Add New Step">
        <svg viewBox="0 0 24 24">
            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
        </svg>
    </button>

    <!-- Add Step Modal - Matching Applicant Type Design -->
    <div id="addStepModal" class="add-step-modal">
        <div class="add-step-modal-content">
            <!-- Decorative Header Background -->
            <div class="add-step-modal-header">
                <!-- Close Button -->
                <button type="button" class="close-btn" id="closeAddStepModal">&times;</button>
                
                <!-- Modal Icon and Title -->
                <div class="add-step-modal-icon">
                    <div class="add-step-modal-icon-container">
                        <svg style="width: 40px; height: 40px; color: var(--color-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Modal Content -->
            <div class="add-step-modal-body">
                <h3>Add New Step</h3>
                <p class="add-step-modal-description">Create a new step for your form to organize fields and improve user experience</p>

                <!-- Form -->
                <form id="addStepForm" action="manage_form.php?applicant_type_id=<?php echo $applicant_type_id; ?>" method="post">
                    <input type="hidden" name="action" value="add_step">

                    <!-- Step Title Field with Icon -->
                    <div class="form-group">
                        <label for="title">
                            <svg style="width: 18px; height: 18px; color: var(--color-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            Step Title
                        </label>
                        <input type="text" id="title" name="title" required placeholder="e.g., Step 1: Personal Information">
                    </div>

                    <!-- Action Buttons -->
                    <div class="add-step-modal-buttons">
                        <button type="button" class="cancel-btn" id="cancelAddStep">Cancel</button>
                        <button type="submit" class="submit-btn">
                            <span style="position: relative; z-index: 2;">Add Step</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Step Modal - Reuses Add Step Styles -->
    <div id="editStepModal" class="add-step-modal" style="display: none;">
        <div class="add-step-modal-content">
            <div class="add-step-modal-header">
                <button type="button" class="close-btn" id="closeEditStepModal">&times;</button>
                <div class="add-step-modal-icon">
                    <div class="add-step-modal-icon-container">
                        <svg style="width: 40px; height: 40px; color: #f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h2m-1 14v-7m-7 7h14a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="add-step-modal-body">
                <h3>Edit Step Title</h3>
                <p class="add-step-modal-description">Update the name of this step</p>

                <form id="editStepForm" action="#" method="post">
                    <input type="hidden" name="action" value="update_step">
                    <div class="form-group">
                        <label for="edit_title">
                            <svg style="width: 18px; height: 18px; color: #f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            Step Title
                        </label>
                        <input type="text" id="edit_title" name="title" required placeholder="Enter step title">
                    </div>
                    <div class="add-step-modal-buttons">
                        <button type="button" class="cancel-btn" id="cancelEditStep">Cancel</button>
                        <button type="submit" class="submit-btn">
                            <span style="position: relative; z-index: 2;">Update Step</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Field Modal - Styled similar to Add/Edit Step -->
    <div id="editFieldModal" class="add-step-modal" style="display: none;">
        <div class="add-step-modal-content">
            <div class="add-step-modal-header">
                <button type="button" class="close-btn" id="closeEditFieldModal">&times;</button>
                <div class="add-step-modal-icon">
                    <div class="add-step-modal-icon-container">
                        <svg style="width: 40px; height: 40px; color: #10b981;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="add-step-modal-body">
                <h3>Edit Field</h3>
                <p class="add-step-modal-description">Update this field’s details</p>

                <form id="editFieldForm" action="#" method="post">
                    <input type="hidden" name="action" value="update_field">
                    <div class="form-group">
                        <label for="field_label">Label</label>
                        <input type="text" id="field_label" name="label" required placeholder="e.g., First Name">
                    </div>
                    <div class="form-group">
                        <label for="field_name">Name/ID</label>
                        <input type="text" id="field_name" name="name" required placeholder="e.g., first_name (no spaces)" pattern="^[A-Za-z][A-Za-z0-9_]*$" title="Start with a letter; letters, numbers, underscores only; no spaces">
                    </div>
                    <div class="form-group">
                        <label for="field_type">Input Type</label>
                        <select id="field_type" name="input_type">
                            <option value="text">Text</option>
                            <option value="email">Email</option>
                            <option value="tel">Telephone (tel)</option>
                            <option value="date">Date</option>
                            <option value="file">File Upload</option>
                            <option value="select">Select (Dropdown)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="field_placeholder">Placeholder Text</label>
                        <input type="text" id="field_placeholder" name="placeholder_text" placeholder="For floating label, use a single space: ' '">
                    </div>
                    <div class="form-group">
                        <input type="checkbox" id="field_required" name="is_required" value="1">
                        <label for="field_required" style="display:inline; font-weight:normal;">This field is required</label>
                    </div>
                    <div class="add-step-modal-buttons">
                        <button type="button" class="cancel-btn" id="cancelEditField">Cancel</button>
                        <button type="submit" class="submit-btn">
                            <span style="position: relative; z-index: 2;">Update Field</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
<div id="confirmationModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1002; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: var(--color-card); color: var(--color-text); padding: 30px; border-radius: 16px; text-align: center; max-width: 450px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.15); transform: scale(0.9); transition: transform 0.3s ease; border: 1px solid var(--color-border);">
        <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
            <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
        </div>
        <h3 id="modalTitle" style="margin: 0 0 15px 0; color: var(--color-text); font-size: 1.4rem; font-weight: 600;">Confirm Action</h3>
        <p id="modalMessage" style="margin: 0 0 25px 0; color: #7f8c8d; font-size: 1rem; line-height: 1.5;">Are you sure you want to proceed?</p>
        <div style="display: flex; gap: 12px; justify-content: center;">
            <button id="modalCancelBtn" style="flex: 1; padding: 12px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
            <button id="modalConfirmBtn" style="flex: 1; padding: 12px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Confirm</button>
        </div>
    </div>
</div>


</body>

</html>