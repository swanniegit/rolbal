<?php
/**
 * Players Page - Profile & Stats
 */

require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Player.php';
require_once __DIR__ . '/includes/ClubMember.php';
require_once __DIR__ . '/includes/Template.php';

$isLoggedIn = Auth::check();
$currentUser = $isLoggedIn ? Auth::user() : null;
$flash = Auth::getFlash();

$viewingPlayerId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$viewingPlayer = $viewingPlayerId ? Player::find($viewingPlayerId) : null;

$isOwnProfile = $isLoggedIn && (!$viewingPlayerId || $viewingPlayerId === Auth::id());

if ($isOwnProfile && $currentUser) {
    $player = $currentUser;
    $stats = Player::getStats($player['id']);
    $sessions = Player::getSessions($player['id']);
    $playerClubs = ClubMember::getPlayerClubs($player['id']);
    $primaryClub = ClubMember::getPrimaryClub($player['id']);
} elseif ($viewingPlayer) {
    $player = $viewingPlayer;
    $stats = Player::getStats($player['id']);
    $sessions = Player::getPublicSessions($player['id']);
    $playerClubs = ClubMember::getPlayerClubs($player['id']);
    $primaryClub = ClubMember::getPrimaryClub($player['id']);
} else {
    $player = null;
    $stats = null;
    $sessions = [];
    $playerClubs = [];
    $primaryClub = null;
}

$pageTitle = $player ? htmlspecialchars($player['name']) : 'Players';
$headerTitle = $player ? 'Profile' : 'Players';
$rightHtml = $isLoggedIn
    ? '<a href="api/auth.php?action=logout" class="logout-btn" id="logoutBtn">Logout</a>'
    : '<span></span>';

Template::pageHead($pageTitle);
?>
<body>
    <div class="app-container">
        <?php Template::header($headerTitle, 'index.php', $rightHtml); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>

            <?php if ($player): ?>
            <!-- Player Profile -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar"><?= strtoupper(substr($player['name'], 0, 1)) ?></div>
                    <div class="profile-info">
                        <h2 class="profile-name"><?= htmlspecialchars($player['name']) ?></h2>
                        <span class="badge"><?= HANDS[$player['hand']] ?> Hand</span>
                    </div>
                </div>

                <?php if ($stats): ?>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-value"><?= $stats['total_sessions'] ?></span>
                        <span class="stat-label">Sessions</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $stats['total_rolls'] ?></span>
                        <span class="stat-label">Rolls</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $stats['first_session'] ? date('M Y', strtotime($stats['first_session'])) : '-' ?></span>
                        <span class="stat-label">Since</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Club Memberships -->
            <?php if (!empty($playerClubs)): ?>
            <div class="section-header">
                <h3>Clubs</h3>
            </div>
            <div class="club-list">
                <?php foreach ($playerClubs as $club): ?>
                <a href="clubs/view.php?slug=<?= htmlspecialchars($club['slug']) ?>" class="club-item <?= ($primaryClub && $primaryClub['id'] === $club['id']) ? 'primary' : '' ?>">
                    <div class="club-icon-wrap small">
                        <?php if ($club['icon_filename']): ?>
                        <img src="assets/club-icons/<?= htmlspecialchars($club['icon_filename']) ?>" alt="" class="club-icon">
                        <?php else: ?>
                        <span class="club-icon-placeholder"><?= strtoupper(substr($club['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="club-info">
                        <span class="club-name"><?= htmlspecialchars($club['name']) ?></span>
                        <span class="club-meta">
                            <span class="badge small"><?= ucfirst($club['role']) ?></span>
                            <?php if ($primaryClub && $primaryClub['id'] === $club['id']): ?>
                            <span class="badge small primary-badge">Primary</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($isOwnProfile): ?>
                    <button type="button"
                            class="set-primary-btn <?= ($primaryClub && $primaryClub['id'] === $club['id']) ? 'hidden' : '' ?>"
                            data-club-id="<?= $club['id'] ?>"
                            title="Set as primary club">
                        &#9734;
                    </button>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php elseif ($isOwnProfile): ?>
            <div class="section-header">
                <h3>Clubs</h3>
            </div>
            <div class="empty-note">
                <p>You haven't joined any clubs yet.</p>
                <a href="clubs/index.php" class="btn-secondary">Browse Clubs</a>
            </div>
            <?php endif; ?>

            <!-- Recent Sessions -->
            <?php if (!empty($sessions)): ?>
            <div class="section-header">
                <h3><?= $isOwnProfile ? 'My Sessions' : 'Public Sessions' ?></h3>
            </div>
            <div class="session-list">
                <?php foreach ($sessions as $session): ?>
                <div class="session-item">
                    <div class="session-main">
                        <a href="game.php?id=<?= $session['id'] ?>" class="session-link">
                            <span class="session-date"><?= date('d M Y', strtotime($session['session_date'])) ?></span>
                            <?php if ($session['description']): ?>
                            <span class="session-desc"><?= htmlspecialchars($session['description']) ?></span>
                            <?php endif; ?>
                        </a>
                        <span class="session-stats">
                            <span class="badge small"><?= HANDS[$session['hand']] ?></span>
                            <span class="roll-count"><?= $session['roll_count'] ?> rolls</span>
                        </span>
                    </div>
                    <?php if ($isOwnProfile): ?>
                    <button type="button"
                            class="visibility-toggle"
                            data-session-id="<?= $session['id'] ?>"
                            data-public="<?= $session['is_public'] ?? 1 ?>"
                            title="<?= ($session['is_public'] ?? 1) ? 'Public' : 'Private' ?>">
                        <span class="visibility-icon"><?= ($session['is_public'] ?? 1) ? '👁' : '👁‍🗨' ?></span>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php elseif ($isOwnProfile): ?>
            <?php Template::emptyState('No sessions yet.', 'game.php', 'Start Your First Game'); ?>
            <?php endif; ?>

            <?php elseif ($isLoggedIn): ?>
            <!-- Should not happen, but fallback -->
            <?php Template::emptyState('Profile not found.'); ?>

            <?php else: ?>
            <!-- Not logged in, no player specified -->
            <div class="auth-prompt">
                <h2>Track Your Progress</h2>
                <p>Create an account to save your bowling sessions and track your improvement over time.</p>

                <div class="auth-buttons">
                    <a href="login.php" class="btn-primary">Login</a>
                    <a href="register.php" class="btn-secondary">Register</a>
                </div>

                <p class="auth-note">You can also play without an account - your sessions will be saved anonymously.</p>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <?php if ($isOwnProfile && $isLoggedIn): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Logout handler
        document.getElementById('logoutBtn')?.addEventListener('click', async function(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'logout');
            await fetch('api/auth.php', { method: 'POST', body: formData });
            window.location.href = 'index.php';
        });

        // Visibility toggle
        document.querySelectorAll('.visibility-toggle').forEach(btn => {
            btn.addEventListener('click', async function() {
                const sessionId = this.dataset.sessionId;
                const isPublic = this.dataset.public === '1';

                try {
                    const res = await fetch('api/session.php', {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: parseInt(sessionId), action: 'toggle_visibility' })
                    });
                    const data = await res.json();

                    if (data.success) {
                        this.dataset.public = data.is_public ? '1' : '0';
                        this.querySelector('.visibility-icon').textContent = data.is_public ? '👁' : '👁‍🗨';
                        this.title = data.is_public ? 'Public' : 'Private';
                    }
                } catch (err) {
                    console.error('Failed to toggle visibility');
                }
            });
        });

        // Set primary club
        document.querySelectorAll('.set-primary-btn').forEach(btn => {
            btn.addEventListener('click', async function(e) {
                e.preventDefault();
                e.stopPropagation();

                const clubId = this.dataset.clubId;

                try {
                    const formData = new FormData();
                    formData.append('action', 'set_primary');
                    formData.append('club_id', clubId);

                    const res = await fetch('api/club.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();

                    if (data.success) {
                        location.reload();
                    }
                } catch (err) {
                    console.error('Failed to set primary club');
                }
            });
        });
    });
    </script>
    <?php endif; ?>
</body>
</html>
