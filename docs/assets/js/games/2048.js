/* =====================================================================
   QuestScene — 2048
   Classic rules: slide, merge equal neighbours once per move, spawn a
   2 (90%) or 4 (10%) in a random empty cell after any move that changed
   the board.
   ===================================================================== */
QSGame.mount(function (stage, ctx) {
  'use strict';

  var N = 4;
  var grid, score, best, startedAt, over, won, rand;
  var boardEl, stats;

  best = parseInt(localStorage.getItem('qs2048best') || '0', 10) || 0;

  function reset() {
    rand = QSGame.rng(ctx.seed + (ctx.daily ? 0 : Date.now() % 100000));
    grid = [];
    for (var i = 0; i < N * N; i++) grid.push(0);
    score = 0; over = false; won = false;
    startedAt = Date.now();
    spawn(); spawn();
    render(true);
    var msg = stage.querySelector('.game-msg');
    if (msg) msg.remove();
  }

  function emptyCells() {
    var out = [];
    for (var i = 0; i < grid.length; i++) if (grid[i] === 0) out.push(i);
    return out;
  }

  function spawn() {
    var free = emptyCells();
    if (!free.length) return null;
    var idx = free[Math.floor(rand() * free.length)];
    grid[idx] = rand() < 0.9 ? 2 : 4;
    return idx;
  }

  /* Collapse one line (array of 4 values) toward index 0. */
  function collapse(line) {
    var vals = line.filter(function (v) { return v !== 0; });
    var out = [], gained = 0, merged = [];

    for (var i = 0; i < vals.length; i++) {
      if (i + 1 < vals.length && vals[i] === vals[i + 1]) {
        var v = vals[i] * 2;
        out.push(v); merged.push(out.length - 1);
        gained += v;
        if (v === 2048) won = true;
        i++;                       // consume the partner; no chain merges
      } else {
        out.push(vals[i]);
      }
    }
    while (out.length < N) out.push(0);
    return { line: out, gained: gained, merged: merged };
  }

  /* Index maths for reading a line in each direction. */
  function lineIndices(dir, k) {
    var idx = [];
    for (var i = 0; i < N; i++) {
      if (dir === 'left')  idx.push(k * N + i);
      if (dir === 'right') idx.push(k * N + (N - 1 - i));
      if (dir === 'up')    idx.push(i * N + k);
      if (dir === 'down')  idx.push((N - 1 - i) * N + k);
    }
    return idx;
  }

  function move(dir) {
    if (over) return;

    var changed = false;
    var mergedCells = [];

    for (var k = 0; k < N; k++) {
      var idx = lineIndices(dir, k);
      var line = idx.map(function (i) { return grid[i]; });
      var res = collapse(line);

      for (var i = 0; i < N; i++) {
        if (grid[idx[i]] !== res.line[i]) changed = true;
        grid[idx[i]] = res.line[i];
      }
      res.merged.forEach(function (m) { mergedCells.push(idx[m]); });
      score += res.gained;
    }

    if (!changed) return;

    var newIdx = spawn();
    render(false, newIdx, mergedCells);

    if (score > best) {
      best = score;
      try { localStorage.setItem('qs2048best', String(best)); } catch (e) {}
    }

    if (!canMove()) endGame();
    else if (won) {
      won = false;                                     // only celebrate once
      var m = QSGame.el('div', 'game-msg win', 'You hit 2048. Keep going for a bigger number.');
      stage.appendChild(m);
      setTimeout(function () { if (m.parentNode) m.remove(); }, 4000);
    }
  }

  function canMove() {
    if (emptyCells().length) return true;
    for (var r = 0; r < N; r++) {
      for (var c = 0; c < N; c++) {
        var v = grid[r * N + c];
        if (c + 1 < N && grid[r * N + c + 1] === v) return true;
        if (r + 1 < N && grid[(r + 1) * N + c] === v) return true;
      }
    }
    return false;
  }

  function endGame() {
    over = true;
    var top = Math.max.apply(null, grid);
    QSGame.finish(stage, {
      text: 'No moves left. Highest tile: ' + top + '.',
      tone: 'lose',
      score: score,
      duration: Date.now() - startedAt,
      detail: 'best tile ' + top
    });
  }

  function tileClass(v) {
    return v <= 2048 ? 't' + v : 'tbig';
  }

  function render(full, newIdx, mergedCells) {
    stats.score.textContent = score.toLocaleString();
    stats.best.textContent = Math.max(best, score).toLocaleString();

    // Remove previous tiles, keep the static background cells.
    boardEl.querySelectorAll('.g2048-tile').forEach(function (t) { t.remove(); });

    var gap = 8;
    var size = (boardEl.clientWidth - gap * (N + 1)) / N;

    for (var i = 0; i < grid.length; i++) {
      if (!grid[i]) continue;
      var r = Math.floor(i / N), c = i % N;
      var t = QSGame.el('div', 'g2048-tile ' + tileClass(grid[i]), String(grid[i]));

      t.style.width = size + 'px';
      t.style.height = size + 'px';
      t.style.left = '0';
      t.style.top = '0';
      t.style.transform = 'translate(' + (gap + c * (size + gap)) + 'px,' +
                                        (gap + r * (size + gap)) + 'px)';
      t.style.fontSize = (grid[i] > 999 ? size * 0.30 : size * 0.42) + 'px';

      if (!full && i === newIdx) t.classList.add('new');
      if (mergedCells && mergedCells.indexOf(i) !== -1) t.classList.add('merged');

      boardEl.appendChild(t);
    }
  }

  /* ---------------- build the UI ---------------- */
  stats = QSGame.statBar(stage, [
    { id: 'score', label: 'Score' },
    { id: 'best',  label: 'Best' }
  ]);

  boardEl = QSGame.el('div', 'g2048');
  for (var i = 0; i < N * N; i++) boardEl.appendChild(QSGame.el('div', 'g2048-cell'));
  stage.appendChild(boardEl);

  var actions = QSGame.el('div', 'game-actions');
  actions.appendChild(QSGame.button('New game', 'btn-grad btn-sm', reset));
  actions.appendChild(QSGame.el('span', 'muted',
    '<span style="font-size:.82rem">Arrow keys, WASD, or swipe</span>'));
  stage.appendChild(actions);

  QSGame.onDirection(boardEl, move);
  window.addEventListener('resize', function () { render(true); });

  reset();
});
