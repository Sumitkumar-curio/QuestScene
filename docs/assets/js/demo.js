/* =====================================================================
   QuestScene — static demo guard.

   Only loaded by the GitHub Pages snapshot in docs/. It adds a banner and
   intercepts the actions that need a server, so a visitor gets an honest
   explanation instead of a silent failure.

   The games are untouched: they are pure client-side JS and work fully here.
   ===================================================================== */
(function () {
  'use strict';

  /* ---- banner ---------------------------------------------------- */
  var bar = document.createElement('div');
  bar.className = 'demo-bar';
  bar.innerHTML =
    '<span class="demo-dot"></span>' +
    '<span><strong>Static preview.</strong> Real content, but joining, chat and posting ' +
    'need the live server. <a href="https://github.com/Sumitkumar-curio/QuestScene" ' +
    'target="_blank" rel="noopener">Source on GitHub</a></span>';
  document.body.appendChild(bar);

  var css = document.createElement('style');
  css.textContent =
    '.demo-bar{position:fixed;left:0;right:0;bottom:0;z-index:400;' +
    'display:flex;align-items:center;gap:10px;justify-content:center;' +
    'padding:10px 16px;font-size:.8rem;line-height:1.4;text-align:center;' +
    'background:#10233A;color:#EAF2FA;border-top:1px solid #F97316;' +
    'font-family:"Plus Jakarta Sans",system-ui,sans-serif}' +
    '.demo-bar a{color:#FFA23D;font-weight:700;text-decoration:underline}' +
    '.demo-bar strong{color:#F97316}' +
    '.demo-dot{width:8px;height:8px;border-radius:50%;background:#F97316;flex-shrink:0;' +
    'box-shadow:0 0 0 0 rgba(249,115,22,.6);animation:demoPulse 2s infinite}' +
    '@keyframes demoPulse{70%{box-shadow:0 0 0 7px rgba(249,115,22,0)}' +
    '100%{box-shadow:0 0 0 0 rgba(249,115,22,0)}}' +
    'body{padding-bottom:104px!important}' +
    '@media(min-width:820px){body{padding-bottom:52px!important}}' +
    '.bottomnav{bottom:44px}';
  document.head.appendChild(css);

  /* ---- explain instead of failing -------------------------------- */
  function note(msg) {
    if (window.qsToast) window.qsToast(msg);
    else alert(msg);
  }

  document.addEventListener('click', function (ev) {
    var el = ev.target.closest(
      '.js-join, .js-save, .js-interest, .js-theme, [type="submit"], button[type="submit"]'
    );
    if (!el) return;

    // The theme toggle is pure CSS — let it through, it works fine here.
    if (el.classList.contains('js-theme')) return;

    ev.preventDefault();
    ev.stopPropagation();
    note('This needs the live site — it is a static preview.');
  }, true);

  document.addEventListener('submit', function (ev) {
    ev.preventDefault();
    note('Forms need the live server. This is a static preview.');
  }, true);

  /* ---- stop the chat poller hammering a 404 ---------------------- */
  var realFetch = window.fetch;
  window.fetch = function (url) {
    if (typeof url === 'string' && url.indexOf('/api/') !== -1) {
      return Promise.resolve({
        json: function () { return Promise.resolve({ ok: false, error: 'static preview' }); }
      });
    }
    return realFetch.apply(this, arguments);
  };
})();
