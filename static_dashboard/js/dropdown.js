// Dropdown toggling behavior
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-dropdown]').forEach(dd => {
    const toggle = dd.querySelector('[data-dropdown-toggle]');
    const menu = dd.querySelector('[data-dropdown-menu]');
    if (!toggle || !menu) return;

    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      menu.classList.toggle('open');
    });

    document.addEventListener('click', () => {
      menu.classList.remove('open');
    });
  });
});