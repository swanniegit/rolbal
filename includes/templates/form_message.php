<?php
/**
 * Form Message Component - Hidden message div for JavaScript feedback
 *
 * @param string $id - Element ID (default: formMessage)
 */

$id = $id ?? 'formMessage';
?>
<div id="<?= htmlspecialchars($id) ?>" class="flash hidden"></div>
