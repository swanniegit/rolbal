<?php
/**
 * Empty State Component
 *
 * @param string $message - Message to display
 * @param string $actionHref - Action button URL (optional)
 * @param string $actionText - Action button text (optional)
 * @param string $extraClass - Additional CSS classes (optional)
 */

$message = $message ?? 'No items found';
$actionHref = $actionHref ?? '';
$actionText = $actionText ?? '';
$extraClass = $extraClass ?? '';
?>
<div class="empty-state<?= $extraClass ? ' ' . htmlspecialchars($extraClass) : '' ?>">
    <p><?= htmlspecialchars($message) ?></p>
    <?php if ($actionHref && $actionText): ?>
    <a href="<?= htmlspecialchars($actionHref) ?>" class="btn-primary"><?= htmlspecialchars($actionText) ?></a>
    <?php endif; ?>
</div>
