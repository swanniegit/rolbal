<?php
/**
 * Reusable Header Component
 *
 * @param string $title - Page title
 * @param string $backHref - Back button URL (optional, no back button if empty)
 * @param string $rightHtml - HTML for right side element (optional)
 * @param bool $compact - Use compact header style (default: true)
 */

$title = $title ?? 'BowlsTracker';
$backHref = $backHref ?? '';
$rightHtml = $rightHtml ?? '<span></span>';
$compact = $compact ?? true;
?>
<header class="app-header<?= $compact ? ' compact' : '' ?>">
    <?php if ($backHref): ?>
    <a href="<?= htmlspecialchars($backHref) ?>" class="back-btn">&larr;</a>
    <?php else: ?>
    <span></span>
    <?php endif; ?>
    <h1 class="app-title"><?= htmlspecialchars($title) ?></h1>
    <?= $rightHtml ?>
</header>
