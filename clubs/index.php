<?php
/**
 * Browse Clubs Page
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';

$isLoggedIn = Auth::check();
$playerName = Auth::name();
$clubs = Club::all();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2d5016">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Rolbal - Clubs</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <div class="app-container">
        <header class="app-header compact">
            <a href="../index.php" class="back-btn">&larr;</a>
            <h1 class="app-title">Clubs</h1>
            <?php if ($isLoggedIn): ?>
            <a href="create.php" class="header-action">+ New</a>
            <?php else: ?>
            <span></span>
            <?php endif; ?>
        </header>

        <main class="main-content">
            <div class="search-bar">
                <input type="text" id="searchInput" placeholder="Search clubs..." class="search-input">
            </div>

            <?php if ($isLoggedIn): ?>
            <div class="section-header">
                <h3>Your Clubs</h3>
            </div>
            <div id="myClubs" class="club-list">
                <p class="loading">Loading...</p>
            </div>
            <?php endif; ?>

            <div class="section-header">
                <h3>All Clubs</h3>
            </div>
            <div id="allClubs" class="club-grid">
                <?php if (empty($clubs)): ?>
                <div class="empty-state">
                    <p>No clubs yet.</p>
                    <?php if ($isLoggedIn): ?>
                    <a href="create.php" class="btn-primary">Create the First Club</a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <?php foreach ($clubs as $club): ?>
                <a href="view.php?slug=<?= htmlspecialchars($club['slug']) ?>" class="club-card">
                    <div class="club-icon-wrap">
                        <?php if ($club['icon_filename']): ?>
                        <img src="../assets/club-icons/<?= htmlspecialchars($club['icon_filename']) ?>" alt="" class="club-icon">
                        <?php else: ?>
                        <span class="club-icon-placeholder"><?= strtoupper(substr($club['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="club-name"><?= htmlspecialchars($club['name']) ?></span>
                    <span class="club-members"><?= $club['member_count'] ?> member<?= $club['member_count'] !== 1 ? 's' : '' ?></span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../js/club.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const allClubsDiv = document.getElementById('allClubs');
        let searchTimeout;

        <?php if ($isLoggedIn): ?>
        loadMyClubs();
        <?php endif; ?>

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length >= 2) {
                searchTimeout = setTimeout(() => searchClubs(query), 300);
            } else if (query.length === 0) {
                location.reload();
            }
        });

        async function searchClubs(query) {
            try {
                const res = await fetch(`../api/club.php?action=search&q=${encodeURIComponent(query)}`);
                const data = await res.json();

                if (data.success) {
                    renderClubs(data.clubs, allClubsDiv);
                }
            } catch (err) {
                console.error('Search failed:', err);
            }
        }

        <?php if ($isLoggedIn): ?>
        async function loadMyClubs() {
            const myClubsDiv = document.getElementById('myClubs');
            try {
                const res = await fetch('../api/club.php?action=my_clubs');
                const data = await res.json();

                if (data.success && data.clubs.length > 0) {
                    let html = '';
                    data.clubs.forEach(club => {
                        html += `
                            <a href="view.php?slug=${club.slug}" class="club-item">
                                <div class="club-icon-wrap small">
                                    ${club.icon_filename
                                        ? `<img src="../assets/club-icons/${club.icon_filename}" alt="" class="club-icon">`
                                        : `<span class="club-icon-placeholder">${club.name.charAt(0).toUpperCase()}</span>`
                                    }
                                </div>
                                <div class="club-info">
                                    <span class="club-name">${escapeHtml(club.name)}</span>
                                    <span class="club-role badge small">${club.role}</span>
                                </div>
                            </a>
                        `;
                    });
                    myClubsDiv.innerHTML = html;
                } else {
                    myClubsDiv.innerHTML = '<p class="empty-note">You haven\'t joined any clubs yet.</p>';
                }
            } catch (err) {
                myClubsDiv.innerHTML = '<p class="error">Failed to load clubs.</p>';
            }
        }
        <?php endif; ?>

        function renderClubs(clubs, container) {
            if (clubs.length === 0) {
                container.innerHTML = '<div class="empty-state"><p>No clubs found.</p></div>';
                return;
            }

            let html = '';
            clubs.forEach(club => {
                html += `
                    <a href="view.php?slug=${club.slug}" class="club-card">
                        <div class="club-icon-wrap">
                            ${club.icon_filename
                                ? `<img src="../assets/club-icons/${club.icon_filename}" alt="" class="club-icon">`
                                : `<span class="club-icon-placeholder">${club.name.charAt(0).toUpperCase()}</span>`
                            }
                        </div>
                        <span class="club-name">${escapeHtml(club.name)}</span>
                        <span class="club-members">${club.member_count} member${club.member_count !== 1 ? 's' : ''}</span>
                    </a>
                `;
            });
            container.innerHTML = html;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    });
    </script>
</body>
</html>
