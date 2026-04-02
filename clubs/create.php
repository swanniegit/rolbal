<?php
/**
 * Create Club Page
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Captcha.php';
require_once __DIR__ . '/../includes/Template.php';

$isLoggedIn = Auth::check();

if (!$isLoggedIn) {
    Auth::flash('error', 'Please login to create a club');
    header('Location: ../login.php');
    exit;
}

$playerName = Auth::name();
$csrfToken = Auth::generateCsrfToken();
$captcha = Captcha::generate();

Template::pageHead('Create Club', [], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header('Create Club', 'index.php'); ?>

        <main class="main-content">
            <form id="createClubForm" class="form-card" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <?php Template::formError(); ?>

                <div class="form-group">
                    <label for="name">Club Name</label>
                    <input type="text" id="name" name="name" maxlength="100" required>
                </div>

                <div class="form-group">
                    <label for="description">Description (optional)</label>
                    <textarea id="description" name="description" rows="3" class="form-textarea"></textarea>
                </div>

                <div class="form-group">
                    <label>Club Icon (optional)</label>
                    <div class="icon-upload">
                        <input type="file" id="icon" name="icon" accept="image/*" class="hidden">
                        <label for="icon" class="icon-preview" id="iconPreview">
                            <span class="icon-preview-text">Click to upload</span>
                            <img src="" alt="" class="icon-preview-img hidden">
                        </label>
                        <button type="button" id="removeIcon" class="btn-small hidden">Remove</button>
                    </div>
                    <small class="form-hint">Max 2MB. JPG, PNG, GIF, or WebP.</small>
                </div>

                <div class="form-group">
                    <label for="captcha">Verify: <span id="captchaQuestion"><?= $captcha['question'] ?></span></label>
                    <div class="captcha-field">
                        <input type="number" id="captcha" name="captcha" required>
                        <button type="button" id="refreshCaptcha" class="captcha-refresh">&#x21bb;</button>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="submitBtn">Create Club</button>
            </form>
        </main>
    </div>

    <script src="../js/club.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('createClubForm');
        const iconInput = document.getElementById('icon');
        const iconPreview = document.getElementById('iconPreview');
        const previewImg = iconPreview.querySelector('.icon-preview-img');
        const previewText = iconPreview.querySelector('.icon-preview-text');
        const removeBtn = document.getElementById('removeIcon');
        const formError = document.getElementById('formError');
        const submitBtn = document.getElementById('submitBtn');

        // Icon preview
        iconInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    showError('Image must be less than 2MB');
                    this.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewImg.classList.remove('hidden');
                    previewText.classList.add('hidden');
                    removeBtn.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            }
        });

        removeBtn.addEventListener('click', function() {
            iconInput.value = '';
            previewImg.src = '';
            previewImg.classList.add('hidden');
            previewText.classList.remove('hidden');
            removeBtn.classList.add('hidden');
        });

        // Captcha refresh
        document.getElementById('refreshCaptcha').addEventListener('click', async function() {
            try {
                const res = await fetch('../api/auth.php?action=captcha');
                const data = await res.json();
                if (data.success) {
                    document.getElementById('captchaQuestion').textContent = data.question;
                    document.getElementById('captcha').value = '';
                }
            } catch (err) {
                console.error('Failed to refresh captcha');
            }
        });

        // Form submit
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideError();
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';

            try {
                const formData = new FormData(this);

                const res = await fetch('../api/club.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();

                if (data.success) {
                    window.location.href = 'view.php?slug=' + data.club.slug;
                } else {
                    showError(data.error || 'Failed to create club');
                }
            } catch (err) {
                showError('Network error. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Club';
            }
        });

        function showError(message) {
            formError.textContent = message;
            formError.classList.remove('hidden');
        }

        function hideError() {
            formError.classList.add('hidden');
        }
    });
    </script>
</body>
</html>
