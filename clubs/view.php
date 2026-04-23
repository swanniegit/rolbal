<?php
/**
 * View Club Page
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/ClubMember.php';
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/Template.php';

$isLoggedIn = Auth::check();
$playerId   = Auth::id();
$flash      = Auth::getFlash();

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    header('Location: index.php');
    exit;
}

$club = Club::findBySlug($slug);
if (!$club) {
    Auth::flash('error', 'Club not found');
    header('Location: index.php');
    exit;
}

$members   = Club::getMembers($club['id']);
$isMember  = $playerId ? ClubMember::isMember($club['id'], $playerId) : false;
$canManage = $playerId ? Club::canManage($club['id'], $playerId) : false;
$userRole  = $playerId ? ClubMember::getRole($club['id'], $playerId) : null;
$csrfToken = Auth::generateCsrfToken();

// Live match count for this club
$db = Database::getInstance();
$stmt = $db->prepare('SELECT COUNT(*) FROM matches WHERE club_id = :id AND status = "live"');
$stmt->execute(['id' => $club['id']]);
$liveMatchCount = (int) $stmt->fetchColumn();

// Competition count (active/completed)
$compCount = 0;
try {
    $stmt = $db->prepare('SELECT COUNT(*) FROM competitions WHERE club_id = :id AND status != "draft"');
    $stmt->execute(['id' => $club['id']]);
    $compCount = (int) $stmt->fetchColumn();
} catch (\PDOException $e) {}

$rightHtml = $canManage
    ? '<a href="manage.php?slug=' . htmlspecialchars($slug) . '" class="header-action">Manage</a>'
    : '<span></span>';

Template::pageHead($club['name'], [], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header($club['name'], 'index.php', $rightHtml); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>

            <!-- Club Profile -->
            <div class="club-profile-block">
                <div class="club-profile-row">
                    <div class="club-icon-wrap medium">
                        <?php if ($club['icon_filename']): ?>
                        <img src="../assets/club-icons/<?= htmlspecialchars($club['icon_filename']) ?>" alt="" class="club-icon">
                        <?php else: ?>
                        <span class="club-icon-placeholder"><?= strtoupper(substr($club['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 class="profile-name"><?= htmlspecialchars($club['name']) ?></h2>
                        <span class="club-owner-line">by <?= htmlspecialchars($club['owner_name']) ?></span>
                        <span class="club-stat-inline"><?= count($members) ?> member<?= count($members) !== 1 ? 's' : '' ?></span>
                    </div>
                </div>

                <?php if ($club['description']): ?>
                <p class="club-description"><?= nl2br(htmlspecialchars($club['description'])) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($isMember): ?>
            <!-- ── Member: actions first ── -->
            <div class="club-action-cards">
                <a href="../matches/index.php?club=<?= $club['id'] ?>" class="club-action-card">
                    <div class="club-action-card-top">
                        <?php if ($liveMatchCount > 0): ?>
                        <span class="live-dot"></span>
                        <?php else: ?>
                        <span class="club-action-icon">&#9679;</span>
                        <?php endif; ?>
                        <span class="club-action-title">Live Scores</span>
                    </div>
                    <span class="club-action-sub">
                        <?= $liveMatchCount > 0
                            ? "{$liveMatchCount} match" . ($liveMatchCount > 1 ? 'es' : '') . " live now"
                            : "No matches live" ?>
                    </span>
                </a>
                <a href="../competitions/index.php?club=<?= $club['id'] ?>" class="club-action-card">
                    <div class="club-action-card-top">
                        <span class="club-action-icon">&#127942;</span>
                        <span class="club-action-title">Competitions</span>
                    </div>
                    <span class="club-action-sub">
                        <?= $compCount > 0 ? "{$compCount} active" : "Browse" ?>
                    </span>
                </a>
            </div>
            <?php else: ?>
            <!-- ── Non-member: join CTA ── -->
            <?php if ($isLoggedIn): ?>
            <div class="club-join-area">
                <?php if ($club['is_public']): ?>
                <button type="button" class="btn-primary btn-full" id="joinBtn" data-club-id="<?= $club['id'] ?>">Join Club</button>
                <?php else: ?>
                <p class="private-note">This club is private — members join by invitation.</p>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="club-join-area">
                <p class="login-prompt"><a href="../login.php">Login</a> to join this club</p>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Members -->
            <div class="section-header">
                <h3>Members (<?= count($members) ?>)</h3>
            </div>
            <div class="member-list">
                <?php foreach ($members as $member): ?>
                <a href="../players.php?id=<?= $member['id'] ?>" class="member-item">
                    <div class="member-avatar"><?= strtoupper(substr($member['name'], 0, 1)) ?></div>
                    <div class="member-info">
                        <span class="member-name"><?= htmlspecialchars($member['name']) ?></span>
                        <span class="member-meta">
                            <span class="badge small"><?= HANDS[$member['hand']] ?></span>
                            <?php if ($member['role'] !== 'member'): ?>
                            <span class="badge small role-<?= $member['role'] ?>"><?= ucfirst($member['role']) ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Leave club (member, non-owner) -->
            <?php if ($isMember && $userRole !== 'owner'): ?>
            <div style="margin-top:1.5rem;">
                <button type="button" class="btn-secondary" id="leaveBtn" data-club-id="<?= $club['id'] ?>">Leave Club</button>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <script src="../js/club.js"></script>
    <script>
    const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

    document.addEventListener('DOMContentLoaded', function () {
        const joinBtn  = document.getElementById('joinBtn');
        const leaveBtn = document.getElementById('leaveBtn');

        if (joinBtn) {
            joinBtn.addEventListener('click', async function () {
                if (!confirm('Join this club?')) return;
                this.disabled  = true;
                this.textContent = 'Joining…';
                try {
                    const fd = new FormData();
                    fd.append('action', 'join');
                    fd.append('club_id', this.dataset.clubId);
                    fd.append('csrf_token', CSRF_TOKEN);
                    const data = await (await fetch('../api/club.php', { method: 'POST', body: fd })).json();
                    if (data.success) { location.reload(); }
                    else { alert(data.error || 'Failed to join'); this.disabled = false; this.textContent = 'Join Club'; }
                } catch { alert('Network error'); this.disabled = false; this.textContent = 'Join Club'; }
            });
        }

        if (leaveBtn) {
            leaveBtn.addEventListener('click', async function () {
                if (!confirm('Leave this club?')) return;
                this.disabled  = true;
                this.textContent = 'Leaving…';
                try {
                    const fd = new FormData();
                    fd.append('action', 'leave');
                    fd.append('club_id', this.dataset.clubId);
                    fd.append('csrf_token', CSRF_TOKEN);
                    const data = await (await fetch('../api/club.php', { method: 'POST', body: fd })).json();
                    if (data.success) { location.reload(); }
                    else { alert(data.error || 'Failed to leave'); this.disabled = false; this.textContent = 'Leave Club'; }
                } catch { alert('Network error'); this.disabled = false; this.textContent = 'Leave Club'; }
            });
        }
    });
    </script>
</body>
</html>
