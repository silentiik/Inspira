// Interactive price calculator for INSPIRKA and Domškolácká akademie.
// Prices match the published ceník (per-month, per child).
(function () {
  var PRICING = {
    inspirka: {
      label: 'INSPIRKA — dětský klub (3–6 let)',
      schedule: 'Po–Pá, 8:00–16:00',
      days: [
        { value: 2, price: 3890, note: 'minimum' },
        { value: 3, price: 5590 },
        { value: 4, price: 6790 },
        { value: 5, price: 7990 }
      ]
    },
    domskolaci: {
      label: 'Domškolácká akademie (1.–9. ročník)',
      schedule: 'Po–St, 9:00–14:00',
      days: [
        { value: 2, price: 3690, note: 'minimum' },
        { value: 3, price: 5390 }
      ]
    }
  };

  var SIBLING_DISCOUNT = 0.10;

  var state = {
    program: 'inspirka',
    days: 5,
    sibling: false
  };

  var els = {};

  function formatKc(amount) {
    return Math.round(amount).toLocaleString('cs-CZ') + ' Kč';
  }

  function currentDayOption() {
    var program = PRICING[state.program];
    var match = program.days.find(function (d) { return d.value === state.days; });
    return match || program.days[program.days.length - 1];
  }

  function render() {
    var program = PRICING[state.program];

    // Program chips
    els.programChips.forEach(function (chip) {
      chip.classList.toggle('is-selected', chip.dataset.program === state.program);
    });

    // Rebuild day chips for the selected program
    els.daysWrap.innerHTML = '';
    program.days.forEach(function (d) {
      var chip = document.createElement('div');
      chip.className = 'option-chip' + (d.value === state.days ? ' is-selected' : '');
      chip.textContent = d.value + ' dny/dní' + (d.note ? ' (' + d.note + ')' : '');
      chip.addEventListener('click', function () {
        state.days = d.value;
        render();
      });
      els.daysWrap.appendChild(chip);
    });

    if (!program.days.some(function (d) { return d.value === state.days; })) {
      state.days = program.days[program.days.length - 1].value;
    }

    var dayOption = currentDayOption();
    var base = dayOption.price;
    var discount = state.sibling ? base * SIBLING_DISCOUNT : 0;
    var total = base - discount;

    els.scheduleText.textContent = program.schedule;
    els.summaryProgram.textContent = program.label;
    els.summaryDays.textContent = state.days + ' dny/dní týdně';
    els.summaryBase.textContent = formatKc(base);
    els.summaryDiscountRow.style.display = state.sibling ? 'flex' : 'none';
    els.summaryDiscount.textContent = '−' + formatKc(discount);
    els.summaryTotal.textContent = formatKc(total);
  }

  function init() {
    els.programChips = Array.prototype.slice.call(document.querySelectorAll('.js-program-chip'));
    els.daysWrap = document.getElementById('calcDays');
    els.siblingToggle = document.getElementById('calcSibling');
    els.scheduleText = document.getElementById('calcSchedule');
    els.summaryProgram = document.getElementById('summaryProgram');
    els.summaryDays = document.getElementById('summaryDays');
    els.summaryBase = document.getElementById('summaryBase');
    els.summaryDiscountRow = document.getElementById('summaryDiscountRow');
    els.summaryDiscount = document.getElementById('summaryDiscount');
    els.summaryTotal = document.getElementById('summaryTotal');

    if (!els.daysWrap) return;

    els.programChips.forEach(function (chip) {
      chip.addEventListener('click', function () {
        state.program = chip.dataset.program;
        state.days = PRICING[state.program].days[PRICING[state.program].days.length - 1].value;
        render();
      });
    });

    els.siblingToggle.addEventListener('change', function () {
      state.sibling = els.siblingToggle.checked;
      render();
    });

    render();
  }

  init();
})();
