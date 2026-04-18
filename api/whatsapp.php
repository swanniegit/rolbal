<?php
declare(strict_types=1);

/**
 * WhatsApp Webhook Endpoint
 *
 * GET  = Meta verification challenge
 * POST = Incoming message/status update processing
 *
 * Webhook URL: https://bowlstracker.co.za/api/whatsapp.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/WhatsAppAPI.php';
require_once __DIR__ . '/../includes/WhatsAppScoring.php';

// ========== GET: Webhook Verification ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    error_log("[WA-WEBHOOK] Verification: mode={$mode}, token_match=" . ($token === WHATSAPP_VERIFY_TOKEN ? 'yes' : 'no'));

    if ($mode === 'subscribe' && $token === WHATSAPP_VERIFY_TOKEN) {
        http_response_code(200);
        echo $challenge;
        exit;
    }

    http_response_code(403);
    echo 'Verification failed';
    exit;
}

// ========== POST: Incoming Messages ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawPayload = file_get_contents('php://input');
    error_log("[WA-WEBHOOK] Received POST: " . substr($rawPayload, 0, 1000));

    // Verify signature — mandatory when APP_SECRET is configured
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    if (WHATSAPP_APP_SECRET) {
        if (!$signature || !WhatsAppAPI::verifySignature($rawPayload, $signature)) {
            error_log("[WA-WEBHOOK] Invalid or missing signature");
            http_response_code(403);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }
    } else {
        error_log("[WA-WEBHOOK] WARNING: WHATSAPP_APP_SECRET not set — signature verification disabled");
    }

    $payload = json_decode($rawPayload, true);

    // Acknowledge immediately (Meta requires fast response)
    http_response_code(200);
    echo json_encode(['status' => 'received']);

    // Flush response before processing
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        ob_end_flush();
        flush();
    }

    try {
        // Process status updates
        $statuses = $payload['entry'][0]['changes'][0]['value']['statuses'] ?? [];
        foreach ($statuses as $status) {
            processStatusUpdate($status);
        }

        // Process incoming messages
        $messages = $payload['entry'][0]['changes'][0]['value']['messages'] ?? [];
        foreach ($messages as $message) {
            processIncomingMessage($message);
        }

    } catch (Exception $e) {
        error_log("[WA-WEBHOOK] Error: " . $e->getMessage());
    }

    exit;
}

// ========== Other Methods ==========
http_response_code(405);
echo 'Method not allowed';
exit;

/**
 * Process status update from Meta (sent, delivered, read, failed)
 */
function processStatusUpdate(array $status): void {
    $metaMessageId = $status['id'] ?? '';
    $statusType = $status['status'] ?? '';
    $recipientId = $status['recipient_id'] ?? '';

    if (!$metaMessageId || !$statusType) {
        return;
    }

    error_log("[WA-WEBHOOK] Status: {$statusType} for {$metaMessageId}");

    // Could update message log status here if needed
    // For now, just log it
}

/**
 * Process incoming message from user
 */
function processIncomingMessage(array $message): void {
    $from = $message['from'] ?? '';
    $messageId = $message['id'] ?? '';
    $type = $message['type'] ?? '';
    $timestamp = $message['timestamp'] ?? time();

    if (!$from || !$messageId) {
        return;
    }

    error_log("[WA-WEBHOOK] Message from {$from}, type: {$type}");

    // Extract content based on type
    $content = null;
    $interactiveData = null;

    switch ($type) {
        case 'text':
            $content = $message['text']['body'] ?? '';
            break;

        case 'interactive':
            $interactive = $message['interactive'] ?? [];
            $interactiveType = $interactive['type'] ?? '';

            if ($interactiveType === 'button_reply') {
                $interactiveData = $interactive['button_reply'] ?? [];
                $content = $interactiveData['id'] ?? '';
            } elseif ($interactiveType === 'list_reply') {
                $interactiveData = $interactive['list_reply'] ?? [];
                $content = $interactiveData['id'] ?? '';
            }
            break;

        default:
            // Unsupported message type
            WhatsAppAPI::sendText($from,
                "Sorry, I can only process text messages and button selections.\n\n" .
                "Send any message to see your pending fixtures."
            );
            return;
    }

    // Log incoming message
    WhatsAppAPI::logMessage('incoming', $from, $type, $messageId, [
        'content' => $content,
        'interactive' => $interactiveData
    ]);

    // Handle via scoring flow
    WhatsAppScoring::handleMessage($from, $type, $content, $interactiveData);
}
