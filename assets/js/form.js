// Tabbed enrollment / contact forms with client-side validation.
// No backend is attached — on success we prepare a mailto: draft to
// centruminspira@gmail.com so a real member of staff still receives it.
(function () {
  var CONTACT_EMAIL = 'centruminspira@gmail.com';

  function showTab(tabId) {
    document.querySelectorAll('.form-tab').forEach(function (t) {
      t.classList.toggle('is-active', t.dataset.tab === tabId);
    });
    document.querySelectorAll('.form-panel').forEach(function (p) {
      p.classList.toggle('is-active', p.id === tabId);
    });
  }

  function initTabs() {
    var tabs = document.querySelectorAll('.form-tab');
    if (!tabs.length) return;
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () { showTab(tab.dataset.tab); });
    });

    var hash = location.hash.replace('#', '');
    if (hash && document.getElementById(hash)) {
      showTab(hash);
    }
  }

  function validateField(field) {
    var input = field.querySelector('input, select, textarea');
    if (!input) return true;
    var valid = input.checkValidity();
    field.classList.toggle('has-error', !valid);
    return valid;
  }

  function buildMailBody(form) {
    var lines = [];
    form.querySelectorAll('[data-label]').forEach(function (input) {
      var label = input.dataset.label;
      var value = input.type === 'checkbox' ? (input.checked ? 'Ano' : 'Ne') : input.value;
      if (value) lines.push(label + ': ' + value);
    });
    return lines.join('%0D%0A');
  }

  function initForm(form) {
    var fields = Array.prototype.slice.call(form.querySelectorAll('.field'));
    var alertBox = form.querySelector('.alert');

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var allValid = fields.reduce(function (ok, field) {
        return validateField(field) && ok;
      }, true);

      if (!allValid) {
        if (alertBox) {
          alertBox.className = 'alert alert--error is-visible';
          alertBox.textContent = 'Zkontrolujte prosím zvýrazněná pole.';
        }
        return;
      }

      var subject = encodeURIComponent(form.dataset.subject || 'Zpráva z webu INSPIRA');
      var body = buildMailBody(form);
      var mailto = 'mailto:' + CONTACT_EMAIL + '?subject=' + subject + '&body=' + body;

      if (alertBox) {
        alertBox.className = 'alert alert--success is-visible';
        alertBox.textContent = 'Děkujeme! Otevíráme vám e-mailového klienta s předvyplněnou zprávou — stačí ji odeslat. Pokud se nic neotevře, napište nám přímo na ' + CONTACT_EMAIL + '.';
      }

      window.location.href = mailto;
    });

    fields.forEach(function (field) {
      var input = field.querySelector('input, select, textarea');
      if (input) {
        input.addEventListener('blur', function () { validateField(field); });
      }
    });
  }

  initTabs();
  document.querySelectorAll('.js-validated-form').forEach(initForm);
})();
