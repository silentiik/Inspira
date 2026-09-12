// Shared site behaviour: mobile nav, dropdown-on-tap, back-to-top button.
(function () {
  // Dismiss a flash message (success/error banner) via its × button.
  document.querySelectorAll('.alert-close').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var alert = btn.closest('.alert');
      if (alert) alert.remove();
    });
  });

  // Styled confirm dialog for destructive form submits — replaces the
  // browser's native confirm() popup. A form opts in with
  // data-confirm="message shown in the dialog".
  var confirmModal = document.querySelector('[data-confirm-modal]');
  if (confirmModal) {
    var confirmMessage = confirmModal.querySelector('[data-confirm-message]');
    var confirmOk = confirmModal.querySelector('[data-confirm-ok]');
    var confirmCancel = confirmModal.querySelector('[data-confirm-cancel]');
    var pendingForm = null;

    function closeConfirmModal() {
      confirmModal.hidden = true;
      pendingForm = null;
    }

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (form.dataset.confirmed === 'true') return; // already confirmed below — let it through
        e.preventDefault();
        pendingForm = form;
        confirmMessage.textContent = form.getAttribute('data-confirm');
        confirmModal.hidden = false;
        confirmOk.focus();
      });
    });

    confirmOk.addEventListener('click', function () {
      if (pendingForm) {
        pendingForm.dataset.confirmed = 'true';
        pendingForm.submit();
      }
      closeConfirmModal();
    });
    confirmCancel.addEventListener('click', closeConfirmModal);
    confirmModal.addEventListener('click', function (e) {
      if (e.target === confirmModal) closeConfirmModal();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !confirmModal.hidden) closeConfirmModal();
    });
  }

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
  // name plus a role/program filter chip, paginated 10 rows at a time.
  // Rows are already sorted A-Z by surname server-side, and slicing
  // them in their existing DOM order keeps that order on every page.
  document.querySelectorAll('.list-toolbar').forEach(function (toolbar) {
    var input = toolbar.querySelector('[data-list-search]');
    var chipsWrap = toolbar.querySelector('[data-list-filter]');
    var countEl = toolbar.querySelector('[data-list-count]');
    var list = toolbar.nextElementSibling;
    if (!input || !list) return;
    var paginationEl = list.nextElementSibling;
    var rows = Array.prototype.slice.call(list.querySelectorAll('.user-row'));
    var pageSize = 10;
    var currentPage = 1;

    function getFiltered() {
      var query = input.value.trim().toLowerCase();
      var activeChip = chipsWrap ? chipsWrap.querySelector('.filter-chip.is-active') : null;
      var filterValue = activeChip ? activeChip.getAttribute('data-filter-value') : '';
      return rows.filter(function (row) {
        var name = row.getAttribute('data-name') || '';
        var matchesSearch = name.indexOf(query) !== -1;
        var matchesFilter = !filterValue || row.getAttribute('data-filter') === filterValue;
        return matchesSearch && matchesFilter;
      });
    }

    function makePageBtn(label, page, opts) {
      opts = opts || {};
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'page-btn' + (opts.active ? ' is-active' : '');
      btn.textContent = label;
      if (opts.disabled) btn.disabled = true;
      if (!opts.disabled && !opts.active) {
        btn.addEventListener('click', function () {
          currentPage = page;
          render();
        });
      }
      return btn;
    }

    function render() {
      var filtered = getFiltered();
      var totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
      currentPage = Math.min(currentPage, totalPages);
      var pageStart = (currentPage - 1) * pageSize;
      var pageRows = filtered.slice(pageStart, pageStart + pageSize);

      rows.forEach(function (row) {
        row.classList.toggle('is-hidden', pageRows.indexOf(row) === -1);
      });
      if (countEl) countEl.textContent = filtered.length + '/' + rows.length;

      if (!paginationEl || !paginationEl.classList.contains('pagination')) return;
      paginationEl.innerHTML = '';
      if (totalPages <= 1) return;

      paginationEl.appendChild(makePageBtn('‹', currentPage - 1, { disabled: currentPage === 1 }));
      for (var p = 1; p <= totalPages; p++) {
        paginationEl.appendChild(makePageBtn(String(p), p, { active: p === currentPage }));
      }
      paginationEl.appendChild(makePageBtn('›', currentPage + 1, { disabled: currentPage === totalPages }));
    }

    input.addEventListener('input', function () {
      currentPage = 1;
      render();
    });

    if (chipsWrap) {
      chipsWrap.querySelectorAll('.filter-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
          chipsWrap.querySelectorAll('.filter-chip').forEach(function (c) {
            c.classList.remove('is-active');
          });
          chip.classList.add('is-active');
          currentPage = 1;
          render();
        });
      });
    }

    render();
  });
})();
