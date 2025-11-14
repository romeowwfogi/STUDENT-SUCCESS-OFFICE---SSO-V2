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

                        <div class="settings-item">
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
                        </div>

                        <div class="settings-item">
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
                        </div>

                        <div class="settings-item">
                            <div class="settings-item__label">
                                <span class="settings-item__text">FAQ's Management</span>
                                <span class="settings-item__description">Create, edit, and organize email templates for consistent communication. This feature ensures standardized messaging for admissions, notifications, and service updates.</span>
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
                                <span class="settings-item__text">Contact Details</span>
                                <span class="settings-item__description">Create, edit, and organize email templates for consistent communication. This feature ensures standardized messaging for admissions, notifications, and service updates.</span>
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
                                <span class="settings-item__text">Privacy Policy</span>
                                <span class="settings-item__description">Create, edit, and organize email templates for consistent communication. This feature ensures standardized messaging for admissions, notifications, and service updates.</span>
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
                                <span class="settings-item__description">Create, edit, and organize email templates for consistent communication. This feature ensures standardized messaging for admissions, notifications, and service updates.</span>
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

                        <div class="settings-item">
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

                        <div class="settings-item">
                            <div class="settings-item__label">
                                <span class="settings-item__text">Link Management</span>
                                <span class="settings-item__description">Manage and organize important links in one centralized location. This module simplifies access to frequently used resources and ensures proper linkage across systems.</span>
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
</body>

</html>