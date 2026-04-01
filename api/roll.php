<?php
/**
 * Roll API
 */

require_once __DIR__ . '/../includes/Roll.php';
require_once __DIR__ . '/../includes/ApiResponse.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sessionId = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
        $endNumber = isset($_POST['end_number']) ? (int)$_POST['end_number'] : 1;
        $endLength = isset($_POST['end_length']) ? (int)$_POST['end_length'] : 0;
        $result = isset($_POST['result']) ? (int)$_POST['result'] : 0;
        $toucher = isset($_POST['toucher']) ? (int)$_POST['toucher'] : 0;

        if (!$sessionId) {
            throw new Exception('Session ID required');
        }

        if (!in_array($endLength, [9, 10, 11])) {
            throw new Exception('Invalid end length');
        }

        if (!in_array($result, [1, 2, 3, 4, 5, 6, 7, 8, 12])) {
            throw new Exception('Invalid result');
        }

        $id = Roll::create($sessionId, $endNumber, $endLength, $result, $toucher);

        ApiResponse::success(['id' => $id]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

        if (!$sessionId) {
            throw new Exception('Session ID required');
        }

        $rolls = Roll::forSession($sessionId);
        ApiResponse::success(['rolls' => $rolls]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
        $undo = isset($_GET['undo']);

        if ($undo && $sessionId) {
            $db = Database::getInstance();
            $stmt = $db->prepare('
                DELETE FROM rolls
                WHERE session_id = :session_id
                ORDER BY created_at DESC
                LIMIT 1
            ');
            $stmt->execute(['session_id' => $sessionId]);
            ApiResponse::success();
        } else {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('Roll ID required');
            }
            Roll::delete($id);
            ApiResponse::success();
        }

    } else {
        ApiResponse::methodNotAllowed();
    }

} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
