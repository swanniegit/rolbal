<?php
/**
 * Roll Model
 */

require_once __DIR__ . '/db.php';

class Roll {

    public static function create(int $sessionId, int $endNumber, int $endLength, int $result, int $toucher = 0, int $delivery = 0): int {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO rolls (session_id, end_number, end_length, result, toucher, delivery)
            VALUES (:session_id, :end_number, :end_length, :result, :toucher, :delivery)
        ');
        $stmt->execute([
            'session_id' => $sessionId,
            'end_number' => $endNumber,
            'end_length' => $endLength,
            'result' => $result,
            'toucher' => $toucher,
            'delivery' => $delivery ?: null,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function find(int $id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM rolls WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function forSession(int $sessionId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT * FROM rolls
            WHERE session_id = :session_id
            ORDER BY created_at ASC
        ');
        $stmt->execute(['session_id' => $sessionId]);
        return $stmt->fetchAll();
    }

    public static function delete(int $id): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM rolls WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Undo (delete) the last roll in a session
     */
    public static function undoLast(int $sessionId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            DELETE FROM rolls
            WHERE session_id = :session_id
            ORDER BY created_at DESC
            LIMIT 1
        ');
        $stmt->execute(['session_id' => $sessionId]);
        return $stmt->rowCount() > 0;
    }

    public static function sessionSummaries(int $playerId): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT
                s.id, s.session_date, s.hand, s.description,
                COUNT(r.id)                                             AS total,
                SUM(r.toucher)                                          AS touchers,
                SUM(CASE WHEN r.result = 8 THEN 1 ELSE 0 END)          AS centres,
                SUM(CASE WHEN r.result IN (20,21,22,23) THEN 1 ELSE 0 END) AS misses,
                SUM(CASE WHEN r.delivery = 14 THEN 1 ELSE 0 END)       AS fh_count,
                SUM(CASE WHEN r.delivery = 13 THEN 1 ELSE 0 END)       AS bh_count,
                SUM(CASE
                    WHEN r.result = 8          THEN 10
                    WHEN r.result IN (3,4)     THEN 7
                    WHEN r.result IN (7,12)    THEN 5
                    WHEN r.result IN (5,6)     THEN 3
                    WHEN r.result IN (1,2)     THEN 2
                    ELSE 0
                END + r.toucher * 5)                                    AS total_score
            FROM sessions s
            LEFT JOIN rolls r ON r.session_id = s.id
            WHERE s.player_id = :pid
            GROUP BY s.id
            HAVING total > 0
            ORDER BY s.session_date ASC, s.id ASC
        ');
        $stmt->execute(['pid' => $playerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function statsForPlayer(int $playerId): array {
        $db   = Database::getInstance();
        $stmt = $db->prepare('
            SELECT r.end_length, r.delivery, r.result,
                   COUNT(*) as cnt, SUM(r.toucher) as touchers
            FROM rolls r
            JOIN sessions s ON s.id = r.session_id
            WHERE s.player_id = :pid
            GROUP BY r.end_length, r.delivery, r.result
        ');
        $stmt->execute(['pid' => $playerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return self::buildStatsFromRows($rows);
    }

    public static function stats(int $sessionId): array {
        $db = Database::getInstance();

        // One comprehensive query: end_length × delivery × result
        $stmt = $db->prepare('
            SELECT end_length, delivery, result, COUNT(*) as cnt, SUM(toucher) as touchers
            FROM rolls WHERE session_id = :id
            GROUP BY end_length, delivery, result
        ');
        $stmt->execute(['id' => $sessionId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data = self::buildStatsFromRows($rows);

        // By end (for legacy use)
        $stmt = $db->prepare('
            SELECT end_number, result, COUNT(*) as count
            FROM rolls WHERE session_id = :id
            GROUP BY end_number, result ORDER BY end_number
        ');
        $stmt->execute(['id' => $sessionId]);
        $data['by_end'] = $stmt->fetchAll();

        return $data;
    }

    private static function buildStatsFromRows(array $rows): array {
        $total      = 0;
        $touchers   = 0;
        $results    = [];
        $endLengths = [];
        $byDelivery = [13 => ['total'=>0,'touchers'=>0,'results'=>[]], 14 => ['total'=>0,'touchers'=>0,'results'=>[]]];
        $byLength   = [
            9  => ['total'=>0,'touchers'=>0,'results'=>[],'fh'=>['total'=>0,'touchers'=>0,'results'=>[]],'bh'=>['total'=>0,'touchers'=>0,'results'=>[]]],
            10 => ['total'=>0,'touchers'=>0,'results'=>[],'fh'=>['total'=>0,'touchers'=>0,'results'=>[]],'bh'=>['total'=>0,'touchers'=>0,'results'=>[]]],
            11 => ['total'=>0,'touchers'=>0,'results'=>[],'fh'=>['total'=>0,'touchers'=>0,'results'=>[]],'bh'=>['total'=>0,'touchers'=>0,'results'=>[]]],
        ];

        foreach ($rows as $r) {
            $cnt = (int)$r['cnt'];
            $t   = (int)$r['touchers'];
            $res = (int)$r['result'];
            $len = (int)$r['end_length'];
            $del = (int)$r['delivery'];

            $total    += $cnt;
            $touchers += $t;
            $results[$res]    = ($results[$res] ?? 0) + $cnt;
            $endLengths[$len] = ($endLengths[$len] ?? 0) + $cnt;

            if (isset($byDelivery[$del])) {
                $byDelivery[$del]['total']    += $cnt;
                $byDelivery[$del]['touchers'] += $t;
                $byDelivery[$del]['results'][$res] = ($byDelivery[$del]['results'][$res] ?? 0) + $cnt;
            }

            if (isset($byLength[$len])) {
                $byLength[$len]['total']    += $cnt;
                $byLength[$len]['touchers'] += $t;
                $byLength[$len]['results'][$res] = ($byLength[$len]['results'][$res] ?? 0) + $cnt;
                $key = $del === 14 ? 'fh' : ($del === 13 ? 'bh' : null);
                if ($key) {
                    $byLength[$len][$key]['total']    += $cnt;
                    $byLength[$len][$key]['touchers'] += $t;
                    $byLength[$len][$key]['results'][$res] = ($byLength[$len][$key]['results'][$res] ?? 0) + $cnt;
                }
            }
        }

        return [
            'total'       => $total,
            'touchers'    => $touchers,
            'results'     => $results,
            'end_lengths' => $endLengths,
            'by_end'      => [],
            'by_delivery' => $byDelivery,
            'by_length'   => $byLength,
        ];
    }
}
