<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get user's full name from session, with fallback
$user_fullname = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Guest User';
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar__header">
        <h2 class="sidebar__title"><?php echo htmlspecialchars($user_fullname); ?></h2>
    </div>

    <nav>
        <ul class="nav-menu">
            <li class="nav-menu__item" data-target="main_content_section">
                <a href="./dashboard_main.php" class="nav-menu__link ">
                    <svg class="nav-menu__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="nav-menu__text">Dashboard</span>
                </a>
            </li>
            <li class="nav-menu__item active" data-target="application_management_section">
                <a href="./application_management.php" class="nav-menu__link active">
                    <svg class="nav-menu__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span class="nav-menu__text">Admission Management</span>
                </a>
            </li>
            <li class="nav-menu__item" data-target="scheduling_section">
                <a href="./schedule_management.php" class="nav-menu__link">
                    <svg class="nav-menu__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="nav-menu__text">Exam Schedule Management</span>
                </a>
            </li>
            <li class="nav-menu__item" data-target="exam_permit_generation_section">
                <a href="./exam_permit_management.php" class="nav-menu__link">
                    <svg class="nav-menu__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span class="nav-menu__text">Exam Permit Management</span>
                </a>
            </li>
            <li class="nav-menu__item">
                <a href="services_management.php" class="nav-menu__link">
                    <svg class="nav-menu__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span class="nav-menu__text">Services Management</span>
                </a>
            </li>
            <!-- <li class="nav-menu__item" data-target="settings_section">
                <a href="./dashboard_settings.php" class="nav-menu__link">
                    <svg class="nav-menu__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="nav-menu__text">Settings</span>
                </a>
            </li> -->
            <li class="nav-menu__item nav-menu__logout">
                <a href="./logout.php" class="nav-menu__link nav-menu__logout-link" onclick="return confirm('Are you sure you want to logout?')">
                    <svg class="nav-menu__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span class="nav-menu__text">Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>