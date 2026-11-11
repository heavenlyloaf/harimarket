// Live product search on the home page: Fetch API + JSON endpoint (api/products.php).
// Without JavaScript the normal search form (GET index.php?q=) still works.
(() => {
  const input = document.querySelector('.search-bar input[name="q"]');
  const grid = document.getElementById('productGrid');
  const sortSel = document.getElementById('sortSelect');
  const none = document.getElementById('noResults');
  if (!grid) return;
  const cat = new URLSearchParams(location.search).get('cat') || '';
  let timer = null, seq = 0;

  const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const stars = r => { const f = Math.round(Number(r) || 0); return '★ '.repeat(f) + '☆ '.repeat(5 - f); };

  function card(p) {
    const el = document.createElement('div');
    el.className = 'product-card';
    el.style.cursor = 'pointer';
    el.addEventListener('click', () => { location.href = 'product.php?id=' + encodeURIComponent(p.id); });
    const imgWrap = document.createElement('div'); imgWrap.className = 'product-img';
    const img = document.createElement('img'); img.src = p.image; img.alt = p.name; img.loading = 'lazy';
    imgWrap.appendChild(img);
    const h = document.createElement('h4'); h.textContent = p.name;
    const foot = document.createElement('div'); foot.className = 'product-footer';
    const price = document.createElement('div'); price.className = 'product-price'; price.textContent = peso(p.price);
    const rate = document.createElement('div'); rate.className = 'product-rating'; rate.textContent = stars(p.rating);
    foot.append(price, rate);
    el.append(imgWrap, h, foot);
    return el;
  }

  async function run() {
    const mine = ++seq;
    const params = new URLSearchParams({ q: input ? input.value.trim() : '', sort: sortSel ? sortSel.value : 'newest' });
    if (cat) params.set('cat', cat);
    try {
      const res = await fetch('api/products.php?' + params, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      if (mine !== seq) return; // a newer request replaced this one
      grid.replaceChildren(...data.products.map(card));
      if (none) none.hidden = data.products.length > 0;
      const url = new URL(location.href);
      ['q', 'sort'].forEach(k => url.searchParams.delete(k));
      if (params.get('q')) url.searchParams.set('q', params.get('q'));
      if (params.get('sort') !== 'newest') url.searchParams.set('sort', params.get('sort'));
      history.replaceState(null, '', url);
    } catch (e) {
      if (none) { none.hidden = false; none.textContent = 'Search is unavailable right now. Please try again.'; }
    }
  }
  if (input) input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 250); });
  if (sortSel) sortSel.addEventListener('change', run);
})();
