<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/components.php';

http_response_code(404);
$nav        = '';
$page_title = 'Page not found';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section" style="max-width:560px">
  <div class="empty">
    <span class="empty-mark" aria-hidden="true">🧭</span>
    <h1 style="font-size:1.6rem">Nothing here</h1>
    <p>That page does not exist. Plenty of things happening elsewhere though.</p>
    <div class="btn-row" style="justify-content:center">
      <a class="btn btn-grad" href="<?= url('discover.php') ?>">Discover plans</a>
      <a class="btn btn-ghost" href="<?= url('index.php') ?>">Home</a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
