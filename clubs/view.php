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
$playerId = Auth::id();
$flash = Auth::getFlash();

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

$members = Club::getMembers($club['id']);
$stats = Club::getStats($club['id']);
$isMember = $playerId ? ClubMember::isMember($club['id'], $playerId) : false;
$canManage = $playerId ? Club::canManage($club['id'], $playerId) : false;
$userRole = $playerId ? ClubMember::getRole($club['id'], $playerId) : null;

$rightHtml = $canManage
    ? '<a href="manage.php?slug=' . htmlspecialchars($slug) . '" class="header-action">Manage</a>'
    : '<span></span>';

Template::pageHead($club['name'], [], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header('Club', 'index.php', $rightHtml); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>

            <!-- Club Profile -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="club-icon-large">
                        <?php if ($club['icon_filename']): ?>
                        <img src="../assets/club-icons/<?= htmlspecialchars($club['icon_filename']) ?>" alt="" class="club-icon">
                        <?php else: ?>
                        <span class="club-icon-placeholder"><?= strtoupper(substr($club['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h2 class="profile-name"><?= htmlspecialchars($club['name']) ?></h2>
                        <span class="club-owner">by <?= htmlspecialchars($club['owner_name']) ?></span>
                    </div>
                </div>

                <?php if ($club['description']): ?>
                <p class="club-description"><?= nl2br(htmlspecialchars($club['description'])) ?></p>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-value"><?= $stats['total_members'] ?></span>
                        <span class="stat-label">Members</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $stats['total_sessions'] ?></span>
                        <span class="stat-label">Sessions</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $stats['total_rolls'] ?></span>
                        <span class="stat-label">Rolls</span>
                    </div>
                </div>

                <!-- Club Quick Actions -->
                <?php if ($isMember): ?>
                <div class="club-quick-actions">
                    <a href="../matches/index.php?club=<?= $club['id'] ?>" class="btn-quick-action">
                        <span class="action-icon">&#9679;</span>
                        <span>Live Scores</span>
                    </a>
                    <a href="../competitions/index.php?club=<?= $club['id'] ?>" class="btn-quick-action">
                        <span class="action-icon">&#127942;</span>
                        <span>Competitions</span>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Join/Leave Actions -->
                <?php if ($isLoggedIn): ?>
                <div class="club-actions">
                    <?php if ($isMember): ?>
                        <?php if ($userRole !== 'owner'): ?>
                        <button type="button" class="btn-secondary" id="leaveBtn" data-club-id="<?= $club['id'] ?>">Leave Club</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($club['is_public']): ?>
                        <button type="button" class="btn-primary" id="joinBtn" data-club-id="<?= $club['id'] ?>">Join Club</button>
                        <?php else: ?>
                        <p class="private-note">This club is private</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="club-actions">
                    <p class="login-prompt"><a href="../login.php">Login</a> to join this club</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Members List -->
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
        </main>
    </div>

    <script src="../js/club.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const joinBtn = document.getElementById('joinBtn');
        const leaveBtn = document.getElementById('leaveBtn');

        if (joinBtn) {
            joinBtn.addEventListener('click', async function() {
                if (!confirm('Join this club?')) return;

                this.disabled = true;
                this.textContent = 'Joining...';

                try {
                    const formData = new FormData();
                    formData.append('action', 'join');
                    formData.append('club_id', this.dataset.clubId);

                    const res = await fetch('../api/club.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();

                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || 'Failed to join');
                        this.disabled = false;
                        this.textContent = 'Join Club';
                    }
                } catch (err) {
                    alert('Network error');
                    this.disabled = false;
                    this.textContent = 'Join Club';
                }
            });
        }

        if (leaveBtn) {
            leaveBtn.addEventListener('click', async function() {
                if (!confirm('Leave this club?')) return;

                this.disabled = true;
                this.textContent = 'Leaving...';

                try {
                    const formData = new FormData();
                    formData.append('action', 'leave');
                    formData.append('club_id', this.dataset.clubId);

                    const res = await fetch('../api/club.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();

                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || 'Failed to leave');
                        this.disabled = false;
                        this.textContent = 'Leave Club';
                    }
                } catch (err) {
                    alert('Network error');
                    this.disabled = false;
                    this.textContent = 'Leave Club';
                }
            });
        }
    });
    </script>
</body>
</html>
