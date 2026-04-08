<?php
/**
 * Club API
 *
 * Security: CSRF validation on state-changing operations
 * Authorization: Membership and ownership checks
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/ClubMember.php';
require_once __DIR__ . '/../includes/Upload.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/ApiResponse.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';

        if ($action === 'list') {
            $clubs = Club::all();
            ApiResponse::success(['clubs' => $clubs]);

        } elseif ($action === 'view') {
            $slug = $_GET['slug'] ?? '';
            if (!$slug) {
                throw new Exception('Club slug required');
            }
            $club = Club::findBySlug($slug);
            if (!$club) {
                ApiResponse::notFound('Club not found');
            }
            $members = Club::getMembers($club['id']);
            $stats = Club::getStats($club['id']);

            $playerId = Auth::id();
            $isMember = $playerId ? ClubMember::isMember($club['id'], $playerId) : false;
            $canManage = $playerId ? Club::canManage($club['id'], $playerId) : false;

            ApiResponse::success([
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
            ApiResponse::success(['members' => $members]);

        } elseif ($action === 'search') {
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                throw new Exception('Search query must be at least 2 characters');
            }
            $clubs = Club::search($query);
            ApiResponse::success(['clubs' => $clubs]);

        } elseif ($action === 'my_clubs') {
            $playerId = ApiResponse::requireAuth();
            $clubs = ClubMember::getPlayerClubs($playerId);
            ApiResponse::success(['clubs' => $clubs]);

        } else {
            ApiResponse::invalidAction();
        }

    } elseif ($method === 'POST') {
        // CSRF validation for all POST actions
        $csrf = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCsrfToken($csrf)) {
            ApiResponse::forbidden('Invalid security token');
        }

        $action = $_POST['action'] ?? '';
        $playerId = ApiResponse::requireAuth();

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

            // Use transaction to ensure club + owner membership are created atomically
            $db = Database::getInstance();
            $db->beginTransaction();
            try {
                $clubId = Club::create($name, $playerId, $description, $iconFilename);
                ClubMember::add($clubId, $playerId, 'owner');
                $db->commit();
            } catch (Exception $e) {
                $db->rollBack();
                // Clean up uploaded icon if club creation failed
                if ($iconFilename) {
                    Upload::deleteClubIcon($iconFilename);
                }
                throw $e;
            }

            $club = Club::find($clubId);
            ApiResponse::success(['club' => $club]);

        } elseif ($action === 'join') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            if (!$clubId) {
                throw new Exception('Club ID required');
            }
            $club = Club::find($clubId);
            if (!$club) {
                ApiResponse::notFound('Club not found');
            }
            if (!$club['is_public']) {
                ApiResponse::forbidden('This club is private');
            }
            if (ClubMember::isMember($clubId, $playerId)) {
                throw new Exception('Already a member');
            }

            ClubMember::add($clubId, $playerId, 'member');
            ApiResponse::success();

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
            ApiResponse::success();

        } elseif ($action === 'update') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            if (!$clubId || !Club::canManage($clubId, $playerId)) {
                ApiResponse::forbidden('Not authorized');
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
            ApiResponse::success(['club' => $club]);

        } elseif ($action === 'upload_icon') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            if (!$clubId || !Club::canManage($clubId, $playerId)) {
                ApiResponse::forbidden('Not authorized');
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
            ApiResponse::success(['icon_filename' => $iconFilename]);

        } elseif ($action === 'remove_icon') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            if (!$clubId || !Club::canManage($clubId, $playerId)) {
                ApiResponse::forbidden('Not authorized');
            }

            $club = Club::find($clubId);
            if ($club['icon_filename']) {
                Upload::deleteClubIcon($club['icon_filename']);
                Club::update($clubId, ['icon_filename' => null]);
            }

            ApiResponse::success();

        } elseif ($action === 'remove_member') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            $memberId = (int) ($_POST['member_id'] ?? 0);

            if (!$clubId || !Club::canManage($clubId, $playerId)) {
                ApiResponse::forbidden('Not authorized');
            }
            if (!$memberId) {
                throw new Exception('Member ID required');
            }

            $memberRole = ClubMember::getRole($clubId, $memberId);
            if ($memberRole === 'owner') {
                ApiResponse::forbidden('Cannot remove the owner');
            }
            if ($memberRole === 'admin' && !Club::isOwner($clubId, $playerId)) {
                ApiResponse::forbidden('Only owner can remove admins');
            }

            ClubMember::remove($clubId, $memberId);
            ApiResponse::success();

        } elseif ($action === 'update_member_role') {
            $clubId = (int) ($_POST['club_id'] ?? 0);
            $memberId = (int) ($_POST['member_id'] ?? 0);
            $role = $_POST['role'] ?? '';

            if (!$clubId || !Club::isOwner($clubId, $playerId)) {
                ApiResponse::forbidden('Only owner can change roles');
            }
            if (!$memberId || !in_array($role, ['admin', 'member'])) {
                throw new Exception('Invalid member or role');
            }

            ClubMember::updateRole($clubId, $memberId, $role);
            ApiResponse::success();

        } elseif ($action === 'set_primary') {
            $clubId = (int) ($_POST['club_id'] ?? 0);

            if ($clubId && !ClubMember::isMember($clubId, $playerId)) {
                ApiResponse::forbidden('Must be a member to set as primary');
            }

            ClubMember::setPrimaryClub($playerId, $clubId ?: null);
            ApiResponse::success();

        } elseif ($action === 'update_member_whatsapp') {
            // Update member's WhatsApp number for scoring integration
            require_once __DIR__ . '/../includes/WhatsAppScoring.php';

            $clubId = (int) ($_POST['club_id'] ?? 0);
            $memberId = (int) ($_POST['member_id'] ?? 0);
            $whatsappNumber = trim($_POST['whatsapp_number'] ?? '');

            if (!$clubId || !Club::canManage($clubId, $playerId)) {
                ApiResponse::forbidden('Not authorized');
            }
            if (!$memberId) {
                throw new Exception('Member ID required');
            }
            if (!ClubMember::isMember($clubId, $memberId)) {
                throw new Exception('Player is not a club member');
            }

            if ($whatsappNumber) {
                // Validate and normalize phone number
                $normalized = WhatsAppAPI::normalizePhone($whatsappNumber);
                if (strlen($normalized) < 10 || strlen($normalized) > 15) {
                    throw new Exception('Invalid phone number format');
                }
                WhatsAppScoring::linkPhoneToPlayer($memberId, $normalized);
            } else {
                // Clear the WhatsApp number
                WhatsAppScoring::unlinkPhone($memberId);
            }

            ApiResponse::success(['whatsapp_number' => $whatsappNumber ? WhatsAppAPI::normalizePhone($whatsappNumber) : null]);

        } else {
            ApiResponse::invalidAction();
        }

    } elseif ($method === 'DELETE') {
        // CSRF validation via header
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Auth::validateCsrfToken($csrf)) {
            ApiResponse::forbidden('Invalid security token');
        }

        $playerId = ApiResponse::requireAuth();

        $clubId = (int) ($_GET['id'] ?? 0);
        if (!$clubId) {
            throw new Exception('Club ID required');
        }

        if (!Club::isOwner($clubId, $playerId)) {
            ApiResponse::forbidden('Only owner can delete the club');
        }

        Club::delete($clubId, $playerId);
        ApiResponse::success();

    } else {
        ApiResponse::methodNotAllowed();
    }

} catch (Exception $e) {
    ApiResponse::error($e->getMessage());
}
