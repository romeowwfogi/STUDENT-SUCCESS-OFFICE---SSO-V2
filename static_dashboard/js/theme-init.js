// Preserve original theme behavior: force light theme on load
document.addEventListener('DOMContentLoaded', () => {
  try {
    localStorage.removeItem('theme');
  } catch (_) {}
  document.documentElement.setAttribute('data-theme', 'light');
});