<?php
/**
 * Create Match Page - Setup new match form
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/ClubMember.php';
require_once __DIR__ . '/../includes/GameMatch.php';
require_once __DIR__ . '/../includes/Template.php';

$isLoggedIn = Auth::check();
$playerId = Auth::id();
$flash = Auth::getFlash();

if (!$isLoggedIn) {
    header('Location: ../login.php');
    exit;
}

$clubId = isset($_GET['club']) ? (int)$_GET['club'] : 0;
if (!$clubId) {
    Auth::flash('error', 'Club ID required');
    header('Location: ../clubs/index.php');
    exit;
}

// Verify permissions
if (!GameMatch::canCreate($playerId, $clubId)) {
    Auth::flash('error', 'Only club admins can create matches');
    header('Location: index.php?club=' . $clubId);
    exit;
}

$club = Club::find($clubId);
if (!$club) {
    Auth::flash('error', 'Club not found');
    header('Location: ../clubs/index.php');
    exit;
}

$gameTypes = GameMatch::getGameTypes();
$members = Club::getMembers($clubId);

Template::pageHead('New Match', ['../css/pages/match-create.css'], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header('New Match', 'index.php?club=' . $clubId); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>

            <form id="matchForm">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="club_id" value="<?= $clubId ?>">
                <input type="hidden" name="game_type" id="gameTypeInput" value="singles">
                <input type="hidden" name="scoring_mode" id="scoringModeInput" value="first_to">

                <!-- Game Type Selection -->
                <div class="form-section">
                    <h3>Game Type</h3>
                    <div class="game-type-grid">
                        <div class="game-type-btn active" data-type="singles" data-mode="first_to">
                            <span class="name">Singles</span>
                            <span class="desc">1v1 · 4 bowls</span>
                        </div>
                        <div class="game-type-btn" data-type="pairs" data-mode="ends">
                            <span class="name">Pairs</span>
                            <span class="desc">2v2 · 3-4 bowls</span>
                        </div>
                        <div class="game-type-btn" data-type="trips" data-mode="ends">
                            <span class="name">Trips</span>
                            <span class="desc">3v3 · 2-3 bowls</span>
                        </div>
                        <div class="game-type-btn" data-type="fours" data-mode="ends">
                            <span class="name">Fours</span>
                            <span class="desc">4v4 · 2 bowls</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Bowls per Player</label>
                            <select name="bowls_per_player" id="bowlsSelect">
                                <option value="4">4 bowls</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label id="targetLabel">First to</label>
                            <select name="target_score" id="targetSelect">
                                <option value="21" selected>21 points</option>
                                <option value="25">25 points</option>
                                <option value="31">31 points</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Team 1 -->
                <div class="team-section team-1">
                    <h4>Team 1</h4>
                    <div class="form-group">
                        <label>Team Name</label>
                        <input type="text" name="team1_name" placeholder="Team 1">
                    </div>
                    <div class="position-row" data-position="skip">
                        <div class="position-label">Skip</div>
                        <input type="text" name="team1_skip" placeholder="Player name" list="memberList">
                    </div>
                    <div class="position-row hidden" data-position="third">
                        <div class="position-label">Third</div>
                        <input type="text" name="team1_third" placeholder="Player name" list="memberList">
                    </div>
                    <div class="position-row hidden" data-position="second">
                        <div class="position-label">Second</div>
                        <input type="text" name="team1_second" placeholder="Player name" list="memberList">
                    </div>
                    <div class="position-row hidden" data-position="lead">
                        <div class="position-label">Lead</div>
                        <input type="text" name="team1_lead" placeholder="Player name" list="memberList">
                    </div>
                </div>

                <!-- Team 2 -->
                <div class="team-section team-2">
                    <h4>Team 2</h4>
                    <div class="form-group">
                        <label>Team Name</label>
                        <input type="text" name="team2_name" placeholder="Team 2">
                    </div>
                    <div class="position-row" data-position="skip">
                        <div class="position-label">Skip</div>
                        <input type="text" name="team2_skip" placeholder="Player name" list="memberList">
                    </div>
                    <div class="position-row hidden" data-position="third">
                        <div class="position-label">Third</div>
                        <input type="text" name="team2_third" placeholder="Player name" list="memberList">
                    </div>
                    <div class="position-row hidden" data-position="second">
                        <div class="position-label">Second</div>
                        <input type="text" name="team2_second" placeholder="Player name" list="memberList">
                    </div>
                    <div class="position-row hidden" data-position="lead">
                        <div class="position-label">Lead</div>
                        <input type="text" name="team2_lead" placeholder="Player name" list="memberList">
                    </div>
                </div>

                <!-- Member datalist for autocomplete -->
                <datalist id="memberList">
                    <?php foreach ($members as $member): ?>
                    <option value="<?= htmlspecialchars($member['name']) ?>">
                    <?php endforeach; ?>
                </datalist>

                <button type="submit" class="btn-start" id="startBtn">Create Match</button>
            </form>
        </main>
    </div>

    <script>
    const GAME_TYPES = <?= json_encode($gameTypes) ?>;
    </script>
    <script src="../js/match-create.js"></script>
</body>
</html>
