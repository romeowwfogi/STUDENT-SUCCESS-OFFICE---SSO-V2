// Generic modal open/close interactions
document.addEventListener('DOMContentLoaded', () => {
  // Generic modal handlers
  document.querySelectorAll('[data-modal-target]').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-modal-target');
      const modal = document.querySelector(target);
      if (modal) {
        modal.classList.add('open');
      }
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = btn.closest('.modal');
      if (modal) {
        modal.classList.remove('open');
      }
    });
  });

  // Overlay-based modals
  const overlay = document.querySelector('.modal-overlay');
  if (overlay) {
    // Open helpers
    document.querySelectorAll('[data-open-overlay]').forEach(el => {
      el.addEventListener('click', () => overlay.classList.add('show'));
    });

    // Close helpers
    document.querySelectorAll('[data-close-overlay]').forEach(el => {
      el.addEventListener('click', () => overlay.classList.remove('show'));
    });

    // Click outside closes
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) overlay.classList.remove('show');
    });
  }
});