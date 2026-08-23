<?php
/**
 * QuestScene — reusable render functions (cards, avatars, empty states).
 */

require_once __DIR__ . '/helpers.php';

function render_avatar(array $u, string $size = 'sm'): void
{
    $url = avatar_url($u);
    if ($url) {
        echo '<img class="avatar avatar-' . e($size) . '" src="' . e($url) . '" alt="' . e($u['name'] ?? '') . '">';
    } else {
        echo '<span class="avatar avatar-' . e($size) . ' avatar-fallback">' . e(initials($u['name'] ?? 'Q')) . '</span>';
    }
}

/**
 * The card used on Home, Discover, community pages, dashboard.
 * $p must come from PLAN_SELECT (queries.php).
 *
 * The markup is layered on purpose: .pcard-3d holds the perspective, and the
 * children sit at different translateZ depths so app.js can tilt the whole
 * thing toward the pointer without any per-element maths.
 */
function render_plan_card(array $p): void
{
    $left    = spots_left($p);
    $full    = $left === 0;
    $cover   = cover_url($p['cover']);
    $isFree  = (float) $p['price'] == 0.0;
    $pct     = (int) min(100, round(($p['going_count'] / max(1, $p['capacity'])) * 100));
    $soon    = strtotime($p['starts_at']) - time();
    $isSoon  = $soon > 0 && $soon < 86400 * 2;

    // Face pile: first three people going, straight out of PLAN_SELECT.
    $names   = array_filter(explode('||', (string) ($p['goer_names'] ?? '')));
    $avatars = explode('||', (string) ($p['goer_avatars'] ?? ''));
    ?>
    <a class="pcard <?= $full ? 'is-full' : '' ?>"
       data-cat="<?= e($p['cat_slug'] ?: 'other') ?>"
       data-tilt
       href="<?= e(plan_url($p)) ?>">
      <span class="pcard-3d">

        <span class="pcard-media">
          <?php if ($cover): ?>
            <img src="<?= e($cover) ?>" alt="" loading="lazy">
          <?php else: ?>
            <span class="pcard-glow" aria-hidden="true"></span>
            <span class="pcard-emoji" aria-hidden="true"><?= e($p['cat_emoji'] ?: '✨') ?></span>
          <?php endif; ?>

          <span class="pcard-shine" aria-hidden="true"></span>

          <span class="pcard-day"><?= e(fmt_day($p['starts_at'])) ?> · <?= e(fmt_time($p['starts_at'])) ?></span>

          <?php if ((int) $p['is_event'] === 1): ?>
            <span class="pcard-flag">Event</span>
          <?php elseif ((int) $p['verified'] === 1): ?>
            <span class="pcard-flag">✓ Verified</span>
          <?php elseif ($isSoon): ?>
            <span class="pcard-flag pcard-flag-soon">Soon</span>
          <?php endif; ?>
        </span>

        <span class="pcard-body">
          <span class="pcard-meta">
            <span class="chip chip-cat"><?= e($p['cat_emoji']) ?> <?= e($p['cat_name'] ?: 'Other') ?></span>
            <span class="chip <?= $isFree ? 'chip-free' : '' ?>"><?= $isFree ? 'Free' : '₹' . number_format((float) $p['price']) ?></span>
          </span>

          <span class="pcard-title"><?= e($p['title']) ?></span>

          <span class="pcard-line">
            <svg viewBox="0 0 24 24" class="ico" aria-hidden="true"><path d="M12 21s7-6.3 7-11a7 7 0 10-14 0c0 4.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            <?= e($p['venue'] ?: $p['city']) ?>
          </span>

          <span class="pcard-foot">
            <span class="facepile">
              <?php foreach (array_slice($names, 0, 3) as $i => $n): ?>
                <?php $av = $avatars[$i] ?? ''; ?>
                <?php if ($av !== ''): ?>
                  <img class="face" src="<?= e(UPLOAD_URL . '/avatars/' . rawurlencode($av)) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <span class="face face-txt"><?= e(initials($n)) ?></span>
                <?php endif; ?>
              <?php endforeach; ?>

              <span class="facepile-txt">
                <?php if ((int) $p['going_count'] === 0): ?>
                  Be the first
                <?php else: ?>
                  <strong><?= (int) $p['going_count'] ?></strong> going
                <?php endif; ?>
              </span>
            </span>

            <span class="spots <?= $full ? 'spots-full' : ($left <= 3 ? 'spots-low' : '') ?>">
              <?= $full ? 'Full' : ($left <= 3 ? $left . ' left' : $left . ' spots') ?>
            </span>
          </span>

          <span class="pcard-bar" aria-hidden="true">
            <span style="width: <?= $pct ?>%"></span>
          </span>
        </span>

      </span>
    </a>
    <?php
}

function render_community_card(array $g): void
{
    $cover = cover_url($g['cover']);
    ?>
    <a class="ccard" href="<?= e(community_url($g)) ?>">
      <div class="ccard-media">
        <?php if ($cover): ?>
          <img src="<?= e($cover) ?>" alt="" loading="lazy">
        <?php else: ?>
          <span class="ccard-emoji" aria-hidden="true"><?= e($g['cat_emoji'] ?: '👥') ?></span>
        <?php endif; ?>
      </div>
      <div class="ccard-body">
        <h3>
          <?= e($g['name']) ?>
          <?php if ((int) $g['verified'] === 1): ?>
            <span class="verified-tick" title="QuestScene Verified">✓</span>
          <?php endif; ?>
        </h3>
        <p class="muted"><?= e(excerpt($g['description'], 78)) ?></p>
        <div class="ccard-foot">
          <span><?= number_format((int) $g['member_count']) ?> members</span>
          <span>·</span>
          <span><?= e($g['city']) ?></span>
        </div>
      </div>
    </a>
    <?php
}

/** Instagram / Telegram / email row. Used in the footer, About and Contact. */
function render_social(bool $withEmail = true): void
{
    ?>
    <div class="social-row">
      <a class="social social-ig" href="<?= e(SOCIAL_INSTAGRAM) ?>" target="_blank" rel="noopener noreferrer">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="#E1306C" stroke="none"/></svg>
        Instagram <?= e(SOCIAL_HANDLE) ?>
      </a>
      <a class="social social-tg" href="<?= e(SOCIAL_TELEGRAM) ?>" target="_blank" rel="noopener noreferrer">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.5 4.3L2.9 11.5c-1 .4-1 1.8.1 2.1l4.6 1.4 1.8 5.4c.3.8 1.3 1 1.9.4l2.5-2.4 4.6 3.4c.7.5 1.7.1 1.9-.7l3.2-15c.2-.9-.7-1.6-1.5-1.3z"/></svg>
        Telegram <?= e(SOCIAL_HANDLE) ?>
      </a>
      <?php if ($withEmail): ?>
        <a class="social social-mail" href="mailto:<?= e(CONTACT_EMAIL) ?>">
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M3 7l9 6 9-6"/></svg>
          <?= e(CONTACT_EMAIL) ?>
        </a>
      <?php endif; ?>
    </div>
    <?php
}

function render_empty(string $title, string $body, ?string $ctaText = null, ?string $ctaHref = null): void
{
    ?>
    <div class="empty">
      <span class="empty-mark" aria-hidden="true">🧭</span>
      <h3><?= e($title) ?></h3>
      <p><?= e($body) ?></p>
      <?php if ($ctaText && $ctaHref): ?>
        <a class="btn btn-grad" href="<?= e($ctaHref) ?>"><?= e($ctaText) ?></a>
      <?php endif; ?>
    </div>
    <?php
}

function render_section_head(string $title, ?string $sub = null, ?string $linkText = null, ?string $linkHref = null): void
{
    ?>
    <div class="section-head">
      <div>
        <h2><?= e($title) ?></h2>
        <?php if ($sub): ?><p class="muted"><?= e($sub) ?></p><?php endif; ?>
      </div>
      <?php if ($linkText && $linkHref): ?>
        <a class="section-link" href="<?= e($linkHref) ?>"><?= e($linkText) ?> <span aria-hidden="true">→</span></a>
      <?php endif; ?>
    </div>
    <?php
}

/** Share row — this is what actually gets pasted into WhatsApp/Telegram. */
function render_share(string $shareUrl, string $text): void
{
    $u = rawurlencode($shareUrl);
    $t = rawurlencode($text);
    ?>
    <div class="share-row" data-share-url="<?= e($shareUrl) ?>" data-share-text="<?= e($text) ?>">
      <a class="share-btn" target="_blank" rel="noopener" href="https://wa.me/?text=<?= $t ?>%20<?= $u ?>">WhatsApp</a>
      <a class="share-btn" target="_blank" rel="noopener" href="https://t.me/share/url?url=<?= $u ?>&text=<?= $t ?>">Telegram</a>
      <a class="share-btn" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?= $t ?>&url=<?= $u ?>">X</a>
      <button type="button" class="share-btn js-copy" data-copy="<?= e($shareUrl) ?>">Copy link</button>
      <button type="button" class="share-btn js-native-share hidden">Share…</button>
    </div>
    <?php
}
