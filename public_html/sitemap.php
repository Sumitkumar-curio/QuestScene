<?php
/**
 * QuestScene — dynamic sitemap.
 * Plan and community pages are the shareable, indexable surface.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: application/xml; charset=utf-8');

$static = ['index.php', 'discover.php', 'our-events.php', 'communities.php', 'forum.php',
           'about.php', 'contact.php', 'safety.php'];
$plans  = q("SELECT slug, created_at FROM plans WHERE status='active' AND visibility='public' ORDER BY starts_at DESC LIMIT 2000");
$comms  = q("SELECT slug, created_at FROM communities WHERE status='active' ORDER BY member_count DESC LIMIT 500");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($static as $s): ?>
  <url><loc><?= e(url($s)) ?></loc><changefreq>daily</changefreq><priority>0.9</priority></url>
<?php endforeach; ?>
<?php foreach ($plans as $p): ?>
  <url><loc><?= e(url('plan.php?s=' . rawurlencode($p['slug']))) ?></loc><lastmod><?= e(date('Y-m-d', strtotime($p['created_at']))) ?></lastmod><priority>0.8</priority></url>
<?php endforeach; ?>
<?php foreach ($comms as $c): ?>
  <url><loc><?= e(url('community.php?s=' . rawurlencode($c['slug']))) ?></loc><lastmod><?= e(date('Y-m-d', strtotime($c['created_at']))) ?></lastmod><priority>0.7</priority></url>
<?php endforeach; ?>
</urlset>
