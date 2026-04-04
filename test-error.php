<?php
/**
 * Test PHP Error Logging
 * Delete this file after testing!
 */

// Trigger a warning
trigger_error("Test warning - error logging is working!", E_USER_WARNING);

// Try to connect to database to test real errors
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $db = Database::getInstance();
    echo "Database connection: OK<br>";
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}

echo "<br>If logging works, check PHP_errors.log for the test warning.<br>";
echo "<br><strong>Delete this file after testing!</strong>";
