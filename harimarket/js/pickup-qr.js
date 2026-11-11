// Draws QR codes for pick-up codes (uses js/vendor/qrcode.js).
// Usage: <div class="qr-target" data-code="8F3K-92QX"></div>
function renderQr(el) {
  if (!window.qrcode || !el.dataset.code) return;
  const qr = qrcode(0, 'M');
  qr.addData(el.dataset.code);
  qr.make();
  el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
  const svg = el.querySelector('svg');
  if (svg) { svg.setAttribute('role', 'img'); svg.setAttribute('aria-label', 'Pick-up QR code ' + el.dataset.code); }
}
document.addEventListener('DOMContentLoaded', () => {
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
});
