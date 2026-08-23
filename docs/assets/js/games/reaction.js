/* =====================================================================
   QuestScene — Reaction Test
   Five rounds, averaged. Jumping the gun voids that round rather than
   silently giving a fake-fast time.
   ===================================================================== */
QSGame.mount(function (stage) {
  'use strict';

  var ROUNDS = 5;
  var state = 'idle';     // idle | waiting | ready | result | done
  var times, round, greenAt, timeoutId;
  var padEl, roundsEl, stats;

  function reset() {
    times = []; round = 0; state = 'idle';
    clearTimeout(timeoutId);
    paintRounds();
    updateStats();
    setPad('Tap to start', 'Five rounds. Wait for green, then tap as fast as you can.', '');
    var msg = stage.querySelector('.game-msg');
    if (msg) msg.remove();
  }

  function setPad(big, sub, cls) {
    padEl.className = 'reaction ' + cls;
    padEl.innerHTML = '<div><div class="big">' + big + '</div><div class="sub">' + sub + '</div></div>';
  }

  function nextRound() {
    if (round >= ROUNDS) return done();

    state = 'waiting';
    setPad('Wait…', 'Tap the moment it turns green', 'wait');

    // 1.2–4.2s so it can never be anticipated.
    timeoutId = setTimeout(function () {
      state = 'ready';
      greenAt = performance.now();
      setPad('TAP!', '', 'go');
    }, 1200 + Math.random() * 3000);
  }

  function hit() {
    if (state === 'idle' || state === 'result') {
      if (state === 'idle') { round = 0; times = []; paintRounds(); }
      return nextRound();
    }

    if (state === 'waiting') {
      // Too early — void the round rather than reward a lucky guess.
      clearTimeout(timeoutId);
      state = 'result';
      setPad('Too early', 'You tapped before green. Tap to try that round again.', 'early');
      return;
    }

    if (state === 'ready') {
      var ms = Math.round(performance.now() - greenAt);
      times.push(ms);
      round++;
      state = 'result';
      paintRounds();
      updateStats();

      if (round >= ROUNDS) return done();
      setPad(ms + ' ms', 'Tap for round ' + (round + 1) + ' of ' + ROUNDS, '');
    }
  }

  function average() {
    if (!times.length) return 0;
    return Math.round(times.reduce(function (a, b) { return a + b; }, 0) / times.length);
  }

  function done() {
    state = 'done';
    var avg = average();
    var verdict = avg < 200 ? 'Genuinely quick.'
                : avg < 260 ? 'Better than most.'
                : avg < 350 ? 'Solidly average.'
                : 'Room to improve.';

    setPad(avg + ' ms', 'Average over ' + ROUNDS + ' rounds. Tap to go again.', '');

    QSGame.finish(stage, {
      text: 'Average ' + avg + ' ms. ' + verdict,
      tone: avg < 260 ? 'win' : '',
      score: avg,                                  // lower is better for this game
      duration: 0,
      detail: times.join('/') + ' ms'
    });

    state = 'idle';
  }

  function paintRounds() {
    roundsEl.innerHTML = '';
    for (var i = 0; i < ROUNDS; i++) {
      var d = QSGame.el('div', 'round-dot' + (times[i] !== undefined ? ' done' : ''),
                        times[i] !== undefined ? times[i] + 'ms' : '—');
      roundsEl.appendChild(d);
    }
  }

  function updateStats() {
    stats.round.textContent = Math.min(round + (state === 'done' ? 0 : 1), ROUNDS) + ' / ' + ROUNDS;
    stats.avg.textContent = times.length ? average() + ' ms' : '—';
    stats.best.textContent = times.length ? Math.min.apply(null, times) + ' ms' : '—';
  }

  /* ---------------- UI ---------------- */
  stats = QSGame.statBar(stage, [
    { id: 'round', label: 'Round', value: '1 / 5' },
    { id: 'avg',   label: 'Average', value: '—' },
    { id: 'best',  label: 'Fastest', value: '—' }
  ]);

  padEl = QSGame.el('div', 'reaction');
  padEl.setAttribute('role', 'button');
  padEl.setAttribute('tabindex', '0');
  padEl.addEventListener('pointerdown', function (ev) { ev.preventDefault(); hit(); });
  padEl.addEventListener('keydown', function (ev) {
    if (ev.key === ' ' || ev.key === 'Enter') { ev.preventDefault(); hit(); }
  });
  stage.appendChild(padEl);

  roundsEl = QSGame.el('div', 'rounds');
  stage.appendChild(roundsEl);

  var actions = QSGame.el('div', 'game-actions');
  actions.appendChild(QSGame.button('Reset', 'btn-ghost btn-sm', reset));
  stage.appendChild(actions);

  reset();
});
