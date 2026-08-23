<?php
/**
 * QuestScene admin — shared shell.
 * Include AFTER setting $admin_page. Ends with an open .admin-shell > div.
 */

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/queries.php';
require_once dirname(__DIR__) . '/includes/components.php';

require_admin();

$admin_page = $admin_page ?? '';
$openReports = (int) qv("SELECT COUNT(*) FROM reports WHERE status = 'open'");

$nav        = '';
$page_title = 'Admin · ' . ucfirst($admin_page ?: 'overview');
require dirname(__DIR__) . '/includes/header.php';

$links = [
  'index'       => ['Overview',    'index.php'],
  'events'      => ['Post event',  'events.php'],
  'plans'       => ['Plans',       'plans.php'],
  'communities' => ['Communities', 'communities.php'],
  'users'       => ['Users',       'users.php'],
  'reports'     => ['Reports' . ($openReports ? " ({$openReports})" : ''), 'reports.php'],
];
?>

<div class="wrap admin-shell">
  <nav class="admin-side" aria-label="Admin">
    <?php foreach ($links as $key => [$label, $href]): ?>
      <a href="<?= url('admin/' . $href) ?>" class="<?= $admin_page === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <a href="<?= url('index.php') ?>">← Back to site</a>
  </nav>

  <div>
