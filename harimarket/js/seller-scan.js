// Seller QR scan page: camera scanning (html5-qrcode) and manual code entry.
// The server does all validation; this script only sends the code with fetch().
(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const result = document.getElementById('result');
  const input = document.getElementById('codeInput');
  const startBtn = document.getElementById('startScan');
  const stopBtn = document.getElementById('stopScan');
  const camNote = document.getElementById('camNote');
  let scanner = null;
  let busy = false;

  async function post(action, code) {
    const body = new URLSearchParams({ action, code });
    const res = await fetch('seller-scan.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
      body
    });
    return res.json();
  }
  function esc(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
  function showError(msg) {
    result.hidden = false; result.className = 'scan-result bad';
    result.innerHTML = '<strong>&#10007; ' + esc(msg) + '</strong>';
  }
  function showOrder(o, code) {
    const items = o.items.map(i => i.quantity + ' &times; ' + esc(i.name)).join('<br>');
    result.hidden = false; result.className = 'scan-result good';
    result.innerHTML = '<strong>&#10003; Valid order #' + o.id + '</strong><p>' + esc(o.buyer_name) + ' &middot; &#8369;' +
      Number(o.total).toFixed(2) + '</p><p>' + items + '</p>' +
      '<button type="button" class="btn primary" id="confirmBtn" style="width:100%">Mark as Picked Up</button>';
    document.getElementById('confirmBtn').addEventListener('click', async () => {
      const r = await post('confirm', code);
      if (!r.ok) return showError(r.error);
      result.className = 'scan-result good';
      result.innerHTML = '<strong>&#10003; Order #' + r.order.id + ' marked as Picked Up.</strong><p>The buyer was notified.</p>';
      input.value = '';
    });
  }
  async function lookup(code) {
    if (busy || !code.trim()) return;
    busy = true;
    try {
      const r = await post('lookup', code);
      r.ok ? showOrder(r.order, code) : showError(r.error);
    } catch (e) {
      showError('Could not reach the server. Check your connection.');
    } finally { busy = false; }
  }
  document.getElementById('codeForm').addEventListener('submit', e => { e.preventDefault(); lookup(input.value); });

  startBtn.addEventListener('click', async () => {
    if (!window.Html5Qrcode) { camNote.textContent = 'Scanner library failed to load. Type the code instead.'; return; }
    try {
      scanner = new Html5Qrcode('reader');
      await scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 220, height: 220 } },
        text => { input.value = text; lookup(text); }, () => {});
      startBtn.hidden = true; stopBtn.hidden = false; camNote.textContent = 'Point the camera at the buyer\'s QR code.';
    } catch (err) {
      camNote.textContent = 'Camera not available or permission denied. You can type the code instead.';
    }
  });
  stopBtn.addEventListener('click', async () => {
    if (scanner) { try { await scanner.stop(); scanner.clear(); } catch (e) {} }
    startBtn.hidden = false; stopBtn.hidden = true;
  });
})();
