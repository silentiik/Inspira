// Parent/teacher portal — DEMO ONLY.
// This is a static site with no server, so "accounts" are just two demo
// profiles and all data (news, lunch picks) is saved in this browser's
// localStorage. It is NOT shared between devices or real families — a
// production version would need a real backend with real authentication.

(function () {
  var DEMO_ACCOUNTS = {
    rodic: { name: 'Petra Nováková', role: 'rodic', roleLabel: 'Rodič', initials: 'PN' },
    ucitel: { name: 'Mgr. Jana Sýkorová', role: 'ucitel', roleLabel: 'Učitelka', initials: 'JS' }
  };

  var DAYS = ['Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek'];
  var MENU_OPTIONS = [
    'Polévka + hlavní jídlo (masité)',
    'Polévka + hlavní jídlo (bezmasé)',
    'Polévka + hlavní jídlo (bez lepku)',
    'Bez oběda'
  ];

  var DEFAULT_NEWS = [
    {
      id: 'seed-1',
      pinned: true,
      title: 'Rodinný den v přírodě — 24. 9. 2026',
      author: 'Mgr. Jana Sýkorová',
      date: '2026-09-05',
      body: 'Zveme všechny rodiny na Rodinný den v přírodě, 24. 9. 2026 od 15 do 18 hodin. Akce je zdarma, těšíme se na vás!'
    },
    {
      id: 'seed-2',
      pinned: false,
      title: 'Od září nově flétničky v INSPIRCE',
      author: 'Mgr. Jana Sýkorová',
      date: '2026-08-20',
      body: 'V případě zájmu nabízíme od září výuku hry na zobcovou flétnu přímo v rámci docházky do INSPIRKY.'
    }
  ];

  var SESSION_KEY = 'inspira_portal_session';

  function newsKey() { return 'inspira_portal_news'; }
  function lunchKey(username) { return 'inspira_portal_lunch_' + username; }

  function getSession() {
    try { return JSON.parse(localStorage.getItem(SESSION_KEY)); }
    catch (e) { return null; }
  }
  function setSession(username) {
    localStorage.setItem(SESSION_KEY, JSON.stringify({ username: username }));
  }
  function clearSession() { localStorage.removeItem(SESSION_KEY); }

  function getNews() {
    try {
      var raw = localStorage.getItem(newsKey());
      return raw ? JSON.parse(raw) : DEFAULT_NEWS.slice();
    } catch (e) { return DEFAULT_NEWS.slice(); }
  }
  function saveNews(list) { localStorage.setItem(newsKey(), JSON.stringify(list)); }

  function getLunch(username) {
    try {
      var raw = localStorage.getItem(lunchKey(username));
      return raw ? JSON.parse(raw) : {};
    } catch (e) { return {}; }
  }
  function saveLunch(username, choices) {
    localStorage.setItem(lunchKey(username), JSON.stringify(choices));
  }

  // ---------- Rendering ----------
  function renderNews(account) {
    var wrap = document.getElementById('newsList');
    if (!wrap) return;
    var news = getNews().sort(function (a, b) {
      if (a.pinned !== b.pinned) return a.pinned ? -1 : 1;
      return b.date.localeCompare(a.date);
    });

    wrap.innerHTML = '';
    news.forEach(function (item) {
      var el = document.createElement('div');
      el.className = 'news-item' + (item.pinned ? ' is-pinned' : '');
      el.innerHTML =
        '<h4>' + (item.pinned ? '📌 ' : '') + escapeHtml(item.title) + '</h4>' +
        '<div class="news-meta">' + escapeHtml(item.author) + ' · ' + formatDate(item.date) + '</div>' +
        '<p style="margin:0;">' + escapeHtml(item.body) + '</p>';
      wrap.appendChild(el);
    });

    var composer = document.getElementById('newsComposer');
    if (composer) composer.style.display = account.role === 'ucitel' ? 'block' : 'none';
  }

  function renderLunch(account) {
    var wrap = document.getElementById('lunchWeek');
    if (!wrap) return;
    var choices = getLunch(account.username);

    wrap.innerHTML = '';
    DAYS.forEach(function (day) {
      var row = document.createElement('div');
      row.className = 'lunch-day' + (choices[day] ? ' is-saved' : '');

      var name = document.createElement('span');
      name.className = 'lunch-day-name';
      name.textContent = day;

      var select = document.createElement('select');
      var emptyOpt = document.createElement('option');
      emptyOpt.value = '';
      emptyOpt.textContent = 'Nevybráno';
      select.appendChild(emptyOpt);
      MENU_OPTIONS.forEach(function (opt) {
        var o = document.createElement('option');
        o.value = opt; o.textContent = opt;
        if (choices[day] === opt) o.selected = true;
        select.appendChild(o);
      });
      select.addEventListener('change', function () {
        choices[day] = select.value;
        row.classList.toggle('is-saved', !!select.value);
      });

      row.appendChild(name);
      row.appendChild(select);
      wrap.appendChild(row);
    });

    var saveBtn = document.getElementById('lunchSaveBtn');
    var savedMsg = document.getElementById('lunchSavedMsg');
    if (saveBtn) {
      saveBtn.onclick = function () {
        saveLunch(account.username, choices);
        if (savedMsg) {
          savedMsg.style.display = 'block';
          setTimeout(function () { savedMsg.style.display = 'none'; }, 2500);
        }
        renderLunch(account);
      };
    }
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
  function formatDate(iso) {
    var parts = iso.split('-');
    return parts[2] + '. ' + parts[1] + '. ' + parts[0];
  }

  function showPortal(username) {
    var account = DEMO_ACCOUNTS[username];
    if (!account) return;
    account.username = username;

    document.getElementById('portalLogin').classList.remove('is-active');
    var shell = document.getElementById('portalShell');
    shell.classList.add('is-active');

    document.getElementById('userAvatar').textContent = account.initials;
    document.getElementById('userName').textContent = account.name;
    document.getElementById('userRole').textContent = account.roleLabel;

    renderNews(account);
    renderLunch(account);

    var composerForm = document.getElementById('newsComposerForm');
    if (composerForm) {
      composerForm.onsubmit = function (e) {
        e.preventDefault();
        var title = document.getElementById('newsTitle').value.trim();
        var body = document.getElementById('newsBody').value.trim();
        var pinned = document.getElementById('newsPinned').checked;
        if (!title || !body) return;

        var news = getNews();
        news.unshift({
          id: 'n-' + Date.now(),
          pinned: pinned,
          title: title,
          author: account.name,
          date: new Date().toISOString().slice(0, 10),
          body: body
        });
        saveNews(news);
        composerForm.reset();
        renderNews(account);
      };
    }
  }

  function showLogin() {
    document.getElementById('portalShell').classList.remove('is-active');
    document.getElementById('portalLogin').classList.add('is-active');
  }

  function init() {
    var loginForm = document.getElementById('portalLoginForm');
    var loginError = document.getElementById('loginError');
    if (!loginForm) return;

    loginForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var username = document.getElementById('loginUsername').value.trim().toLowerCase();
      var password = document.getElementById('loginPassword').value;

      // Demo-only check: any password works as long as a valid demo
      // username is used. There is no real authentication here.
      if (DEMO_ACCOUNTS[username] && password.length > 0) {
        loginError.style.display = 'none';
        setSession(username);
        showPortal(username);
      } else {
        loginError.style.display = 'block';
      }
    });

    document.querySelectorAll('.demo-account-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.getElementById('loginUsername').value = btn.dataset.username;
        document.getElementById('loginPassword').value = 'demo';
      });
    });

    var logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', function () {
        clearSession();
        showLogin();
      });
    }

    var session = getSession();
    if (session && DEMO_ACCOUNTS[session.username]) {
      showPortal(session.username);
    }
  }

  init();
})();
