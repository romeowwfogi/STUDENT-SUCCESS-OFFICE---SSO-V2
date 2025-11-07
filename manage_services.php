<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
include 'connection/db_connect.php';

// Optional: target service context
$serviceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : null;

// --- DATA FETCHING: Get services list for display ---
$services = [];
$result = $conn->query("SELECT service_id, name, description, icon, button_text, is_active FROM services_list ORDER BY service_id DESC");
while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}

// --- DATA FETCHING: Get service fields if a service is selected ---
$serviceFields = [];
if ($serviceId) {
    $stmt = $conn->prepare("SELECT field_id, label, field_type, is_required, display_order, allowed_file_types FROM services_fields WHERE service_id = ? ORDER BY display_order ASC, field_id ASC");
    $stmt->bind_param('i', $serviceId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $serviceFields[] = $row;
        }
        $res->close();
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Success Office - Manage Services</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
</head>

<body>
    <!-- Mobile Navbar -->
    <?php include "includes/mobile_navbar.php"; ?>

    <div class="layout">
        <!-- Sidebar -->
        <?php include "includes/sidebar.php"; ?>

        <main class="main-content">
            <header class="header">
                <div class="header__left">
                    <h1 class="header__title">Manage Services</h1>
                </div>
                <div class="header__actions">
                    <button onclick="window.location.href='services_management.php'" class="btn btn--secondary">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Services
                    </button>
                </div>
            </header>

            <section class="section active" id="manage_services_section" style="margin: 0 20px;">
                <!-- Service Fields management UI -->
                <div class="table-container" style="margin-bottom: 20px;">
                    <div class="table-container__header" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                        <div>
                            <h2 class="table-container__title">Service Fields</h2>
                            <p class="table-container__subtitle">Configure fields for the selected service</p>
                        </div>
                        <div>
                            <button id="openAddFieldModalBtn" class="btn btn--primary" type="button" title="Add New Field">Add New Field</button>
                        </div>
                    </div>
                    <table class="table" id="serviceFieldsTable">
                        <thead>
                            <tr>
                                <th style="width:90px;">Order</th>
                                <th>Label</th>
                                <th style="width:180px;">Type</th>
                                <th style="width:140px;">Required?</th>
                                <th style="width:220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($serviceFields)): ?>
                                <?php foreach ($serviceFields as $field): ?>
                                    <tr data-field-id="<?php echo (int)$field['field_id']; ?>">
                                        <td><?php echo (int)$field['display_order']; ?></td>
                                        <td><?php echo htmlspecialchars($field['label']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($field['field_type'])); ?></td>
                                        <td><?php echo ((int)$field['is_required'] === 1) ? 'Yes' : 'No'; ?></td>
                                        <td data-cell="Actions">
                                            <div class="table__actions">
                                                <button type="button" class="table__btn table__btn--edit" title="Edit Field" onclick="openEditFieldModal(<?php echo (int)$field['field_id']; ?>, '<?php echo htmlspecialchars($field['label'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars(strtolower($field['field_type']), ENT_QUOTES); ?>', <?php echo (int)$field['is_required']; ?>, <?php echo (int)$field['display_order']; ?>, '<?php echo htmlspecialchars($field['allowed_file_types'] ?? '', ENT_QUOTES); ?>')">Edit</button>
                                                <?php
                                                $optionTypes = ['select', 'checkbox', 'radio'];
                                                if (in_array(strtolower($field['field_type']), $optionTypes, true)):
                                                ?>
                                                    <button type="button" class="table__btn table__btn--view" title="Manage Options"
                                                        onclick="openOptionsModal(<?php echo (int)$field['field_id']; ?>, <?php echo (int)$serviceId; ?>, '<?php echo htmlspecialchars($field['label'], ENT_QUOTES); ?>')">Manage Options</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align:center;">No fields found. Select a service to view fields.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </section>
        </main>
    </div>

    <!-- Global Loader Overlay (consistent with other pages) -->
    <div id="loadingOverlay" class="loading-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); display: none; justify-content: center; align-items: center; z-index: 9999; backdrop-filter: blur(4px);">
        <div class="loading-spinner" style="text-align: center; color: white;">
            <div class="spinner" style="width: 50px; height: 50px; border: 4px solid rgba(255, 255, 255, 0.3); border-top: 4px solid #ffffff; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px auto;"></div>
            <div class="loading-text" style="font-size: 18px; font-weight: 500; color: white; margin-top: 10px;">Processing...</div>
        </div>
    </div>
    <style>
        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>

    <!-- Add Field Modal -->
    <div id="addFieldModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1001; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div role="dialog" aria-modal="true" aria-labelledby="addFieldModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 720px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text); display: flex; flex-direction: column; max-height: 85vh;">
            <!-- Close Button -->
            <button type="button" id="closeAddFieldModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

            <!-- Modal Header -->
            <div style="padding: 32px 32px 16px 32px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <h3 id="addFieldModalTitle" style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Add New Field</h3>
                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Create a field for this service. Supports text, textarea, date, select, checkbox, radio, and file.</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 32px 24px 32px; overflow-y: auto;">
                <div id="addFieldInlineMessage" class="alert" style="display:none; margin-bottom: 12px;"></div>
                <form id="addFieldForm">
                    <div class="form-group">
                        <label for="fieldLabel" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Label</label>
                        <input type="text" id="fieldLabel" name="label" placeholder="e.g., Gender" required style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    </div>

                    <div class="form-group" style="margin-top:12px;">
                        <label for="fieldType" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Field Type</label>
                        <select id="fieldType" name="field_type" required style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="date">Date</option>
                            <option value="number">Number</option>
                            <option value="email">Email</option>
                            <option value="select">Select</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="radio">Radio</option>
                            <option value="file">File</option>
                        </select>
                    </div>

                    <div class="form-group" id="allowedFileTypesGroup" style="margin-top:12px; display:none;">
                        <label for="allowedFileTypes" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Allowed file types (comma-separated)</label>
                        <input type="text" id="allowedFileTypes" name="allowed_file_types" placeholder="e.g., .pdf,.jpg,.png" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                        <small style="color:#718096; display:block; margin-top:6px;">Example: .pdf,.jpg,.png (no spaces). Leave blank to allow any type.</small>
                    </div>

                    <div class="form-group" style="margin-top:12px; gap:12px; align-items:center;">
                        <div style="flex:1;">
                            <label for="displayOrder" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Display Order</label>
                            <input type="number" id="displayOrder" name="display_order" min="0" value="0" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                            <small style="color:#718096; display:block; margin-top:6px;">Leave 0 to auto-place after existing fields.</small>
                        </div>

                        <div style="flex:1; display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" id="isRequired" name="is_required" checked>
                            <label for="isRequired" style="font-weight:600; color:#2d3748; font-size:0.9rem;">Required</label>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button type="button" id="cancelAddFieldBtn" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                <button type="button" id="confirmAddFieldBtn" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Add Field</button>
            </div>
        </div>
    </div>

    <!-- Edit Field Modal -->
    <div id="editFieldModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1001; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div role="dialog" aria-modal="true" aria-labelledby="editFieldModalTitle" style="background: var(--color-card); border-radius: 20px; max-width: 720px; width: 92%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); position: relative; color: var(--color-text); display: flex; flex-direction: column; max-height: 85vh;">
            <!-- Close Button -->
            <button type="button" id="closeEditFieldModalBtn" style="position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px; transition: all 0.2s ease; z-index: 10;">&times;</button>

            <!-- Modal Header -->
            <div style="padding: 32px 32px 16px 32px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 32px; height: 32px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h2m-2 14h2m-7-7h12" />
                    </svg>
                </div>
                <h3 id="editFieldModalTitle" style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.6rem; font-weight: 700; letter-spacing: -0.025em;">Edit Field</h3>
                <p style="color: #718096; margin: 0; line-height: 1.5; font-size: 0.95rem;">Update field properties. Supports text, textarea, date, select, checkbox, radio, and file.</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 32px 24px 32px; overflow-y: auto;">
                <div id="editFieldInlineMessage" class="alert" style="display:none; margin-bottom: 12px;"></div>
                <form id="editFieldForm">
                    <input type="hidden" id="editFieldId" />
                    <div class="form-group">
                        <label for="editFieldLabel" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Label</label>
                        <input type="text" id="editFieldLabel" name="label" required style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                    </div>

                    <div class="form-group" style="margin-top:12px;">
                        <label for="editFieldType" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Field Type</label>
                        <select id="editFieldType" name="field_type" required style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="date">Date</option>
                            <option value="number">Number</option>
                            <option value="email">Email</option>
                            <option value="select">Select</option>
                            <option value="checkbox">Checkbox</option>
                            <option value="radio">Radio</option>
                            <option value="file">File</option>
                        </select>
                    </div>

                    <div class="form-group" id="editAllowedFileTypesGroup" style="margin-top:12px; display:none;">
                        <label for="editAllowedFileTypes" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Allowed file types (comma-separated)</label>
                        <input type="text" id="editAllowedFileTypes" name="allowed_file_types" placeholder="e.g., .pdf,.jpg,.png" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                        <small style="color:#718096; display:block; margin-top:6px;">Example: .pdf,.jpg,.png (no spaces). Leave blank to allow any type.</small>
                    </div>

                    <div class="form-group" style="margin-top:12px; gap:12px; align-items:center;">
                        <div style="flex:1;">
                            <label for="editDisplayOrder" style="display:block; margin-bottom:8px; font-weight:600; color:#2d3748; font-size:0.9rem;">Display Order</label>
                            <input type="number" id="editDisplayOrder" name="display_order" min="0" value="0" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;">
                        </div>

                        <div style="flex:1; display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" id="editIsRequired" name="is_required">
                            <label for="editIsRequired" style="font-weight:600; color:#2d3748; font-size:0.9rem;">Required</label>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div style="padding: 20px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button type="button" id="cancelEditFieldBtn" style="flex: 1; padding: 14px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                <button type="button" id="confirmEditFieldBtn" style="flex: 1; padding: 14px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24, 165, 88, 0.4);">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Options Modal -->
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

    <!-- Confirmation Modal (styled like other pages) -->
    <div id="confirmationModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 1002; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div role="dialog" aria-modal="true" style="background: var(--color-card); border-radius: 20px; max-width: 400px; width: 92%; margin: auto; border: 1px solid var(--color-border); color: var(--color-text); box-shadow: 0 20px 60px rgba(0,0,0,0.1); padding: 24px 24px 20px 24px;">
            <div style="text-align: center; margin-bottom: 16px;">
                <div style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center; color: white;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5" />
                    </svg>
                </div>
                <h3 id="modalTitle" style="margin: 0 0 8px 0; color: #1a202c; font-size: 1.5rem; font-weight: 700; letter-spacing: -0.025em;">Confirm Action</h3>
                <p id="modalMessage" style="color: #718096; margin: 0; line-height: 1.6; font-size: 0.95rem;">Are you sure you want to proceed?</p>
            </div>
            <div style="display:flex; gap: 12px; justify-content:center; margin-top: 12px;">
                <button id="modalCancelBtn" style="flex: 1; padding: 12px 24px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none;">Cancel</button>
                <button id="modalConfirmBtn" style="flex: 1; padding: 12px 24px; border: none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white; border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; box-shadow: 0 4px 14px rgba(24,165,88,0.4);">Confirm</button>
            </div>
        </div>
    </div>

    <script src="theme-init.js"></script>
    <script>
        const addFieldModal = document.getElementById('addFieldModal');
        const openAddFieldModalBtn = document.getElementById('openAddFieldModalBtn');
        const closeAddFieldModalBtn = document.getElementById('closeAddFieldModalBtn');
        const cancelAddFieldBtn = document.getElementById('cancelAddFieldBtn');
        const confirmAddFieldBtn = document.getElementById('confirmAddFieldBtn');
        const addFieldInlineMessage = document.getElementById('addFieldInlineMessage');
        const addFieldForm = document.getElementById('addFieldForm');
        const fieldTypeSelect = document.getElementById('fieldType');
        const allowedFileTypesGroup = document.getElementById('allowedFileTypesGroup');
        const allowedFileTypesInput = document.getElementById('allowedFileTypes');
        const currentServiceId = <?php echo (int)($serviceId ?? 0); ?>;

        // Edit modal elements
        const editFieldModal = document.getElementById('editFieldModal');
        const closeEditFieldModalBtn = document.getElementById('closeEditFieldModalBtn');
        const cancelEditFieldBtn = document.getElementById('cancelEditFieldBtn');
        const confirmEditFieldBtn = document.getElementById('confirmEditFieldBtn');
        const editFieldInlineMessage = document.getElementById('editFieldInlineMessage');
        const editFieldForm = document.getElementById('editFieldForm');
        const editFieldIdInput = document.getElementById('editFieldId');
        const editFieldLabelInput = document.getElementById('editFieldLabel');
        const editFieldTypeSelect = document.getElementById('editFieldType');
        const editAllowedFileTypesGroup = document.getElementById('editAllowedFileTypesGroup');
        const editAllowedFileTypesInput = document.getElementById('editAllowedFileTypes');
        const editDisplayOrderInput = document.getElementById('editDisplayOrder');
        const editIsRequiredInput = document.getElementById('editIsRequired');

        function appendServiceFieldRow(fieldId, label, fieldType, isRequired, displayOrder) {
            const tbody = document.querySelector('#serviceFieldsTable tbody');
            if (!tbody) return;
            const optionTypes = ['select', 'checkbox', 'radio'];
            const typeLabel = fieldType.charAt(0).toUpperCase() + fieldType.slice(1);
            const tr = document.createElement('tr');
            tr.setAttribute('data-field-id', String(fieldId));
            tr.innerHTML = `
                <td>${Number(displayOrder) || 0}</td>
                <td>${escapeHtml(label)}</td>
                <td>${escapeHtml(typeLabel)}</td>
                <td>${isRequired === 1 ? 'Yes' : 'No'}</td>
                <td data-cell="Actions">
                    <div class="table__actions">
                        <button type="button" class="table__btn table__btn--edit" title="Edit Field" onclick="openEditFieldModal(${fieldId}, '${escapeHtml(label)}', '${fieldType}', ${isRequired}, ${Number(displayOrder) || 0}, '')">Edit</button>
                        ${optionTypes.includes(fieldType) ? `<button type=\"button\" class=\"table__btn table__btn--view\" title=\"Manage Options\" onclick=\"openOptionsModal(${fieldId}, ${currentServiceId}, '${escapeHtml(label)}')\">Manage Options</button>` : ''}
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        }

        function updateServiceFieldRow(fieldId, label, fieldType, isRequired, displayOrder) {
            const row = document.querySelector(`#serviceFieldsTable tbody tr[data-field-id='${CSS.escape(String(fieldId))}']`);
            if (!row) return;
            const optionTypes = ['select', 'checkbox', 'radio'];
            const typeLabel = fieldType.charAt(0).toUpperCase() + fieldType.slice(1);
            const cells = row.querySelectorAll('td');
            if (cells[0]) cells[0].textContent = String(Number(displayOrder) || 0);
            if (cells[1]) cells[1].textContent = label;
            if (cells[2]) cells[2].textContent = typeLabel;
            if (cells[3]) cells[3].textContent = isRequired === 1 ? 'Yes' : 'No';
            const actionsCell = cells[4];
            if (actionsCell) {
                actionsCell.innerHTML = `
                    <div class="table__actions">
                        <button type="button" class="table__btn table__btn--edit" title="Edit Field" onclick="openEditFieldModal(${fieldId}, '${escapeHtml(label)}', '${fieldType}', ${isRequired}, ${Number(displayOrder) || 0}, '')">Edit</button>
                        ${optionTypes.includes(fieldType) ? `<button type=\"button\" class=\"table__btn table__btn--view\" title=\"Manage Options\" onclick=\"openOptionsModal(${fieldId}, ${currentServiceId}, '${escapeHtml(label)}')\">Manage Options</button>` : ''}
                    </div>
                `;
            }
        }

        // Toggle edit allowed file types input
        editFieldTypeSelect.addEventListener('change', function() {
            const isFile = String(this.value).toLowerCase() === 'file';
            editAllowedFileTypesGroup.style.display = isFile ? 'block' : 'none';
        });

        function openAddFieldModal() {
            if (!currentServiceId) {
                showConfirm('No Service Selected', 'Please select a service first.', () => {});
                return;
            }
            addFieldInlineMessage.style.display = 'none';
            addFieldInlineMessage.textContent = '';
            addFieldForm.reset();
            addFieldModal.classList.add('show');
            addFieldModal.style.display = 'flex';
            addFieldModal.style.visibility = 'visible';
            addFieldModal.style.opacity = '1';
        }

        function closeAddFieldModal() {
            addFieldModal.classList.remove('show');
            addFieldModal.style.display = 'none';
            addFieldModal.style.visibility = 'hidden';
            addFieldModal.style.opacity = '0';
        }

        function showAddFieldMessage(type, text) {
            addFieldInlineMessage.className = 'alert alert-' + type;
            addFieldInlineMessage.textContent = text;
            addFieldInlineMessage.style.display = 'block';
        }

        // Toggle allowed file types input
        fieldTypeSelect.addEventListener('change', function() {
            const isFile = String(this.value).toLowerCase() === 'file';
            allowedFileTypesGroup.style.display = isFile ? 'block' : 'none';
        });

        // Wire buttons
        openAddFieldModalBtn?.addEventListener('click', openAddFieldModal);
        closeAddFieldModalBtn?.addEventListener('click', closeAddFieldModal);
        cancelAddFieldBtn?.addEventListener('click', closeAddFieldModal);
        addFieldModal?.addEventListener('click', (e) => {
            if (e.target === addFieldModal) closeAddFieldModal();
        });

        async function doAddField() {
            const label = document.getElementById('fieldLabel').value.trim();
            const fieldType = String(fieldTypeSelect.value).toLowerCase();
            const isRequired = document.getElementById('isRequired').checked ? 1 : 0;
            const displayOrderVal = parseInt(document.getElementById('displayOrder').value || '0', 10) || 0;
            let allowedTypes = allowedFileTypesInput.value.trim();

            if (!label) {
                showAddFieldMessage('error', 'Label is required');
                return;
            }
            const allowedFieldTypes = ['text', 'textarea', 'date', 'number', 'email', 'select', 'checkbox', 'radio', 'file'];
            if (!allowedFieldTypes.includes(fieldType)) {
                showAddFieldMessage('error', 'Invalid field type');
                return;
            }
            if (fieldType === 'file' && allowedTypes.length > 0) {
                const invalidSpace = /\s/.test(allowedTypes);
                if (invalidSpace) {
                    showAddFieldMessage('error', 'Remove spaces in allowed file types. Use comma-separated values like .pdf,.jpg');
                    return;
                }
            } else if (fieldType !== 'file') {
                allowedTypes = '';
            }

            const doSubmit = async () => {
                showLoader();
                try {
                    const fd = new FormData();
                    fd.append('ajax', '1');
                    fd.append('action', 'add_field');
                    fd.append('service_id', String(currentServiceId));
                    fd.append('label', label);
                    fd.append('field_type', fieldType);
                    fd.append('is_required', String(isRequired));
                    fd.append('display_order', String(displayOrderVal));
                    if (allowedTypes) fd.append('allowed_file_types', allowedTypes);

                    const res = await fetch('manage_service_fields.php', {
                        method: 'POST',
                        body: fd
                    });
                    if (!res.ok) {
                        const txt = await res.text();
                        showAddFieldMessage('error', `Server responded ${res.status}. ${txt.slice(0, 180)}`);
                        return;
                    }
                    let data;
                    try {
                        data = await res.json();
                    } catch (jsonErr) {
                        const txt = await res.text().catch(() => '');
                        showAddFieldMessage('error', `Unexpected response while adding field. ${txt ? txt.slice(0, 180) : ''}`);
                        return;
                    }
                    if (data.ok) {
                        // Success: show confirmation modal and refresh
                        if (modalTitle && modalMessage && modalConfirmBtn && modalCancelBtn && confirmationModal) {
                            setConfirmLoading(false);
                            modalTitle.textContent = 'Successfully Added!';
                            const typeLabel = fieldType.charAt(0).toUpperCase() + fieldType.slice(1);
                            modalMessage.innerHTML = `<div style="margin-top:6px;"><div>Label: <strong>${escapeHtml(label)}</strong></div><div>Type: <strong>${escapeHtml(typeLabel)}</strong></div></div>`;
                            modalLocked = true;
                            modalConfirmBtn.textContent = 'Okay';
                            modalCancelBtn.style.display = 'none';
                            // Dismiss only; no reload. Also append the new row to the table.
                            modalConfirmBtn.onclick = () => {
                                closeConfirmationModal(true);
                                closeAddFieldModal();
                            };
                            confirmationModal.style.display = 'flex';
                            appendServiceFieldRow(data.field_id, label, fieldType, isRequired, data.display_order);
                        } else {
                            showAddFieldMessage('success', 'Field added');
                            appendServiceFieldRow(data.field_id, label, fieldType, isRequired, data.display_order);
                        }
                    } else {
                        closeConfirmationModal();
                        showAddFieldMessage('error', data.error || 'Error adding field');
                    }
                } catch (e) {
                    closeConfirmationModal();
                    showAddFieldMessage('error', `Network or server error adding field: ${e?.message || e}`);
                } finally {
                    hideLoader();
                }
            };

            const summary = [`Label: ${label}`, `Type: ${fieldType}`];
            if (fieldType === 'file') summary.push(`Allowed: ${allowedTypes || '(any)'}`);
            showConfirm('Confirm Add Field', `Are you sure you want to add this field?\n${summary.join('\n')}`, doSubmit);
        }

        confirmAddFieldBtn?.addEventListener('click', doAddField);

        function openEditFieldModal(fieldId, label, fieldType, isRequired, displayOrder, allowedFileTypes) {
            editFieldInlineMessage.style.display = 'none';
            editFieldInlineMessage.textContent = '';
            editFieldForm.reset();
            editFieldIdInput.value = String(fieldId);
            editFieldLabelInput.value = label || '';
            editFieldTypeSelect.value = (fieldType || 'text').toLowerCase();
            editIsRequiredInput.checked = (parseInt(isRequired, 10) === 1);
            editDisplayOrderInput.value = String(parseInt(displayOrder, 10) || 0);
            editAllowedFileTypesInput.value = allowedFileTypes || '';
            // Toggle file types group based on type
            editAllowedFileTypesGroup.style.display = (String(editFieldTypeSelect.value).toLowerCase() === 'file') ? 'block' : 'none';

            editFieldModal.classList.add('show');
            editFieldModal.style.display = 'flex';
            editFieldModal.style.visibility = 'visible';
            editFieldModal.style.opacity = '1';
        }

        function closeEditFieldModal() {
            editFieldModal.classList.remove('show');
            editFieldModal.style.display = 'none';
            editFieldModal.style.visibility = 'hidden';
            editFieldModal.style.opacity = '0';
        }

        function showEditFieldMessage(type, text) {
            editFieldInlineMessage.className = 'alert alert-' + type;
            editFieldInlineMessage.textContent = text;
            editFieldInlineMessage.style.display = 'block';
        }

        closeEditFieldModalBtn?.addEventListener('click', closeEditFieldModal);
        cancelEditFieldBtn?.addEventListener('click', closeEditFieldModal);
        editFieldModal?.addEventListener('click', (e) => {
            if (e.target === editFieldModal) closeEditFieldModal();
        });

        async function doEditField() {
            const fieldId = parseInt(editFieldIdInput.value || '0', 10) || 0;
            const label = editFieldLabelInput.value.trim();
            const fieldType = String(editFieldTypeSelect.value).toLowerCase();
            const isRequired = editIsRequiredInput.checked ? 1 : 0;
            const displayOrderVal = parseInt(editDisplayOrderInput.value || '0', 10) || 0;
            let allowedTypes = editAllowedFileTypesInput.value.trim();

            if (!fieldId) {
                showEditFieldMessage('error', 'Invalid field id');
                return;
            }
            if (!label) {
                showEditFieldMessage('error', 'Label is required');
                return;
            }
            const allowedFieldTypes = ['text', 'textarea', 'date', 'number', 'email', 'select', 'checkbox', 'radio', 'file'];
            if (!allowedFieldTypes.includes(fieldType)) {
                showEditFieldMessage('error', 'Invalid field type');
                return;
            }
            if (fieldType === 'file' && allowedTypes.length > 0) {
                const invalidSpace = /\s/.test(allowedTypes);
                if (invalidSpace) {
                    showEditFieldMessage('error', 'Remove spaces in allowed file types. Use comma-separated values like .pdf,.jpg');
                    return;
                }
            } else if (fieldType !== 'file') {
                allowedTypes = '';
            }

            const doSubmit = async () => {
                showLoader();
                try {
                    const fd = new FormData();
                    fd.append('ajax', '1');
                    fd.append('action', 'update_field');
                    fd.append('service_id', String(currentServiceId));
                    fd.append('field_id', String(fieldId));
                    fd.append('label', label);
                    fd.append('field_type', fieldType);
                    fd.append('is_required', String(isRequired));
                    fd.append('display_order', String(displayOrderVal));
                    if (allowedTypes) fd.append('allowed_file_types', allowedTypes);

                    const res = await fetch('manage_service_fields.php', {
                        method: 'POST',
                        body: fd
                    });
                    if (!res.ok) {
                        const txt = await res.text();
                        showEditFieldMessage('error', `Server responded ${res.status}. ${txt.slice(0, 180)}`);
                        return;
                    }
                    let data;
                    try {
                        data = await res.json();
                    } catch (jsonErr) {
                        const txt = await res.text().catch(() => '');
                        showEditFieldMessage('error', `Unexpected response while updating field. ${txt ? txt.slice(0, 180) : ''}`);
                        return;
                    }
                    if (data.ok) {
                        if (modalTitle && modalMessage && modalConfirmBtn && modalCancelBtn && confirmationModal) {
                            setConfirmLoading(false);
                            modalTitle.textContent = 'Successfully Updated!';
                            const typeLabel = fieldType.charAt(0).toUpperCase() + fieldType.slice(1);
                            modalMessage.innerHTML = `<div style=\"margin-top:6px;\"><div>Label: <strong>${escapeHtml(label)}</strong></div><div>Type: <strong>${escapeHtml(typeLabel)}</strong></div></div>`;
                            modalLocked = true;
                            modalConfirmBtn.textContent = 'Okay';
                            modalCancelBtn.style.display = 'none';
                            modalConfirmBtn.onclick = () => {
                                closeConfirmationModal(true);
                                closeEditFieldModal();
                            };
                            confirmationModal.style.display = 'flex';
                            updateServiceFieldRow(fieldId, label, fieldType, isRequired, displayOrderVal);
                        } else {
                            showEditFieldMessage('success', 'Field updated');
                            updateServiceFieldRow(fieldId, label, fieldType, isRequired, displayOrderVal);
                        }
                    } else {
                        closeConfirmationModal();
                        showEditFieldMessage('error', data.error || 'Error updating field');
                    }
                } catch (e) {
                    closeConfirmationModal();
                    showEditFieldMessage('error', `Network or server error updating field: ${e?.message || e}`);
                } finally {
                    hideLoader();
                }
            };

            const summary = [`Label: ${label}`, `Type: ${fieldType}`];
            if (fieldType === 'file') summary.push(`Allowed: ${allowedTypes || '(any)'}`);
            showConfirm('Confirm Update', `Are you sure you want to update this field?\n${summary.join('\n')}`, doSubmit);
        }

        confirmEditFieldBtn?.addEventListener('click', doEditField);

        const optionsModal = document.getElementById('optionsModal');
        const optionsTableBody = document.getElementById('optionsTableBody');
        const optionsFieldLabelEl = document.getElementById('optionsFieldLabel');
        const optionsModalMessage = document.getElementById('optionsModalMessage');
        const optionsSuccessOkBtn = document.getElementById('optionsSuccessOkBtn');
        const optionsSaveBtn = document.getElementById('optionsSaveBtn');
        const optionsCloseBtn = document.getElementById('optionsCloseBtn');
        // Confirmation modal elements
        const confirmationModal = document.getElementById('confirmationModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalConfirmBtn = document.getElementById('modalConfirmBtn');
        const modalCancelBtn = document.getElementById('modalCancelBtn');
        let modalLocked = false;

        // Loader controls (consistent with other pages)
        function showLoader() {
            var loader = document.getElementById('loadingOverlay');
            if (loader) {
                document.body.appendChild(loader);
                loader.style.display = 'flex';
            }
        }

        function hideLoader() {
            var loader = document.getElementById('loadingOverlay');
            if (loader) loader.style.display = 'none';
        }

        function openOptionsModal(fieldId, serviceId, fieldLabel) {
            optionsModal.dataset.fieldId = String(fieldId);
            optionsModal.dataset.serviceId = String(serviceId || '');
            optionsFieldLabelEl.textContent = fieldLabel;
            // Use existing modal pattern: toggle .show, and ensure flex display fallback
            optionsModal.classList.add('show');
            optionsModal.style.display = 'flex';
            optionsModal.style.visibility = 'visible';
            optionsModal.style.opacity = '1';
            // Reset footer buttons and message state
            if (optionsSuccessOkBtn) optionsSuccessOkBtn.style.display = 'none';
            if (optionsSaveBtn) optionsSaveBtn.style.display = '';
            if (optionsCloseBtn) optionsCloseBtn.style.display = '';
            optionsModalMessage.style.display = 'none';
            optionsModalMessage.textContent = '';
            loadOptions();
        }

        function closeOptionsModal() {
            optionsModal.classList.remove('show');
            optionsModal.style.display = 'none';
            optionsModal.style.visibility = 'hidden';
            optionsModal.style.opacity = '0';
            optionsTableBody.innerHTML = '';
            optionsModalMessage.style.display = 'none';
            optionsModalMessage.textContent = '';
            if (optionsSuccessOkBtn) optionsSuccessOkBtn.style.display = 'none';
            if (optionsSaveBtn) optionsSaveBtn.style.display = '';
            if (optionsCloseBtn) optionsCloseBtn.style.display = '';
            // Reset confirmation modal state
            modalLocked = false;
            if (confirmationModal) confirmationModal.style.display = 'none';
        }

        // Close modal when clicking outside content
        optionsModal.addEventListener('click', function(e) {
            if (e.target === optionsModal) closeOptionsModal();
        });

        function showMessage(type, text) {
            optionsModalMessage.className = 'alert alert-' + type;
            optionsModalMessage.textContent = text;
            optionsModalMessage.style.display = 'block';
            try {
                optionsModalMessage.setAttribute('role', 'alert');
            } catch (e) {}
            if (optionsModalMessage && optionsModalMessage.scrollIntoView) {
                optionsModalMessage.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }

        // Basic confirmation modal handlers
        function closeConfirmationModal(force) {
            if (modalLocked && !force) return;
            if (confirmationModal) confirmationModal.style.display = 'none';
            if (modalConfirmBtn) modalConfirmBtn.textContent = 'Confirm';
            if (modalCancelBtn) modalCancelBtn.style.display = '';
            if (modalConfirmBtn) modalConfirmBtn.onclick = null;
            try {
                setConfirmLoading(false);
            } catch (e) {}
        }
        if (modalCancelBtn) modalCancelBtn.addEventListener('click', closeConfirmationModal);
        if (confirmationModal) confirmationModal.addEventListener('click', (e) => {
            if (e.target === confirmationModal) closeConfirmationModal();
        });

        // Toggle loading state for confirmation modal buttons
        function setConfirmLoading(isLoading) {
            if (!modalConfirmBtn || !modalCancelBtn || !confirmationModal) return;
            confirmationModal.setAttribute('aria-busy', isLoading ? 'true' : 'false');
            modalConfirmBtn.disabled = !!isLoading;
            modalCancelBtn.disabled = !!isLoading;
            if (isLoading) {
                modalConfirmBtn.dataset.prevText = modalConfirmBtn.textContent;
                modalConfirmBtn.textContent = 'Processing...';
            } else {
                if (modalConfirmBtn.dataset.prevText) {
                    modalConfirmBtn.textContent = modalConfirmBtn.dataset.prevText;
                    delete modalConfirmBtn.dataset.prevText;
                }
            }
        }

        // Show a confirmation modal (Cancel + Confirm). onConfirm runs when Confirm is clicked.
        function showConfirm(title, message, onConfirm) {
            if (!confirmationModal || !modalTitle || !modalMessage || !modalConfirmBtn || !modalCancelBtn) {
                // Fallback to immediate execution if modal elements not found
                if (typeof onConfirm === 'function') onConfirm();
                return;
            }
            modalLocked = false; // allow closing
            modalTitle.textContent = title || 'Confirm Action';
            modalMessage.textContent = message || 'Are you sure you want to proceed?';
            modalConfirmBtn.textContent = 'Confirm';
            modalCancelBtn.style.display = '';
            // Clear previous handlers then set new
            modalConfirmBtn.onclick = null;
            modalConfirmBtn.onclick = async () => {
                // Stay in modal and show loader while processing
                setConfirmLoading(true);
                try {
                    if (typeof onConfirm === 'function') await onConfirm();
                } catch (err) {
                    // Ensure modal closes and we show inline error
                    closeConfirmationModal();
                    showMessage('error', `Action failed: ${err?.message || err}`);
                }
                // Do not reset loading state here: success path updates modal to "Okay"; failure closes the modal.
            };
            confirmationModal.style.display = 'flex';
        }

        async function loadOptions() {
            const fieldId = optionsModal.dataset.fieldId;
            const serviceId = optionsModal.dataset.serviceId;
            showLoader();
            try {
                const res = await fetch(`manage_service_options.php?field_id=${fieldId}&service_id=${serviceId}&ajax=1&action=get`);
                const data = await res.json();
                if (!data.ok) {
                    showMessage('error', data.error || 'Failed to load options');
                    return;
                }
                const opts = data.options || [];
                optionsTableBody.innerHTML = '';
                if (opts.length === 0) {
                    optionsTableBody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No options found. Add one below.</td></tr>';
                } else {
                    for (const opt of opts) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <input type="hidden" data-name="option_id" value="${opt.option_id}">
                            <td><input type="text" data-name="option_label" value="${escapeHtml(opt.option_label || '')}" required style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;"></td>
                            <td><input type="text" data-name="option_value" value="${escapeHtml(opt.option_value || '')}" required style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;"></td>
                            <td><input type="number" data-name="display_order" value="${Number(opt.display_order) || 0}" required style="width: 80px; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s ease; box-sizing: border-box; background: #f7fafc; color: #2d3748;"></td>
                            <td>
                                <button type="button" class="table__btn table__btn--delete" onclick="deleteOption(${opt.option_id})">Delete</button>
                            </td>
                        `;
                        optionsTableBody.appendChild(tr);
                    }
                }
            } catch (e) {
                showMessage('error', 'Error loading options');
            } finally {
                hideLoader();
            }
        }

        async function saveOptionsChanges() {
            const rows = Array.from(optionsTableBody.querySelectorAll('tr'));
            const fd = new FormData();
            fd.append('ajax', '1');
            fd.append('action', 'update_options');
            for (const tr of rows) {
                const id = tr.querySelector('input[data-name="option_id"]').value;
                const lbl = tr.querySelector('input[data-name="option_label"]').value;
                const val = tr.querySelector('input[data-name="option_value"]').value;
                const ord = tr.querySelector('input[data-name="display_order"]').value;
                fd.append('option_id[]', id);
                fd.append('option_label[]', lbl);
                fd.append('option_value[]', val);
                fd.append('display_order[]', ord);
            }
            const fieldId = optionsModal.dataset.fieldId;
            const serviceId = optionsModal.dataset.serviceId;
            showLoader();
            try {
                const res = await fetch(`manage_service_options.php?field_id=${fieldId}&service_id=${serviceId}&ajax=1`, {
                    method: 'POST',
                    body: fd
                });
                // If server returned non-2xx, surface status and short text
                if (!res.ok) {
                    const txt = await res.text();
                    showMessage('error', `Server responded ${res.status}. ${txt.slice(0, 180)}`);
                    return;
                }
                let data;
                try {
                    data = await res.json();
                } catch (jsonErr) {
                    const txt = await res.text().catch(() => '');
                    showMessage('error', `Unexpected response while updating. ${txt ? txt.slice(0, 180) : ''}`);
                    return;
                }
                if (data.ok) {
                    // Show success in a separate confirmation modal with Okay to reload
                    modalTitle.textContent = 'Success';
                    modalMessage.textContent = 'Options updated successfully.';
                    modalLocked = true;
                    modalConfirmBtn.textContent = 'Okay';
                    modalCancelBtn.style.display = 'none';
                    modalConfirmBtn.onclick = () => {
                        window.location.reload();
                    };
                    confirmationModal.style.display = 'flex';
                } else if (typeof data.errors !== 'undefined') {
                    // Failure: dismiss confirmation modal if visible
                    closeConfirmationModal();
                    showMessage('warning', `Some options failed to update (${data.errors}).`);
                } else {
                    // Failure: dismiss confirmation modal if visible
                    closeConfirmationModal();
                    showMessage('error', data.error || 'Failed to update options');
                }
            } catch (e) {
                // Failure: dismiss confirmation modal if visible
                closeConfirmationModal();
                showMessage('error', `Network or server error updating options: ${e?.message || e}`);
            } finally {
                hideLoader();
            }
        }

        async function addNewOption() {
            const lbl = document.getElementById('newOptionLabel').value.trim();
            const val = document.getElementById('newOptionValue').value.trim();
            const ord = document.getElementById('newOptionOrder').value.trim();
            if (!lbl || !val) {
                showMessage('error', 'Label and Value are required');
                return;
            }
            const fieldId = optionsModal.dataset.fieldId;
            const serviceId = optionsModal.dataset.serviceId;

            const doAdd = async () => {
                const fd = new FormData();
                fd.append('ajax', '1');
                fd.append('action', 'add_option');
                fd.append('option_label', lbl);
                fd.append('option_value', val);
                fd.append('display_order', ord || '0');
                try {
                    const res = await fetch(`manage_service_options.php?field_id=${fieldId}&service_id=${serviceId}&ajax=1`, {
                        method: 'POST',
                        body: fd
                    });
                    if (!res.ok) {
                        const txt = await res.text();
                        showMessage('error', `Server responded ${res.status}. ${txt.slice(0, 180)}`);
                        return;
                    }
                    let data;
                    try {
                        data = await res.json();
                    } catch (jsonErr) {
                        const txt = await res.text();
                        showMessage('error', `Unexpected response while adding option. ${txt ? txt.slice(0, 180) : ''}`);
                        return;
                    }
                    if (data.ok) {
                        document.getElementById('newOptionLabel').value = '';
                        document.getElementById('newOptionValue').value = '';
                        document.getElementById('newOptionOrder').value = '0';
                        // Show success in confirmation modal
                        if (modalTitle && modalMessage && modalConfirmBtn && modalCancelBtn && confirmationModal) {
                            setConfirmLoading(false);
                            const safeLbl = escapeHtml(lbl);
                            const safeVal = escapeHtml(val);
                            modalTitle.textContent = 'Successfully Added!';
                            modalMessage.innerHTML = `
                                <div style="margin-top:6px;">
                                    <div>Label: <strong>${safeLbl}</strong></div>
                                    <div>Value: <strong>${safeVal}</strong></div>
                                </div>
                            `;
                            modalLocked = true;
                            modalConfirmBtn.textContent = 'Okay';
                            modalCancelBtn.style.display = 'none';
                            modalConfirmBtn.onclick = () => {
                                closeConfirmationModal(true);
                            };
                            confirmationModal.style.display = 'flex';
                        } else {
                            // Fallback to inline message
                            showMessage('success', `Successfully Added! Label: ${lbl} • Value: ${val}`);
                        }
                        loadOptions();
                    } else {
                        // Failure: dismiss confirmation modal if visible
                        closeConfirmationModal();
                        showMessage('error', data.error || 'Error adding option');
                    }
                } catch (e) {
                    // Failure: dismiss confirmation modal if visible
                    closeConfirmationModal();
                    showMessage('error', `Network or server error adding option: ${e?.message || e}`);
                }
            };

            // Ask for confirmation before adding
            const msg = `Are you sure you want to add this option?\nLabel: ${lbl}\nValue: ${val}`;
            showConfirm('Confirm Add', msg, doAdd);
        }

        async function deleteOption(optionId) {
            const fieldId = optionsModal.dataset.fieldId;
            const serviceId = optionsModal.dataset.serviceId;

            const doDelete = async () => {
                try {
                    const res = await fetch(`manage_service_options.php?field_id=${fieldId}&service_id=${serviceId}&ajax=1&action=delete_option&id=${optionId}`);
                    if (!res.ok) {
                        const txt = await res.text();
                        showMessage('error', `Server responded ${res.status}. ${txt.slice(0, 180)}`);
                        return;
                    }
                    let data;
                    try {
                        data = await res.json();
                    } catch (jsonErr) {
                        const txt = await res.text();
                        showMessage('error', `Unexpected response while deleting option. ${txt ? txt.slice(0, 180) : ''}`);
                        return;
                    }
                    if (data.ok) {
                        // Show success in confirmation modal
                        if (modalTitle && modalMessage && modalConfirmBtn && modalCancelBtn && confirmationModal) {
                            setConfirmLoading(false);
                            // Try to read label/value from the row being deleted
                            let delLbl = '',
                                delVal = '';
                            try {
                                const btn = optionsTableBody?.querySelector(`button[onclick="deleteOption(${optionId})"]`);
                                const row = btn?.closest('tr');
                                const lblInput = row?.querySelector('input[data-name="option_label"]');
                                const valInput = row?.querySelector('input[data-name="option_value"]');
                                delLbl = lblInput?.value?.trim() || '';
                                delVal = valInput?.value?.trim() || '';
                            } catch (_) {}
                            const safeLbl = escapeHtml(delLbl);
                            const safeVal = escapeHtml(delVal);
                            modalTitle.textContent = 'Successfully Deleted!';
                            modalMessage.innerHTML = `
                                <div style="margin-top:6px;">
                                    <div>Label: <strong>${safeLbl}</strong></div>
                                    <div>Value: <strong>${safeVal}</strong></div>
                                </div>
                            `;
                            modalLocked = true;
                            modalConfirmBtn.textContent = 'Okay';
                            modalCancelBtn.style.display = 'none';
                            modalConfirmBtn.onclick = () => {
                                closeConfirmationModal(true);
                            };
                            confirmationModal.style.display = 'flex';
                        } else {
                            // Fallback to inline message
                            showMessage('success', 'Option deleted');
                        }
                        loadOptions();
                    } else {
                        // Failure: dismiss confirmation modal if visible
                        closeConfirmationModal();
                        showMessage('error', data.error || 'Error deleting option');
                    }
                } catch (e) {
                    // Failure: dismiss confirmation modal if visible
                    closeConfirmationModal();
                    showMessage('error', `Network or server error deleting option: ${e?.message || e}`);
                }
            };

            // Ask for confirmation before deleting
            // Include label/value details in confirmation like adding option
            let delLblForConfirm = '',
                delValForConfirm = '';
            try {
                const btn = optionsTableBody?.querySelector(`button[onclick="deleteOption(${optionId})"]`);
                const row = btn?.closest('tr');
                const lblInput = row?.querySelector('input[data-name="option_label"]');
                const valInput = row?.querySelector('input[data-name="option_value"]');
                delLblForConfirm = lblInput?.value?.trim() || '';
                delValForConfirm = valInput?.value?.trim() || '';
            } catch (e) {}
            const delMsg = `Are you sure you want to delete this option?\nLabel: ${delLblForConfirm}\nValue: ${delValForConfirm}`;
            showConfirm('Confirm Delete', delMsg, doDelete);
        }

        // Utility escape
        function escapeHtml(str) {
            return String(str).replace(/[&<>"]/g, function(s) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;'
                };
                return map[s] || s;
            });
        }
    </script>
</body>

</html>