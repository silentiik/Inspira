// Shared site behaviour: mobile nav, dropdown-on-tap, back-to-top button.
(function () {
  // Re-align to a pinned/unpinned post (dashboard.php redirects to
  // #news-<id>) once webfonts and images have actually finished loading.
  // The font-swap reflow and any image without known dimensions both
  // shift page height after the browser's initial anchor scroll, which
  // otherwise shows up as the page jumping a second time.
  if (location.hash.indexOf('#news-') === 0) {
    var scrollTarget = document.querySelector(location.hash);
    if (scrollTarget) {
      var realign = function () { scrollTarget.scrollIntoView({ block: 'start' }); };
      if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(realign);
      }
      window.addEventListener('load', realign);
    }
  }

  // Obědy's week-jump date picker: navigate as soon as a date is
  // chosen, instead of making the user also press a "Go" button.
  document.querySelectorAll('[data-week-picker]').forEach(function (input) {
    input.addEventListener('change', function () {
      if (input.value) input.form.submit();
    });
  });

  // Rich-text formatting toolbar for the news post editor (create and
  // edit forms both use this same markup). document.execCommand is
  // deprecated but still works fine in every current browser for this
  // small a feature set, and keeps the page dependency-free.
  var richtextWraps = document.querySelectorAll('[data-richtext]');
  if (richtextWraps.length) {
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}
  }
  richtextWraps.forEach(function (wrap) {
    var editor = wrap.querySelector('[data-rt-editor]');
    var input = wrap.querySelector('[data-rt-input]');
    if (!editor || !input) return;

    wrap.querySelectorAll('[data-rt-cmd]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        editor.focus();
        document.execCommand(btn.getAttribute('data-rt-cmd'), false, null);
      });
    });

    // Font size: there's no execCommand for an arbitrary CSS size, so
    // apply the classic workaround — request the largest legacy <font
    // size> value, then swap each resulting <font> for a <span> with
    // the actual pixel size we want.
    var fontSizeSelect = wrap.querySelector('[data-rt-fontsize]');
    if (fontSizeSelect) {
      fontSizeSelect.addEventListener('change', function () {
        editor.focus();
        document.execCommand('fontSize', false, '7');
        editor.querySelectorAll('font[size="7"]').forEach(function (fontEl) {
          var span = document.createElement('span');
          span.style.fontSize = fontSizeSelect.value + 'px';
          while (fontEl.firstChild) span.appendChild(fontEl.firstChild);
          fontEl.parentNode.replaceChild(span, fontEl);
        });
      });
    }

    var form = wrap.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        input.value = editor.innerHTML;
      });
    }
  });

  // Dismiss a flash message (success/error banner) via its × button.
  document.querySelectorAll('.alert-close').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var alert = btn.closest('.alert');
      if (alert) alert.remove();
    });
  });

  // News post edit toggle (dashboard.php): the pencil icon shows/hides
  // that post's inline edit form, highlighting the post while it's open.
  // Only one post can be edited at a time — opening another one closes
  // whichever was open first.
  document.querySelectorAll('[data-toggle-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var form = document.getElementById(btn.getAttribute('data-toggle-edit'));
      if (!form) return;
      var willOpen = form.hidden;

      document.querySelectorAll('.news-edit-form').forEach(function (otherForm) {
        if (otherForm === form || otherForm.hidden) return;
        otherForm.hidden = true;
        var otherItem = otherForm.closest('.news-item');
        if (otherItem) otherItem.classList.remove('is-editing');
      });

      form.hidden = !willOpen;
      var item = form.closest('.news-item');
      if (item) item.classList.toggle('is-editing', willOpen);
    });
  });

  // Custom file-picker buttons (create/edit news post): the real file
  // input is visually hidden and triggered by a styled <label>; this
  // just keeps the "no file chosen" text next to it up to date.
  document.querySelectorAll('[data-file-picker]').forEach(function (input) {
    var status = input.parentElement.querySelector('[data-file-picker-status]');
    if (!status) return;
    input.addEventListener('change', function () {
      if (input.files.length === 0) {
        status.textContent = 'Nevybrán žádný soubor';
      } else if (input.files.length === 1) {
        status.textContent = input.files[0].name;
      } else {
        status.textContent = input.files.length + ' souborů vybráno';
      }
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

  // Nastaveni jidelnicku: editing a day that already had a meal typed in
  // asks whether it's just a wording fix (keep everyone's existing
  // choice) or a genuinely different dish (reset that day's choices).
  // A day that had no meal yet always saves straight through.
  var menuForm = document.querySelector('[data-menu-form]');
  var menuChangeModal = document.querySelector('[data-menu-change-modal]');
  if (menuForm && menuChangeModal) {
    var menuChangeCancel = menuChangeModal.querySelector('[data-menu-change-cancel]');
    var menuChangeTextOnly = menuChangeModal.querySelector('[data-menu-change-text-only]');
    var menuChangeReset = menuChangeModal.querySelector('[data-menu-change-reset]');

    function closeMenuChangeModal() {
      menuChangeModal.hidden = true;
    }

    function submitMenuFormWithChoice(choice) {
      var field = document.createElement('input');
      field.type = 'hidden';
      field.name = 'reset_choice';
      field.value = choice;
      menuForm.appendChild(field);
      menuForm.dataset.confirmed = 'true';
      menuForm.submit();
    }

    menuForm.addEventListener('submit', function (e) {
      if (menuForm.dataset.confirmed === 'true') return;
      var changedExistingMeal = false;
      menuForm.querySelectorAll('.lunch-day-meal-input').forEach(function (input) {
        if (input.defaultValue !== '' && input.value !== input.defaultValue) {
          changedExistingMeal = true;
        }
      });
      if (!changedExistingMeal) return; // nothing already-set was edited — save straight through
      e.preventDefault();
      menuChangeModal.hidden = false;
    });

    menuChangeCancel.addEventListener('click', closeMenuChangeModal);
    menuChangeTextOnly.addEventListener('click', function () {
      closeMenuChangeModal();
      submitMenuFormWithChoice('text_only');
    });
    menuChangeReset.addEventListener('click', function () {
      closeMenuChangeModal();
      submitMenuFormWithChoice('meal_change');
    });
    menuChangeModal.addEventListener('click', function (e) {
      if (e.target === menuChangeModal) closeMenuChangeModal();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !menuChangeModal.hidden) closeMenuChangeModal();
    });
  }

  // Lightbox for news pictures: clicking a thumbnail (or a full-size
  // post image) opens it enlarged instead of navigating to the raw file.
  var lightboxModal = document.querySelector('[data-lightbox-modal]');
  if (lightboxModal) {
    var lightboxImage = lightboxModal.querySelector('[data-lightbox-image]');
    var lightboxClose = lightboxModal.querySelector('[data-lightbox-close]');

    function closeLightbox() {
      lightboxModal.hidden = true;
      lightboxImage.src = '';
    }

    document.querySelectorAll('[data-lightbox]').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        var thumb = link.querySelector('img');
        lightboxImage.src = link.getAttribute('href');
        lightboxImage.alt = thumb ? thumb.alt : '';
        lightboxModal.hidden = false;
      });
    });

    lightboxClose.addEventListener('click', closeLightbox);
    lightboxModal.addEventListener('click', function (e) {
      if (e.target === lightboxModal) closeLightbox();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !lightboxModal.hidden) closeLightbox();
    });
  }

  // Paginate the news board, 10 posts per page — same numbered
  // ‹ 1 2 3 › control used for the admin account/children lists.
  var newsPagination = document.querySelector('[data-news-pagination]');
  if (newsPagination) {
    var newsItems = Array.prototype.slice.call(document.querySelectorAll('.news-item'));
    var newsPageSize = 10;
    var newsCurrentPage = 1;

    // If we've been redirected to a specific post's anchor (pinning,
    // editing), start on the page that actually contains it instead of
    // always defaulting to page 1 and hiding it.
    if (location.hash.indexOf('#news-') === 0) {
      var targetIndex = newsItems.findIndex(function (item) { return '#' + item.id === location.hash; });
      if (targetIndex !== -1) {
        newsCurrentPage = Math.floor(targetIndex / newsPageSize) + 1;
      }
    }

    var renderNewsPage = function () {
      var totalPages = Math.max(1, Math.ceil(newsItems.length / newsPageSize));
      newsCurrentPage = Math.min(newsCurrentPage, totalPages);
      var start = (newsCurrentPage - 1) * newsPageSize;
      var end = start + newsPageSize;

      newsItems.forEach(function (item, i) {
        item.classList.toggle('is-hidden', i < start || i >= end);
      });

      newsPagination.innerHTML = '';
      if (totalPages <= 1) return;

      var makeNewsPageBtn = function (label, page, opts) {
        opts = opts || {};
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'page-btn' + (opts.active ? ' is-active' : '');
        btn.textContent = label;
        if (opts.disabled) btn.disabled = true;
        if (!opts.disabled && !opts.active) {
          btn.addEventListener('click', function () {
            newsCurrentPage = page;
            renderNewsPage();
            newsPagination.scrollIntoView({ block: 'nearest' });
          });
        }
        return btn;
      };

      newsPagination.appendChild(makeNewsPageBtn('‹', newsCurrentPage - 1, { disabled: newsCurrentPage === 1 }));
      for (var p = 1; p <= totalPages; p++) {
        newsPagination.appendChild(makeNewsPageBtn(String(p), p, { active: p === newsCurrentPage }));
      }
      newsPagination.appendChild(makeNewsPageBtn('›', newsCurrentPage + 1, { disabled: newsCurrentPage === totalPages }));
    };

    renderNewsPage();
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

  // Mesicni prehled roster (Obedy -> Prehled obedu): search by name plus
  // a group filter chip. It's a short, fixed list, so unlike the
  // admin/users.php toolbars above there's no pagination to manage.
  document.querySelectorAll('[data-overview-toolbar]').forEach(function (toolbar) {
    var input = toolbar.querySelector('[data-overview-search]');
    var chipsWrap = toolbar.querySelector('[data-overview-filter]');
    var countEl = toolbar.querySelector('[data-overview-count]');
    var table = toolbar.nextElementSibling;
    if (!input || !table) return;
    var rows = Array.prototype.slice.call(table.querySelectorAll('[data-overview-row]'));
    var sumEl = table.querySelector('[data-overview-sum]');
    var sumPriceEl = table.querySelector('[data-overview-sum-price]');

    function formatKc(amount) {
      return amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Kč';
    }

    function render() {
      var query = input.value.trim().toLowerCase();
      var activeChip = chipsWrap ? chipsWrap.querySelector('.filter-chip.is-active') : null;
      var filterValue = activeChip ? activeChip.getAttribute('data-filter-value') : '';
      var visibleCount = 0;
      var sum = 0;
      var sumAmount = 0;
      rows.forEach(function (row) {
        var name = row.getAttribute('data-name') || '';
        var matchesSearch = name.indexOf(query) !== -1;
        var matchesFilter = !filterValue || row.getAttribute('data-filter') === filterValue;
        var visible = matchesSearch && matchesFilter;
        row.hidden = !visible;
        if (visible) {
          visibleCount++;
          sum += parseInt(row.getAttribute('data-count'), 10) || 0;
          sumAmount += parseInt(row.getAttribute('data-amount'), 10) || 0;
        }
      });
      if (countEl) countEl.textContent = visibleCount + '/' + rows.length;
      if (sumEl) sumEl.textContent = sum;
      if (sumPriceEl) sumPriceEl.textContent = formatKc(sumAmount);
    }

    input.addEventListener('input', render);
    if (chipsWrap) {
      chipsWrap.querySelectorAll('.filter-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
          chipsWrap.querySelectorAll('.filter-chip').forEach(function (c) {
            c.classList.remove('is-active');
          });
          chip.classList.add('is-active');
          render();
        });
      });
    }

    render();
  });

  // Mesicni prehled: clicking a roster row shows that person's day-by-day
  // order breakdown (date, meal, price) in the "Detail mesice" card below,
  // instead of navigating anywhere — the data for every row is already on
  // the page via data-orders, so this needs no server round-trip.
  document.querySelectorAll('[data-month-detail-body]').forEach(function (detailBody) {
    var stack = detailBody.closest('.stack');
    var table = stack ? stack.querySelector('.overview-table') : null;
    if (!table) return;
    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody [data-overview-row]'));
    var card = detailBody.closest('.form-card');
    var exportBtn = card ? card.querySelector('[data-month-detail-export]') : null;

    function escapeHtml(text) {
      var div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    var CZECH_DAY_ABBR = ['Ne', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So'];

    function formatDate(ymd) {
      var parts = ymd.split('-').map(function (p) { return parseInt(p, 10); });
      // Built from y/m/d parts (not new Date(ymd)) so this reads as the
      // calendar date it names regardless of the browser's own timezone.
      var date = new Date(parts[0], parts[1] - 1, parts[2]);
      var dayAbbr = CZECH_DAY_ABBR[date.getDay()];
      return parts[2] + '. ' + parts[1] + '. ' + parts[0] + ' (' + dayAbbr + ')';
    }

    function formatKc(amount) {
      return amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Kč';
    }

    function clearSelection() {
      rows.forEach(function (r) { r.classList.remove('is-selected'); });
      detailBody.innerHTML = '<p class="hint-text">Není vybrán uživatel</p>';
      if (exportBtn) exportBtn.disabled = true;
    }

    rows.forEach(function (row) {
      row.addEventListener('click', function () {
        if (row.classList.contains('is-selected')) {
          clearSelection();
          return;
        }

        rows.forEach(function (r) { r.classList.remove('is-selected'); });
        row.classList.add('is-selected');
        if (exportBtn) exportBtn.disabled = false;

        var displayName = row.getAttribute('data-display-name') || '';
        var orders = [];
        try {
          orders = JSON.parse(row.getAttribute('data-orders') || '[]');
        } catch (e) {
          orders = [];
        }

        var total = 0;
        var rowsHtml = orders.map(function (o) {
          total += o.price;
          return '<tr><td>' + formatDate(o.date) + '</td><td>' + escapeHtml(o.meal) + '</td><td>' + formatKc(o.price) + '</td></tr>';
        }).join('');

        var summaryHtml =
          '<div class="month-detail-summary">' +
          '<span>' + escapeHtml(displayName) + '</span>' +
          '<span>Počet obědů: ' + orders.length + '</span>' +
          '<span>Celková cena: ' + formatKc(total) + '</span>' +
          '</div>';

        if (orders.length === 0) {
          detailBody.innerHTML = summaryHtml + '<p class="hint-text">Tento měsíc nemá žádné objednané obědy.</p>';
          return;
        }

        detailBody.innerHTML =
          summaryHtml +
          '<table class="price-table month-detail-table">' +
          '<thead><tr><th>Datum</th><th>Jídlo</th><th>Cena</th></tr></thead>' +
          '<tbody>' + rowsHtml + '</tbody>' +
          '</table>';
      });
    });

    // "Export" reuses the browser's own print dialog (which offers "Save
    // as PDF") rather than pulling in a PDF-generation library. The
    // current detail is cloned into a dedicated print-only element at the
    // end of body — hiding the rest of the page with display:none (not
    // visibility) there is what keeps the printed output to a single page
    // instead of several blank ones, since visibility:hidden alone still
    // reserves the whole page's normal layout height.
    if (exportBtn) {
      exportBtn.addEventListener('click', function () {
        if (exportBtn.disabled) return;
        var printArea = document.getElementById('month-detail-print-area');
        if (!printArea) {
          printArea = document.createElement('div');
          printArea.id = 'month-detail-print-area';
          document.body.appendChild(printArea);
        }
        printArea.innerHTML = detailBody.innerHTML;
        document.documentElement.classList.add('printing-month-detail');
        window.print();
      });
    }
  });

  window.addEventListener('afterprint', function () {
    document.documentElement.classList.remove('printing-month-detail');
  });
})();
