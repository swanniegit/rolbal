<?php
declare(strict_types=1);

/**
 * WhatsApp Cloud API Client
 *
 * Sends messages via Meta Cloud API v21.0
 * Adapted from liveHis WhatsApp module
 */

require_once __DIR__ . '/config.php';

class WhatsAppAPI {

    private const API_VERSION = 'v25.0';

    /**
     * Send a plain text message
     */
    public static function sendText(string $to, string $message): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => self::normalizePhone($to),
            'type' => 'text',
            'text' => ['body' => $message]
        ];
        return self::sendPayload($payload);
    }

    /**
     * Send interactive button message (max 3 buttons)
     *
     * @param string $to Recipient phone
     * @param string $body Message body text
     * @param array $buttons [['id' => 'btn_1', 'title' => 'Button 1'], ...]
     */
    public static function sendButtons(string $to, string $body, array $buttons): array {
        $buttonObjects = [];
        foreach (array_slice($buttons, 0, 3) as $btn) {
            $buttonObjects[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $btn['id'],
                    'title' => substr($btn['title'], 0, 20) // Max 20 chars
                ]
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => self::normalizePhone($to),
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => ['buttons' => $buttonObjects]
            ]
        ];

        return self::sendPayload($payload);
    }

    /**
     * Send interactive list message (max 10 items)
     *
     * @param string $to Recipient phone
     * @param string $body Message body text
     * @param string $buttonText Text for the list button (max 20 chars)
     * @param array $rows [['id' => 'row_1', 'title' => 'Title', 'description' => 'Desc'], ...]
     * @param string $sectionTitle Section header (max 24 chars)
     */
    public static function sendList(string $to, string $body, string $buttonText, array $rows, string $sectionTitle = 'Options'): array {
        $rowObjects = [];
        foreach (array_slice($rows, 0, 10) as $row) {
            $rowObjects[] = [
                'id' => $row['id'],
                'title' => substr($row['title'], 0, 24),
                'description' => substr($row['description'] ?? '', 0, 72)
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => self::normalizePhone($to),
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'body' => ['text' => $body],
                'action' => [
                    'button' => substr($buttonText, 0, 20),
                    'sections' => [
                        [
                            'title' => substr($sectionTitle, 0, 24),
                            'rows' => $rowObjects
                        ]
                    ]
                ]
            ]
        ];

        return self::sendPayload($payload);
    }

    /**
     * Send an image message via URL
     *
     * @param string $to Recipient phone
     * @param string $imageUrl Public URL of the image
     * @param string $caption Optional caption text
     */
    public static function sendImage(string $to, string $imageUrl, string $caption = ''): array {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => self::normalizePhone($to),
            'type' => 'image',
            'image' => [
                'link' => $imageUrl
            ]
        ];

        if ($caption) {
            $payload['image']['caption'] = $caption;
        }

        return self::sendPayload($payload);
    }

    /**
     * Send message payload to Meta API
     */
    private static function sendPayload(array $payload): array {
        $phoneNumberId = WHATSAPP_PHONE_ID;
        $accessToken = WHATSAPP_ACCESS_TOKEN;
        $appSecret = WHATSAPP_APP_SECRET;
        $to = $payload['to'] ?? 'unknown';

        if (!$phoneNumberId || !$accessToken) {
            self::logError('Missing WhatsApp API credentials');
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Missing API credentials'
            ];
        }

        $url = "https://graph.facebook.com/" . self::API_VERSION . "/{$phoneNumberId}/messages";

        // Add appsecret_proof if configured
        if ($appSecret) {
            $proof = hash_hmac('sha256', $accessToken, $appSecret);
            $url .= "?appsecret_proof={$proof}";
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            self::logError('cURL error: ' . $curlError);
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Connection error: ' . $curlError
            ];
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMsg = $data['error']['message'] ?? 'Unknown API error';
            self::logError("API error ($httpCode): $errorMsg", ['to' => $to]);
            return [
                'success' => false,
                'message_id' => null,
                'error' => $errorMsg
            ];
        }

        $messageId = $data['messages'][0]['id'] ?? null;

        self::logMessage('outgoing', $to, $payload['type'] ?? 'unknown', $messageId, $payload);

        return [
            'success' => true,
            'message_id' => $messageId,
            'error' => null
        ];
    }

    /**
     * Normalize phone number to international format (South African)
     */
    public static function normalizePhone(string $phone): string {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Handle South African numbers
        if (strlen($phone) === 9 && !str_starts_with($phone, '0')) {
            // Missing leading 0 or country code
            $phone = '27' . $phone;
        } elseif (str_starts_with($phone, '0')) {
            // Local format 0xx xxx xxxx -> 27xx xxx xxxx
            $phone = '27' . substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Verify webhook signature from Meta
     */
    public static function verifySignature(string $payload, string $signature): bool {
        if (!WHATSAPP_APP_SECRET) {
            return true; // Skip verification if not configured
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, WHATSAPP_APP_SECRET);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Log outgoing/incoming message to database
     */
    public static function logMessage(string $direction, string $phone, string $type, ?string $messageId, ?array $payload = null): void {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare('
                INSERT INTO whatsapp_logs (phone_number, direction, message_type, message_id, payload)
                VALUES (:phone, :direction, :type, :message_id, :payload)
            ');
            $stmt->execute([
                'phone' => self::normalizePhone($phone),
                'direction' => $direction,
                'type' => $type,
                'message_id' => $messageId,
                'payload' => $payload ? json_encode($payload) : null
            ]);
        } catch (Exception $e) {
            error_log('[WhatsApp] Log error: ' . $e->getMessage());
        }
    }

    /**
     * Log error
     */
    private static function logError(string $message, array $context = []): void {
        $contextStr = $context ? ' ' . json_encode($context) : '';
        error_log("[WhatsApp ERROR] {$message}{$contextStr}");
    }
}
