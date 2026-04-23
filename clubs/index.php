<?php
/**
 * Browse Clubs Page
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/ClubMember.php';
require_once __DIR__ . '/../includes/Template.php';

$isLoggedIn = Auth::check();
$clubs      = Club::all();
$myClubs    = ($isLoggedIn) ? ClubMember::getPlayerClubs(Auth::id()) : [];

$rightHtml = $isLoggedIn
    ? '<a href="create.php" class="header-action">+ New</a>'
    : '<a href="../login.php" class="header-action">Login</a>';

Template::pageHead('Clubs', [], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header('Clubs', '../index.php', $rightHtml); ?>

        <main class="main-content">

            <?php if ($isLoggedIn && !empty($myClubs)): ?>
            <div class="section-header">
                <h3>Your Clubs</h3>
            </div>
            <div class="my-clubs-list">
                <?php foreach ($myClubs as $c): ?>
                <div class="my-club-item">
                    <a href="view.php?slug=<?= htmlspecialchars($c['slug']) ?>" class="my-club-link">
                        <div class="club-icon-wrap small">
                            <?php if ($c['icon_filename']): ?>
                            <img src="../assets/club-icons/<?= htmlspecialchars($c['icon_filename']) ?>" alt="" class="club-icon">
                            <?php else: ?>
                            <span class="club-icon-placeholder"><?= strtoupper(substr($c['name'], 0, 1)) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="club-info">
                            <span class="club-name"><?= htmlspecialchars($c['name']) ?></span>
                            <span class="club-meta"><?= $c['member_count'] ?> member<?= $c['member_count'] !== 1 ? 's' : '' ?> &middot; <span class="badge small role-<?= $c['role'] ?>"><?= ucfirst($c['role']) ?></span></span>
                        </div>
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 3l5 5-5 5"/></svg>
                    </a>
                    <div class="my-club-shortcuts">
                        <a href="../matches/index.php?club=<?= $c['id'] ?>" class="club-shortcut">Live Scores</a>
                        <a href="../competitions/index.php?club=<?= $c['id'] ?>" class="club-shortcut">Competitions</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php elseif ($isLoggedIn): ?>
            <p class="empty-note" style="margin-bottom:1.5rem;">You haven't joined any clubs yet.</p>
            <?php endif; ?>

            <div class="section-header">
                <h3><?= (!empty($myClubs)) ? 'Find a Club' : 'All Clubs' ?></h3>
            </div>

            <div class="search-bar" style="margin-bottom:1rem;">
                <input type="text" id="searchInput" placeholder="Search clubs..." class="search-input">
            </div>

            <div id="allClubs" class="club-grid">
                <?php if (empty($clubs)): ?>
                <div class="empty-state" style="grid-column:1/-1;">
                    <p>No clubs yet.</p>
                    <?php if ($isLoggedIn): ?>
                    <a href="create.php" class="btn-primary">Create the First Club</a>
                    <?php else: ?>
                    <p style="margin-top:1rem;font-size:0.875rem;">
                        <a href="../login.php" style="color:var(--primary);font-weight:600;">Login</a> or
                        <a href="../register.php" style="color:var(--primary);font-weight:600;">Register</a>
                        to create a club
                    </p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <?php foreach ($clubs as $c): ?>
                <a href="view.php?slug=<?= htmlspecialchars($c['slug']) ?>" class="club-card">
                    <div class="club-icon-wrap">
                        <?php if ($c['icon_filename']): ?>
                        <img src="../assets/club-icons/<?= htmlspecialchars($c['icon_filename']) ?>" alt="" class="club-icon">
                        <?php else: ?>
                        <span class="club-icon-placeholder"><?= strtoupper(substr($c['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="club-name"><?= htmlspecialchars($c['name']) ?></span>
                    <span class="club-members"><?= $c['member_count'] ?> member<?= $c['member_count'] !== 1 ? 's' : '' ?></span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <script>
    (function () {
        const searchInput  = document.getElementById('searchInput');
        const allClubsDiv  = document.getElementById('allClubs');
        const originalHtml = allClubsDiv.innerHTML;
        let   debounce;

        searchInput.addEventListener('input', function () {
            clearTimeout(debounce);
            const q = this.value.trim();
            if (q.length === 0) {
                allClubsDiv.innerHTML = originalHtml;
                return;
            }
            if (q.length < 2) return;
            debounce = setTimeout(() => searchClubs(q), 280);
        });

        async function searchClubs(q) {
            try {
                const res  = await fetch(`../api/club.php?action=search&q=${encodeURIComponent(q)}`);
                const data = await res.json();
                if (data.success) renderClubs(data.clubs);
            } catch {}
        }

        function renderClubs(clubs) {
            if (!clubs.length) {
                allClubsDiv.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><p>No clubs found.</p></div>';
                return;
            }
            allClubsDiv.innerHTML = clubs.map(c => `
                <a href="view.php?slug=${c.slug}" class="club-card">
                    <div class="club-icon-wrap">
                        ${c.icon_filename
                            ? `<img src="../assets/club-icons/${c.icon_filename}" alt="" class="club-icon">`
                            : `<span class="club-icon-placeholder">${esc(c.name).charAt(0).toUpperCase()}</span>`}
                    </div>
                    <span class="club-name">${esc(c.name)}</span>
                    <span class="club-members">${c.member_count} member${c.member_count !== 1 ? 's' : ''}</span>
                </a>
            `).join('');
        }

        function esc(t) {
            const d = document.createElement('div');
            d.textContent = t;
            return d.innerHTML;
        }
    })();
    </script>
</body>
</html>
