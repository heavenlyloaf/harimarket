// Live notifications: polls api/notifications.php with the Fetch API,
// updates the bell badges and shows a toast for each new notification.
(() => {
  const POLL_MS = 15000;
  const MAX_BACKOFF_MS = 120000;
  let lastId = -1;
  let failures = 0;
  let timer = null;

  const toastBox = document.createElement('div');
  toastBox.className = 'toast-box';
  toastBox.setAttribute('role', 'status');
  toastBox.setAttribute('aria-live', 'polite');
  document.body.appendChild(toastBox);

  const showToast = ({ message, link }) => {
    const a = document.createElement('a');
    a.className = 'toast';
    a.href = link || 'notifications.php';
    a.textContent = message;               // textContent: never injects HTML
    toastBox.appendChild(a);
    setTimeout(() => a.remove(), 7000);
  };

  const setBadges = (count) => {
    document.querySelectorAll('.notif-icon-wrap').forEach((wrap) => {
      let badge = wrap.querySelector('.notif-badge');
      if (count > 0) {
        if (!badge) {
          badge = document.createElement('span');
          badge.className = 'notif-badge';
          wrap.appendChild(badge);
        }
        badge.textContent = count > 9 ? '9+' : String(count);
      } else if (badge) {
        badge.remove();
      }
    });
  };

  const setOffline = (offline) => {
    let bar = document.getElementById('offlineBar');
    if (offline && !bar) {
      bar = document.createElement('div');
      bar.id = 'offlineBar';
      bar.className = 'offline-bar';
      bar.textContent = 'Connection problem. Live updates are paused and will retry automatically.';
      document.body.prepend(bar);
    } else if (!offline && bar) {
      bar.remove();
    }
  };

  const poll = async () => {
    if (document.hidden) return schedule();
    try {
      const ctrl = new AbortController();
      const t = setTimeout(() => ctrl.abort(), 8000);   // timeout handling
      const res = await fetch(`api/notifications.php?since=${lastId}`, {
        credentials: 'same-origin',
        signal: ctrl.signal,
        headers: { Accept: 'application/json' },
      });
      clearTimeout(t);
      if (res.status === 401) return;                    // logged out: stop polling
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      failures = 0;
      setOffline(false);
      if (lastId >= 0) data.items.forEach(showToast);
      lastId = data.latest_id;
      setBadges(data.unread);
    } catch (err) {
      failures += 1;
      if (failures >= 3) setOffline(true);
    }
    schedule();
  };

  const schedule = () => {
    clearTimeout(timer);
    const delay = Math.min(POLL_MS * 2 ** Math.max(0, failures - 2), MAX_BACKOFF_MS);
    timer = setTimeout(poll, delay);
  };

  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  window.addEventListener('online', poll);
  poll();
})();
