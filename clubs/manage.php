<?php
/**
 * Manage Club Page
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/ClubMember.php';
require_once __DIR__ . '/../includes/constants.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2d5016">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Rolbal - Manage <?= htmlspecialchars($club['name']) ?></title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="app-container">
        <header class="app-header compact">
            <a href="view.php?slug=<?= htmlspecialchars($slug) ?>" class="back-btn">&larr;</a>
            <h1 class="app-title">Manage Club</h1>
            <span></span>
        </header>

        <main class="main-content">
            <div id="formMessage" class="flash hidden"></div>

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

    <script src="../js/club.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const clubId = <?= $club['id'] ?>;
        const clubSlug = '<?= htmlspecialchars($club['slug']) ?>';

        // Edit club form
        document.getElementById('editClubForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            if (!this.querySelector('[name="is_public"]').checked) {
                formData.set('is_public', '0');
            }

            try {
                const res = await fetch('../api/club.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    showMessage('success', 'Club updated successfully');
                    if (data.club.slug !== clubSlug) {
                        window.location.href = 'manage.php?slug=' + data.club.slug;
                    }
                } else {
                    showMessage('error', data.error || 'Update failed');
                }
            } catch (err) {
                showMessage('error', 'Network error');
            }
        });

        // Icon upload
        document.getElementById('iconForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const fileInput = document.getElementById('iconFile');
            if (!fileInput.files[0]) {
                showMessage('error', 'Please select an image');
                return;
            }

            const formData = new FormData(this);
            const btn = document.getElementById('uploadIconBtn');
            btn.disabled = true;
            btn.textContent = 'Uploading...';

            try {
                const res = await fetch('../api/club.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    showMessage('success', 'Icon updated');
                    location.reload();
                } else {
                    showMessage('error', data.error || 'Upload failed');
                }
            } catch (err) {
                showMessage('error', 'Network error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Upload Icon';
            }
        });

        // Remove icon
        const removeIconBtn = document.getElementById('removeIconBtn');
        if (removeIconBtn) {
            removeIconBtn.addEventListener('click', async function() {
                if (!confirm('Remove club icon?')) return;

                const formData = new FormData();
                formData.append('action', 'remove_icon');
                formData.append('club_id', clubId);

                try {
                    const res = await fetch('../api/club.php', { method: 'POST', body: formData });
                    const data = await res.json();

                    if (data.success) {
                        location.reload();
                    } else {
                        showMessage('error', data.error || 'Failed to remove icon');
                    }
                } catch (err) {
                    showMessage('error', 'Network error');
                }
            });
        }

        // Role change
        document.querySelectorAll('.role-select').forEach(select => {
            select.addEventListener('change', async function() {
                const memberId = this.dataset.memberId;
                const role = this.value;

                const formData = new FormData();
                formData.append('action', 'update_member_role');
                formData.append('club_id', clubId);
                formData.append('member_id', memberId);
                formData.append('role', role);

                try {
                    const res = await fetch('../api/club.php', { method: 'POST', body: formData });
                    const data = await res.json();

                    if (data.success) {
                        showMessage('success', 'Role updated');
                    } else {
                        showMessage('error', data.error || 'Failed to update role');
                        location.reload();
                    }
                } catch (err) {
                    showMessage('error', 'Network error');
                }
            });
        });

        // Remove member
        document.querySelectorAll('.remove-member').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (!confirm('Remove this member from the club?')) return;

                const memberId = this.dataset.memberId;
                const memberItem = this.closest('.manage-member-item');

                const formData = new FormData();
                formData.append('action', 'remove_member');
                formData.append('club_id', clubId);
                formData.append('member_id', memberId);

                try {
                    const res = await fetch('../api/club.php', { method: 'POST', body: formData });
                    const data = await res.json();

                    if (data.success) {
                        memberItem.remove();
                        showMessage('success', 'Member removed');
                    } else {
                        showMessage('error', data.error || 'Failed to remove member');
                    }
                } catch (err) {
                    showMessage('error', 'Network error');
                }
            });
        });

        // Delete club
        const deleteBtn = document.getElementById('deleteClubBtn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', async function() {
                const clubName = '<?= addslashes($club['name']) ?>';
                const confirmation = prompt(`Type "${clubName}" to confirm deletion:`);

                if (confirmation !== clubName) {
                    if (confirmation !== null) {
                        showMessage('error', 'Club name did not match');
                    }
                    return;
                }

                this.disabled = true;
                this.textContent = 'Deleting...';

                try {
                    const res = await fetch(`../api/club.php?id=${clubId}`, { method: 'DELETE' });
                    const data = await res.json();

                    if (data.success) {
                        window.location.href = 'index.php';
                    } else {
                        showMessage('error', data.error || 'Failed to delete club');
                        this.disabled = false;
                        this.textContent = 'Delete Club';
                    }
                } catch (err) {
                    showMessage('error', 'Network error');
                    this.disabled = false;
                    this.textContent = 'Delete Club';
                }
            });
        }

        function showMessage(type, message) {
            const msgDiv = document.getElementById('formMessage');
            msgDiv.textContent = message;
            msgDiv.className = 'flash flash-' + type;
            msgDiv.classList.remove('hidden');
            setTimeout(() => msgDiv.classList.add('hidden'), 3000);
        }
    });
    </script>
</body>
</html>
