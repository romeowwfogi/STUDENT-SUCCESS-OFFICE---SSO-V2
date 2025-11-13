<?php
// Authentication middleware - protect this page
require_once 'middleware/auth.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal Dashboard</title>
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
                        <h2 class="settings-title">Settings</h2>
                    </div>

                    <div class="settings-content">
                        <div class="settings-item">
                            <div class="settings-item__label">
                                <span class="settings-item__text">Change Fullname</span>
                                <span class="settings-item__description">Update the name displayed on your account. This helps ensure your profile information stays accurate and personalized.</span>
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
                                <span class="settings-item__text">Change Email Address</span>
                                <span class="settings-item__description">Modify the email linked to your account. Make sure to use a valid email address to receive important notifications and password recovery options.</span>
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
                                <span class="settings-item__text">Change Password</span>
                                <span class="settings-item__description">Strengthen your account security by setting a new password. Choose a strong, unique password that you don’t use anywhere else.</span>
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

    <!-- Change Fullname Modal -->
    <div id="changeFullnameModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
        <div role="dialog" aria-modal="true" aria-labelledby="changeFullnameTitle" style="background: var(--color-card); border-radius: 20px; max-width: 560px; width: 95%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
            <!-- Close Button -->
            <button type="button" id="closeFullnameModalBtn" style="position: absolute; top: 16px; right: 16px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px;">
                ×
            </button>

            <!-- Modal Header -->
            <div style="padding: 32px 24px 12px 24px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user-round-pen-icon lucide-user-round-pen">
                        <path d="M2 21a8 8 0 0 1 10.821-7.487" />
                        <path d="M21.378 16.626a1 1 0 0 0-3.004-3.004l-4.01 4.012a2 2 0 0 0-.506.854l-.837 2.87a.5.5 0 0 0 .62.62l2.87-.837a2 2 0 0 0 .854-.506z" />
                        <circle cx="10" cy="8" r="5" />
                    </svg>
                </div>
                <h3 id="changeFullnameTitle" style="margin: 0; font-weight: 600;">Change Fullname</h3>
                <p style="margin: 8px 0 0 0; color: var(--color-muted, #718096); font-size: 0.95rem;">Enter your new name details. Fields with <span style="color:#dc2626;">*</span> are required.</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 24px 24px 24px;">
                <form id="changeFullnameForm">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                        <label style="display:flex; flex-direction:column; gap:6px;">
                            <span style="font-weight:500;">First Name <span style="color:#dc2626;">*</span></span>
                            <input type="text" id="first_name" name="first_name" required style="padding: 12px 14px; border: 1px solid var(--color-border); border-radius: 10px; outline: none; background: #fff;" />
                        </label>
                        <label style="display:flex; flex-direction:column; gap:6px;">
                            <span style="font-weight:500;">Middle Name (optional)</span>
                            <input type="text" id="middle_name" name="middle_name" style="padding: 12px 14px; border: 1px solid var(--color-border); border-radius: 10px; outline: none; background: #fff;" />
                        </label>
                        <label style="display:flex; flex-direction:column; gap:6px;">
                            <span style="font-weight:500;">Last Name <span style="color:#dc2626;">*</span></span>
                            <input type="text" id="last_name" name="last_name" required style="padding: 12px 14px; border: 1px solid var(--color-border); border-radius: 10px; outline: none; background: #fff;" />
                        </label>
                        <label style="display:flex; flex-direction:column; gap:6px;">
                            <span style="font-weight:500;">Suffix (optional)</span>
                            <input type="text" id="suffix" name="suffix" placeholder="Jr., Sr., III" style="padding: 12px 14px; border: 1px solid var(--color-border); border-radius: 10px; outline: none; background: #fff;" />
                        </label>
                    </div>
                    <div id="fullnameFormError" style="display:none; margin-top:10px; color:#dc2626; font-size:0.9rem;"></div>
                    <div style="margin-top: 18px; display:flex; gap:10px;">
                        <button type="button" id="cancelFullnameBtn" style="flex:1; padding:12px 16px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight:600; cursor:pointer;">Cancel</button>
                        <button type="submit" id="continueFullnameBtn" style="flex:1; padding:12px 16px; border:none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color:#fff; border-radius:12px; font-weight:600; cursor:pointer;">Continue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Email Modal -->
    <div id="changeEmailModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
        <div role="dialog" aria-modal="true" aria-labelledby="changeEmailTitle" style="background: var(--color-card); border-radius: 20px; max-width: 480px; width: 95%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text); position: relative;">
            <!-- Close Button -->
            <button type="button" id="closeEmailModalBtn" style="position: absolute; top: 16px; right: 16px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px;">×</button>

            <!-- Modal Header -->
            <div style="padding: 32px 24px 12px 24px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 6.5V18a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6.5l10 6 10-6z" />
                    </svg>
                </div>
                <h3 id="changeEmailTitle" style="margin: 0; font-weight: 600;">Change Email Address</h3>
                <p style="margin: 8px 0 0 0; color: var(--color-muted, #718096); font-size: 0.95rem;">Enter your new email. We’ll ask your password to apply changes.</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 24px 24px 24px;">
                <form id="changeEmailForm">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                        <label style="display:flex; flex-direction:column; gap:6px;">
                            <span style="font-weight:500;">New Email <span style="color:#dc2626;">*</span></span>
                            <input type="email" id="new_email" name="new_email" required style="padding: 12px 14px; border: 1px solid var(--color-border); border-radius: 10px; outline: none; background: #fff;" />
                        </label>
                    </div>
                    <div id="emailFormError" style="display:none; margin-top:10px; color:#dc2626; font-size:0.9rem;"></div>
                    <div style="margin-top: 18px; display:flex; gap:10px;">
                        <button type="button" id="cancelEmailBtn" style="flex:1; padding:12px 16px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight:600; cursor:pointer;">Cancel</button>
                        <button type="submit" id="continueEmailBtn" style="flex:1; padding:12px 16px; border:none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color:#fff; border-radius:12px; font-weight:600; cursor:pointer;">Continue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
        <div role="dialog" aria-modal="true" aria-labelledby="changePasswordTitle" style="background: var(--color-card); border-radius: 20px; max-width: 480px; width: 95%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 85vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text); position: relative;">
            <!-- Close Button -->
            <button type="button" id="closeChangePasswordModalBtn" style="position: absolute; top: 16px; right: 16px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px;">×</button>

            <!-- Modal Header -->
            <div style="padding: 32px 24px 12px 24px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="14" x="2" y="8" rx="2" />
                        <path d="M7 8V6a5 5 0 0 1 10 0v2" />
                    </svg>
                </div>
                <h3 id="changePasswordTitle" style="margin: 0; font-weight: 600;">Change Password</h3>
                <p style="margin: 8px 0 0 0; color: var(--color-muted, #718096); font-size: 0.95rem;">Enter a new password and confirm. Requirements: at least 8 characters, include uppercase, lowercase, number, and symbol. We’ll ask your current password to apply changes.</p>
            </div>

            <!-- Modal Body -->
            <div style="padding: 0 24px 24px 24px;">
                <form id="changePasswordForm">
                    <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                        <label style="display:flex; flex-direction:column; gap:6px;">
                            <span style="font-weight:500;">New Password <span style="color:#dc2626;">*</span></span>
                            <div style="position: relative;">
                                <input type="password" id="new_password" name="new_password" required style="padding: 12px 44px 12px 14px; border: 1px solid var(--color-border); border-radius: 10px; outline: none; background: #fff; width: 100%;" />
                                <button type="button" id="toggleNewPassword" aria-label="Show password" style="position:absolute; right:8px; top:50%; transform: translateY(-50%); background: transparent; border: none; color: var(--color-muted, #718096); width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display:flex; align-items:center; justify-content:center;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                            </div>
                        </label>
                        <label style="display:flex; flex-direction:column; gap:6px;">
                            <span style="font-weight:500;">Confirm New Password <span style="color:#dc2626;">*</span></span>
                            <div style="position: relative;">
                                <input type="password" id="confirm_new_password" name="confirm_new_password" required style="padding: 12px 44px 12px 14px; border: 1px solid var(--color-border); border-radius: 10px; outline: none; background: #fff; width: 100%;" />
                                <button type="button" id="toggleConfirmPassword" aria-label="Show password" style="position:absolute; right:8px; top:50%; transform: translateY(-50%); background: transparent; border: none; color: var(--color-muted, #718096); width: 32px; height: 32px; border-radius: 8px; cursor: pointer; display:flex; align-items:center; justify-content:center;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </button>
                            </div>
                        </label>
                    </div>
                    <div id="passwordRequirements" style="margin-top:10px; display:grid; gap:6px; font-size:0.9rem;">
                        <div id="reqLen" data-label="At least 8 characters" style="color:#dc2626;">• At least 8 characters</div>
                        <div id="reqUpper" data-label="Uppercase letter" style="color:#dc2626;">• Uppercase letter</div>
                        <div id="reqLower" data-label="Lowercase letter" style="color:#dc2626;">• Lowercase letter</div>
                        <div id="reqDigit" data-label="Number" style="color:#dc2626;">• Number</div>
                        <div id="reqSymbol" data-label="Symbol" style="color:#dc2626;">• Symbol</div>
                        <div id="reqMatch" data-label="Passwords match" style="color:#dc2626;">• Passwords match</div>
                    </div>
                    <div id="passwordChangeFormError" style="display:none; margin-top:10px; color:#dc2626; font-size:0.9rem;"></div>
                    <div style="margin-top: 18px; display:flex; gap:10px;">
                        <button type="button" id="cancelChangePasswordBtn" style="flex:1; padding:12px 16px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight:600; cursor:pointer;">Cancel</button>
                        <button type="submit" id="continueChangePasswordBtn" style="flex:1; padding:12px 16px; border:none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color:#fff; border-radius:12px; font-weight:600; cursor:pointer;">Continue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Password Confirmation Modal -->
    <div id="passwordConfirmModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3003; align-items: center; justify-content: center; backdrop-filter: blur(4px); overflow-y: auto; padding: 16px;">
        <div role="dialog" aria-modal="true" aria-labelledby="passwordConfirmTitle" style="background: var(--color-card); border-radius: 20px; max-width: 480px; width: 95%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; max-height: 70vh; overflow-y: auto; border: 1px solid var(--color-border); color: var(--color-text);">
            <button type="button" id="closePasswordModalBtn" style="position: absolute; top: 16px; right: 16px; background: rgba(0,0,0,0.05); border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #718096; font-size: 18px;">×</button>
            <div style="padding: 32px 24px 12px 24px; text-align: center;">
                <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 18px; margin: 0 auto 16px auto; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-key-round-icon lucide-key-round">
                        <path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z" />
                        <circle cx="16.5" cy="7.5" r=".5" fill="currentColor" />
                    </svg>
                </div>
                <h3 id="passwordConfirmTitle" style="margin: 0; font-weight: 600;">Confirm with Password</h3>
                <p style="margin: 8px 0 0 0; color: var(--color-muted, #718096); font-size: 0.95rem;">Enter your account password to apply changes.</p>
            </div>
            <div style="padding: 0 24px 24px 24px;">
                <form id="passwordConfirmForm">
                    <label style="display:flex; flex-direction:column; gap:6px;">
                        <span style="font-weight:500;">Password <span style="color:#dc2626;">*</span></span>
                        <div style="position: relative;">
                            <input type="password" id="confirm_password" name="confirm_password" required style="padding: 12px 44px 12px 14px; border: 1px solid var(--color-border); border-radius: 10px; outline: none; background: #fff; width: 100%;" />
                            <button type="button" id="toggleConfirmPasswordBtn" aria-label="Show password" title="Show/Hide password" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border: none; background: transparent; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #718096;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                        </div>
                    </label>
                    <div id="passwordFormError" style="display:none; margin-top:10px; color:#dc2626; font-size:0.9rem;"></div>
                    <div style="margin-top: 18px; display:flex; gap:10px;">
                        <button type="button" id="cancelPasswordBtn" style="flex:1; padding:12px 16px; border: 2px solid var(--color-border); background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight:600; cursor:pointer;">Cancel</button>
                        <button type="submit" id="confirmPasswordBtn" style="flex:1; padding:12px 16px; border:none; background: linear-gradient(135deg, #18a558 0%, #136515 100%); color:#fff; border-radius:12px; font-weight:600; cursor:pointer;">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Status Message Modal (standard design) -->
    <div id="messageModal" style="display: none; position: fixed; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.4); z-index: 3002; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div role="dialog" aria-modal="true" aria-labelledby="messageModalTitle" style="background: var(--color-card); border-radius: 20px; text-align: center; max-width: 420px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.1), 0 8px 25px rgba(0,0,0,0.08); margin: auto; overflow: hidden; border: 1px solid var(--color-border); color: var(--color-text);">
            <div style="padding: 32px 32px 16px 32px;">
                <div id="messageModalIcon" style="width: 56px; height: 56px; background: linear-gradient(135deg, #18a558 0%, #136515 100%); border-radius: 16px; margin: 0 auto 20px auto; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"></path>
                    </svg>
                </div>
                <h3 id="messageModalTitle" style="margin: 0 0 12px 0; color: #1a202c; font-size: 1.5rem; font-weight: 700; letter-spacing: -0.025em;">Notice</h3>
                <p id="messageModalText" style="color: #718096; margin: 0; line-height: 1.6; font-size: 0.95rem;">Message</p>
            </div>
            <div style="padding: 16px 32px 32px 32px; display: flex; gap: 12px; justify-content: center;">
                <button id="messageModalOkBtn" style="flex: 1; padding: 12px 24px; border: none; background: var(--color-card); color: var(--color-text); border-radius: 12px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s ease; outline: none; border: 2px solid var(--color-border);">Close</button>
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
            const openBtn = document.getElementById('openFullnameModalBtn');
            const fullnameModal = document.getElementById('changeFullnameModal');
            const closeFullnameModalBtn = document.getElementById('closeFullnameModalBtn');
            const cancelFullnameBtn = document.getElementById('cancelFullnameBtn');
            const fullnameForm = document.getElementById('changeFullnameForm');
            const fullnameFormError = document.getElementById('fullnameFormError');

            const passwordModal = document.getElementById('passwordConfirmModal');
            const closePasswordModalBtn = document.getElementById('closePasswordModalBtn');
            const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');
            const passwordForm = document.getElementById('passwordConfirmForm');
            const passwordFormError = document.getElementById('passwordFormError');
            const togglePwdBtn = document.getElementById('toggleConfirmPasswordBtn');
            const confirmPasswordInput = document.getElementById('confirm_password');
            let pwdVisible = false;
            if (togglePwdBtn && confirmPasswordInput) {
                const eyeSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" /><circle cx="12" cy="12" r="3" /></svg>';
                const eyeOffSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21 21 0 0 1 5.06-6.08" /><path d="M22 12s-4-8-10-8a10.94 10.94 0 0 0-3.22.5" /><line x1="1" y1="1" x2="23" y2="23"></line><circle cx="12" cy="12" r="3" /></svg>';

                function setPasswordIcon(visible) {
                    togglePwdBtn.innerHTML = visible ? eyeOffSVG : eyeSVG;
                    togglePwdBtn.title = visible ? 'Hide password' : 'Show password';
                    togglePwdBtn.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
                }
                setPasswordIcon(false);
                togglePwdBtn.addEventListener('click', function() {
                    pwdVisible = !pwdVisible;
                    confirmPasswordInput.type = pwdVisible ? 'text' : 'password';
                    setPasswordIcon(pwdVisible);
                });
            }

            const messageModal = document.getElementById('messageModal');
            const messageModalTitle = document.getElementById('messageModalTitle');
            const messageModalText = document.getElementById('messageModalText');
            const messageModalOkBtn = document.getElementById('messageModalOkBtn');
            const messageModalIcon = document.getElementById('messageModalIcon');

            function showModal(el) {
                el.style.display = 'flex';
            }

            function hideModal(el) {
                el.style.display = 'none';
            }

            function showMessage(title, text, kind) {
                messageModalTitle.textContent = title || (kind === 'success' ? 'Success' : 'Error');
                messageModalText.textContent = text || '';
                // icon + color swap by kind
                if (messageModalIcon) {
                    if (kind === 'success') {
                        messageModalIcon.style.background = 'linear-gradient(135deg, #18a558 0%, #136515 100%)';
                        messageModalIcon.innerHTML = '<svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"></path></svg>';
                    } else {
                        messageModalIcon.style.background = 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)';
                        messageModalIcon.innerHTML = '<svg style="width: 28px; height: 28px; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>';
                    }
                }
                showModal(messageModal);
            }

            function showLoader() {
                var loader = document.getElementById('loadingOverlay');
                if (loader) {
                    // ensure loader sits top-most
                    document.body.appendChild(loader);
                    loader.style.display = 'flex';
                }
            }

            function hideLoader() {
                var loader = document.getElementById('loadingOverlay');
                if (loader) loader.style.display = 'none';
            }

            function getTrimmedValue(id) {
                return (document.getElementById(id).value || '').trim();
            }

            // Email modal controls (declare before use)
            const openEmailModalBtn = document.getElementById('openEmailModalBtn');
            const emailModal = document.getElementById('changeEmailModal');
            const closeEmailModalBtn = document.getElementById('closeEmailModalBtn');
            const cancelEmailBtn = document.getElementById('cancelEmailBtn');
            const emailForm = document.getElementById('changeEmailForm');
            const emailFormError = document.getElementById('emailFormError');

            // Change password modal controls (declare before use)
            const openPasswordModalBtn = document.getElementById('openPasswordModalBtn');
            const changePasswordModal = document.getElementById('changePasswordModal');
            const closeChangePasswordModalBtn = document.getElementById('closeChangePasswordModalBtn');
            const cancelChangePasswordBtn = document.getElementById('cancelChangePasswordBtn');
            const changePasswordForm = document.getElementById('changePasswordForm');
            const passwordChangeFormError = document.getElementById('passwordChangeFormError');
            const newPasswordInput = document.getElementById('new_password');
            const confirmNewPasswordInput = document.getElementById('confirm_new_password');
            const reqLen = document.getElementById('reqLen');
            const reqUpper = document.getElementById('reqUpper');
            const reqLower = document.getElementById('reqLower');
            const reqDigit = document.getElementById('reqDigit');
            const reqSymbol = document.getElementById('reqSymbol');
            const reqMatch = document.getElementById('reqMatch');
            const toggleNewPasswordBtn = document.getElementById('toggleNewPassword');
            const toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');

            function updateReqVisual(el, ok) {
                if (!el) return;
                el.style.color = ok ? '#18a558' : '#dc2626';
                var label = (el.dataset && el.dataset.label) ? el.dataset.label : el.textContent.replace(/^✓\s|•\s/, '');
                el.textContent = (ok ? '✓ ' : '• ') + label;
            }

            function refreshPasswordRequirements() {
                var pwd = newPasswordInput ? newPasswordInput.value : '';
                var confirmPwd = confirmNewPasswordInput ? confirmNewPasswordInput.value : '';
                var lenOK = pwd.length >= 8;
                var upperOK = /[A-Z]/.test(pwd);
                var lowerOK = /[a-z]/.test(pwd);
                var digitOK = /\d/.test(pwd);
                var symbolOK = /[^A-Za-z0-9]/.test(pwd);
                var matchOK = pwd.length > 0 && confirmPwd.length > 0 && pwd === confirmPwd;
                updateReqVisual(reqLen, lenOK);
                updateReqVisual(reqUpper, upperOK);
                updateReqVisual(reqLower, lowerOK);
                updateReqVisual(reqDigit, digitOK);
                updateReqVisual(reqSymbol, symbolOK);
                updateReqVisual(reqMatch, matchOK);
            }

            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    passwordChangeFormError.style.display = 'none';
                    refreshPasswordRequirements();
                });
            }
            if (confirmNewPasswordInput) {
                confirmNewPasswordInput.addEventListener('input', function() {
                    passwordChangeFormError.style.display = 'none';
                    refreshPasswordRequirements();
                });
            }
            refreshPasswordRequirements();

            // Show/Hide password toggles
            // Eye icons for show/hide
            const eyeIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z" /><circle cx="12" cy="12" r="3" /></svg>';
            const eyeOffIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20C5 20 1 12 1 12a21.77 21.77 0 0 1 7.06-7.06"/><path d="M10.73 5.08A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.77 21.77 0 0 1-3.4 4.65"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

            function toggleVisibility(inputEl, btnEl) {
                if (!inputEl || !btnEl) return;
                const isPassword = inputEl.type === 'password';
                inputEl.type = isPassword ? 'text' : 'password';
                btnEl.innerHTML = isPassword ? eyeOffIcon : eyeIcon;
                btnEl.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
            }
            toggleNewPasswordBtn && toggleNewPasswordBtn.addEventListener('click', function() {
                toggleVisibility(newPasswordInput, toggleNewPasswordBtn);
            });
            toggleConfirmPasswordBtn && toggleConfirmPasswordBtn.addEventListener('click', function() {
                toggleVisibility(confirmNewPasswordInput, toggleConfirmPasswordBtn);
            });

            // Open fullname modal
            openBtn && openBtn.addEventListener('click', function() {
                showModal(fullnameModal);
            });

            // Open email modal
            openEmailModalBtn && openEmailModalBtn.addEventListener('click', function() {
                showModal(emailModal);
            });

            // Open change password modal
            openPasswordModalBtn && openPasswordModalBtn.addEventListener('click', function() {
                showModal(changePasswordModal);
            });

            // Close actions
            closeFullnameModalBtn && closeFullnameModalBtn.addEventListener('click', function() {
                hideModal(fullnameModal);
            });
            cancelFullnameBtn && cancelFullnameBtn.addEventListener('click', function() {
                hideModal(fullnameModal);
            });
            fullnameModal && fullnameModal.addEventListener('click', function(e) {
                if (e.target === fullnameModal) {
                    hideModal(fullnameModal);
                }
            });

            // Close email modal actions
            closeEmailModalBtn && closeEmailModalBtn.addEventListener('click', function() {
                hideModal(emailModal);
            });
            cancelEmailBtn && cancelEmailBtn.addEventListener('click', function() {
                hideModal(emailModal);
            });
            emailModal && emailModal.addEventListener('click', function(e) {
                if (e.target === emailModal) {
                    hideModal(emailModal);
                }
            });

            // Close change password modal actions
            closeChangePasswordModalBtn && closeChangePasswordModalBtn.addEventListener('click', function() {
                hideModal(changePasswordModal);
            });
            cancelChangePasswordBtn && cancelChangePasswordBtn.addEventListener('click', function() {
                hideModal(changePasswordModal);
            });
            changePasswordModal && changePasswordModal.addEventListener('click', function(e) {
                if (e.target === changePasswordModal) {
                    hideModal(changePasswordModal);
                }
            });

            closePasswordModalBtn && closePasswordModalBtn.addEventListener('click', function() {
                hideModal(passwordModal);
            });
            cancelPasswordBtn && cancelPasswordBtn.addEventListener('click', function() {
                hideModal(passwordModal);
            });
            passwordModal && passwordModal.addEventListener('click', function(e) {
                if (e.target === passwordModal) {
                    hideModal(passwordModal);
                }
            });

            messageModalOkBtn && messageModalOkBtn.addEventListener('click', function() {
                hideModal(messageModal);
            });
            messageModal && messageModal.addEventListener('click', function(e) {
                if (e.target === messageModal) {
                    hideModal(messageModal);
                }
            });

            // Step 1a: validate fullname and proceed to password modal
            fullnameForm && fullnameForm.addEventListener('submit', function(e) {
                e.preventDefault();
                fullnameFormError.style.display = 'none';
                const first = getTrimmedValue('first_name');
                const middle = getTrimmedValue('middle_name');
                const last = getTrimmedValue('last_name');
                const suffix = getTrimmedValue('suffix');

                if (!first || !last) {
                    fullnameFormError.textContent = 'First name and Last name are required.';
                    fullnameFormError.style.display = 'block';
                    return;
                }

                // stash values for submit
                passwordModal.dataset.payload = JSON.stringify({
                    action: 'fullname',
                    first_name: first,
                    middle_name: middle,
                    last_name: last,
                    suffix: suffix
                });
                hideModal(fullnameModal);
                showModal(passwordModal);
            });

            // Step 1b: validate email and proceed to password modal
            emailForm && emailForm.addEventListener('submit', function(e) {
                e.preventDefault();
                emailFormError.style.display = 'none';
                const email = getTrimmedValue('new_email');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!email) {
                    emailFormError.textContent = 'Email is required.';
                    emailFormError.style.display = 'block';
                    return;
                }
                if (!emailRegex.test(email)) {
                    emailFormError.textContent = 'Please enter a valid email address.';
                    emailFormError.style.display = 'block';
                    return;
                }
                passwordModal.dataset.payload = JSON.stringify({
                    action: 'email',
                    email: email
                });
                hideModal(emailModal);
                showModal(passwordModal);
            });

            // Step 1c: validate new password and proceed to password modal
            changePasswordForm && changePasswordForm.addEventListener('submit', function(e) {
                e.preventDefault();
                passwordChangeFormError.style.display = 'none';
                const newPwd = getTrimmedValue('new_password');
                const confirmNewPwd = getTrimmedValue('confirm_new_password');
                if (!newPwd || !confirmNewPwd) {
                    passwordChangeFormError.textContent = 'Both password fields are required.';
                    passwordChangeFormError.style.display = 'block';
                    return;
                }
                if (newPwd.length < 8) {
                    passwordChangeFormError.textContent = 'Password must be at least 8 characters.';
                    passwordChangeFormError.style.display = 'block';
                    return;
                }
                const hasUpper = /[A-Z]/.test(newPwd);
                const hasLower = /[a-z]/.test(newPwd);
                const hasDigit = /\d/.test(newPwd);
                const hasSymbol = /[^A-Za-z0-9]/.test(newPwd);
                if (!(hasUpper && hasLower && hasDigit && hasSymbol)) {
                    passwordChangeFormError.textContent = 'Include uppercase, lowercase, number, and symbol.';
                    passwordChangeFormError.style.display = 'block';
                    return;
                }
                if (newPwd !== confirmNewPwd) {
                    passwordChangeFormError.textContent = 'Passwords do not match.';
                    passwordChangeFormError.style.display = 'block';
                    return;
                }
                passwordModal.dataset.payload = JSON.stringify({
                    action: 'password',
                    new_password: newPwd
                });
                hideModal(changePasswordModal);
                showModal(passwordModal);
            });

            // Step 2: confirm password and submit to server (handles fullname or email)
            passwordForm && passwordForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                passwordFormError.style.display = 'none';
                const pwd = getTrimmedValue('confirm_password');
                if (!pwd) {
                    passwordFormError.textContent = 'Password is required.';
                    passwordFormError.style.display = 'block';
                    return;
                }

                let payload = {};
                try {
                    payload = JSON.parse(passwordModal.dataset.payload || '{}');
                } catch {
                    payload = {};
                }
                const action = payload.action || 'fullname';
                let endpoint = '';
                let body = null;
                if (action === 'fullname') {
                    if (!payload.first_name || !payload.last_name) {
                        showMessage('Error', 'Missing name details. Please try again.', 'error');
                        return;
                    }
                    endpoint = 'update_fullname.php';
                    body = new URLSearchParams({
                        first_name: payload.first_name,
                        middle_name: payload.middle_name || '',
                        last_name: payload.last_name,
                        suffix: payload.suffix || '',
                        password: pwd
                    });
                } else if (action === 'email') {
                    if (!payload.email) {
                        showMessage('Error', 'Missing email details. Please try again.', 'error');
                        return;
                    }
                    endpoint = 'update_email.php';
                    body = new URLSearchParams({
                        email: payload.email,
                        password: pwd
                    });
                } else if (action === 'password') {
                    if (!payload.new_password) {
                        showMessage('Error', 'Missing new password details. Please try again.', 'error');
                        return;
                    }
                    endpoint = 'update_password.php';
                    body = new URLSearchParams({
                        new_password: payload.new_password,
                        password: pwd
                    });
                } else {
                    showMessage('Error', 'Unknown action. Please try again.', 'error');
                    return;
                }

                try {
                    showLoader();
                    const res = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body
                    });
                    const data = await res.json();
                    if (data && data.success) {
                        hideLoader();
                        hideModal(passwordModal);
                        if (action === 'fullname') {
                            showMessage('Fullname Updated', 'Your name has been updated successfully.', 'success');
                            // Update sidebar title if present
                            const sidebarTitle = document.querySelector('.sidebar__title');
                            if (sidebarTitle && data.updated_name) {
                                sidebarTitle.textContent = data.updated_name;
                            }
                        } else if (action === 'email') {
                            showMessage('Email Updated', 'Your email has been updated successfully.', 'success');
                        } else if (action === 'password') {
                            showMessage('Password Updated', 'Your password has been updated successfully.', 'success');
                        }
                        // Clear password field
                        document.getElementById('confirm_password').value = '';
                    } else {
                        hideLoader();
                        passwordFormError.textContent = (data && data.error) ? data.error : 'Incorrect password or server error.';
                        passwordFormError.style.display = 'block';
                    }
                } catch (err) {
                    hideLoader();
                    passwordFormError.textContent = 'Network error. Please try again.';
                    passwordFormError.style.display = 'block';
                }
            });
        })();
    </script>
</body>

</html>