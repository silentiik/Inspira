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
})();
