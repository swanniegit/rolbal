<?php
/**
 * Game Session Page - Record Rolls
 */

require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/Session.php';
require_once __DIR__ . '/includes/Roll.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Template.php';

// Free play limit for anonymous users
define('FREE_GAMES_PER_MONTH', 3);

$isLoggedIn = Auth::check();
$sessionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$freeGamesUsed = 0;
$limitReached = false;

// Track anonymous games via cookie
if (!$isLoggedIn && !$sessionId) {
    $cookieName = 'rolbal_free_games';
    $currentMonth = date('Y-m');

    if (isset($_COOKIE[$cookieName])) {
        $data = json_decode($_COOKIE[$cookieName], true);
        if ($data && isset($data['month']) && $data['month'] === $currentMonth) {
            $freeGamesUsed = (int)$data['count'];
        }
    }

    $limitReached = $freeGamesUsed >= FREE_GAMES_PER_MONTH;
}
$session = null;
$rolls = [];
$currentEnd = 1;
$currentBowl = 1;
$gameComplete = false;

if ($sessionId) {
    $session = Session::find($sessionId);
    if ($session) {
        $rolls = Roll::forSession($sessionId);
        $rollCount = count($rolls);
        $bowlsPerEnd = (int)$session['bowls_per_end'];
        $totalEnds = (int)$session['total_ends'];

        // Calculate current position
        $currentEnd = floor($rollCount / $bowlsPerEnd) + 1;
        $currentBowl = ($rollCount % $bowlsPerEnd) + 1;

        // Check if starting new end (need to select length)
        $needEndLength = ($rollCount % $bowlsPerEnd) === 0 && $rollCount < ($totalEnds * $bowlsPerEnd);

        // Check if game complete
        if ($rollCount >= $totalEnds * $bowlsPerEnd) {
            $gameComplete = true;
            $currentEnd = $totalEnds;
            $currentBowl = $bowlsPerEnd;
        }
    }
}
?>
<?php Template::pageHead($session ? 'Game' : 'New Game'); ?>
<body>
    <div class="app-container">
        <header class="app-header compact">
            <a href="index.php" class="back-btn">&larr;</a>
            <h1 class="app-title"><?= $session ? 'Game' : 'New Game' ?></h1>
            <span class="roll-count" id="rollCount"><?= count($rolls) ?></span>
        </header>

        <?php if (!$session && $limitReached): ?>
        <!-- Free Play Limit Reached -->
        <main class="main-content">
            <div class="limit-prompt">
                <div class="limit-icon">🎳</div>
                <h2>Free Limit Reached</h2>
                <p>You've used your <?= FREE_GAMES_PER_MONTH ?> free games this month.</p>
                <p>Create an account to play unlimited games and track your progress!</p>
                <div class="auth-buttons">
                    <a href="register.php" class="btn-primary">Register Free</a>
                    <a href="login.php" class="btn-secondary">Login</a>
                </div>
                <p class="limit-note">Free games reset on the 1st of each month.</p>
            </div>
        </main>

        <?php elseif (!$session): ?>
        <!-- New Session Form -->
        <main class="main-content">
            <?php if (!$isLoggedIn): ?>
            <div class="free-games-notice">
                <?= FREE_GAMES_PER_MONTH - $freeGamesUsed ?> free game<?= (FREE_GAMES_PER_MONTH - $freeGamesUsed) !== 1 ? 's' : '' ?> remaining this month
            </div>
            <?php endif; ?>
            <form id="sessionForm" class="form-card">
                <div class="form-group">
                    <label>Hand</label>
                    <div class="btn-group">
                        <button type="button" class="btn-toggle" data-field="hand" data-value="L">Left</button>
                        <button type="button" class="btn-toggle" data-field="hand" data-value="R">Right</button>
                    </div>
                    <input type="hidden" name="hand" id="hand" required>
                </div>

                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="session_date" id="sessionDate" required>
                </div>

                <div class="form-group">
                    <label>Bowls per End</label>
                    <div class="btn-group">
                        <button type="button" class="btn-toggle" data-field="bowls_per_end" data-value="2">2</button>
                        <button type="button" class="btn-toggle" data-field="bowls_per_end" data-value="3">3</button>
                        <button type="button" class="btn-toggle active" data-field="bowls_per_end" data-value="4">4</button>
                    </div>
                    <input type="hidden" name="bowls_per_end" id="bowls_per_end" value="4">
                </div>

                <div class="form-group">
                    <label>Number of Ends</label>
                    <div class="btn-group">
                        <button type="button" class="btn-toggle" data-field="total_ends" data-value="10">10</button>
                        <button type="button" class="btn-toggle active" data-field="total_ends" data-value="15">15</button>
                        <button type="button" class="btn-toggle" data-field="total_ends" data-value="21">21</button>
                    </div>
                    <input type="hidden" name="total_ends" id="total_ends" value="15">
                </div>

                <div class="form-group">
                    <label>Description (optional)</label>
                    <input type="text" name="description" id="description" placeholder="e.g. Practice, League Match">
                </div>

                <button type="submit" class="btn-primary">Start Game</button>
            </form>
            <input type="hidden" id="isLoggedIn" value="<?= $isLoggedIn ? '1' : '0' ?>">
            <input type="hidden" id="freeGamesUsed" value="<?= $freeGamesUsed ?>">
        </main>

        <?php elseif ($gameComplete): ?>
        <!-- Game Complete -->
        <main class="main-content">
            <div class="game-complete">
                <h2>Game Complete!</h2>
                <p><?= $session['total_ends'] ?> ends, <?= count($rolls) ?> bowls</p>
                <a href="stats.php?id=<?= $sessionId ?>" class="btn-primary">View Statistics</a>
                <a href="index.php" class="btn-secondary">Back to Home</a>
            </div>
        </main>

        <?php else: ?>
        <!-- Roll Recording Interface -->
        <main class="main-content game-active">
            <div class="session-info">
                <span class="badge"><?= HANDS[$session['hand']] ?></span>
                <span><?= date('d M Y', strtotime($session['session_date'])) ?></span>
            </div>

            <!-- Progress -->
            <div class="game-progress">
                <div class="progress-text">
                    End <strong id="currentEnd"><?= $currentEnd ?></strong> of <?= $session['total_ends'] ?>
                    <span class="separator">|</span>
                    Bowl <strong id="currentBowl"><?= $currentBowl ?></strong> of <?= $session['bowls_per_end'] ?>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill" style="width: <?= (count($rolls) / ($session['total_ends'] * $session['bowls_per_end'])) * 100 ?>%"></div>
                </div>
            </div>

            <!-- Step 1: End Length (shown at start of each end) -->
            <div class="roll-step <?= !$needEndLength ? 'hidden' : '' ?>" id="stepEndLength">
                <h2>End <?= $currentEnd ?> - Length</h2>
                <div class="btn-group vertical">
                    <button type="button" class="btn-choice" data-field="end_length" data-value="11">Short End</button>
                    <button type="button" class="btn-choice" data-field="end_length" data-value="10">Middle End</button>
                    <button type="button" class="btn-choice" data-field="end_length" data-value="9">Long End</button>
                </div>
            </div>

            <!-- Step 2: Result Position -->
            <div class="roll-step <?= $needEndLength ? 'hidden' : '' ?>" id="stepResult">
                <h2>Bowl <?= $currentBowl ?></h2>
                <div class="result-row">
                    <div class="green-container">
                        <!-- Row 1: Top miss bar -->
                        <button type="button" class="btn-miss btn-miss-top" data-field="result" data-value="22">Too Long / Ditch</button>
                        <!-- Row 2: Left bar, Grid, Right bar -->
                        <button type="button" class="btn-miss btn-miss-left" data-field="result" data-value="20">Too Far Left</button>
                        <div class="green-grid">
                            <button type="button" class="btn-pos" data-field="result" data-value="5">Long Left</button>
                            <button type="button" class="btn-pos" data-field="result" data-value="7">Long Centre</button>
                            <button type="button" class="btn-pos" data-field="result" data-value="6">Long Right</button>
                            <button type="button" class="btn-pos" data-field="result" data-value="3">Level Left</button>
                            <button type="button" class="btn-pos target" data-field="result" data-value="8">Centre</button>
                            <button type="button" class="btn-pos" data-field="result" data-value="4">Level Right</button>
                            <button type="button" class="btn-pos" data-field="result" data-value="1">Short Left</button>
                            <button type="button" class="btn-pos" data-field="result" data-value="12">Short Centre</button>
                            <button type="button" class="btn-pos" data-field="result" data-value="2">Short Right</button>
                        </div>
                        <button type="button" class="btn-miss btn-miss-right" data-field="result" data-value="21">Too Far Right</button>
                        <!-- Row 3: Bottom miss bar -->
                        <button type="button" class="btn-miss btn-miss-bottom" data-field="result" data-value="23">Too Short</button>
                    </div>
                    <button type="button" class="btn-toucher" id="toucherBtn">Toucher</button>
                </div>
            </div>

            <!-- Undo Last Roll -->
            <div class="action-bar">
                <button type="button" class="btn-secondary" id="undoBtn" <?= empty($rolls) ? 'disabled' : '' ?>>Undo Last</button>
            </div>
        </main>

        <input type="hidden" id="sessionId" value="<?= $sessionId ?>">
        <input type="hidden" id="bowlsPerEnd" value="<?= $session['bowls_per_end'] ?>">
        <input type="hidden" id="totalEnds" value="<?= $session['total_ends'] ?>">
        <input type="hidden" id="totalRolls" value="<?= count($rolls) ?>">
        <?php endif; ?>
    </div>

    <script src="js/api.js"></script>
    <script src="js/ui.js"></script>
    <script src="js/game.js?v=3"></script>
</body>
</html>
