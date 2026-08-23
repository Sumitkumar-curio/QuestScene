/* =====================================================================
   QuestScene — 3D hero backdrop (three.js)

   Drifting low-poly shapes in brand orange over the navy ground, with a
   slow parallax toward the pointer.

   This is a garnish, not a feature. It only ever loads when it can be
   afforded: three.js (~655KB, ~150KB gzipped) is fetched lazily AFTER the
   page is interactive, and never at all on a device that fails the checks
   below. The CSS radial-gradient hero remains the baseline everyone sees.
   ===================================================================== */
(function () {
  'use strict';

  var mount = document.getElementById('hero3d');
  if (!mount) return;

  /* ---- affordability checks ------------------------------------- */

  function shouldSkip() {
    // Someone has asked for less movement — respect it, no argument.
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return 'reduced-motion';
    }
    // Data Saver on: do not spend 150KB on decoration.
    var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if (conn) {
      if (conn.saveData) return 'save-data';
      if (/^(slow-)?2g$/.test(conn.effectiveType || '')) return 'slow-network';
    }
    // Low-memory phones: this is exactly the audience we must not jank.
    if (navigator.deviceMemory && navigator.deviceMemory < 4) return 'low-memory';

    // No WebGL, no scene.
    try {
      var c = document.createElement('canvas');
      if (!(c.getContext('webgl2') || c.getContext('webgl'))) return 'no-webgl';
    } catch (e) {
      return 'no-webgl';
    }
    return null;
  }

  var skip = shouldSkip();
  if (skip) {
    mount.dataset.skipped = skip;
    return;
  }

  /* ---- load three.js only once the page is idle ------------------ */

  function whenIdle(fn) {
    if ('requestIdleCallback' in window) requestIdleCallback(fn, { timeout: 2500 });
    else setTimeout(fn, 1200);
  }

  whenIdle(function () {
    import(mount.dataset.lib)
      .then(function (THREE) { start(THREE); })
      .catch(function () { mount.dataset.skipped = 'load-failed'; });
  });

  /* ---- the scene -------------------------------------------------- */

  function start(THREE) {
    var w = mount.clientWidth;
    var h = mount.clientHeight;
    if (!w || !h) return;

    var isPhone = window.innerWidth < 700;
    var COUNT = isPhone ? 14 : 26;

    var renderer = new THREE.WebGLRenderer({ alpha: true, antialias: !isPhone, powerPreference: 'low-power' });
    renderer.setSize(w, h);
    // Capping pixel ratio matters far more than object count on phones.
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, isPhone ? 1.5 : 2));
    mount.appendChild(renderer.domElement);

    var scene = new THREE.Scene();
    var camera = new THREE.PerspectiveCamera(55, w / h, 0.1, 100);
    camera.position.z = 15;

    scene.add(new THREE.AmbientLight(0xffffff, 0.65));

    var key = new THREE.DirectionalLight(0xffb070, 1.5);
    key.position.set(5, 6, 8);
    scene.add(key);

    var rim = new THREE.DirectionalLight(0x3a6ea8, 0.9);
    rim.position.set(-7, -3, 4);
    scene.add(rim);

    var geometries = [
      new THREE.IcosahedronGeometry(1, 0),
      new THREE.TorusGeometry(0.75, 0.3, 8, 18),
      new THREE.OctahedronGeometry(1, 0),
      new THREE.DodecahedronGeometry(0.95, 0)
    ];

    var palette = [0xf97316, 0xffa23d, 0xea5a0b, 0x2b4a6f, 0x3fa34d];

    var shapes = [];
    for (var i = 0; i < COUNT; i++) {
      var geo = geometries[i % geometries.length];
      var mat = new THREE.MeshStandardMaterial({
        color: palette[i % palette.length],
        roughness: 0.42,
        metalness: 0.18,
        flatShading: true,
        transparent: true,
        opacity: 0.86
      });

      var mesh = new THREE.Mesh(geo, mat);
      mesh.position.set(
        (Math.random() - 0.5) * 26,
        (Math.random() - 0.5) * 14,
        (Math.random() - 0.5) * 14 - 3
      );
      var s = 0.28 + Math.random() * 0.6;
      mesh.scale.setScalar(s);
      mesh.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, 0);

      mesh.userData = {
        spin: (Math.random() - 0.5) * 0.006,
        bob: 0.25 + Math.random() * 0.5,
        phase: Math.random() * Math.PI * 2,
        baseY: mesh.position.y
      };

      shapes.push(mesh);
      scene.add(mesh);
    }

    /* ---- interaction + loop --------------------------------------- */

    var targetX = 0, targetY = 0, curX = 0, curY = 0;

    window.addEventListener('pointermove', function (ev) {
      targetX = (ev.clientX / window.innerWidth - 0.5) * 2;
      targetY = (ev.clientY / window.innerHeight - 0.5) * 2;
    }, { passive: true });

    var running = true;
    var raf = null;

    // Stop rendering entirely when the hero scrolls away or the tab hides —
    // a spinning canvas nobody can see is pure battery drain.
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        running = entries[0].isIntersecting;
        if (running && !raf) raf = requestAnimationFrame(loop);
      }, { threshold: 0.01 }).observe(mount);
    }

    document.addEventListener('visibilitychange', function () {
      if (!document.hidden && running && !raf) raf = requestAnimationFrame(loop);
    });

    var t0 = performance.now();

    function loop(now) {
      raf = null;
      if (!running || document.hidden) return;

      var t = (now - t0) / 1000;

      curX += (targetX - curX) * 0.04;
      curY += (targetY - curY) * 0.04;
      camera.position.x = curX * 1.6;
      camera.position.y = -curY * 1.1;
      camera.lookAt(0, 0, 0);

      for (var i = 0; i < shapes.length; i++) {
        var m = shapes[i], d = m.userData;
        m.rotation.x += d.spin;
        m.rotation.y += d.spin * 1.4;
        m.position.y = d.baseY + Math.sin(t * 0.5 + d.phase) * d.bob;
      }

      renderer.render(scene, camera);
      raf = requestAnimationFrame(loop);
    }

    var resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        var nw = mount.clientWidth, nh = mount.clientHeight;
        if (!nw || !nh) return;
        camera.aspect = nw / nh;
        camera.updateProjectionMatrix();
        renderer.setSize(nw, nh);
      }, 200);
    });

    mount.classList.add('is-live');
    raf = requestAnimationFrame(loop);
  }
})();
