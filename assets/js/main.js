// Shared site behaviour: mobile nav, dropdown-on-tap, back-to-top button.
(function () {
  var toggle = document.querySelector('.nav-toggle');
  var navLinks = document.querySelector('.nav-links');

  if (toggle && navLinks) {
    toggle.addEventListener('click', function () {
      var isOpen = navLinks.classList.toggle('is-open');
      document.body.classList.toggle('nav-is-open', isOpen);
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    // On mobile, tapping a "Služby" parent link should expand its dropdown
    // instead of navigating away immediately.
    document.querySelectorAll('.has-dropdown > a').forEach(function (link) {
      link.addEventListener('click', function (e) {
        if (window.innerWidth > 880) return;
        var parent = link.parentElement;
        if (!parent.classList.contains('is-open')) {
          e.preventDefault();
          parent.classList.add('is-open');
        }
      });
    });
  }

  // Back to top button
  var backToTop = document.querySelector('.back-to-top');
  if (backToTop) {
    window.addEventListener('scroll', function () {
      backToTop.classList.toggle('is-visible', window.scrollY > 500);
    });
    backToTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // Guardian picker: search-as-you-type filter over the checkbox list
  // of accounts a child can be linked to (admin/users.php).
  document.querySelectorAll('[data-guardian-search]').forEach(function (input) {
    var list = input.parentElement.querySelector('.checkbox-list');
    if (!list) return;
    var items = list.querySelectorAll('.checkbox-list-item');
    input.addEventListener('input', function () {
      var query = input.value.trim().toLowerCase();
      items.forEach(function (item) {
        var matches = item.textContent.toLowerCase().indexOf(query) !== -1;
        item.classList.toggle('is-hidden', !matches);
      });
    });
    // Enter shouldn't submit the surrounding form — it's just a filter.
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') e.preventDefault();
    });
  });

  // Accounts / children overview toolbars (admin/users.php): search by
  // name plus a role/program filter chip, combined.
  document.querySelectorAll('.list-toolbar').forEach(function (toolbar) {
    var input = toolbar.querySelector('[data-list-search]');
    var chipsWrap = toolbar.querySelector('[data-list-filter]');
    var countEl = toolbar.querySelector('[data-list-count]');
    var list = toolbar.nextElementSibling;
    if (!input || !list) return;
    var rows = list.querySelectorAll('.user-row');

    function applyFilters() {
      var query = input.value.trim().toLowerCase();
      var activeChip = chipsWrap ? chipsWrap.querySelector('.filter-chip.is-active') : null;
      var filterValue = activeChip ? activeChip.getAttribute('data-filter-value') : '';
      var visible = 0;
      rows.forEach(function (row) {
        var name = (row.getAttribute('data-name') || '');
        var matchesSearch = name.indexOf(query) !== -1;
        var matchesFilter = !filterValue || row.getAttribute('data-filter') === filterValue;
        var show = matchesSearch && matchesFilter;
        row.classList.toggle('is-hidden', !show);
        if (show) visible++;
      });
      if (countEl) countEl.textContent = visible + '/' + rows.length;
    }

    input.addEventListener('input', applyFilters);

    if (chipsWrap) {
      chipsWrap.querySelectorAll('.filter-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
          chipsWrap.querySelectorAll('.filter-chip').forEach(function (c) {
            c.classList.remove('is-active');
          });
          chip.classList.add('is-active');
          applyFilters();
        });
      });
    }

    applyFilters();
  });
})();
