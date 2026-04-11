<?php
/**
 * Minimal HS256 JWT implementation — no external library needed.
 * Tokens are stateless; only refresh tokens are stored in the DB.
 */

class Jwt {

    private static function b64Encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64Decode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function encode(array $payload, string $secret): string {
        $header  = self::b64Encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = self::b64Encode(json_encode($payload));
        $sig     = self::b64Encode(hash_hmac('sha256', "$header.$payload", $secret, true));
        return "$header.$payload.$sig";
    }

    /**
     * @throws RuntimeException on invalid signature, malformed token, or expiry
     */
    public static function decode(string $token, string $secret): array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed token');
        }

        [$header, $payload, $sig] = $parts;

        $expected = self::b64Encode(hash_hmac('sha256', "$header.$payload", $secret, true));
        if (!hash_equals($expected, $sig)) {
            throw new RuntimeException('Invalid signature');
        }

        $data = json_decode(self::b64Decode($payload), true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid payload');
        }

        if (isset($data['exp']) && $data['exp'] < time()) {
            throw new RuntimeException('Token expired');
        }

        return $data;
    }
}
