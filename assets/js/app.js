/* =====================================================================
   QuestScene — app.js
   Vanilla, no dependencies. Handles: toasts, copy/share, join buttons,
   forum interest, chat polling, create-plan form niceties.
   ===================================================================== */
(function () {
  'use strict';

  var QS = window.QS || {};

  /* -----------------------------------------------------------------
     Toast
     ----------------------------------------------------------------- */
  var toastEl = null, toastTimer = null;

  function toast(msg) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'toast';
      toastEl.setAttribute('role', 'status');
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.classList.remove('show'); }, 2600);
  }
  window.qsToast = toast;

  /* -----------------------------------------------------------------
     POST helper (always sends the CSRF token)
     ----------------------------------------------------------------- */
  function post(path, data) {
    var body = new FormData();
    Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
    body.append('_csrf', QS.csrf);

    return fetch(QS.url + '/' + path, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': QS.csrf }
    }).then(function (r) {
      return r.json().catch(function () {
        throw new Error('Server error. Please refresh and try again.');
      });
    });
  }

  /* -----------------------------------------------------------------
     Gradient stroke for the active bottom-nav icon
     ----------------------------------------------------------------- */
  function injectGradientDef() {
    if (document.getElementById('qsGradSvg')) return;
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('id', 'qsGradSvg');
    svg.setAttribute('width', '0');
    svg.setAttribute('height', '0');
    svg.style.position = 'absolute';
    svg.innerHTML =
      '<defs><linearGradient id="qsGrad" x1="0" y1="0" x2="1" y2="1">' +
      '<stop offset="0%" stop-color="#FFA23D"/>' +
      '<stop offset="52%" stop-color="#F97316"/>' +
      '<stop offset="100%" stop-color="#EA5A0B"/>' +
      '</linearGradient></defs>';
    document.body.appendChild(svg);
  }

  /* -----------------------------------------------------------------
     Copy link / native share
     ----------------------------------------------------------------- */
  function initShare() {
    document.addEventListener('click', function (ev) {
      var btn = ev.target.closest('.js-copy');
      if (!btn) return;
      ev.preventDefault();
      var text = btn.dataset.copy || location.href;

      var done = function () { toast('Link copied'); };
      var fail = function () {
        // Fallback for http:// origins where the clipboard API is blocked.
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); }
        catch (e) { toast('Copy this: ' + text); }
        document.body.removeChild(ta);
      };

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done).catch(fail);
      } else {
        fail();
      }
    });

    // Only show the OS share sheet button where it actually works.
    if (navigator.share) {
      document.querySelectorAll('.js-native-share').forEach(function (btn) {
        btn.classList.remove('hidden');
        btn.addEventListener('click', function () {
          var row = btn.closest('[data-share-url]');
          if (!row) return;
          navigator.share({
            title: document.title,
            text: row.dataset.shareText || '',
            url: row.dataset.shareUrl
          }).catch(function () { /* user dismissed */ });
        });
      });
    }
  }

  /* -----------------------------------------------------------------
     Join / leave a plan
     ----------------------------------------------------------------- */
  function initJoin() {
    document.querySelectorAll('.js-join').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!QS.uid) { location.href = QS.url + '/login.php?next=' + encodeURIComponent(location.pathname + location.search); return; }

        btn.disabled = true;
        var planId = btn.dataset.plan;
        var leaving = btn.dataset.state === 'going';

        post('api/join.php', { plan_id: planId, action: leaving ? 'leave' : 'join' })
          .then(function (res) {
            btn.disabled = false;
            if (!res.ok) { toast(res.error || 'Something went wrong'); return; }

            // The same plan can have more than one Join button on screen
            // (sidebar box plus the phone sticky bar) — keep them in step.
            var peers = document.querySelectorAll('.js-join[data-plan="' + planId + '"]');
            var compact = { going: 'Going ✓', waitlist: 'Waitlisted', left: 'Join' };
            var full    = { going: 'You’re going ✓', waitlist: 'On the waitlist', left: 'Join Plan' };

            peers.forEach(function (b) {
              b.dataset.state = res.state;
              b.textContent = b.closest('.plan-sticky') ? compact[res.state] : full[res.state];
              b.classList.toggle('btn-grad', res.state === 'left');
              b.classList.toggle('btn-ok', res.state !== 'left');
            });

            if (res.state === 'going') {
              haptic([12, 40, 18]);
              burst(btn);
              toast('You’re in. Plan chat is now open.');
            } else if (res.state === 'waitlist') {
              toast('Plan is full — you’re on the waitlist.');
            } else {
              toast('You left the plan.');
            }

            document.querySelectorAll('[data-going-count]').forEach(function (n) {
              n.textContent = res.going_count;
            });
            document.querySelectorAll('[data-going-bar]').forEach(function (n) {
              n.style.width = res.percent + '%';
            });

            var chatLink = document.querySelector('[data-plan-chat]');
            if (chatLink) chatLink.classList.toggle('hidden', res.state === 'left');
          })
          .catch(function (err) { btn.disabled = false; toast(err.message); });
      });
    });
  }

  /* -----------------------------------------------------------------
     Save / unsave a plan
     ----------------------------------------------------------------- */
  function initSave() {
    document.querySelectorAll('.js-save').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!QS.uid) { location.href = QS.url + '/login.php'; return; }
        btn.disabled = true;
        post('api/save.php', { plan_id: btn.dataset.plan }).then(function (res) {
          btn.disabled = false;
          if (!res.ok) { toast(res.error || 'Could not save'); return; }
          btn.textContent = res.saved ? 'Saved ✓' : 'Save';
          btn.classList.toggle('btn-ok', res.saved);
          toast(res.saved ? 'Saved to your list' : 'Removed from saved');
        }).catch(function (e) { btn.disabled = false; toast(e.message); });
      });
    });
  }

  /* -----------------------------------------------------------------
     Forum: "I'm interested"
     ----------------------------------------------------------------- */
  function initInterest() {
    document.querySelectorAll('.js-interest').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!QS.uid) { location.href = QS.url + '/login.php'; return; }
        btn.disabled = true;
        post('api/interest.php', { post_id: btn.dataset.post }).then(function (res) {
          btn.disabled = false;
          if (!res.ok) { toast(res.error || 'Could not update'); return; }
          btn.classList.toggle('on', res.interested);
          btn.querySelector('.count').textContent = res.count;
        }).catch(function (e) { btn.disabled = false; toast(e.message); });
      });
    });
  }

  /* -----------------------------------------------------------------
     Chat — long-ish polling. Shared hosting has no websockets, so we
     poll every 4s while the tab is visible and back off when hidden.
     ----------------------------------------------------------------- */
  function initChat() {
    var panel = document.getElementById('chat');
    if (!panel) return;

    var scroll  = panel.querySelector('.chat-scroll');
    var form    = panel.querySelector('.chat-form');
    var input   = form ? form.querySelector('input[name="body"]') : null;
    var roomType = panel.dataset.roomType;
    var roomId   = panel.dataset.roomId;
    var lastId   = parseInt(panel.dataset.lastId || '0', 10);
    var timer    = null;
    var sending  = false;

    function atBottom() {
      return scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < 90;
    }
    function toBottom() { scroll.scrollTop = scroll.scrollHeight; }

    function esc(s) {
      var d = document.createElement('div');
      d.textContent = s;
      return d.innerHTML;
    }

    function append(m) {
      var mine = String(m.user_id) === String(QS.uid);
      var el = document.createElement('div');
      el.className = 'msg' + (mine ? ' mine' : '');
      el.innerHTML =
        '<span class="avatar avatar-xs avatar-fallback">' + esc(m.initials) + '</span>' +
        '<div>' +
          '<div class="msg-body">' +
            '<div class="msg-who">' + esc(m.name) + '</div>' +
            '<div class="msg-text">' + esc(m.body) + '</div>' +
          '</div>' +
          '<div class="msg-time">' + esc(m.time) + '</div>' +
        '</div>';
      scroll.appendChild(el);
    }

    function poll() {
      fetch(QS.url + '/api/chat_fetch.php?type=' + encodeURIComponent(roomType) +
            '&id=' + encodeURIComponent(roomId) + '&after=' + lastId,
            { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.ok || !res.messages.length) return;
          var stick = atBottom();
          res.messages.forEach(function (m) {
            append(m);
            lastId = Math.max(lastId, m.id);
          });
          if (stick) toBottom();
        })
        .catch(function () { /* transient network error — next tick retries */ });
    }

    function schedule() {
      clearInterval(timer);
      timer = setInterval(poll, document.hidden ? 20000 : 4000);
    }

    document.addEventListener('visibilitychange', function () {
      schedule();
      if (!document.hidden) poll();
    });

    if (form) {
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var body = input.value.trim();
        if (!body || sending) return;

        sending = true;
        input.value = '';

        post('api/chat_send.php', { type: roomType, id: roomId, body: body })
          .then(function (res) {
            sending = false;
            if (!res.ok) { toast(res.error || 'Message not sent'); input.value = body; return; }
            append(res.message);
            lastId = Math.max(lastId, res.message.id);
            toBottom();
          })
          .catch(function (e) { sending = false; input.value = body; toast(e.message); });
      });
    }

    toBottom();
    schedule();
  }

  /* -----------------------------------------------------------------
     Create-plan form: live character counts + sensible default datetime
     ----------------------------------------------------------------- */
  function initCreateForm() {
    var dt = document.getElementById('starts_at');
    if (dt && !dt.value) {
      // Default: tomorrow, 7:00 AM — the most common QuestScene plan.
      var d = new Date();
      d.setDate(d.getDate() + 1);
      d.setHours(7, 0, 0, 0);
      var pad = function (n) { return String(n).padStart(2, '0'); };
      dt.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) +
                 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    document.querySelectorAll('[data-count-for]').forEach(function (out) {
      var field = document.getElementById(out.dataset.countFor);
      if (!field) return;
      var max = field.getAttribute('maxlength');
      var upd = function () { out.textContent = field.value.length + (max ? ' / ' + max : ''); };
      field.addEventListener('input', upd);
      upd();
    });
  }

  var reduceMotion = window.matchMedia &&
                     window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var finePointer  = window.matchMedia &&
                     window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  /* -----------------------------------------------------------------
     3D tilt on cards.
     Pointer only — on touch a tilt fights the scroll, so phones get a
     press-in effect instead (see initPressFeedback).
     ----------------------------------------------------------------- */
  function initTilt() {
    if (reduceMotion || !finePointer) return;

    var MAX = 7;   // degrees; more than this looks like a gimmick

    document.querySelectorAll('[data-tilt]').forEach(function (card) {
      var inner = card.querySelector('.pcard-3d') || card.firstElementChild;
      if (!inner) return;
      var frame = null;

      card.addEventListener('pointermove', function (ev) {
        if (frame) return;                       // coalesce to one write per frame
        frame = requestAnimationFrame(function () {
          frame = null;
          var r = card.getBoundingClientRect();
          var px = (ev.clientX - r.left) / r.width  - 0.5;
          var py = (ev.clientY - r.top)  / r.height - 0.5;
          card.classList.add('is-tilting');
          inner.style.transform =
            'rotateY(' + (px * MAX * 2) + 'deg) rotateX(' + (-py * MAX * 2) + 'deg) translateY(-4px)';
        });
      });

      card.addEventListener('pointerleave', function () {
        if (frame) { cancelAnimationFrame(frame); frame = null; }
        card.classList.remove('is-tilting');
        inner.style.transform = '';
      });
    });
  }

  /* -----------------------------------------------------------------
     Press feedback for touch, plus a haptic tick where supported.
     ----------------------------------------------------------------- */
  function initPressFeedback() {
    document.addEventListener('pointerdown', function (ev) {
      var t = ev.target.closest('.pcard, .gcard, .btn, .catpill, .fpill');
      if (!t) return;
      t.style.transition = 'transform .1s ease';
      t.style.transform = 'scale(.975)';
    }, { passive: true });

    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (evt) {
      document.addEventListener(evt, function (ev) {
        var t = ev.target.closest && ev.target.closest('.pcard, .gcard, .btn, .catpill, .fpill');
        if (!t) return;
        t.style.transform = '';
        setTimeout(function () { t.style.transition = ''; }, 120);
      }, { passive: true });
    });
  }

  function haptic(ms) {
    try { if (navigator.vibrate) navigator.vibrate(ms || 12); } catch (e) {}
  }
  window.qsHaptic = haptic;

  /** Little confetti burst — the reward moment when someone joins a plan. */
  function burst(anchor) {
    if (reduceMotion || !anchor) return;

    var r = anchor.getBoundingClientRect();
    var cx = r.left + r.width / 2;
    var cy = r.top + r.height / 2;
    var colors = ['#F97316', '#FFA23D', '#EA5A0B', '#3FA34D', '#FFFFFF'];

    for (var i = 0; i < 18; i++) {
      var p = document.createElement('span');
      var angle = (Math.PI * 2 * i) / 18 + Math.random() * 0.4;
      var dist = 60 + Math.random() * 70;

      p.style.cssText =
        'position:fixed;left:' + cx + 'px;top:' + cy + 'px;width:7px;height:7px;' +
        'border-radius:' + (Math.random() > .5 ? '50%' : '2px') + ';' +
        'background:' + colors[i % colors.length] + ';pointer-events:none;z-index:300;' +
        'will-change:transform,opacity';
      document.body.appendChild(p);

      (function (node, a, d) {
        var t0 = performance.now();
        (function tick(now) {
          var pr = Math.min(1, (now - t0) / 750);
          var ease = 1 - Math.pow(1 - pr, 3);
          node.style.transform =
            'translate(' + Math.cos(a) * d * ease + 'px,' +
                          (Math.sin(a) * d * ease + pr * pr * 90) + 'px) scale(' + (1 - pr) + ')';
          node.style.opacity = String(1 - pr);
          if (pr < 1) requestAnimationFrame(tick);
          else node.remove();
        })(t0);
      })(p, angle, dist);
    }
  }

  /* -----------------------------------------------------------------
     Reveal on scroll — cards fade and rise as they enter the viewport.
     ----------------------------------------------------------------- */
  function initReveal() {
    if (reduceMotion || !('IntersectionObserver' in window)) return;

    var targets = document.querySelectorAll('.pcard, .ccard, .gcard, .step, .pastcard, .weekrow');
    if (!targets.length) return;

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        en.target.style.transition = 'opacity .5s ease, transform .5s cubic-bezier(.2,.8,.3,1)';
        en.target.style.opacity = '1';
        en.target.style.transform = '';
        io.unobserve(en.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.04 });

    targets.forEach(function (n, i) {
      // Only stagger the first screenful; below the fold it just adds lag.
      n.style.opacity = '0';
      n.style.transform = 'translateY(14px)';
      n.style.transitionDelay = (Math.min(i, 6) * 45) + 'ms';
      io.observe(n);
    });
  }

  /* -----------------------------------------------------------------
     Count numbers up when they scroll into view.
     ----------------------------------------------------------------- */
  function initCountUp() {
    if (reduceMotion || !('IntersectionObserver' in window)) return;

    var nums = document.querySelectorAll('.hero-stat strong, .stat .v');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        var node = en.target;
        io.unobserve(node);

        var raw = node.textContent.replace(/,/g, '').trim();
        var target = parseInt(raw, 10);
        if (isNaN(target) || target <= 0 || target > 1000000) return;

        var started = null, dur = 900;
        function step(ts) {
          if (!started) started = ts;
          var p = Math.min(1, (ts - started) / dur);
          var eased = 1 - Math.pow(1 - p, 3);
          node.textContent = Math.round(target * eased).toLocaleString();
          if (p < 1) requestAnimationFrame(step);
        }
        node.textContent = '0';
        requestAnimationFrame(step);
      });
    }, { threshold: 0.5 });

    nums.forEach(function (n) { io.observe(n); });
  }

  /* -----------------------------------------------------------------
     Dark / light mode. The initial value is applied by the inline script
     in <head>; this only handles the toggle and persistence.
     ----------------------------------------------------------------- */
  function initTheme() {
    var root = document.documentElement;

    document.querySelectorAll('.js-theme').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
        root.setAttribute('data-theme', next);
        try { localStorage.setItem('qs-theme', next); } catch (e) { /* private mode */ }

        var meta = document.querySelector('meta[name="theme-color"]:not([media])');
        if (meta) meta.setAttribute('content', next === 'light' ? '#FBF9F7' : '#071018');

        toast(next === 'light' ? 'Light mode' : 'Dark mode');
      });
    });
  }

  /* -----------------------------------------------------------------
     Close the account dropdown on outside click / Escape
     ----------------------------------------------------------------- */
  function initMenus() {
    document.addEventListener('click', function (ev) {
      document.querySelectorAll('details.menu[open]').forEach(function (d) {
        if (!d.contains(ev.target)) d.removeAttribute('open');
      });
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') {
        document.querySelectorAll('details.menu[open]').forEach(function (d) { d.removeAttribute('open'); });
      }
    });
  }

  /* -----------------------------------------------------------------
     Confirm destructive actions
     ----------------------------------------------------------------- */
  function initConfirms() {
    document.addEventListener('submit', function (ev) {
      var form = ev.target;
      var msg = form.dataset.confirm;
      if (msg && !window.confirm(msg)) ev.preventDefault();
    });
  }

  /* ----------------------------------------------------------------- */
  function boot() {
    injectGradientDef();
    initShare();
    initJoin();
    initSave();
    initInterest();
    initChat();
    initCreateForm();
    initTheme();
    initTilt();
    initPressFeedback();
    initReveal();
    initCountUp();
    initMenus();
    initConfirms();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
