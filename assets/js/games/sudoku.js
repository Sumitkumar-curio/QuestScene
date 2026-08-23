/* =====================================================================
   QuestScene — Sudoku
   Puzzles are generated at runtime: fill a full valid grid by randomised
   backtracking, then remove clues while a uniqueness check still passes.
   Every puzzle therefore has exactly one solution.
   ===================================================================== */
QSGame.mount(function (stage, ctx) {
  'use strict';

  var LEVELS = { Easy: 40, Medium: 32, Hard: 27, Expert: 24 };  // clues remaining
  var level = 'Easy';

  var solution, puzzle, board, fixed, selected = -1, mistakes = 0;
  var startedAt, timerId, finished = false;
  var gridEl, stats, rand;

  /* ---------------- generator ---------------- */

  function canPlace(g, i, v) {
    var r = Math.floor(i / 9), c = i % 9;
    for (var k = 0; k < 9; k++) {
      if (g[r * 9 + k] === v) return false;
      if (g[k * 9 + c] === v) return false;
    }
    var br = Math.floor(r / 3) * 3, bc = Math.floor(c / 3) * 3;
    for (var a = 0; a < 3; a++) {
      for (var b = 0; b < 3; b++) {
        if (g[(br + a) * 9 + bc + b] === v) return false;
      }
    }
    return true;
  }

  function fill(g) {
    var i = g.indexOf(0);
    if (i === -1) return true;

    var vals = QSGame.shuffle([1, 2, 3, 4, 5, 6, 7, 8, 9], rand);
    for (var k = 0; k < 9; k++) {
      if (canPlace(g, i, vals[k])) {
        g[i] = vals[k];
        if (fill(g)) return true;
        g[i] = 0;
      }
    }
    return false;
  }

  /** Count solutions, bailing out at 2 — that is all we need to know. */
  function countSolutions(g, cap) {
    var i = g.indexOf(0);
    if (i === -1) return 1;

    var total = 0;
    for (var v = 1; v <= 9; v++) {
      if (canPlace(g, i, v)) {
        g[i] = v;
        total += countSolutions(g, cap);
        g[i] = 0;
        if (total >= cap) return total;
      }
    }
    return total;
  }

  function generate(clues) {
    solution = new Array(81).fill(0);
    fill(solution);

    puzzle = solution.slice();
    var order = QSGame.shuffle(
      Array.from({ length: 81 }, function (_, i) { return i; }), rand
    );
    var removed = 0, target = 81 - clues;

    for (var k = 0; k < order.length && removed < target; k++) {
      var i = order[k], keep = puzzle[i];
      puzzle[i] = 0;

      if (countSolutions(puzzle.slice(), 2) !== 1) {
        puzzle[i] = keep;          // removing it created ambiguity — put it back
      } else {
        removed++;
      }
    }
  }

  /* ---------------- game state ---------------- */

  function reset() {
    rand = QSGame.rng(ctx.seed + (ctx.daily ? 0 : Date.now() % 100000) + level.length);
    gridEl.innerHTML = '<div class="game-loading">Generating a puzzle…</div>';

    // Yield a frame so the loading text paints before the solver blocks.
    setTimeout(function () {
      generate(LEVELS[level]);
      board = puzzle.slice();
      fixed = puzzle.map(function (v) { return v !== 0; });
      selected = -1; mistakes = 0; finished = false;
      startedAt = Date.now();

      buildGrid();
      updateStats();
      clearInterval(timerId);
      timerId = setInterval(updateStats, 1000);

      var msg = stage.querySelector('.game-msg');
      if (msg) msg.remove();
    }, 30);
  }

  function buildGrid() {
    gridEl.innerHTML = '';
    gridEl.className = 'sudoku';

    for (var i = 0; i < 81; i++) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'sk-cell' + (fixed[i] ? ' fixed' : '') +
                    (Math.floor(i / 9) % 3 === 2 ? ' sk-row3' : '');
      b.dataset.i = i;
      b.textContent = board[i] || '';
      b.addEventListener('click', onCell);
      gridEl.appendChild(b);
    }
    paint();
  }

  function onCell(ev) {
    var i = parseInt(ev.currentTarget.dataset.i, 10);
    if (fixed[i] || finished) return;
    selected = i;
    paint();
  }

  function place(v) {
    if (selected < 0 || finished || fixed[selected]) return;

    if (v === 0) { board[selected] = 0; paint(); return; }

    board[selected] = v;

    if (v !== solution[selected]) {
      mistakes++;
      updateStats();
    }
    paint();

    if (board.every(function (x, i) { return x === solution[i]; })) win();
  }

  function paint() {
    var cells = gridEl.querySelectorAll('.sk-cell');
    var selVal = selected >= 0 ? board[selected] : 0;
    var sr = selected >= 0 ? Math.floor(selected / 9) : -1;
    var sc = selected >= 0 ? selected % 9 : -1;
    var sb = selected >= 0 ? Math.floor(sr / 3) * 3 + Math.floor(sc / 3) : -1;

    for (var i = 0; i < 81; i++) {
      var c = cells[i];
      var r = Math.floor(i / 9), col = i % 9;
      var bx = Math.floor(r / 3) * 3 + Math.floor(col / 3);

      c.textContent = board[i] || '';
      c.classList.toggle('sel', i === selected);
      c.classList.toggle('peer', selected >= 0 && i !== selected && (r === sr || col === sc || bx === sb));
      c.classList.toggle('same', selVal > 0 && board[i] === selVal && i !== selected);
      c.classList.toggle('bad', board[i] !== 0 && !fixed[i] && board[i] !== solution[i]);
    }
  }

  function updateStats() {
    stats.time.textContent = QSGame.fmtTime(Date.now() - startedAt);
    stats.mistakes.textContent = String(mistakes);
    stats.level.textContent = level;
  }

  function win() {
    finished = true;
    clearInterval(timerId);

    var secs = Math.round((Date.now() - startedAt) / 1000);
    var base = { Easy: 3000, Medium: 5000, Hard: 8000, Expert: 12000 }[level];

    // Fast and clean scores best; never drops below a floor so finishing always counts.
    var score = Math.max(200, Math.round(base - secs * 2 - mistakes * 150));

    QSGame.finish(stage, {
      text: 'Solved in ' + QSGame.fmtTime(Date.now() - startedAt) +
            ' with ' + mistakes + ' mistake' + (mistakes === 1 ? '' : 's') + '.',
      tone: 'win',
      score: score,
      duration: Date.now() - startedAt,
      detail: level + ', ' + secs + 's'
    });
  }

  /* ---------------- UI ---------------- */

  var seg = QSGame.el('div', 'seg');
  Object.keys(LEVELS).forEach(function (name) {
    var b = document.createElement('button');
    b.type = 'button';
    b.textContent = name;
    b.className = name === level ? 'on' : '';
    b.addEventListener('click', function () {
      level = name;
      seg.querySelectorAll('button').forEach(function (x) { x.classList.remove('on'); });
      b.classList.add('on');
      reset();
    });
    seg.appendChild(b);
  });
  stage.appendChild(seg);
  stage.appendChild(QSGame.el('div', '', '<div style="height:12px"></div>'));

  stats = QSGame.statBar(stage, [
    { id: 'time',      label: 'Time',      value: '0:00' },
    { id: 'mistakes',  label: 'Mistakes',  value: '0' },
    { id: 'level',     label: 'Level',     value: level }
  ]);

  gridEl = QSGame.el('div', 'sudoku');
  stage.appendChild(gridEl);

  var pad = QSGame.el('div', 'sk-pad');
  for (var n = 1; n <= 9; n++) {
    (function (v) {
      var b = document.createElement('button');
      b.type = 'button'; b.textContent = String(v);
      b.addEventListener('click', function () { place(v); });
      pad.appendChild(b);
    })(n);
  }
  var erase = document.createElement('button');
  erase.type = 'button'; erase.className = 'wide'; erase.textContent = 'Erase';
  erase.addEventListener('click', function () { place(0); });
  pad.appendChild(erase);
  stage.appendChild(pad);

  var actions = QSGame.el('div', 'game-actions');
  actions.appendChild(QSGame.button('New puzzle', 'btn-grad btn-sm', reset));
  stage.appendChild(actions);

  document.addEventListener('keydown', function (ev) {
    if (ev.key >= '1' && ev.key <= '9') place(parseInt(ev.key, 10));
    else if (ev.key === 'Backspace' || ev.key === 'Delete' || ev.key === '0') place(0);
    else if (selected >= 0) {
      var r = Math.floor(selected / 9), c = selected % 9;
      if (ev.key === 'ArrowUp'    && r > 0) { selected -= 9; paint(); ev.preventDefault(); }
      if (ev.key === 'ArrowDown'  && r < 8) { selected += 9; paint(); ev.preventDefault(); }
      if (ev.key === 'ArrowLeft'  && c > 0) { selected -= 1; paint(); ev.preventDefault(); }
      if (ev.key === 'ArrowRight' && c < 8) { selected += 1; paint(); ev.preventDefault(); }
    }
  });

  reset();
});
