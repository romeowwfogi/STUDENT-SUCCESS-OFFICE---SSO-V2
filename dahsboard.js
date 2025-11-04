// ==================== MAIN.JS - Dashboard Functionality ====================



// ==================== navigation js ====================
// Select all nav items and all sections
// highlight active link in navbar or sidebar
document.addEventListener("DOMContentLoaded", () => {
    const currentPage = window.location.pathname.split("/").pop();
    const navLinks = document.querySelectorAll(".nav-menu__link");

    navLinks.forEach(link => {
        const linkPage = link.getAttribute("href").split("/").pop();
        if (linkPage === currentPage) {
            link.classList.add("active");
        } else {
            link.classList.remove("active");
        }
    });
});


// ==================== navigation js end====================



// ==================== button filter container====================
// Select elements for Exam Permits section
const filterButton = document.querySelector('.button.filter');
const filterPopup = document.getElementById('filterPopup');
const closeFilter = document.getElementById('closeFilter');

// Open the modal when clicking the Filter button
if (filterButton && filterPopup) {
    filterButton.addEventListener('click', () => {
        filterPopup.style.display = 'flex'; // flex to center modal
    });
}

// Close the modal when clicking the Close button
if (closeFilter && filterPopup) {
    closeFilter.addEventListener('click', () => {
        filterPopup.style.display = 'none';
    });
}

// Optional: Close modal when clicking outside the modal content
if (filterPopup) {
    window.addEventListener('click', (e) => {
        if (e.target === filterPopup) {
            filterPopup.style.display = 'none';
        }
    });
}

// ==================== Application Management Filter ====================
const filterButtonApplications = document.querySelector('#application_management_section .button.filter');
const filterPopupApplications = document.getElementById('filterPopupApplications');
const closeFilterApplications = document.getElementById('closeFilterApplications');

if (filterButtonApplications && filterPopupApplications) {
    filterButtonApplications.addEventListener('click', () => {
        filterPopupApplications.style.display = 'flex';
    });
}

if (closeFilterApplications && filterPopupApplications) {
    closeFilterApplications.addEventListener('click', () => {
        filterPopupApplications.style.display = 'none';
    });
}

if (filterPopupApplications) {
    window.addEventListener('click', (e) => {
        if (e.target === filterPopupApplications) {
            filterPopupApplications.style.display = 'none';
        }
    });
}

// ==================== Scheduling Filter ====================
const filterButtonScheduling = document.querySelector('#scheduling_section .button.filter');
const filterPopupScheduling = document.getElementById('filterPopupScheduling');
const closeFilterScheduling = document.getElementById('closeFilterScheduling');

if (filterButtonScheduling && filterPopupScheduling) {
    filterButtonScheduling.addEventListener('click', () => {
        filterPopupScheduling.style.display = 'flex';
    });
}

if (closeFilterScheduling && filterPopupScheduling) {
    closeFilterScheduling.addEventListener('click', () => {
        filterPopupScheduling.style.display = 'none';
    });
}

if (filterPopupScheduling) {
    window.addEventListener('click', (e) => {
        if (e.target === filterPopupScheduling) {
            filterPopupScheduling.style.display = 'none';
        }
    });
}


/**
 * Enhanced sidebar toggle functionality with smooth transitions
 * Includes auto-close on outside click and proper main content expansion
 */
// ==================== SIDEBAR TOGGLE ====================
const sidebar = document.getElementById('sidebar');
const mainContent = document.querySelector('.main-content');
const sidebarToggle = document.getElementById('sidebarToggle');
const mobileMenuToggle = document.getElementById('mobileMenuToggle');

// Function to close sidebar (for both desktop and mobile)
function closeSidebar() {
    // Keep sidebar open at all times
    localStorage.setItem('sidebarCollapsed', 'false');
}

// Function to open sidebar
function openSidebar() {
    if (!sidebar) return; // Guard for pages without sidebar
    if (window.innerWidth > 640) {
        sidebar.classList.remove('collapsed');
        if (mainContent) {
            mainContent.classList.remove('expanded');
        }
        localStorage.setItem('sidebarCollapsed', 'false');
    } else {
        sidebar.classList.add('mobile-open');
    }
}

// Mobile toggle
if (mobileMenuToggle) {
    mobileMenuToggle.addEventListener('click', (e) => {
        e.stopPropagation(); // Prevent event bubbling
        // Always open on mobile
        openSidebar();
    });
}

// Ensure sidebar stays open; disable auto-close behavior

// Prevent sidebar from closing when clicking inside it
if (sidebar) {
    sidebar.addEventListener('click', (e) => {
        e.stopPropagation();
    });
}

// Load sidebar state from localStorage
localStorage.setItem('sidebarCollapsed', 'false');
// Force open on initial load
document.addEventListener('DOMContentLoaded', () => {
    openSidebar();
});

// Responsive auto-reset
window.addEventListener('resize', () => {
    // Keep sidebar open across breakpoints
    openSidebar();
});






// ==================== Table Button Functionality sa permist to  ====================
const sendButtons = document.querySelectorAll('.sendbtn');// inuutusan natin na kuhainang may mga class name na .sendbtn

sendButtons.forEach(button => {//sinasabi natin sa bawat button na icliclik ay 
    button.addEventListener('click', () => {

        const row = button.closest('tr');//hahanapin niya yung pinaka malapit na tr or cnatiner nung button nayon 
        const status_badge = row.querySelector('.status').textContent; //ito diniclear natin para matawag yung mga buttons
        const show_view_button = row.querySelector('.showViewbtn');
        const show_Resend_button = row.querySelector('.showResendbtn');

        if (status_badge === "Failed") {//pag ststus niya ay failed mag shshsow up lang is resend
            show_view_button.style.display = "none";
            show_Resend_button.style.display = "block";
            button.style.display = "none";
        }
        else if (status_badge === "Sent") {//pag ststus niya ay failed mag shshsow up lang is Sview
            show_view_button.style.display = "block";
            show_Resend_button.style.display = "block";
            button.style.display = "none";
        }
    });
});


/**
 * Enhanced theme functionality with toggle button
 * Replaces dropdown with simple light/dark toggle
 */
const themeToggle = document.getElementById('themeToggle');
const mobileThemeToggle = document.getElementById('mobileThemeToggle');
const settingsThemeToggle = document.getElementById('settingsThemeToggle');
const html = document.documentElement;

// Force light theme globally
try { localStorage.removeItem('theme'); } catch (_) {}
html.setAttribute('data-theme', 'light');

// Apply initial theme (always light) and update icons
applyTheme('light');

function applyTheme(_) {
    html.setAttribute('data-theme', 'light');
    try { localStorage.setItem('theme', 'light'); } catch (_) {}
    updateThemeToggleIcons();
}

function toggleTheme() {
    // Disabled: keep light theme
    return;
}

// Update toggle button icons to reflect current theme
function updateThemeToggleIcons() {
    const isDark = false; // always light

    // Update desktop toggle icon
    if (themeToggle) {
        const icon = themeToggle.querySelector('svg path');
        if (icon) {
            // Sun icon to indicate light mode
            icon.setAttribute('d', 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z');
        }
    }

    // Update mobile toggle icon
    if (mobileThemeToggle) {
        const icon = mobileThemeToggle.querySelector('svg path');
        if (icon) {
            // Sun icon to indicate light mode
            icon.setAttribute('d', 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z');
        }
    }

    // Update settings toggle icon
    if (settingsThemeToggle) {
        const icon = settingsThemeToggle.querySelector('svg path');
        if (icon) {
            if (isDark) {
                // Sun icon for dark mode (to switch to light)
                icon.setAttribute('d', 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z');
            } else {
                // Moon icon for light mode (to switch to dark)
                icon.setAttribute('d', 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z');
            }
        }
    }
}

// Listen for system theme changes
// Disable system theme change handling
try {
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    if (mq && mq.removeEventListener) mq.removeEventListener('change', () => {});
} catch (_) {}

// Theme toggle button handlers
if (themeToggle) {
    themeToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        // disabled
    });
}
if (mobileThemeToggle) {
    mobileThemeToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        // disabled
    });
}
if (settingsThemeToggle) {
    settingsThemeToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        // disabled
    });
}

/**
* Navigation menu active state based on current page
* Works for multi-page dashboards
*/
const navLinks = document.querySelectorAll('.nav-menu__link');
const currentPage = window.location.pathname.split("/").pop(); // e.g. "dashboard.html"

navLinks.forEach(link => {
    const linkPage = link.getAttribute('href').split("/").pop(); // e.g. "dashboard.html"

    if (linkPage === currentPage) {
        link.classList.add('active');
    } else {
        link.classList.remove('active');
    }
});


// ==================== CHART.JS - Application Trends Chart ====================

// Chart functionality is now handled in dashboard_main.php with real data

/**
 * Responsive handling
 * Adjusts layout based on window size
 */
function handleResize() {
    // Guard for pages without mainContent/sidebar
    if (!sidebar || !mainContent) return;
    if (window.innerWidth <= 640) {
        mainContent.classList.remove('expanded');
        sidebar.classList.remove('collapsed');
    } else {
        sidebar.classList.remove('mobile-open');
    }
}
window.addEventListener('resize', handleResize);

// ==================== TABLE CHECKBOX FUNCTIONALITY ====================

/**
 * Select All checkbox functionality for all dashboard tables
 * Handles individual table select all and row selection
 */
document.addEventListener('DOMContentLoaded', function () {
    // Get all select all checkboxes
    const selectAllCheckboxes = document.querySelectorAll('input[id^="selectAll"]');

    selectAllCheckboxes.forEach(selectAllCheckbox => {
        // Get the table container
        const table = selectAllCheckbox.closest('table');
        const rowCheckboxes = table.querySelectorAll('.row-checkbox');

        // Select All functionality
        selectAllCheckbox.addEventListener('change', function () {
            const isChecked = this.checked;
            rowCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
        });

        // Individual row checkbox functionality
        rowCheckboxes.forEach(rowCheckbox => {
            rowCheckbox.addEventListener('change', function () {
                const checkedRows = table.querySelectorAll('.row-checkbox:checked');
                const totalRows = rowCheckboxes.length;

                // Update select all checkbox state
                if (checkedRows.length === 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                } else if (checkedRows.length === totalRows) {
                    selectAllCheckbox.checked = true;
                    selectAllCheckbox.indeterminate = false;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = true;
                }
            });
        });
    });
});

// ==================== TABLE SORTING FUNCTIONALITY ====================

/**
 * Table sorting functionality for sortable columns
 * Handles ascending/descending sort with visual feedback
 */
document.addEventListener('DOMContentLoaded', function () {
    const sortableHeaders = document.querySelectorAll('.sortable');

    sortableHeaders.forEach(header => {
        header.addEventListener('click', function () {
            const table = this.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const column = this.dataset.column;
            const currentSort = this.classList.contains('sorted-asc') ? 'asc' :
                this.classList.contains('sorted-desc') ? 'desc' : 'none';

            // Remove sort classes from all headers in this table
            table.querySelectorAll('.sortable').forEach(h => {
                h.classList.remove('sorted-asc', 'sorted-desc');
            });

            // Determine new sort direction
            let newSort = 'asc';
            if (currentSort === 'asc') {
                newSort = 'desc';
            }

            // Add appropriate class
            this.classList.add(newSort === 'asc' ? 'sorted-asc' : 'sorted-desc');

            // Sort rows
            rows.sort((a, b) => {
                let aValue, bValue;

                if (column === 'name') {
                    // Find the name cell (could be in different positions depending on table)
                    const nameCellA = a.querySelector('[data-cell="Student Name"]') ||
                        a.cells[2]; // Fallback to 3rd column
                    const nameCellB = b.querySelector('[data-cell="Student Name"]') ||
                        b.cells[2]; // Fallback to 3rd column
                    aValue = nameCellA ? nameCellA.textContent.trim() : '';
                    bValue = nameCellB ? nameCellB.textContent.trim() : '';
                } else if (column === 'email') {
                    // Find the email cell
                    const emailCellA = a.querySelector('[data-cell="Email"]') ||
                        a.cells[3]; // Fallback to 4th column
                    const emailCellB = b.querySelector('[data-cell="Email"]') ||
                        b.cells[3]; // Fallback to 4th column
                    aValue = emailCellA ? emailCellA.textContent.trim() : '';
                    bValue = emailCellB ? emailCellB.textContent.trim() : '';
                } else if (column === 'course') {
                    // Find the course cell
                    const courseCellA = a.querySelector('[data-cell="Course"]') ||
                        a.cells[3]; // Fallback to 4th column
                    const courseCellB = b.querySelector('[data-cell="Course"]') ||
                        b.cells[3]; // Fallback to 4th column
                    aValue = courseCellA ? courseCellA.textContent.trim() : '';
                    bValue = courseCellB ? courseCellB.textContent.trim() : '';
                }

                // Compare values
                if (aValue < bValue) return newSort === 'asc' ? -1 : 1;
                if (aValue > bValue) return newSort === 'asc' ? 1 : -1;
                return 0;
            });

            // Re-append sorted rows
            rows.forEach(row => tbody.appendChild(row));
        });
    });
});