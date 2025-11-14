<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Success Office - System Settings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="dashboard.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

</head>

<body>
    <!-- Layout Container -->
    <div class="layout">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Settings Section -->
            <section class="section active" id="settings_section">
                <div class="settings-container">
                    <div class="settings-header">
                        <h2 class="settings-title">System Settings</h2>
                    </div>

                    <div class="settings-content">
                        <div class="settings-item">
                            <div class="settings-item__label">
                                <span class="settings-item__text">Admission Prediction</span>
                                <span class="settings-item__description">Upload .csv with the required columns to train and instantly predict the next year.<br>
                                    <b>Required Columns:</b>
                                    Year, Unemployment_Rate, Num_Competing_Schools,
                                    Applicants_Business_Accountancy, Applicants_Nursing, Applicants_Tuition_Based,
                                    Applicants_Hospitality, Applicants_Engineering, Applicants_Computer_Studies,
                                    Applicants_Education, Applicants_Within_City, Applicants_Outside_City,
                                    Admission_Rate_Last_Year, Total_Admitted
                                </span>
                            </div>
                            <div class="settings-item__control">
                                <button class="btn btn--icon" id="openAdmissionPredictionModalBtn" aria-label="Admission Prediction">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right">
                                        <path d="m9 18 6-6-6-6" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="settings-item" onclick="window.location.href = 'officer_management.php';">
                            <div class="settings-item__label">
                                <span class="settings-item__text">Officer Management</span>
                                <span class="settings-item__description">Manage officer information efficiently, including profiles, roles, and permissions. This module helps streamline administrative oversight and maintain accurate personnel records.</span>
                            </div>
                            <div class="settings-item__control">
                                <button class="btn btn--icon" id="openFullnameModalBtn" aria-label="Change fullname">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right">
                                        <path d="m9 18 6-6-6-6" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- <div class="settings-item">
                            <div class="settings-item__label">
                                <span class="settings-item__text">Expiration Configuration</span>
                                <span class="settings-item__description">Configure and manage expiration time limits for registration verification links, OTPs, and password reset links. This ensures enhanced security by preventing the use of expired credentials and maintaining system integrity.</span>
                            </div>
                            <div class="settings-item__control">
                                <button class="btn btn--icon" id="openFullnameModalBtn" aria-label="Change fullname">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right">
                                        <path d="m9 18 6-6-6-6" />
                                    </svg>
                                </button>
                            </div>
                        </div> -->

                        <!-- <div class="settings-item" onclick="window.location.href = 'faqs_management.php';">
                            <div class="settings-item__label">
                                <span class="settings-item__text">Email Template Management</span>
                                <span class="settings-item__description">Create, edit, and organize email templates for consistent communication. This feature ensures standardized messaging for admissions, notifications, and service updates.</span>
                            </div>
                            <div class="settings-item__control">
                                <button class="btn btn--icon" id="openEmailModalBtn" aria-label="Change email">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right">
                                        <path d="m9 18 6-6-6-6" />
                                    </svg>
                                </button>
                            </div>
                        </div> -->

                        <div class="settings-item" onclick="window.location.href = 'faqs_management.php';">
                            <div class="settings-item__label">
                                <span class="settings-item__text">FAQ's Management</span>
                                <span class="settings-item__description">Manage and organize frequently asked questions with ease. This module helps keep information clear, accessible, and up to date for users.</span>
                            </div>
                            <div class="settings-item__control">
                                <button class="btn btn--icon" id="openEmailModalBtn" aria-label="Change email">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right">
                                        <path d="m9 18 6-6-6-6" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="settings-item" onclick="window.location.href = 'demo_video_management.php';">
                            <div class="settings-item__label">
                                <span class="settings-item__text">Demo Video Management</span>
                                <span class="settings-item__description">Manage demo content and configurations efficiently. This module helps organize demonstrations, track versions, and ensure accurate, up-to-date materials for presentations or testing.</span>
                            </div>
                            <div class="settings-item__control">
                                <button class="btn btn--icon" id="openEmailModalBtn" aria-label="Change email">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right">
                                        <path d="m9 18 6-6-6-6" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="settings-item" onclick="window.location.href = 'contact_details.php';">
                            <div class="settings-item__label">
                                <span class="settings-item__text">Contact Details</span>
                                <span class="settings-item__description">Maintain and organize contact information efficiently. This module ensures accurate, accessible records to support effective communication and streamlined operations.</span>
                            </div>
                            <div class="settings-item__control">
                                <button class="btn btn--icon" id="openEmailModalBtn" aria-label="Change email">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right">
                                        <path d="m9 18 6-6-6-6" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- <div class="settings-item">
                            <div class="settings-item__label">
                                <span class="settings-item__text">Privacy Policy</span>
                                <span class="settings-item__description">Configure and manage platform’s privacy policy with ease. This module helps ensure clear, compliant, and up-to-date privacy information for users.</span>
                            </div>
                            <div class="settings-item__control">
                                <button class="btn btn--icon" id="openEmailModalBtn" aria-label="Change email">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right">
                                        <path d="m9 18 6-6-6-6" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="settings-item">
                            <div class="settings-item__label">
                                <span class="settings-item__text">Terms and Conditions</span>
                                <span class="settings-item__description">Create and manage your platform’s terms and conditions efficiently. This module helps ensure clear, compliant, and up-to-date usage guidelines for users.</span>
                            </div>
                            <div class="settings-item__control">
                                <button class="btn btn--icon" id="openEmailModalBtn" aria-label="Change email">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right">
                                        <path d="m9 18 6-6-6-6" />
                                    </svg>
                                </button>
                            </div>
                        </div> -->

                        <div class="settings-item" onclick="window.location.href = 'admission_status_remarks.php';">
                            <div class="settings-item__label">
                                <span class="settings-item__text">Admission Status-Remarks Management</span>
                                <span class="settings-item__description">Track and update admission statuses with detailed remarks. This allows for transparent communication and easy monitoring of application progress.</span>
                            </div>
                            <div class="settings-item__control">
                                <button class="btn btn--icon" id="openPasswordModalBtn" aria-label="Change password">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right">
                                        <path d="m9 18 6-6-6-6" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="settings-item" onclick="window.location.href = 'services_status_remarks.php';">
                            <div class="settings-item__label">
                                <span class="settings-item__text">Services Status-Remarks Management</span>
                                <span class="settings-item__description">Monitor and update the status of various services along with remarks. This feature supports better service tracking, accountability, and communication with stakeholders.</span>
                            </div>
                            <div class="settings-item__control">
                                <button class="btn btn--icon" id="openPasswordModalBtn" aria-label="Change password">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right">
                                        <path d="m9 18 6-6-6-6" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="settings-item" onclick="window.location.href = 'link_management.php';">
                            <div class="settings-item__label">
                                <span class="settings-item__text">Link Management</span>
                                <span class="settings-item__description">Organize and maintain important links with ease. This module helps ensure quick access, proper categorization, and accurate updates for all essential URLs.</span>
                            </div>
                            <div class="settings-item__control">
                                <button class="btn btn--icon" id="openPasswordModalBtn" aria-label="Change password">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right">
                                        <path d="m9 18 6-6-6-6" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <script src="./src/Javascript/dahsboard.js"></script>

    <!-- Admission Prediction Modal -->
    <div id="admissionPredictionModal" style="display:none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3500; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
        <div style="background: #ffffff; border-radius: 16px; width: 96%; max-width: 560px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div style="display: flex; align-items: center; gap: 12px; padding: 24px 24px 0 24px;">
                <div style="width: 48px; height: 48px; border-radius: 12px; display: grid; place-items: center; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18" />
                        <path d="M7 14l4-4 4 4 4-4" />
                    </svg>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.25rem; color: #1a202c;">Admission Prediction</h3>
                    <p style="margin: 6px 0 0 0; color: #4a5568;">Upload a .csv file with the required columns to train and predict next year.</p>
                </div>
                <button type="button" id="closeAdmissionPredictionModal" style="margin-left: auto; background: transparent; border: none; cursor: pointer; color: #718096;" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div style="padding: 0 24px 24px 24px;">
                <form id="admissionPredictionForm" enctype="multipart/form-data" style="display:flex; flex-direction: column; gap: 14px;">
                    <div>
                        <label for="predictionCsv" style="display:block; font-weight: 600; color: #2d3748; margin-bottom: 8px;">Upload CSV</label>
                        <input type="file" id="predictionCsv" name="file" accept=".csv,text/csv" style="width:100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px;" />
                        <small style="color:#718096; display:block; margin-top:8px;">File must be a CSV and include the required columns.</small>
                        <div id="predictionError" style="display:none; margin-top:8px; padding:10px; background:#fff5f5; color:#b91c1c; border:1px solid #fecaca; border-radius:8px;">Validation error.</div>
                    </div>
                    <div style="display:flex; gap:10px; justify-content:flex-end;">
                        <button type="button" id="startPredictionBtn" style="padding: 12px 16px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color:#fff; border:none; border-radius:10px; font-weight:600; cursor:pointer;">Train & Predict</button>
                    </div>
                </form>
                <div id="predictionResultWrap" style="display:none; margin-top: 12px; padding: 16px; border: 1px solid #e2e8f0; border-radius: 12px; background:#f8fafc;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color: white;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <div style="font-weight:700; color:#1a202c;">Prediction Result</div>
                            <div id="predictionResultText" style="color:#4a5568;">Ready.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loader Overlay (match existing pages) -->
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
    <script>
        (function() {
            function showLoader() {
                var ov = document.getElementById('loadingOverlay');
                if (ov) ov.style.display = 'flex';
            }

            function hideLoader() {
                var ov = document.getElementById('loadingOverlay');
                if (ov) ov.style.display = 'none';
            }

            var openBtn = document.getElementById('openAdmissionPredictionModalBtn');
            var modal = document.getElementById('admissionPredictionModal');
            var closeBtn = document.getElementById('closeAdmissionPredictionModal');
            var startBtn = document.getElementById('startPredictionBtn');
            var fileInput = document.getElementById('predictionCsv');
            var errBox = document.getElementById('predictionError');
            var resultWrap = document.getElementById('predictionResultWrap');
            var resultText = document.getElementById('predictionResultText');

            function openModal() {
                if (modal) modal.style.display = 'flex';
            }

            function closeModal() {
                if (modal) modal.style.display = 'none';
            }

            openBtn && openBtn.addEventListener('click', openModal);
            closeBtn && closeBtn.addEventListener('click', closeModal);
            modal && modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });

            function validateCsvFile(file) {
                if (!file) return 'Please select a CSV file.';
                var nameOk = /\.csv$/i.test(file.name);
                var typeOk = (file.type || '').includes('csv') || (file.type || '') === 'application/vnd.ms-excel' || (file.type || '') === '';
                if (!nameOk) return 'File must have .csv extension.';
                if (!typeOk) return 'File must be a CSV.';
                if (file.size <= 0) return 'File cannot be empty.';
                return '';
            }

            async function validateHeaders(file) {
                return new Promise(function(resolve) {
                    try {
                        var reader = new FileReader();
                        reader.onload = function() {
                            var text = String(reader.result || '');
                            var firstLine = text.split(/\r?\n/)[0] || '';
                            if (!firstLine) {
                                resolve('CSV missing header row.');
                                return;
                            }
                            var headers = firstLine.split(',').map(function(h) {
                                return h.trim();
                            });
                            var required = [
                                'Year', 'Unemployment_Rate', 'Num_Competing_Schools',
                                'Applicants_Business_Accountancy', 'Applicants_Nursing', 'Applicants_Tuition_Based',
                                'Applicants_Hospitality', 'Applicants_Engineering', 'Applicants_Computer_Studies',
                                'Applicants_Education', 'Applicants_Within_City', 'Applicants_Outside_City',
                                'Admission_Rate_Last_Year', 'Total_Admitted'
                            ];
                            var missing = required.filter(function(col) {
                                return !headers.includes(col);
                            });
                            if (missing.length) resolve('Missing required columns: ' + missing.join(', '));
                            else resolve('');
                        };
                        reader.onerror = function() {
                            resolve('Unable to read CSV for validation.');
                        };
                        reader.readAsText(file);
                    } catch (_) {
                        resolve('Unable to validate CSV headers.');
                    }
                });
            }

            async function startPrediction() {
                errBox && (errBox.style.display = 'none');
                resultWrap && (resultWrap.style.display = 'none');
                resultText && (resultText.textContent = '');

                var file = fileInput && fileInput.files && fileInput.files[0];
                var basicErr = validateCsvFile(file);
                if (basicErr) {
                    errBox.textContent = basicErr;
                    errBox.style.display = 'block';
                    return;
                }

                var headerErr = await validateHeaders(file);
                if (headerErr) {
                    errBox.textContent = headerErr;
                    errBox.style.display = 'block';
                    return;
                }

                var formData = new FormData();
                formData.append('file', file, file.name || 'dataset.csv');

                try {
                    showLoader();
                    var resp = await fetch('train_predict_proxy.php', {
                        method: 'POST',
                        body: formData,
                        mode: 'cors',
                        credentials: 'omit'
                    });

                    var ok = resp.ok;
                    var contentType = resp.headers.get('content-type') || '';
                    if (!ok) {
                        var text = '';
                        try {
                            text = await resp.text();
                        } catch (_) {}
                        throw new Error(text || ('Request failed with status ' + resp.status));
                    }
                    var data;
                    if (contentType.includes('application/json')) {
                        data = await resp.json();
                    } else {
                        var txt = await resp.text();
                        try {
                            data = JSON.parse(txt);
                        } catch (_) {
                            throw new Error('Unexpected response format');
                        }
                    }
                    hideLoader();
                    if (data && typeof data.total_admitted_prediction !== 'undefined' && data.year) {
                        resultText.textContent = 'Total Admitted Prediction: ' + Number(data.total_admitted_prediction).toFixed(2) + ' • Year: ' + String(data.year);
                        resultWrap.style.display = 'block';
                    } else {
                        throw new Error('Missing expected fields in response');
                    }
                } catch (err) {
                    hideLoader();
                    errBox.textContent = (err && err.message) ? err.message : 'Request failed. Please try again.';
                    errBox.style.display = 'block';
                }
            }

            startBtn && startBtn.addEventListener('click', startPrediction);
        })();
    </script>
</body>

</html>