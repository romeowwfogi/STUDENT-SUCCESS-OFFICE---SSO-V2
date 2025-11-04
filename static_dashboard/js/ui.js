// Core UI interactions: sidebar toggle, navigation activation
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.querySelector('#sidebar');
  const mainContent = document.querySelector('.main-content');
  const mobileMenuToggle = document.querySelector('#mobileMenuToggle');

  // Force sidebar open for pixel parity
  if (sidebar) {
    sidebar.classList.remove('collapsed');
  }
  if (mainContent) {
    mainContent.style.marginLeft = '250px';
  }

  // Mobile menu opens sidebar (no collapse toggle)
  if (mobileMenuToggle && sidebar) {
    mobileMenuToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      sidebar.classList.remove('collapsed');
      mainContent && (mainContent.style.marginLeft = '250px');
    });
  }

  // Activate nav link based on current page
  const navLinks = document.querySelectorAll('.nav-menu__link');
  const currentPage = window.location.pathname.split('/').pop();
  navLinks.forEach(link => {
    const linkPage = (link.getAttribute('href') || '').split('/').pop();
    if (linkPage === currentPage) {
      link.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });

  // Table sorting visual toggles
  const sortableHeaders = document.querySelectorAll('.sortable');
  sortableHeaders.forEach(header => {
    header.addEventListener('click', () => {
      const table = header.closest('table');
      if (!table) return;
      table.querySelectorAll('.sortable').forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
      if (!header.classList.contains('sort-asc')) {
        header.classList.add('sort-asc');
      } else {
        header.classList.remove('sort-asc');
        header.classList.add('sort-desc');
      }
    });
  });

  // Filter popup logic (generic, if present)
  const filterConfigs = [
    { btn: document.querySelector('.button.filter'), popup: document.getElementById('filterPopup'), close: document.getElementById('closeFilter') },
    { btn: document.querySelector('#application_management_section .button.filter'), popup: document.getElementById('filterPopupApplications'), close: document.getElementById('closeFilterApplications') },
    { btn: document.querySelector('#scheduling_section .button.filter'), popup: document.getElementById('filterPopupScheduling'), close: document.getElementById('closeFilterScheduling') }
  ];

  filterConfigs.forEach(cfg => {
    const { btn, popup, close } = cfg;
    if (btn && popup) {
      btn.addEventListener('click', () => { popup.style.display = 'flex'; });
    }
    if (close && popup) {
      close.addEventListener('click', () => { popup.style.display = 'none'; });
    }
    if (popup) {
      window.addEventListener('click', (e) => { if (e.target === popup) popup.style.display = 'none'; });
    }
  });
});