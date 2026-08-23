/* =====================================================================
   QuestScene — Memory Match
   Eight pairs on a 4x4 board. Score rewards fewer moves and less time.
   ===================================================================== */
QSGame.mount(function (stage, ctx) {
  'use strict';

  var FACES = ['🏃', '🥾', '⚽', '🚴', '🎨', '🎵', '🎮', '☕', '📚', '🚀', '♟️', '🏸'];

  var cards, flipped, matched, moves, startedAt, timerId, busy, finished;
  var boardEl, stats;

  function reset() {
    var rand = QSGame.rng(ctx.seed + (ctx.daily ? 0 : Date.now() % 100000));

    // Pick 8 of the 12 faces, then duplicate and shuffle.
    var picked = QSGame.shuffle(FACES.slice(), rand).slice(0, 8);
    cards = QSGame.shuffle(picked.concat(picked), rand);

    flipped = []; matched = []; moves = 0; busy = false; finished = false;
    startedAt = Date.now();

    build();
    updateStats();
    clearInterval(timerId);
    timerId = setInterval(updateStats, 1000);

    var msg = stage.querySelector('.game-msg');
    if (msg) msg.remove();
  }

  function build() {
    boardEl.innerHTML = '';
    cards.forEach(function (face, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'mcard';
      b.dataset.i = i;
      b.setAttribute('aria-label', 'Card ' + (i + 1));
      b.innerHTML =
        '<span class="mcard-inner">' +
          '<span class="mcard-face mcard-back">?</span>' +
          '<span class="mcard-face mcard-front">' + face + '</span>' +
        '</span>';
      b.addEventListener('click', function () { flip(i); });
      boardEl.appendChild(b);
    });
  }

  function cardEl(i) { return boardEl.querySelector('[data-i="' + i + '"]'); }

  function flip(i) {
    if (busy || finished) return;
    if (matched.indexOf(i) !== -1 || flipped.indexOf(i) !== -1) return;

    flipped.push(i);
    cardEl(i).classList.add('flipped');

    if (flipped.length < 2) return;

    moves++;
    updateStats();

    var a = flipped[0], b = flipped[1];

    if (cards[a] === cards[b]) {
      matched.push(a, b);
      flipped = [];
      cardEl(a).classList.add('done');
      cardEl(b).classList.add('done');

      if (matched.length === cards.length) win();
    } else {
      busy = true;
      setTimeout(function () {
        cardEl(a).classList.remove('flipped');
        cardEl(b).classList.remove('flipped');
        flipped = [];
        busy = false;
      }, 700);
    }
  }

  function updateStats() {
    if (finished) return;
    stats.moves.textContent = String(moves);
    stats.pairs.textContent = (matched.length / 2) + ' / 8';
    stats.time.textContent = QSGame.fmtTime(Date.now() - startedAt);
  }

  function win() {
    finished = true;
    clearInterval(timerId);

    var secs = Math.round((Date.now() - startedAt) / 1000);
    // 8 pairs in 8 moves is perfect; every extra move and second costs.
    var score = Math.max(100, Math.round(5000 - (moves - 8) * 90 - secs * 12));

    QSGame.finish(stage, {
      text: 'All pairs found in ' + moves + ' moves and ' + QSGame.fmtTime(Date.now() - startedAt) + '.',
      tone: 'win',
      score: score,
      duration: Date.now() - startedAt,
      detail: moves + ' moves, ' + secs + 's'
    });
  }

  /* ---------------- UI ---------------- */
  stats = QSGame.statBar(stage, [
    { id: 'pairs', label: 'Pairs', value: '0 / 8' },
    { id: 'moves', label: 'Moves', value: '0' },
    { id: 'time',  label: 'Time',  value: '0:00' }
  ]);

  boardEl = QSGame.el('div', 'memory');
  stage.appendChild(boardEl);

  var actions = QSGame.el('div', 'game-actions');
  actions.appendChild(QSGame.button('New board', 'btn-grad btn-sm', reset));
  stage.appendChild(actions);

  reset();
});
