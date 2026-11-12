// Dashboard charts: loads api/stats.php with Fetch and draws Chart.js charts.
(() => {
  const host = document.getElementById('chartGrid');
  if (!host || typeof Chart === 'undefined') return;

  const PALETTE = ['#4f6ef7', '#f2a33a', '#2fb67c', '#e0584f', '#8a6cf0', '#3bb4d6'];
  const charts = [];

  const card = (title) => {
    const wrap = document.createElement('div');
    wrap.className = 'chart-card';
    const h = document.createElement('h3');
    h.textContent = title;
    const box = document.createElement('div');
    box.className = 'chart-box';
    const canvas = document.createElement('canvas');
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', title);
    box.appendChild(canvas);
    wrap.append(h, box);
    host.appendChild(wrap);
    return { canvas, box };
  };

  const empty = (box, text = 'No data yet.') => {
    box.innerHTML = '';
    const p = document.createElement('p');
    p.className = 'chart-msg';
    p.textContent = text;
    box.appendChild(p);
  };

  const themeColors = () => {
    const dark = document.documentElement.dataset.theme === 'dark';
    return { text: dark ? '#e6e9ef' : '#333', grid: dark ? 'rgba(255,255,255,.12)' : 'rgba(0,0,0,.08)' };
  };

  const draw = (title, type, data, label, opts = {}) => {
    const { canvas, box } = card(title);
    if (!data.values.some((v) => v > 0)) return empty(box);
    const circular = type === 'doughnut';
    const c = themeColors();
    charts.push(new Chart(canvas, {
      type,
      data: {
        labels: data.labels,
        datasets: [{
          label,
          data: data.values,
          backgroundColor: circular || type === 'bar' ? PALETTE : 'rgba(79,110,247,.18)',
          borderColor: type === 'line' ? PALETTE[0] : undefined,
          fill: type === 'line',
          tension: 0.3,
          borderWidth: circular ? 0 : 2,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: circular, position: 'bottom', labels: { color: c.text } } },
        scales: circular ? {} : {
          x: { ticks: { color: c.text, maxRotation: 0, autoSkip: true }, grid: { color: c.grid } },
          y: { beginAtZero: true, ticks: { color: c.text, precision: 0 }, grid: { color: c.grid } },
        },
        ...opts,
      },
    }));
  };

  const load = async () => {
    try {
      const res = await fetch('api/stats.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const d = await res.json();
      host.innerHTML = '';
      if (d.role === 'seller') {
        draw('Orders by status', 'doughnut', d.status, 'Orders');
        draw('Earnings, last 14 days (₱)', 'line', d.earnings, 'Earnings');
        draw('Top products (picked up)', 'bar', d.top, 'Units sold', { indexAxis: 'y' });
      } else {
        draw('Users by role', 'doughnut', d.users, 'Users');
        draw('Orders, last 14 days', 'line', d.orders, 'Orders');
        draw('Approved products by category', 'bar', d.categories, 'Products');
      }
    } catch (err) {
      host.innerHTML = '';
      const p = document.createElement('p');
      p.className = 'chart-msg';
      p.textContent = 'Charts could not be loaded. Please refresh the page.';
      host.appendChild(p);
    }
  };

  // Redraw with readable axis colours when the theme is switched.
  document.addEventListener('themechange', () => { charts.splice(0).forEach((ch) => ch.destroy()); load(); });
  load();
})();
