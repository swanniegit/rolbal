<?php
/**
 * Manage Club Page
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/ClubMember.php';
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/Template.php';

$isLoggedIn = Auth::check();
$playerId = Auth::id();

if (!$isLoggedIn) {
    Auth::flash('error', 'Please login to manage clubs');
    header('Location: ../login.php');
    exit;
}

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

if (!Club::canManage($club['id'], $playerId)) {
    Auth::flash('error', 'Not authorized');
    header('Location: view.php?slug=' . urlencode($slug));
    exit;
}

$members = Club::getMembers($club['id']);
$isOwner = Club::isOwner($club['id'], $playerId);
$csrfToken = Auth::generateCsrfToken();

Template::pageHead('Manage ' . $club['name'], [], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header('Manage Club', 'view.php?slug=' . htmlspecialchars($slug)); ?>

        <main class="main-content">
            <?php Template::formMessage(); ?>

            <!-- Edit Club Details -->
            <div class="form-card">
                <h3 class="form-title">Club Details</h3>

                <form id="editClubForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="club_id" value="<?= $club['id'] ?>">

                    <div class="form-group">
                        <label for="name">Club Name</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($club['name']) ?>" maxlength="100" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3" class="form-textarea"><?= htmlspecialchars($club['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_public" value="1" <?= $club['is_public'] ? 'checked' : '' ?>>
                            Public club (visible in browse list)
                        </label>
                    </div>

                    <button type="submit" class="btn-primary">Save Changes</button>
                </form>
            </div>

            <!-- Club Icon -->
            <div class="form-card">
                <h3 class="form-title">Club Icon</h3>

                <div class="current-icon">
                    <?php if ($club['icon_filename']): ?>
                    <div class="club-icon-large" id="currentIconWrap">
                        <img src="../assets/club-icons/<?= htmlspecialchars($club['icon_filename']) ?>" alt="" class="club-icon" id="currentIcon">
                    </div>
                    <?php else: ?>
                    <div class="club-icon-large" id="currentIconWrap">
                        <span class="club-icon-placeholder" id="iconPlaceholder"><?= strtoupper(substr($club['name'], 0, 1)) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <form id="iconForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_icon">
                    <input type="hidden" name="club_id" value="<?= $club['id'] ?>">

                    <div class="form-group">
                        <input type="file" id="iconFile" name="icon" accept="image/*">
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn-primary" id="uploadIconBtn">Upload Icon</button>
                        <?php if ($club['icon_filename']): ?>
                        <button type="button" class="btn-secondary" id="removeIconBtn">Remove</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Members Management -->
            <div class="form-card">
                <h3 class="form-title">Members (<?= count($members) ?>)</h3>

                <div class="manage-member-list">
                    <?php foreach ($members as $member): ?>
                    <div class="manage-member-item" data-member-id="<?= $member['id'] ?>">
                        <div class="member-avatar"><?= strtoupper(substr($member['name'], 0, 1)) ?></div>
                        <div class="member-info">
                            <span class="member-name"><?= htmlspecialchars($member['name']) ?></span>
                            <span class="badge small role-<?= $member['role'] ?>"><?= ucfirst($member['role']) ?></span>
                        </div>
                        <?php if ($member['role'] !== 'owner'): ?>
                        <div class="member-actions">
                            <?php if ($isOwner): ?>
                            <select class="role-select" data-member-id="<?= $member['id'] ?>">
                                <option value="member" <?= $member['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                                <option value="admin" <?= $member['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <?php endif; ?>
                            <button type="button" class="btn-small btn-danger remove-member" data-member-id="<?= $member['id'] ?>">Remove</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($isOwner): ?>
            <!-- Danger Zone -->
            <div class="form-card danger-zone">
                <h3 class="form-title danger">Danger Zone</h3>
                <p class="danger-text">Deleting the club is permanent. All members will be removed.</p>
                <button type="button" class="btn-danger" id="deleteClubBtn">Delete Club</button>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    window.CLUB_ID = <?= $club['id'] ?>;
    window.CLUB_SLUG = '<?= htmlspecialchars($club['slug']) ?>';
    window.CLUB_NAME = '<?= addslashes($club['name']) ?>';
    </script>
    <script src="../js/api.js"></script>
    <script src="../js/ui.js"></script>
    <script src="../js/club.js"></script>
    <script src="../js/club-manage.js"></script>
</body>
</html>
