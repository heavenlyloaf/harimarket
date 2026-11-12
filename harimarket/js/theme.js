// Dark / light mode toggle. The choice is saved in localStorage and
// applied before first paint by the inline snippet in partials/head.php.
(() => {
  const KEY = 'hm-theme';
  const root = document.documentElement;
  const read = () => { try { return localStorage.getItem(KEY); } catch { return null; } };
  const save = (v) => { try { localStorage.setItem(KEY, v); } catch { /* storage blocked */ } };

  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'theme-toggle';

  const render = () => {
    const dark = root.dataset.theme === 'dark';
    btn.textContent = dark ? '☀' : '🌙';
    btn.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    btn.title = btn.getAttribute('aria-label');
  };

  btn.addEventListener('click', () => {
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    save(next);
    render();
    document.dispatchEvent(new CustomEvent('themechange', { detail: next }));
  });

  if (!root.dataset.theme) root.dataset.theme = read() || 'light';
  render();
  document.body.appendChild(btn);
})();
