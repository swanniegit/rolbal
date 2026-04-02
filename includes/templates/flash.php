<?php
/**
 * Flash Message Component
 *
 * @param array|null $flash - Flash message array with 'type' and 'message' keys
 */

if (!empty($flash) && isset($flash['type']) && isset($flash['message'])):
?>
<div class="flash flash-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>
