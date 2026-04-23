<?php
/**
 * Simple email helper using PHP mail()
 */

class Mailer {

    public static function sendFeedback(string $fromName, string $fromEmail, string $message): bool {
        $subject = 'BowlsTracker Feedback from ' . $fromName;

        $body = "From: " . $fromName . " <" . $fromEmail . ">\r\n\r\n"
              . $message . "\r\n\r\n"
              . "---\r\nSent via BowlsTracker feedback form\r\nhttps://bowlstracker.co.za";

        $headers = implode("\r\n", [
            'From: BowlsTracker <noreply@bowlstracker.co.za>',
            'Reply-To: ' . $fromName . ' <' . $fromEmail . '>',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion(),
        ]);

        return mail('christo@yellowarcher.co.za', $subject, $body, $headers);
    }

    public static function sendWelcome(string $toEmail, string $toName): bool {
        $subject = 'Welcome to BowlsTracker!';

        $body = "Hi " . $toName . ",\r\n\r\n"
              . "Welcome to BowlsTracker — great to have you on board!\r\n\r\n"
              . "Here's what you can do:\r\n\r\n"
              . "PRACTICE SESSIONS\r\n"
              . "Record every bowl on a visual grid — end length, delivery (forehand/backhand), and where it lands. Over time you'll see exactly where your bowls are going.\r\n\r\n"
              . "STATISTICS\r\n"
              . "After a few sessions, head to your Stats page to see patterns in your game — which deliveries are most consistent, where you're missing, and how you're improving over time.\r\n\r\n"
              . "CHALLENGES\r\n"
              . "Try the built-in challenges for structured practice. Each challenge gives you a sequence of ends at specific lengths and deliveries, and scores your accuracy. See how high you can score!\r\n\r\n"
              . "CLUBS\r\n"
              . "Join or create a club to connect with other members. Club admins can set up live match scoring for singles, pairs, trips, or fours — members follow the scoreboard in real time as ends are recorded.\r\n\r\n"
              . "INSTALL AS AN APP\r\n"
              . "BowlsTracker works as a PWA — on your phone, tap \"Add to Home Screen\" to install it like a native app for quick access.\r\n\r\n"
              . "You can get started at: https://bowlstracker.co.za\r\n\r\n"
              . "Any feedback or questions? Just reply to this email.\r\n\r\n"
              . "Good bowling!\r\n"
              . "Christo\r\n"
              . "BowlsTracker\r\n"
              . "https://bowlstracker.co.za";

        $headers = implode("\r\n", [
            'From: BowlsTracker <noreply@bowlstracker.co.za>',
            'Reply-To: christo@yellowarcher.co.za',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion(),
        ]);

        return mail($toEmail, $subject, $body, $headers);
    }

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
