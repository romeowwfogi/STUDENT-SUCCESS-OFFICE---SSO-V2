// Global Theme Initialization (force light theme)
(function () {
  // Always force light theme and clear any saved preference
  try { localStorage.removeItem('theme'); } catch (_) { }
  document.documentElement.removeAttribute('data-theme');
  document.documentElement.setAttribute('data-theme', 'light');
})();