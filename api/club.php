<?php
/**
 * Club API
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/ClubMember.php';
require_once __DIR__ . '/../includes/Upload.php';
require_once __DIR__ . '/../includes/Auth.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            $clubs = Club::all();
            echo json_encode(['success' => true, 'clubs' => $clubs]);

        } elseif ($action === 'view') {
            $slug = $_GET['slug'] ?? '';
            if (!$slug) {
                throw new Exception('Club slug required');
            }
            $club = Club::findBySlug($slug);
            if (!$club) {
                http_response_code(404);
                throw new Exception('Club not found');
            }
            $members = Club::getMembers($club['id']);
            $stats = Club::getStats($club['id']);

            $playerId = Auth::id();
            $isMember = $playerId ? ClubMember::isMember($club['id'], $playerId) : false;
            $canManage = $playerId ? Club::canManage($club['id'], $playerId) : false;

            echo json_encode([
                'success' => true,
                'club' => $club,
                'members' => $members,
                'stats' => $stats,
                'is_member' => $isMember,
                'can_manage' => $canManage
            ]);

        } elseif ($action === 'members') {
            $id = (int) ($_GET['id'] ?? 0);
            if (!$id) {
                throw new Exception('Club ID required');
            }
            $members = Club::getMembers($id);
            echo json_encode(['success' => true, 'members' => $members]);

        } elseif ($action === 'search') {
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                throw new Exception('Search query must be at least 2 characters');
            }
            $clubs = Club::search($query);
            echo json_encode(['success' => true, 'clubs' => $clubs]);

        } elseif ($action === 'my_clubs') {
            $playerId = Auth::id();
            if (!$playerId) {
                throw new Exception('Login required');
            }
            $clubs = ClubMember::getPlayerClubs($playerId);
            echo json_encode(['success' => true, 'clubs' => $clubs]);

        } else {
            throw new Exception('Invalid action');
        }

    } elseif ($method === 'POST') {
        $action = $_POST['action'] ?? '';
        $playerId = Auth::id();

        if (!$playerId) {
            throw new Exception('Login required');
        }

        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '') ?: null;

            if (strlen($name) < 2) {
                throw new Exception('Club name must be at least 2 characters');
            }
            if (strlen($name) > 100) {
                throw new Exception('Club name cannot exceed 100 characters');
            }

            $iconFilename = null;
            if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
                $iconFilename = Upload::clubIcon($_FILES['icon']);
                if (!$iconFilename) {
                    throw new Exception('Invalid image file');
                }
            }

            $clubId = Club::create($name, $playerId, $description, $iconFilename);
            ClubMember::add($clubId, $playerId, 'owner');

            $club = Club::find($clubId);
            echo json_encode(['success' => true, 'club' => $club]);

        } elseif ($action === 'join') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            if (!$clubId) {
                throw new Exception('Club ID required');
            }
            $club = Club::find($clubId);
            if (!$club) {
                throw new Exception('Club not found');
            }
            if (!$club['is_public']) {
                throw new Exception('This club is private');
            }
            if (ClubMember::isMember($clubId, $playerId)) {
                throw new Exception('Already a member');
            }

            ClubMember::add($clubId, $playerId, 'member');
            echo json_encode(['success' => true]);

        } elseif ($action === 'leave') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            if (!$clubId) {
                throw new Exception('Club ID required');
            }
            if (!ClubMember::isMember($clubId, $playerId)) {
                throw new Exception('Not a member');
            }
            $role = ClubMember::getRole($clubId, $playerId);
            if ($role === 'owner') {
                throw new Exception('Owners cannot leave their club. Transfer ownership or delete the club.');
            }

            ClubMember::remove($clubId, $playerId);
            ClubMember::setPrimaryClub($playerId, null);
            echo json_encode(['success' => true]);

        } elseif ($action === 'update') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            if (!$clubId || !Club::canManage($clubId, $playerId)) {
                throw new Exception('Not authorized');
            }

            $updates = [];

            if (isset($_POST['name'])) {
                $name = trim($_POST['name']);
                if (strlen($name) < 2 || strlen($name) > 100) {
                    throw new Exception('Invalid club name');
                }
                $updates['name'] = $name;
            }

            if (isset($_POST['description'])) {
                $updates['description'] = trim($_POST['description']) ?: null;
            }

            if (isset($_POST['is_public'])) {
                $updates['is_public'] = $_POST['is_public'] ? 1 : 0;
            }

            if (!empty($updates)) {
                Club::update($clubId, $updates);
            }

            $club = Club::find($clubId);
            echo json_encode(['success' => true, 'club' => $club]);

        } elseif ($action === 'upload_icon') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            if (!$clubId || !Club::canManage($clubId, $playerId)) {
                throw new Exception('Not authorized');
            }

            if (!isset($_FILES['icon']) || $_FILES['icon']['error'] !== UPLOAD_ERR_OK) {
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
                    UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
                    UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
                ];
                $errorCode = $_FILES['icon']['error'] ?? UPLOAD_ERR_NO_FILE;
                throw new Exception($uploadErrors[$errorCode] ?? 'Upload failed');
            }

            // Explicit validation with specific error messages
            $file = $_FILES['icon'];
            if ($file['size'] > Upload::MAX_SIZE) {
                throw new Exception('File too large (max 2MB)');
            }

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, Upload::ALLOWED_EXTENSIONS)) {
                throw new Exception('Invalid file type. Allowed: ' . implode(', ', Upload::ALLOWED_EXTENSIONS));
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, Upload::ALLOWED_TYPES)) {
                throw new Exception('Invalid image format');
            }

            $club = Club::find($clubId);
            if ($club['icon_filename']) {
                Upload::deleteClubIcon($club['icon_filename']);
            }

            $iconFilename = Upload::clubIcon($file);
            if (!$iconFilename) {
                throw new Exception('Failed to process image');
            }

            Club::update($clubId, ['icon_filename' => $iconFilename]);
            echo json_encode(['success' => true, 'icon_filename' => $iconFilename]);

        } elseif ($action === 'remove_icon') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            if (!$clubId || !Club::canManage($clubId, $playerId)) {
                throw new Exception('Not authorized');
            }

            $club = Club::find($clubId);
            if ($club['icon_filename']) {
                Upload::deleteClubIcon($club['icon_filename']);
                Club::update($clubId, ['icon_filename' => null]);
            }

            echo json_encode(['success' => true]);

        } elseif ($action === 'remove_member') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            $memberId = (int) ($_POST['member_id'] ?? 0);

            if (!$clubId || !Club::canManage($clubId, $playerId)) {
                throw new Exception('Not authorized');
            }
            if (!$memberId) {
                throw new Exception('Member ID required');
            }

            $memberRole = ClubMember::getRole($clubId, $memberId);
            if ($memberRole === 'owner') {
                throw new Exception('Cannot remove the owner');
            }
            if ($memberRole === 'admin' && !Club::isOwner($clubId, $playerId)) {
                throw new Exception('Only owner can remove admins');
            }

            ClubMember::remove($clubId, $memberId);
            echo json_encode(['success' => true]);

        } elseif ($action === 'update_member_role') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            $memberId = (int) ($_POST['member_id'] ?? 0);
            $role = $_POST['role'] ?? '';

            if (!$clubId || !Club::isOwner($clubId, $playerId)) {
                throw new Exception('Only owner can change roles');
            }
            if (!$memberId || !in_array($role, ['admin', 'member'])) {
                throw new Exception('Invalid member or role');
            }

            ClubMember::updateRole($clubId, $memberId, $role);
            echo json_encode(['success' => true]);

        } elseif ($action === 'set_primary') {
            $clubId = (int) ($_POST['club_id'] ?? 0);

            if ($clubId && !ClubMember::isMember($clubId, $playerId)) {
                throw new Exception('Must be a member to set as primary');
            }

            ClubMember::setPrimaryClub($playerId, $clubId ?: null);
            echo json_encode(['success' => true]);

        } else {
            throw new Exception('Invalid action');
        }

    } elseif ($method === 'DELETE') {
        $playerId = Auth::id();
        if (!$playerId) {
            throw new Exception('Login required');
        }

        $clubId = (int) ($_GET['id'] ?? 0);
        if (!$clubId) {
            throw new Exception('Club ID required');
        }

        if (!Club::isOwner($clubId, $playerId)) {
            throw new Exception('Only owner can delete the club');
        }

        Club::delete($clubId, $playerId);
        echo json_encode(['success' => true]);

    } else {
        throw new Exception('Method not allowed');
    }

} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
