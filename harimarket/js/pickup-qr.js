// Draws QR codes for pick-up codes (uses js/vendor/qrcode.js).
// Usage: <div class="qr-target" data-code="8F3K-92QX"></div>
function renderQr(el) {
  const code = el.dataset.code;
  if (!code) return;
  try {
    if (typeof qrcode !== 'function') throw new Error('qrcode.js did not load');
    const qr = qrcode(0, 'M');
    qr.addData(code);
    qr.make();
    el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
    const svg = el.querySelector('svg');
    if (svg) {
      svg.setAttribute('role', 'img');
      svg.setAttribute('aria-label', 'Pick-up QR code ' + code);
    }
  } catch (err) {
    console.error('QR render failed:', err);
    el.innerHTML = '<p class="muted-small">QR code unavailable (' + err.message + '). Show the code below to the seller.</p>';
  }
}
function initQr() {
  document.querySelectorAll('.qr-target').forEach(renderQr);
  document.querySelectorAll('[data-qr-open]').forEach(btn => {
    btn.addEventListener('click', () => {
      const modal = document.getElementById(btn.dataset.qrOpen);
      if (!modal) return;
      modal.hidden = false;
      modal.querySelectorAll('.qr-target').forEach(renderQr);
    });
  });
  document.querySelectorAll('[data-qr-close]').forEach(el => {
    el.addEventListener('click', () => { const m = el.closest('.qr-modal'); if (m) m.hidden = true; });
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.qr-modal').forEach(m => m.hidden = true);
  });
}
// Run whether or not DOMContentLoaded has already fired.
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initQr);
else initQr();
