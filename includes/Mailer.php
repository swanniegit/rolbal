<?php
/**
 * Simple email helper using PHP mail()
 */

class Mailer {

    public static function sendVerification(string $toEmail, string $toName, string $token): bool {
        $appUrl    = rtrim(APP_URL, '/');
        $verifyUrl = $appUrl . '/verify.php?token=' . urlencode($token);

        $subject = 'Verify your BowlsTracker account';

        $body = "Hi " . $toName . ",\r\n\r\n"
              . "Thanks for signing up to BowlsTracker!\r\n\r\n"
              . "Click the link below to verify your email address and activate your account:\r\n\r\n"
              . $verifyUrl . "\r\n\r\n"
              . "This link expires in 24 hours.\r\n\r\n"
              . "If you didn't register for BowlsTracker, you can safely ignore this email.\r\n\r\n"
              . "Cheers,\r\nThe BowlsTracker Team\r\n"
              . "https://bowlstracker.co.za";

        $headers = implode("\r\n", [
            'From: BowlsTracker <noreply@bowlstracker.co.za>',
            'Reply-To: noreply@bowlstracker.co.za',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion(),
        ]);

        return mail($toEmail, $subject, $body, $headers);
    }
}
