<?php
declare(strict_types=1);

/**
 * WhatsApp Scoring & Practice Flow
 *
 * Manages conversation state for:
 * 1. Competition fixture scoring
 * 2. Practice session recording
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/WhatsAppAPI.php';
require_once __DIR__ . '/CompetitionFixture.php';
require_once __DIR__ . '/CompetitionParticipant.php';
require_once __DIR__ . '/Competition.php';
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/Roll.php';
require_once __DIR__ . '/constants.php';

class WhatsAppScoring {

    // Session states
    const STATE_IDLE = 'idle';
    const STATE_MAIN_MENU = 'main_menu';
    const STATE_SELECTING_FIXTURE = 'selecting_fixture';
    const STATE_ENTERING_SCORE1 = 'entering_score1';
    const STATE_ENTERING_SCORE2 = 'entering_score2';
    const STATE_PRACTICE_HAND = 'practice_hand';
    const STATE_PRACTICE_BOWLS = 'practice_bowls';
    const STATE_PRACTICE_LENGTH = 'practice_length';
    const STATE_PRACTICE_RESULT = 'practice_result';
    const STATE_PRACTICE_TOUCHER = 'practice_toucher';
    const STATE_PRACTICE_CONTINUE = 'practice_continue';

    // Result position labels (short versions for WhatsApp buttons)
    const RESULT_LABELS = [
        1  => 'Short L',
        2  => 'Short R',
        3  => 'Level L',
        4  => 'Level R',
        5  => 'Long L',
        6  => 'Long R',
        7  => 'Long C',
        8  => 'Centre',
        12 => 'Short C',
        20 => 'Miss Left',
        21 => 'Miss Right',
        22 => 'Ditch/Long',
        23 => 'Too Short'
    ];

    /**
     * Get or create session for phone number
     */
    public static function getSession(string $phone): ?array {
        $phone = WhatsAppAPI::normalizePhone($phone);
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT * FROM whatsapp_sessions WHERE phone_number = :phone');
        $stmt->execute(['phone' => $phone]);
        $session = $stmt->fetch();

        if (!$session) {
            // Try to find linked player
            $stmt = $db->prepare('SELECT id FROM players WHERE whatsapp_number = :phone');
            $stmt->execute(['phone' => $phone]);
            $player = $stmt->fetch();

            // Create new session
            $stmt = $db->prepare('
                INSERT INTO whatsapp_sessions (phone_number, player_id, state)
                VALUES (:phone, :player_id, :state)
            ');
            $stmt->execute([
                'phone' => $phone,
                'player_id' => $player ? $player['id'] : null,
                'state' => self::STATE_IDLE
            ]);

            return self::getSession($phone);
        }

        return $session;
    }

    /**
     * Update session state and data
     */
    public static function updateSession(string $phone, array $data): bool {
        $phone = WhatsAppAPI::normalizePhone($phone);
        $db = Database::getInstance();

        $allowed = ['state', 'fixture_id', 'score1', 'competition_id', 'player_id',
                    'session_id', 'current_hand', 'current_end_length', 'current_end_number', 'bowl_count'];
        $updates = [];
        $params = ['phone' => $phone];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $updates[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $sql = 'UPDATE whatsapp_sessions SET ' . implode(', ', $updates) . ' WHERE phone_number = :phone';
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Clear session back to idle
     */
    public static function clearSession(string $phone): bool {
        $phone = WhatsAppAPI::normalizePhone($phone);
        $db = Database::getInstance();

        $stmt = $db->prepare('
            UPDATE whatsapp_sessions
            SET state = :state, fixture_id = NULL, score1 = NULL, competition_id = NULL,
                session_id = NULL, current_hand = NULL, current_end_length = NULL,
                current_end_number = 1, bowl_count = 0
            WHERE phone_number = :phone
        ');
        return $stmt->execute(['phone' => $phone, 'state' => self::STATE_IDLE]);
    }

    /**
     * Handle incoming message - main entry point
     */
    public static function handleMessage(string $phone, string $type, ?string $content, ?array $interactiveData = null): void {
        $session = self::getSession($phone);

        if (!$session['player_id']) {
            WhatsAppAPI::sendText($phone,
                "Your phone number is not linked to a BowlsTracker account.\n\n" .
                "Please ask your club admin to link your WhatsApp number to your profile."
            );
            return;
        }

        // Extract button/list ID if interactive
        $actionId = null;
        if ($interactiveData) {
            $actionId = $interactiveData['id'] ?? null;
        }

        // Handle cancel/menu at any time
        if ($content === 'menu' || $content === 'cancel' || $actionId === 'menu' || $actionId === 'cancel') {
            self::clearSession($phone);
            self::showMainMenu($phone, $session);
            return;
        }

        // Handle "done" to end practice session at any time
        if (($content === 'done' || $actionId === 'done') && $session['session_id']) {
            self::endPracticeSession($phone, $session);
            return;
        }

        // Handle "undo" to remove last bowl
        if (($content === 'undo' || $actionId === 'undo') && $session['session_id']) {
            self::undoLastBowl($phone, $session);
            return;
        }

        // Handle based on current state
        switch ($session['state']) {
            case self::STATE_IDLE:
            case self::STATE_MAIN_MENU:
                self::handleMainMenu($phone, $session, $content, $actionId);
                break;

            case self::STATE_SELECTING_FIXTURE:
                self::handleFixtureSelection($phone, $session, $content, $actionId);
                break;

            case self::STATE_ENTERING_SCORE1:
                self::handleScore1Entry($phone, $session, $content, $interactiveData);
                break;

            case self::STATE_ENTERING_SCORE2:
                self::handleScore2Entry($phone, $session, $content, $interactiveData);
                break;

            case self::STATE_PRACTICE_HAND:
                self::handlePracticeHand($phone, $session, $actionId);
                break;

            case self::STATE_PRACTICE_BOWLS:
                self::handlePracticeBowls($phone, $session, $actionId);
                break;

            case self::STATE_PRACTICE_LENGTH:
                self::handlePracticeLength($phone, $session, $actionId);
                break;

            case self::STATE_PRACTICE_RESULT:
                self::handlePracticeResult($phone, $session, $actionId);
                break;

            case self::STATE_PRACTICE_TOUCHER:
                self::handlePracticeToucher($phone, $session, $actionId);
                break;

            case self::STATE_PRACTICE_CONTINUE:
                self::handlePracticeContinue($phone, $session, $actionId);
                break;

            default:
                self::showMainMenu($phone, $session);
        }
    }

    /**
     * Show main menu with options
     */
    private static function showMainMenu(string $phone, array $session): void {
        self::updateSession($phone, ['state' => self::STATE_MAIN_MENU]);

        $pendingFixtures = self::getPendingFixturesForPlayer($session['player_id']);
        $fixtureCount = count($pendingFixtures);

        $buttons = [
            ['id' => 'practice', 'title' => 'Start Practice']
        ];

        if ($fixtureCount > 0) {
            $buttons[] = ['id' => 'fixtures', 'title' => "Score Fixture ($fixtureCount)"];
        }

        $buttons[] = ['id' => 'stats', 'title' => 'My Stats'];

        WhatsAppAPI::sendButtons($phone,
            "Welcome to BowlsTracker!\n\nWhat would you like to do?",
            $buttons
        );
    }

    /**
     * Handle main menu selection
     */
    private static function handleMainMenu(string $phone, array $session, ?string $content, ?string $actionId): void {
        $action = $actionId ?? strtolower(trim($content ?? ''));

        switch ($action) {
            case 'practice':
            case 'start practice':
                self::startPractice($phone, $session);
                break;

            case 'fixtures':
            case 'score fixture':
                self::showFixtureList($phone, $session);
                break;

            case 'stats':
            case 'my stats':
                self::showStats($phone, $session);
                break;

            default:
                self::showMainMenu($phone, $session);
        }
    }

    // ========== PRACTICE FLOW ==========

    /**
     * Start practice session - ask for hand + bowls combined
     */
    private static function startPractice(string $phone, array $session): void {
        self::updateSession($phone, ['state' => self::STATE_PRACTICE_HAND]);

        $rows = [
            ['id' => 'setup_L_4', 'title' => 'Left Hand, 4 Bowls', 'description' => ''],
            ['id' => 'setup_R_4', 'title' => 'Right Hand, 4 Bowls', 'description' => ''],
            ['id' => 'setup_L_3', 'title' => 'Left Hand, 3 Bowls', 'description' => ''],
            ['id' => 'setup_R_3', 'title' => 'Right Hand, 3 Bowls', 'description' => ''],
            ['id' => 'setup_L_2', 'title' => 'Left Hand, 2 Bowls', 'description' => ''],
            ['id' => 'setup_R_2', 'title' => 'Right Hand, 2 Bowls', 'description' => '']
        ];

        WhatsAppAPI::sendList($phone,
            "New practice session\n\n_Type 'undo' or 'done' anytime_",
            "Select Setup",
            $rows,
            "Hand & Bowls"
        );
    }

    /**
     * Handle hand + bowls selection (combined)
     */
    private static function handlePracticeHand(string $phone, array $session, ?string $actionId): void {
        if (!$actionId || !str_starts_with($actionId, 'setup_')) {
            WhatsAppAPI::sendText($phone, "Please select your hand and bowls per end.");
            return;
        }

        // Parse setup_L_4 -> hand=L, bowls=4
        $parts = explode('_', $actionId);
        $hand = $parts[1] ?? 'R';
        $bowlsPerEnd = (int)($parts[2] ?? 4);

        // Create the practice session
        $sessionId = Session::create($hand, date('Y-m-d'), $bowlsPerEnd, 15, 'WhatsApp Practice', $session['player_id']);

        self::updateSession($phone, [
            'state' => self::STATE_PRACTICE_LENGTH,
            'session_id' => $sessionId,
            'current_hand' => $hand,
            'current_end_number' => 1,
            'bowl_count' => 0
        ]);

        self::askEndLength($phone, 1);
    }

    /**
     * Handle bowls per end selection (legacy - now combined with hand)
     */
    private static function handlePracticeBowls(string $phone, array $session, ?string $actionId): void {
        // Redirect to hand selection if somehow we get here
        self::startPractice($phone, $session);
    }

    /**
     * Ask for end length
     */
    private static function askEndLength(string $phone, int $endNumber): void {
        WhatsAppAPI::sendButtons($phone,
            "End $endNumber - Select length:\n\n_Type 'undo' or 'done' anytime_",
            [
                ['id' => 'len_11', 'title' => 'Short'],
                ['id' => 'len_10', 'title' => 'Middle'],
                ['id' => 'len_9', 'title' => 'Long']
            ]
        );
    }

    /**
     * Handle end length selection
     */
    private static function handlePracticeLength(string $phone, array $session, ?string $actionId): void {
        if (!$actionId || !str_starts_with($actionId, 'len_')) {
            WhatsAppAPI::sendText($phone, "Please select Short, Middle, or Long.");
            return;
        }

        $length = (int)substr($actionId, 4); // 9, 10, or 11

        self::updateSession($phone, [
            'state' => self::STATE_PRACTICE_RESULT,
            'current_end_length' => $length
        ]);

        self::askResult($phone, $session['current_end_number'] ?? 1, ($session['bowl_count'] ?? 0) + 1);
    }

    /**
     * Ask for result position - send grid image then list
     */
    private static function askResult(string $phone, int $endNumber, int $bowlNumber): void {
        // Send the visual grid image
        $gridUrl = 'https://bowlstracker.co.za/assets/position-grid.png';
        WhatsAppAPI::sendImage($phone, $gridUrl, "End $endNumber, Bowl $bowlNumber");

        // Then send the position list
        $rows = [
            ['id' => 'res_8', 'title' => 'Centre', 'description' => 'Perfect draw'],
            ['id' => 'res_12', 'title' => 'Short Centre', 'description' => 'On line, short'],
            ['id' => 'res_7', 'title' => 'Long Centre', 'description' => 'On line, long'],
            ['id' => 'res_3', 'title' => 'Level Left', 'description' => 'Level with jack'],
            ['id' => 'res_4', 'title' => 'Level Right', 'description' => 'Level with jack'],
            ['id' => 'res_1', 'title' => 'Short Left', 'description' => ''],
            ['id' => 'res_2', 'title' => 'Short Right', 'description' => ''],
            ['id' => 'res_5', 'title' => 'Long Left', 'description' => ''],
            ['id' => 'res_6', 'title' => 'Long Right', 'description' => ''],
            ['id' => 'res_miss', 'title' => 'Miss', 'description' => 'Off the green']
        ];

        WhatsAppAPI::sendList($phone,
            "Select position:",
            "Positions",
            $rows,
            "Bowl Position"
        );
    }

    /**
     * Handle result selection
     */
    private static function handlePracticeResult(string $phone, array $session, ?string $actionId): void {
        if (!$actionId || !str_starts_with($actionId, 'res_')) {
            WhatsAppAPI::sendText($phone, "Please select a position from the list.");
            return;
        }

        $resultCode = substr($actionId, 4);

        if ($resultCode === 'miss') {
            self::askMissType($phone);
            return;
        }

        $result = (int)$resultCode;

        // Store result temporarily and ask about toucher
        self::updateSession($phone, [
            'state' => self::STATE_PRACTICE_TOUCHER,
            'score1' => $result
        ]);

        WhatsAppAPI::sendButtons($phone,
            "Was it a toucher?",
            [
                ['id' => 'touch_yes', 'title' => 'Yes, Toucher!'],
                ['id' => 'touch_no', 'title' => 'No']
            ]
        );
    }

    /**
     * Ask miss type
     */
    private static function askMissType(string $phone): void {
        $rows = [
            ['id' => 'res_22', 'title' => 'Too Long / Ditch', 'description' => ''],
            ['id' => 'res_23', 'title' => 'Too Short', 'description' => ''],
            ['id' => 'res_20', 'title' => 'Too Far Left', 'description' => ''],
            ['id' => 'res_21', 'title' => 'Too Far Right', 'description' => '']
        ];

        WhatsAppAPI::sendList($phone,
            "What type of miss?",
            "Select",
            $rows,
            "Miss Type"
        );
    }

    /**
     * Handle toucher selection
     */
    private static function handlePracticeToucher(string $phone, array $session, ?string $actionId): void {
        $toucher = ($actionId === 'touch_yes') ? 1 : 0;
        $result = (int)$session['score1'];
        // Record the roll
        Roll::create(
            $session['session_id'],
            $session['current_end_number'],
            $session['current_end_length'],
            $result,
            $toucher
        );

        $bowlCount = ($session['bowl_count'] ?? 0) + 1;
        $endNumber = $session['current_end_number'];

        // Get bowls_per_end from the practice session
        $practiceSession = Session::find($session['session_id']);
        $bowlsPerEnd = $practiceSession['bowls_per_end'] ?? 4;

        // Get position name for brief confirmation
        $positionName = self::RESULT_LABELS[$result] ?? 'Miss';
        $toucherText = $toucher ? ' (T)' : '';

        if ($bowlCount < $bowlsPerEnd) {
            // More bowls to go in this end - go directly to next bowl
            self::updateSession($phone, [
                'state' => self::STATE_PRACTICE_RESULT,
                'bowl_count' => $bowlCount,
                'score1' => null
            ]);

            // Brief confirmation then ask for next bowl position
            WhatsAppAPI::sendText($phone, "✓ {$positionName}{$toucherText}");
            self::askResult($phone, $endNumber, $bowlCount + 1);
        } else {
            // End complete - ask what's next
            self::updateSession($phone, [
                'state' => self::STATE_PRACTICE_CONTINUE,
                'bowl_count' => $bowlCount,
                'score1' => null
            ]);

            WhatsAppAPI::sendButtons($phone,
                "✓ {$positionName}{$toucherText}\n\n" .
                "End $endNumber complete ({$bowlCount} bowls)",
                [
                    ['id' => 'next_end', 'title' => 'Next End'],
                    ['id' => 'end_session', 'title' => 'End Session']
                ]
            );
        }
    }

    /**
     * Handle continue/next selection
     */
    private static function handlePracticeContinue(string $phone, array $session, ?string $actionId): void {
        switch ($actionId) {
            case 'next_bowl':
                // Same end, next bowl
                self::updateSession($phone, ['state' => self::STATE_PRACTICE_RESULT]);
                self::askResult($phone, $session['current_end_number'], ($session['bowl_count'] ?? 0) + 1);
                break;

            case 'next_end':
                // New end
                $newEnd = ($session['current_end_number'] ?? 1) + 1;
                self::updateSession($phone, [
                    'state' => self::STATE_PRACTICE_LENGTH,
                    'current_end_number' => $newEnd,
                    'bowl_count' => 0
                ]);
                self::askEndLength($phone, $newEnd);
                break;

            case 'end_session':
                self::endPracticeSession($phone, $session);
                break;

            default:
                WhatsAppAPI::sendText($phone, "Please select Next Bowl, Next End, or End Session.");
        }
    }

    /**
     * End practice session and show summary
     */
    private static function endPracticeSession(string $phone, array $session): void {
        $stats = Roll::stats($session['session_id']);
        $sessionData = Session::find($session['session_id']);

        $total = $stats['total'];
        $touchers = $stats['touchers'];
        $toucherPct = $total > 0 ? round(($touchers / $total) * 100) : 0;

        // Count good positions (centre, level)
        $good = 0;
        foreach ([8, 12, 7, 3, 4] as $pos) {
            $good += $stats['results'][$pos] ?? 0;
        }
        $goodPct = $total > 0 ? round(($good / $total) * 100) : 0;

        $summary = "Practice session complete!\n\n";
        $summary .= "Total bowls: *{$total}*\n";
        $summary .= "Touchers: *{$touchers}* ({$toucherPct}%)\n";
        $summary .= "Good positions: *{$good}* ({$goodPct}%)\n\n";
        $summary .= "View full stats at bowlstracker.co.za";

        WhatsAppAPI::sendText($phone, $summary);

        self::clearSession($phone);

        // Offer to start another
        WhatsAppAPI::sendButtons($phone,
            "What would you like to do?",
            [
                ['id' => 'practice', 'title' => 'New Practice'],
                ['id' => 'menu', 'title' => 'Main Menu']
            ]
        );
    }

    /**
     * Undo last recorded bowl
     */
    private static function undoLastBowl(string $phone, array $session): void {
        $deleted = Roll::undoLast($session['session_id']);

        if (!$deleted) {
            WhatsAppAPI::sendText($phone, "Nothing to undo.");
            return;
        }

        // Decrement bowl count
        $bowlCount = max(0, ($session['bowl_count'] ?? 1) - 1);
        $endNumber = $session['current_end_number'];

        self::updateSession($phone, [
            'state' => self::STATE_PRACTICE_RESULT,
            'bowl_count' => $bowlCount,
            'score1' => null
        ]);

        WhatsAppAPI::sendText($phone, "Undone! Re-enter bowl " . ($bowlCount + 1));
        self::askResult($phone, $endNumber, $bowlCount + 1);
    }

    /**
     * Show player stats
     */
    private static function showStats(string $phone, array $session): void {
        require_once __DIR__ . '/Player.php';

        $stats = Player::getStats($session['player_id']);

        $msg = "Your BowlsTracker Stats:\n\n";
        $msg .= "Sessions: *{$stats['total_sessions']}*\n";
        $msg .= "Total Bowls: *{$stats['total_rolls']}*\n";

        if ($stats['first_session']) {
            $msg .= "First session: " . date('d M Y', strtotime($stats['first_session'])) . "\n";
        }
        if ($stats['last_session']) {
            $msg .= "Last session: " . date('d M Y', strtotime($stats['last_session'])) . "\n";
        }

        $msg .= "\nView detailed stats at bowlstracker.co.za";

        WhatsAppAPI::sendText($phone, $msg);

        self::clearSession($phone);
        self::showMainMenu($phone, $session);
    }

    // ========== FIXTURE SCORING FLOW ==========

    /**
     * Show fixture list
     */
    private static function showFixtureList(string $phone, array $session): void {
        $fixtures = self::getPendingFixturesForPlayer($session['player_id']);

        if (empty($fixtures)) {
            WhatsAppAPI::sendText($phone, "You have no pending fixtures to score.");
            self::showMainMenu($phone, $session);
            return;
        }

        self::updateSession($phone, ['state' => self::STATE_SELECTING_FIXTURE]);

        if (count($fixtures) <= 3) {
            $buttons = [];
            foreach ($fixtures as $f) {
                $buttons[] = [
                    'id' => 'fix_' . $f['id'],
                    'title' => substr($f['short_name'], 0, 20)
                ];
            }
            WhatsAppAPI::sendButtons($phone, "Select a fixture to score:", $buttons);
        } else {
            $rows = [];
            foreach ($fixtures as $f) {
                $rows[] = [
                    'id' => 'fix_' . $f['id'],
                    'title' => $f['short_name'],
                    'description' => $f['competition_name'] ?? ''
                ];
            }
            WhatsAppAPI::sendList($phone, "Select a fixture to score:", "View Fixtures", $rows, "Pending");
        }
    }

    /**
     * Handle fixture selection
     */
    private static function handleFixtureSelection(string $phone, array $session, ?string $content, ?string $actionId): void {
        $id = $actionId ?? $content;

        if (!$id || !preg_match('/fix_(\d+)/', $id, $matches)) {
            WhatsAppAPI::sendText($phone, "Please select a fixture from the list.");
            return;
        }

        $fixtureId = (int)$matches[1];
        $fixture = CompetitionFixture::findWithDetails($fixtureId);

        if (!$fixture) {
            WhatsAppAPI::sendText($phone, "Fixture not found.");
            self::showMainMenu($phone, $session);
            return;
        }

        if (!self::canPlayerScoreFixture($session['player_id'], $fixture)) {
            WhatsAppAPI::sendText($phone, "You don't have permission to score this fixture.");
            self::showMainMenu($phone, $session);
            return;
        }

        if ($fixture['status'] === 'completed') {
            WhatsAppAPI::sendText($phone, "This fixture has already been completed.");
            self::showMainMenu($phone, $session);
            return;
        }

        self::updateSession($phone, [
            'state' => self::STATE_ENTERING_SCORE1,
            'fixture_id' => $fixtureId,
            'competition_id' => $fixture['competition_id']
        ]);

        $team1Name = $fixture['participant1_name'] ?? 'Team 1';
        self::sendScorePrompt($phone, $team1Name);
    }

    /**
     * Handle score 1 entry
     */
    private static function handleScore1Entry(string $phone, array $session, ?string $content, ?array $interactiveData): void {
        $score = self::extractScore($content, $interactiveData);

        if ($score === null || $score < 0) {
            WhatsAppAPI::sendText($phone, "Please enter a valid score (0 or higher).");
            return;
        }

        self::updateSession($phone, [
            'state' => self::STATE_ENTERING_SCORE2,
            'score1' => $score
        ]);

        $fixture = CompetitionFixture::findWithDetails($session['fixture_id']);
        $team2Name = $fixture['participant2_name'] ?? 'Team 2';

        self::sendScorePrompt($phone, $team2Name);
    }

    /**
     * Handle score 2 entry
     */
    private static function handleScore2Entry(string $phone, array $session, ?string $content, ?array $interactiveData): void {
        $score2 = self::extractScore($content, $interactiveData);

        if ($score2 === null || $score2 < 0) {
            WhatsAppAPI::sendText($phone, "Please enter a valid score (0 or higher).");
            return;
        }

        $score1 = (int)$session['score1'];
        $fixtureId = (int)$session['fixture_id'];

        $fixture = CompetitionFixture::findWithDetails($fixtureId);

        if (!$fixture) {
            WhatsAppAPI::sendText($phone, "Error: Fixture not found.");
            self::clearSession($phone);
            return;
        }

        $result = CompetitionFixture::recordDetailedScore($fixtureId, $score1, $score2);

        if ($result) {
            $team1Name = $fixture['participant1_name'] ?? 'Team 1';
            $team2Name = $fixture['participant2_name'] ?? 'Team 2';

            $winner = $score1 > $score2 ? $team1Name : ($score2 > $score1 ? $team2Name : 'Draw');

            WhatsAppAPI::sendText($phone,
                "Score recorded!\n\n" .
                "*{$team1Name}*: {$score1}\n" .
                "*{$team2Name}*: {$score2}\n\n" .
                ($score1 !== $score2 ? "Winner: {$winner}" : "Result: Draw")
            );

            self::clearSession($phone);

            $remainingFixtures = self::getPendingFixturesForPlayer($session['player_id']);
            if (!empty($remainingFixtures)) {
                WhatsAppAPI::sendButtons($phone,
                    "You have " . count($remainingFixtures) . " more fixture(s) to score.",
                    [
                        ['id' => 'fixtures', 'title' => 'Score Another'],
                        ['id' => 'menu', 'title' => 'Main Menu']
                    ]
                );
            } else {
                self::showMainMenu($phone, $session);
            }
        } else {
            WhatsAppAPI::sendText($phone, "Error recording score. Please try again.");
            self::clearSession($phone);
        }
    }

    /**
     * Send score prompt with list picker
     */
    private static function sendScorePrompt(string $phone, string $teamName): void {
        $rows = [];
        for ($i = 0; $i <= 9; $i++) {
            $rows[] = ['id' => 'score_' . $i, 'title' => (string)$i, 'description' => ''];
        }

        WhatsAppAPI::sendList($phone,
            "Enter score for *{$teamName}*:\n\nSelect from list or type a number for scores above 9.",
            "Select Score",
            $rows,
            "Score"
        );
    }

    /**
     * Extract score from message
     */
    private static function extractScore(?string $content, ?array $interactiveData): ?int {
        if ($interactiveData) {
            $id = $interactiveData['id'] ?? '';
            if (str_starts_with($id, 'score_')) {
                return (int)substr($id, 6);
            }
        }

        if ($content !== null) {
            $content = trim($content);
            if (is_numeric($content) && (int)$content >= 0) {
                return (int)$content;
            }
        }

        return null;
    }

    /**
     * Get pending fixtures for player
     */
    public static function getPendingFixturesForPlayer(int $playerId): array {
        $db = Database::getInstance();

        $stmt = $db->prepare('
            SELECT cf.id, cf.competition_id, cf.status,
                   c.name as competition_name, c.club_id
            FROM competition_fixtures cf
            JOIN competitions c ON c.id = cf.competition_id
            JOIN club_members cm ON cm.club_id = c.club_id AND cm.player_id = :player_id
            WHERE cf.status IN ("pending", "scheduled")
            AND cf.participant1_id IS NOT NULL
            AND cf.participant2_id IS NOT NULL
            AND cm.role IN ("owner", "admin")
            ORDER BY cf.scheduled_at ASC, cf.id ASC
            LIMIT 10
        ');
        $stmt->execute(['player_id' => $playerId]);
        $fixtures = $stmt->fetchAll();

        foreach ($fixtures as &$f) {
            $detailed = CompetitionFixture::findWithDetails($f['id']);
            if ($detailed) {
                $p1 = substr($detailed['participant1_name'], 0, 10);
                $p2 = substr($detailed['participant2_name'], 0, 10);
                $f['short_name'] = "{$p1} v {$p2}";
            } else {
                $f['short_name'] = "Fixture #{$f['id']}";
            }
        }

        return $fixtures;
    }

    /**
     * Check if player can score fixture
     */
    private static function canPlayerScoreFixture(int $playerId, array $fixture): bool {
        return Competition::canManage($playerId, $fixture['competition_id']);
    }

    /**
     * Link phone to player
     */
    public static function linkPhoneToPlayer(int $playerId, string $phone): bool {
        $phone = WhatsAppAPI::normalizePhone($phone);
        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            $stmt = $db->prepare('UPDATE players SET whatsapp_number = :phone WHERE id = :id');
            $stmt->execute(['phone' => $phone, 'id' => $playerId]);

            $stmt = $db->prepare('UPDATE whatsapp_sessions SET player_id = :player_id WHERE phone_number = :phone');
            $stmt->execute(['player_id' => $playerId, 'phone' => $phone]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Unlink phone from player
     */
    public static function unlinkPhone(int $playerId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE players SET whatsapp_number = NULL WHERE id = :id');
        return $stmt->execute(['id' => $playerId]);
    }
}
