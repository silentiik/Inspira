// Interactive weekly schedule for INSPIRA — filterable by program.
(function () {
  var PROGRAMS = {
    inspirka: { label: 'INSPIRKA', color: '#d88a95' },
    domskolaci: { label: 'Domškolácká akademie', color: '#f2b872' },
    krouzky: { label: 'Kroužky', color: '#6fae7f' }
  };

  var EVENTS = [
    { day: 'Po', start: '8:00', end: '16:00', program: 'inspirka', title: 'INSPIRKA — celý den', sub: 'Hra, tvoření, pobyt venku' },
    { day: 'Po', start: '9:00', end: '14:00', program: 'domskolaci', title: 'Domškolácká akademie', sub: 'Výuka v malé skupince' },
    { day: 'Po', start: '15:30', end: '16:15', program: 'krouzky', title: 'Hravá angličtina s Miškou', sub: '4–7 let' },

    { day: 'Út', start: '8:00', end: '16:00', program: 'inspirka', title: 'INSPIRKA — celý den', sub: 'Hra, tvoření, pobyt venku' },
    { day: 'Út', start: '9:00', end: '14:00', program: 'domskolaci', title: 'Domškolácká akademie', sub: 'Výuka v malé skupince' },
    { day: 'Út', start: '16:00', end: '16:45', program: 'krouzky', title: 'Muzikohrátky', sub: '3–8 let' },

    { day: 'St', start: '8:00', end: '16:00', program: 'inspirka', title: 'INSPIRKA — celý den', sub: 'Hra, tvoření, pobyt venku' },
    { day: 'St', start: '9:00', end: '14:00', program: 'domskolaci', title: 'Domškolácká akademie', sub: 'Výuka v malé skupince' },
    { day: 'St', start: '15:00', end: '15:45', program: 'krouzky', title: 'Tanečky', sub: '4–9 let' },

    { day: 'Čt', start: '8:00', end: '16:00', program: 'inspirka', title: 'INSPIRKA — celý den', sub: 'Hra, tvoření, pobyt venku' },
    { day: 'Čt', start: '15:30', end: '16:15', program: 'krouzky', title: 'Hravá angličtina s Miškou', sub: '4–7 let' },

    { day: 'Pá', start: '8:00', end: '16:00', program: 'inspirka', title: 'INSPIRKA — celý den', sub: 'Hra, tvoření, pobyt venku' },
    { day: 'Pá', start: '15:00', end: '16:00', program: 'krouzky', title: 'Výtvarný kroužek', sub: 'Všechny věkové skupiny' }
  ];

  var DAYS = ['Po', 'Út', 'St', 'Čt', 'Pá'];
  var activeFilters = new Set(Object.keys(PROGRAMS));

  function render() {
    var container = document.getElementById('scheduleDays');
    if (!container) return;
    container.innerHTML = '';

    DAYS.forEach(function (day) {
      var col = document.createElement('div');
      col.className = 'schedule-day-col';

      var heading = document.createElement('h3');
      heading.textContent = day;
      col.appendChild(heading);

      var dayEvents = EVENTS
        .filter(function (e) { return e.day === day && activeFilters.has(e.program); })
        .sort(function (a, b) { return a.start.localeCompare(b.start); });

      if (dayEvents.length === 0) {
        var empty = document.createElement('p');
        empty.className = 'schedule-empty';
        empty.textContent = 'Žádný program';
        col.appendChild(empty);
      } else {
        dayEvents.forEach(function (e) {
          var block = document.createElement('div');
          block.className = 'schedule-block';
          block.style.background = PROGRAMS[e.program].color;
          block.innerHTML = e.start + '–' + e.end + ' · ' + e.title + '<span class="sub">' + e.sub + '</span>';
          col.appendChild(block);
        });
      }

      container.appendChild(col);
    });
  }

  function initFilters() {
    var wrap = document.getElementById('scheduleFilters');
    if (!wrap) return;

    Object.keys(PROGRAMS).forEach(function (key) {
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'filter-chip is-active';
      chip.dataset.program = key;
      chip.innerHTML = '<span class="dot" style="background:' + PROGRAMS[key].color + '"></span>' + PROGRAMS[key].label;
      chip.addEventListener('click', function () {
        if (activeFilters.has(key)) {
          activeFilters.delete(key);
          chip.classList.remove('is-active');
        } else {
          activeFilters.add(key);
          chip.classList.add('is-active');
        }
        render();
      });
      wrap.appendChild(chip);
    });
  }

  initFilters();
  render();
})();
