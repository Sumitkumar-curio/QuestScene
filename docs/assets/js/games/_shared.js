/* =====================================================================
   QuestScene — shared game harness.
   Loaded before each game script. Provides the stage, seeded RNG,
   score submission and swipe handling.
   ===================================================================== */
window.QSGame = (function () {
  'use strict';

  var QS = window.QS || {};

  /** Seeded RNG (mulberry32) so the Daily Challenge is identical for everyone. */
  function rng(seed) {
    var a = seed >>> 0;
    return function () {
      a |= 0; a = (a + 0x6D2B79F5) | 0;
      var t = Math.imul(a ^ (a >>> 15), 1 | a);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }

  /** Fisher-Yates using a supplied random function. */
  function shuffle(arr, rand) {
    for (var i = arr.length - 1; i > 0; i--) {
      var j = Math.floor(rand() * (i + 1));
      var t = arr[i]; arr[i] = arr[j]; arr[j] = t;
    }
    return arr;
  }

  function el(tag, cls, html) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (html !== undefined) n.innerHTML = html;
    return n;
  }

  function fmtTime(ms) {
    var s = Math.floor(ms / 1000);
    return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
  }

  /** Send a finished run to the server. Silent no-op for signed-out players. */
  function submit(stage, score, durationMs, detail) {
    if (stage.dataset.signedIn !== '1') {
      return Promise.resolve({ ok: false, anonymous: true });
    }

    var body = new FormData();
    body.append('_csrf', QS.csrf);
    body.append('game', stage.dataset.game);
    body.append('score', Math.round(score));
    body.append('duration_ms', Math.round(durationMs || 0));
    if (detail) body.append('detail', detail);
    if (stage.dataset.daily) body.append('daily', stage.dataset.daily);

    return fetch(QS.url + '/api/score.php', {
      method: 'POST', body: body, credentials: 'same-origin',
      headers: { 'X-CSRF-Token': QS.csrf }
    })
      .then(function (r) { return r.json(); })
      .catch(function () { return { ok: false }; });
  }

  /**
   * Show the end-of-run message, then submit and append the placing.
   * Every game funnels through here so the wording stays consistent.
   */
  function finish(stage, opts) {
    var msg = stage.querySelector('.game-msg') || el('div', 'game-msg');
    msg.className = 'game-msg ' + (opts.tone || '');
    msg.textContent = opts.text;
    if (!msg.parentNode) stage.appendChild(msg);

    if (opts.score === undefined || opts.score === null) return;

    submit(stage, opts.score, opts.duration, opts.detail).then(function (res) {
      if (res && res.ok) {
        msg.textContent = opts.text + ' — ' + res.label +
          (res.personal_best ? '. New personal best!' : '.') +
          ' You are #' + res.rank + ' on the board.';
        if (res.personal_best && window.qsToast) window.qsToast('New personal best: ' + res.label);
      } else if (res && res.anonymous) {
        msg.innerHTML = opts.text + ' — <a href="' + QS.url +
          '/register.php" style="color:var(--brand-2);font-weight:700">sign up free</a> to save scores.';
      } else if (res && res.error) {
        msg.textContent = opts.text + ' (score not saved: ' + res.error + ')';
      }
    });
  }

  /** Arrow keys / WASD plus touch swipe, normalised to up|down|left|right. */
  function onDirection(node, handler) {
    document.addEventListener('keydown', function (ev) {
      var map = {
        ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right',
        w: 'up', s: 'down', a: 'left', d: 'right',
        W: 'up', S: 'down', A: 'left', D: 'right'
      };
      var dir = map[ev.key];
      if (!dir) return;
      ev.preventDefault();
      handler(dir);
    });

    var sx = 0, sy = 0, tracking = false;

    node.addEventListener('touchstart', function (ev) {
      if (ev.touches.length !== 1) return;
      sx = ev.touches[0].clientX; sy = ev.touches[0].clientY; tracking = true;
    }, { passive: true });

    node.addEventListener('touchend', function (ev) {
      if (!tracking) return;
      tracking = false;
      var t = ev.changedTouches[0];
      var dx = t.clientX - sx, dy = t.clientY - sy;
      if (Math.max(Math.abs(dx), Math.abs(dy)) < 28) return;   // a tap, not a swipe
      handler(Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'right' : 'left') : (dy > 0 ? 'down' : 'up'));
    }, { passive: true });
  }

  /** Standard stat strip above a board. Returns {key: valueNode}. */
  function statBar(stage, keys) {
    var bar = el('div', 'game-bar');
    var out = {};
    keys.forEach(function (k) {
      var s = el('div', 'game-stat');
      s.appendChild(el('div', 'k', k.label));
      var v = el('div', 'v', k.value !== undefined ? k.value : '0');
      s.appendChild(v);
      bar.appendChild(s);
      out[k.id] = v;
    });
    stage.appendChild(bar);
    return out;
  }

  function button(label, cls, onClick) {
    var b = el('button', 'btn ' + (cls || 'btn-ghost'), label);
    b.type = 'button';
    b.addEventListener('click', onClick);
    return b;
  }

  /** Boot helper: clears the loading state and hands the game its stage. */
  function mount(fn) {
    function go() {
      var stage = document.getElementById('game');
      if (!stage) return;
      stage.innerHTML = '';
      try {
        fn(stage, {
          seed: parseInt(stage.dataset.seed || '1', 10),
          daily: stage.dataset.daily || '',
          rand: rng(parseInt(stage.dataset.seed || '1', 10))
        });
      } catch (err) {
        stage.innerHTML = '<div class="game-loading">This game failed to start. ' +
                          'Please refresh the page.</div>';
        if (window.console) console.error(err);
      }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go);
    else go();
  }

  return {
    rng: rng, shuffle: shuffle, el: el, fmtTime: fmtTime,
    submit: submit, finish: finish, onDirection: onDirection,
    statBar: statBar, button: button, mount: mount
  };
})();
