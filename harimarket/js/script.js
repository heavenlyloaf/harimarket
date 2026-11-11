document.addEventListener("DOMContentLoaded", () => {
const bottomSearchBtn = document.getElementById('bottomSearchBtn');
const searchBar       = document.querySelector('.search-bar');

if (bottomSearchBtn && searchBar) {
  bottomSearchBtn.addEventListener('click', (e) => {
    e.preventDefault();

    searchBar.style.display   = 'block';
    searchBar.style.width     = '100%';
    searchBar.style.margin    = '0.5rem 0';
    searchBar.style.animation = 'fadeSlideDown 0.2s ease';

    const input = searchBar.querySelector('input[type="text"]');
    if (input) {
      input.focus();
      input.select();
    }

    function onOutsideClick(ev) {
      if (!searchBar.contains(ev.target) && ev.target !== bottomSearchBtn) {
        searchBar.style.display = '';
        document.removeEventListener('click', onOutsideClick);
      }
    }

    setTimeout(() => document.addEventListener('click', onOutsideClick), 50);
  });
}

  function setupDropdown(buttonIdOrEl) {
    const btn = typeof buttonIdOrEl === 'string'
      ? document.getElementById(buttonIdOrEl)
      : buttonIdOrEl;

    if (!btn) return;

    const container = btn.closest('.dropdown');
    if (!container) return;
    const menu = container.querySelector('.dropdown-menu');
    if (!menu) return;

    function openMenu() {
      document.querySelectorAll('.dropdown-menu.show').forEach(m => {
        if (m !== menu) {
          m.classList.remove('show');
          const b = m.closest('.dropdown')?.querySelector('.dropdown-toggle');
          if (b) b.setAttribute('aria-expanded', 'false');
        }
      });

      menu.classList.add('show');
      btn.setAttribute('aria-expanded', 'true');

      requestAnimationFrame(() => {
        const rect = menu.getBoundingClientRect();
        if (rect.left < 8) {
          menu.style.left = '0';
          menu.style.right = 'auto';
          menu.style.transform = 'none';
        } else if (rect.right > window.innerWidth - 8) {
          menu.style.left = 'auto';
          menu.style.right = '0';
          menu.style.transform = 'none';
        }
      });

      document.addEventListener('click', onDocClick);
      document.addEventListener('keydown', onKeyDown);
    }

    function closeMenu() {
      menu.classList.remove('show');
      menu.style.left = '';
      menu.style.right = '';
      menu.style.transform = '';
      btn.setAttribute('aria-expanded', 'false');
      document.removeEventListener('click', onDocClick);
      document.removeEventListener('keydown', onKeyDown);
    }

    function toggleMenu(e) {
      e.stopPropagation();
      menu.classList.contains('show') ? closeMenu() : openMenu();
    }

    function onDocClick(e) {
      if (!container.contains(e.target)) closeMenu();
    }

    function onKeyDown(e) {
      if (e.key === 'Escape') { closeMenu(); btn.focus(); }
    }

    btn.addEventListener('click', toggleMenu);

    btn.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        openMenu();
        const first = menu.querySelector('[role="menuitem"]');
        if (first) first.focus();
      }
    });

    menu.querySelectorAll('a[role="menuitem"]').forEach(item => {
      item.setAttribute('tabindex', '0');
    });
  }

  setupDropdown('userMenuButton');
  setupDropdown('categoriesMenuButton');
  setupDropdown('bottomUserMenuButton'); 


  document.querySelectorAll('.product-card').forEach(card => {
    card.addEventListener('click', () => {
      const link = card.getAttribute('data-link');
      if (link) window.location.href = link;
    });
  });

});


document.querySelectorAll('.star-picker').forEach(picker => {
  const stars  = picker.querySelectorAll('.star');
  const inputs = picker.querySelectorAll('input[type=radio]');

  function highlight(upTo) {
    stars.forEach((s, i) => {
      s.style.color = i < upTo ? '#f5a623' : '#ccc';
    });
  }

  stars.forEach((star, idx) => {
    star.addEventListener('mouseover', () => highlight(idx + 1));
    star.addEventListener('mouseout',  () => {
      const checked = [...inputs].findIndex(r => r.checked);
      highlight(checked + 1);
    });
    star.addEventListener('click', () => {
      inputs[idx].checked = true;
      highlight(idx + 1);
    });
  });
});