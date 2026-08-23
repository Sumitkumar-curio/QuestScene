/* =====================================================================
   QuestScene — Tic-Tac-Toe
   Two players on one phone, or against the computer. "Unbeatable" is
   full minimax, so a draw is genuinely the best a human can manage.
   ===================================================================== */
QSGame.mount(function (stage) {
  'use strict';

  var LINES = [
    [0,1,2],[3,4,5],[6,7,8],   // rows
    [0,3,6],[1,4,7],[2,5,8],   // columns
    [0,4,8],[2,4,6]            // diagonals
  ];

  var mode = 'hard';           // easy | hard | two
  var board, turn, over, streak = 0, wins = 0, draws = 0, losses = 0;
  var startedAt;
  var boardEl, stats, statusEl;

  function reset(keepScore) {
    board = new Array(9).fill('');
    turn = 'X';
    over = false;
    startedAt = Date.now();
    if (!keepScore) { wins = 0; draws = 0; losses = 0; streak = 0; }
    render();
    setStatus(mode === 'two' ? 'X to play' : 'Your move — you are X');
    var msg = stage.querySelector('.game-msg');
    if (msg) msg.remove();
  }

  function winner(b) {
    for (var i = 0; i < LINES.length; i++) {
      var L = LINES[i];
      if (b[L[0]] && b[L[0]] === b[L[1]] && b[L[1]] === b[L[2]]) {
        return { player: b[L[0]], line: L };
      }
    }
    return b.indexOf('') === -1 ? { player: 'draw', line: [] } : null;
  }

  function freeCells(b) {
    var out = [];
    for (var i = 0; i < 9; i++) if (!b[i]) out.push(i);
    return out;
  }

  /** Minimax with depth preference, so it wins fast and loses slow. */
  function minimax(b, player, depth) {
    var w = winner(b);
    if (w) {
      if (w.player === 'O') return { score: 10 - depth };
      if (w.player === 'X') return { score: depth - 10 };
      return { score: 0 };
    }

    var free = freeCells(b);
    var best = null;

    for (var i = 0; i < free.length; i++) {
      var idx = free[i];
      b[idx] = player;
      var res = minimax(b, player === 'O' ? 'X' : 'O', depth + 1);
      b[idx] = '';

      var val = res.score;
      if (best === null ||
          (player === 'O' && val > best.score) ||
          (player === 'X' && val < best.score)) {
        best = { score: val, move: idx };
      }
    }
    return best;
  }

  function computerMove() {
    var free = freeCells(board);
    if (!free.length) return;

    var idx;
    if (mode === 'easy') {
      // Blocks or wins when it is obvious, otherwise plays at random.
      idx = findImmediate('O') ?? findImmediate('X') ?? free[Math.floor(Math.random() * free.length)];
    } else {
      idx = minimax(board.slice(), 'O', 0).move;
    }

    board[idx] = 'O';
    turn = 'X';
    render();
    checkEnd();
  }

  /** A cell that immediately completes a line for `p`, or null. */
  function findImmediate(p) {
    for (var i = 0; i < LINES.length; i++) {
      var L = LINES[i];
      var vals = [board[L[0]], board[L[1]], board[L[2]]];
      var count = vals.filter(function (v) { return v === p; }).length;
      var empty = vals.indexOf('');
      if (count === 2 && empty !== -1) return L[empty];
    }
    return null;
  }

  function play(i) {
    if (over || board[i]) return;

    if (mode === 'two') {
      board[i] = turn;
      turn = turn === 'X' ? 'O' : 'X';
      render();
      if (!checkEnd()) setStatus(turn + ' to play');
      return;
    }

    if (turn !== 'X') return;
    board[i] = 'X';
    turn = 'O';
    render();

    if (checkEnd()) return;
    setStatus('Computer thinking…');
    setTimeout(computerMove, 220);
  }

  function checkEnd() {
    var w = winner(board);
    if (!w) return false;

    over = true;
    render(w.line);

    if (mode === 'two') {
      setStatus(w.player === 'draw' ? "It's a draw" : w.player + ' wins');
      QSGame.finish(stage, {
        text: w.player === 'draw' ? 'Draw. Play again?' : w.player + ' takes it.',
        tone: w.player === 'draw' ? '' : 'win'
      });
      return true;
    }

    if (w.player === 'X') { wins++; streak++; }
    else if (w.player === 'draw') { draws++; }
    else { losses++; streak = 0; }

    updateStats();

    var text = w.player === 'X' ? 'You win.'
             : w.player === 'draw' ? 'Draw.'
             : 'Computer wins.';
    if (mode === 'hard' && w.player === 'draw') {
      text = 'Draw — which is the best possible result against Unbeatable.';
    }
    setStatus(text);

    // Only the solo modes are scored: a win is worth more than a hard-earned draw.
    var points = (w.player === 'X' ? 100 : w.player === 'draw' ? 40 : 10)
               * (mode === 'hard' ? 3 : 1)
               + streak * 10;

    QSGame.finish(stage, {
      text: text,
      tone: w.player === 'X' ? 'win' : w.player === 'draw' ? '' : 'lose',
      score: points,
      duration: Date.now() - startedAt,
      detail: mode + ', streak ' + streak
    });
    return true;
  }

  function setStatus(t) { statusEl.textContent = t; }

  function updateStats() {
    stats.wins.textContent = String(wins);
    stats.draws.textContent = String(draws);
    stats.losses.textContent = String(losses);
  }

  function render(winLine) {
    boardEl.innerHTML = '';
    for (var i = 0; i < 9; i++) {
      (function (idx) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = board[idx];
        b.className = board[idx] ? board[idx].toLowerCase() : '';
        if (winLine && winLine.indexOf(idx) !== -1) b.classList.add('win');
        b.disabled = over || !!board[idx];
        b.setAttribute('aria-label', 'Square ' + (idx + 1) + (board[idx] ? ', ' + board[idx] : ', empty'));
        b.addEventListener('click', function () { play(idx); });
        boardEl.appendChild(b);
      })(i);
    }
  }

  /* ---------------- UI ---------------- */
  var seg = QSGame.el('div', 'seg');
  [['easy', 'Easy'], ['hard', 'Unbeatable'], ['two', '2 players']].forEach(function (m) {
    var b = document.createElement('button');
    b.type = 'button';
    b.textContent = m[1];
    b.className = m[0] === mode ? 'on' : '';
    b.addEventListener('click', function () {
      mode = m[0];
      seg.querySelectorAll('button').forEach(function (x) { x.classList.remove('on'); });
      b.classList.add('on');
      reset();
    });
    seg.appendChild(b);
  });
  stage.appendChild(seg);
  stage.appendChild(QSGame.el('div', '', '<div style="height:12px"></div>'));

  stats = QSGame.statBar(stage, [
    { id: 'wins',   label: 'Won' },
    { id: 'draws',  label: 'Drawn' },
    { id: 'losses', label: 'Lost' }
  ]);

  statusEl = QSGame.el('div', 'muted', '');
  statusEl.style.cssText = 'text-align:center;font-size:.9rem;font-weight:700;margin-bottom:12px';
  stage.appendChild(statusEl);

  boardEl = QSGame.el('div', 'ttt');
  stage.appendChild(boardEl);

  var actions = QSGame.el('div', 'game-actions');
  actions.appendChild(QSGame.button('Play again', 'btn-grad btn-sm', function () { reset(true); }));
  actions.appendChild(QSGame.button('Reset score', 'btn-ghost btn-sm', function () { reset(false); }));
  stage.appendChild(actions);

  reset();
});
