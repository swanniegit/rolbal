<?php
/**
 * Form Error Component - Hidden error div for JavaScript forms
 *
 * @param string $id - Element ID (default: formError)
 */

$id = $id ?? 'formError';
?>
<div id="<?= htmlspecialchars($id) ?>" class="form-error hidden"></div>
