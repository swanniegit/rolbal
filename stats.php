<?php
/**
 * Statistics Page
 */

require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/Session.php';
require_once __DIR__ . '/includes/Roll.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Template.php';

$isLoggedIn = Auth::check();
$sessionId  = isset($_GET['id'])  ? (int)$_GET['id']  : 0;
$showAll    = isset($_GET['all']) && $isLoggedIn && !$sessionId;

$session   = null;
$stats     = null;
$sessions  = [];
$summaries = [];

// Always load sessions list when logged in (needed for dropdown)
if ($isLoggedIn) {
    $sessions  = Session::forPlayer(Auth::id());
    $summaries = Roll::sessionSummaries(Auth::id());
}

$challengeStats = [];

if ($sessionId) {
    $session = Session::find($sessionId);
    if ($session) {
        $stats = Roll::stats($sessionId);
    }
} elseif ($showAll) {
    $stats = Roll::statsForPlayer(Auth::id());
    // Challenge stats grouped per challenge
    $db = Database::getInstance();
    $cstmt = $db->prepare('
        SELECT c.id, c.name, c.difficulty,
               COUNT(ca.id)                                    AS attempts,
               MAX(ca.total_score)                             AS best_score,
               ROUND(AVG(ca.total_score))                      AS avg_score,
               MAX(ca.max_possible_score)                      AS max_possible,
               GROUP_CONCAT(ca.total_score ORDER BY ca.created_at ASC SEPARATOR ",") AS history
        FROM challenges c
        JOIN challenge_attempts ca ON ca.challenge_id = c.id
        WHERE ca.player_id = :pid AND ca.completed_at IS NOT NULL
        GROUP BY c.id
        ORDER BY c.name
    ');
    $cstmt->execute(['pid' => Auth::id()]);
    $challengeStats = $cstmt->fetchAll(PDO::FETCH_ASSOC);

    // Awards computation
    $totalChallenges  = (int)$db->query('SELECT COUNT(*) FROM challenges')->fetchColumn();
    $sessionCount     = count($summaries);
    $totalBowls       = $stats['total'] ?? 0;
    $totalCentres     = $stats['results'][8] ?? 0;
    $totalTouchers    = $stats['touchers'] ?? 0;
    $challengesDone   = count($challengeStats);

    $bestEfficiency = 0;
    $bestAvg        = 0.0;
    foreach ($summaries as $_s) {
        if ((int)$_s['total'] > 0) {
            $_eff = (int)$_s['total_score'] / ((int)$_s['total'] * 10) * 100;
            if ($_eff > $bestEfficiency) $bestEfficiency = (int)$_eff;
            $_avg = (int)$_s['total_score'] / (int)$_s['total'];
            if ($_avg > $bestAvg) $bestAvg = $_avg;
        }
    }

    $awardChecks = [
        'first_session'   => $sessionCount >= 1,
        'first_centre'    => $totalCentres >= 1,
        'first_toucher'   => $totalTouchers >= 1,
        'first_challenge' => $challengesDone >= 1,
        'sessions_10'     => $sessionCount >= 10,
        'bowls_100'       => $totalBowls >= 100,
        'centres_10'      => $totalCentres >= 10,
        'eff_60'          => $bestEfficiency >= 60,
        'sessions_25'     => $sessionCount >= 25,
        'bowls_500'       => $totalBowls >= 500,
        'centres_50'      => $totalCentres >= 50,
        'challenges_5'    => $challengesDone >= 5,
        'eff_80'          => $bestEfficiency >= 80,
        'sessions_50'     => $sessionCount >= 50,
        'bowls_1000'      => $totalBowls >= 1000,
        'centres_100'     => $totalCentres >= 100,
        'all_challenges'  => $challengesDone >= $totalChallenges && $totalChallenges > 0,
        'avg_2'           => $bestAvg >= 2,
        'avg_4'           => $bestAvg >= 4,
        'avg_7'           => $bestAvg >= 7,
        'avg_9'           => $bestAvg >= 9,
        'avg_10'          => $bestAvg >= 10,
    ];
    foreach ($awardChecks as $_code => $_earned) {
        if ($_earned) $earnedAwards[] = $_code;
    }

    // Find closest unearned milestone for progress hint ([$current, $target, $unit])
    $milestoneProgress = [
        'sessions_10'  => [$sessionCount,   10,   'sessions'],
        'bowls_100'    => [$totalBowls,      100,  'bowls'],
        'centres_10'   => [$totalCentres,    10,   'centres'],
        'sessions_25'  => [$sessionCount,    25,   'sessions'],
        'bowls_500'    => [$totalBowls,      500,  'bowls'],
        'centres_50'   => [$totalCentres,    50,   'centres'],
        'challenges_5' => [$challengesDone,  5,    'challenges'],
        'sessions_50'  => [$sessionCount,    50,   'sessions'],
        'bowls_1000'   => [$totalBowls,      1000, 'bowls'],
        'centres_100'  => [$totalCentres,    100,  'centres'],
        'avg_2'        => [$bestAvg,         2,    'pts/bowl'],
        'avg_4'        => [$bestAvg,         4,    'pts/bowl'],
        'avg_7'        => [$bestAvg,         7,    'pts/bowl'],
        'avg_9'        => [$bestAvg,         9,    'pts/bowl'],
        'avg_10'       => [$bestAvg,         10,   'pts/bowl'],
    ];
    $bestRatio = -1;
    foreach ($milestoneProgress as $_code => [$_cur, $_tgt, $_unit]) {
        if (in_array($_code, $earnedAwards)) continue;
        $_ratio = $_tgt > 0 ? $_cur / $_tgt : 0;
        if ($_ratio > $bestRatio) {
            $bestRatio     = $_ratio;
            $_curFmt       = is_float($_cur) ? round($_cur, 1) : $_cur;
            $nextMilestone = ['code'=>$_code, 'def'=>$awardDefs[$_code], 'current'=>$_curFmt, 'target'=>$_tgt, 'unit'=>$_unit, 'pct'=>min(100, (int)($_ratio*100))];
        }
    }
} elseif ($isLoggedIn) {
    // Default: combined view
    header('Location: stats.php?all=1');
    exit;
} else {
    header('Location: login.php');
    exit;
}

$fh = $stats ? $stats['by_delivery'][14] : ['total'=>0,'touchers'=>0,'results'=>[]];
$bh = $stats ? $stats['by_delivery'][13] : ['total'=>0,'touchers'=>0,'results'=>[]];
$hasDelivery = $stats && ($fh['total'] + $bh['total']) > 0;

// Score calculation
$scoring = [8=>10, 3=>7, 4=>7, 7=>5, 12=>5, 5=>3, 6=>3, 1=>2, 2=>2, 20=>0, 21=>0, 22=>0, 23=>0];
$totalScore = 0;
if ($stats) {
    foreach ($stats['results'] as $code => $cnt) {
        $totalScore += ($scoring[$code] ?? 0) * $cnt;
    }
    $totalScore += $stats['touchers'] * 5;
}
$avgScore   = ($stats && $stats['total'] > 0) ? round($totalScore / $stats['total'], 1) : 0;
$efficiency = ($stats && $stats['total'] > 0) ? round($totalScore / ($stats['total'] * 10) * 100) : 0;

function deliveryMetrics(array $d, int $total): array {
    $mc = [20,21,22,23];
    return [
        'bowls'   => $d['total'],
        'pct'     => $total > 0 ? round(($d['total'] / $total) * 100) : 0,
        'centre'  => $d['total'] > 0 ? round((($d['results'][8] ?? 0) / $d['total']) * 100) : 0,
        'misses'  => $d['total'] > 0 ? round((array_sum(array_intersect_key($d['results'], array_flip($mc))) / $d['total']) * 100) : 0,
        'toucher' => $d['total'] > 0 ? round(($d['touchers'] / $d['total']) * 100) : 0,
    ];
}

function renderPositionGrid(array $groups, array $results, int $total): string {
    $html = '<div class="stats-table">';
    foreach ($groups as $row => $positions) {
        $html .= '<div class="stats-row"><span class="row-label">' . $row . '</span>';
        foreach ($positions as $code => $label) {
            $count = $results[$code] ?? 0;
            $pct   = $total > 0 ? round(($count / $total) * 100) : 0;
            $hl    = $code === 8 ? ' highlight' : '';
            $html .= '<div class="stats-cell' . $hl . '">'
                   . '<span class="cell-count">' . $count . '</span>'
                   . '<span class="cell-pct">' . $pct . '%</span>'
                   . '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

$positionGroups = [
    'Long'  => [5 => 'Left', 7 => 'Centre', 6 => 'Right'],
    'Level' => [3 => 'Left', 8 => 'Centre', 4 => 'Right'],
    'Short' => [1 => 'Left', 12 => 'Centre', 2 => 'Right'],
];

// Award definitions (static)
$awardDefs = [
    // Bronze
    'first_session'   => ['tier'=>'bronze',   'icon'=>'1st',  'name'=>'First Steps',    'desc'=>'Completed your first practice session'],
    'first_centre'    => ['tier'=>'bronze',   'icon'=>'CTR',  'name'=>'On Target',       'desc'=>'Hit your first centre'],
    'first_toucher'   => ['tier'=>'bronze',   'icon'=>'TCH',  'name'=>'Touch of Class',  'desc'=>'Recorded your first toucher'],
    'first_challenge' => ['tier'=>'bronze',   'icon'=>'CHG',  'name'=>'Challenger',      'desc'=>'Completed your first challenge'],
    // Silver
    'sessions_10'    => ['tier'=>'silver',   'icon'=>'10',   'name'=>'Regular Trainer', 'desc'=>'10 practice sessions recorded'],
    'bowls_100'      => ['tier'=>'silver',   'icon'=>'100',  'name'=>'Century Mark',    'desc'=>'100 bowls recorded'],
    'centres_10'     => ['tier'=>'silver',   'icon'=>'x10',  'name'=>'Bullseye',        'desc'=>'10 centre hits'],
    'eff_60'         => ['tier'=>'silver',   'icon'=>'60%',  'name'=>'Getting Sharp',   'desc'=>'60%+ efficiency in any session'],
    // Gold
    'sessions_25'    => ['tier'=>'gold',     'icon'=>'25',   'name'=>'Dedicated',       'desc'=>'25 practice sessions recorded'],
    'bowls_500'      => ['tier'=>'gold',     'icon'=>'500',  'name'=>'Iron Bowler',     'desc'=>'500 bowls recorded'],
    'centres_50'     => ['tier'=>'gold',     'icon'=>'x50',  'name'=>'Dead Eye',        'desc'=>'50 centre hits'],
    'challenges_5'   => ['tier'=>'gold',     'icon'=>'x5',   'name'=>'Veteran',         'desc'=>'Completed 5 different challenges'],
    'eff_80'         => ['tier'=>'gold',     'icon'=>'80%',  'name'=>'Elite Form',      'desc'=>'80%+ efficiency in any session'],
    // Platinum
    'sessions_50'    => ['tier'=>'platinum', 'icon'=>'50',   'name'=>'Bowls Legend',    'desc'=>'50 practice sessions recorded'],
    'bowls_1000'     => ['tier'=>'platinum', 'icon'=>'1K',   'name'=>'1000 Bowls',      'desc'=>'1000 bowls recorded'],
    'centres_100'    => ['tier'=>'platinum', 'icon'=>'100C', 'name'=>'Marksman',        'desc'=>'100 centre hits'],
    'all_challenges' => ['tier'=>'platinum', 'icon'=>'ALL',  'name'=>'Grand Master',    'desc'=>'Completed every available challenge'],
    // Scoring milestones (best session avg pts/bowl)
    'avg_2'  => ['tier'=>'bronze',   'icon'=>'2pt',  'name'=>'Warming Up',   'desc'=>'Average 2+ pts/bowl in a session (20% of max)'],
    'avg_4'  => ['tier'=>'silver',   'icon'=>'4pt',  'name'=>'Finding Form', 'desc'=>'Average 4+ pts/bowl in a session (40% of max)'],
    'avg_7'  => ['tier'=>'gold',     'icon'=>'7pt',  'name'=>'Sharp Shooter','desc'=>'Average 7+ pts/bowl in a session (70% of max)'],
    'avg_9'  => ['tier'=>'gold',     'icon'=>'9pt',  'name'=>'Near Perfect', 'desc'=>'Average 9+ pts/bowl in a session (90% of max)'],
    'avg_10' => ['tier'=>'platinum', 'icon'=>'10pt', 'name'=>'Perfection',   'desc'=>'Average 10+ pts/bowl in a session (all centres!)'],
];
$earnedAwards    = [];
$nextMilestone   = null;
$totalChallenges = 0;

$backHref  = $sessionId ? 'game.php?id=' . $sessionId : 'index.php';
$rollCount = $stats ? $stats['total'] : 0;
$pageTitle = $showAll ? 'All Games' : 'Statistics';

Template::pageHead($pageTitle, ['css/pages/stats.css']);
?>
<body>
    <div class="app-container">
        <?php Template::header($pageTitle, $backHref, '<span class="roll-count">' . $rollCount . '</span>'); ?>

        <main class="main-content">

        <?php if ($isLoggedIn && !empty($sessions)): ?>
        <!-- Game picker dropdown -->
        <div class="game-picker">
            <select id="gamePicker" onchange="
                var v=this.value;
                window.location.href = v==='all' ? 'stats.php?all=1' : 'stats.php?id='+v;
            ">
                <option value="all" <?= $showAll ? 'selected' : '' ?>>All Games (Combined)</option>
                <?php foreach (array_reverse($sessions) as $s): ?>
                <option value="<?= $s['id'] ?>" <?= $sessionId===$s['id']?'selected':'' ?>>
                    <?= date('d M Y', strtotime($s['session_date'])) ?>
                    &mdash; <?= $s['hand'] ?>H
                    (<?= $s['roll_count'] ?> bowls)
                    <?php if ($s['description']): ?>&mdash; <?= htmlspecialchars($s['description']) ?><?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if (!$stats): ?>
            <?php Template::emptyState('No games recorded yet', 'game.php', 'Start New Game'); ?>

        <?php else: ?>

                <?php if ($showAll): ?>
            <!-- Awards -->
            <div class="stats-section">
                <h2>Awards <span class="grid-subtitle"><?= count($earnedAwards) ?>/<?= count($awardDefs) ?> earned</span></h2>
                <?php if ($bestAvg > 0): ?>
                <div class="award-pb">
                    <span>Top score</span>
                    <span><strong><?= round($bestAvg, 1) ?></strong> pts/bowl &nbsp;<span class="award-pb-eff"><?= min(100, round($bestAvg / 10 * 100)) ?>% of max</span></span>
                </div>
                <?php endif; ?>
                <div class="award-grid">
                    <?php foreach ($awardDefs as $code => $def):
                        $isEarned = in_array($code, $earnedAwards);
                    ?>
                    <div class="award-badge <?= $isEarned ? 'award-badge--earned' : 'award-badge--locked' ?>"
                         title="<?= htmlspecialchars($def['desc']) ?>">
                        <div class="award-icon award-tier--<?= $def['tier'] ?>">
                            <?= htmlspecialchars($def['icon']) ?>
                        </div>
                        <span class="award-name"><?= htmlspecialchars($def['name']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($nextMilestone): ?>
                <div class="award-next">
                    <div class="award-next-top">
                        <span class="award-next-label">Next: <strong><?= htmlspecialchars($nextMilestone['def']['name']) ?></strong></span>
                        <span class="award-next-prog"><?= $nextMilestone['current'] ?> / <?= $nextMilestone['target'] ?> <?= htmlspecialchars($nextMilestone['unit']) ?></span>
                    </div>
                    <div class="award-next-bar-wrap">
                        <div class="award-next-bar" style="width:<?= $nextMilestone['pct'] ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
                <button class="award-share-btn" onclick="shareStats()">Share on Facebook</button>
            </div>
            <?php endif; ?>

        <?php if ($showAll && !empty($summaries)): ?>
            <!-- Score Progress (All Games) -->
            <div class="stats-section">
                <h2>Score Progress</h2>
                <div class="progress-list">
                    <?php
                    $maxAvg = 0;
                    foreach ($summaries as $s) {
                        $avg = (int)$s['total'] > 0 ? $s['total_score'] / $s['total'] : 0;
                        if ($avg > $maxAvg) $maxAvg = $avg;
                    }
                    $maxAvg = max($maxAvg, 0.1);
                    foreach ($summaries as $s):
                        $t   = (int)$s['total'];
                        if ($t === 0) continue;
                        $avg = round($s['total_score'] / $t, 1);
                        $ctr = round($s['centres'] / $t * 100);
                        $barW = round($avg / 10 * 100); // max 10 pts/bowl
                    ?>
                    <a href="stats.php?id=<?= $s['id'] ?>" class="prog-row">
                        <div class="prog-top">
                            <div class="prog-left">
                                <span class="prog-date"><?= date('d M Y', strtotime($s['session_date'])) ?></span>
                                <span class="prog-tag"><?= $s['hand'] ?>H &nbsp;·&nbsp; <?= $t ?> bowls</span>
                            </div>
                            <div class="prog-right">
                                <span class="prog-avg"><?= $avg ?></span>
                                <span class="prog-avg-lbl">pts/bowl</span>
                            </div>
                        </div>
                        <div class="prog-bar-wrap">
                            <div class="prog-bar" style="width:<?= $barW ?>%"></div>
                        </div>
                        <div class="prog-foot">
                            <span class="prog-ctr"><?= $ctr ?>% centre</span>
                            <span class="prog-total"><?= $s['total_score'] ?> pts total</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <p class="prog-legend">Bar = avg score per bowl (max 10). Tap a row to view that game.</p>
            </div>
            <?php endif; ?>

            <?php if ($showAll && !empty($challengeStats)): ?>
            <div class="stats-section">
                <h2>Challenge Progress</h2>
                <div class="ch-grid">
                    <?php foreach ($challengeStats as $cs):
                        $bestPct = $cs['max_possible'] > 0 ? round($cs['best_score'] / $cs['max_possible'] * 100) : 0;
                        $avgPct  = $cs['max_possible'] > 0 ? round($cs['avg_score']  / $cs['max_possible'] * 100) : 0;
                        $history = array_map('intval', explode(',', $cs['history']));
                        $last8   = array_slice($history, -8);
                        $histMax = max(1, max($last8));
                    ?>
                    <div class="ch-card">
                        <div class="ch-card-top">
                            <span class="ch-name"><?= htmlspecialchars($cs['name']) ?></span>
                            <span class="ch-diff ch-diff--<?= $cs['difficulty'] ?>"><?= ucfirst($cs['difficulty']) ?></span>
                        </div>
                        <div class="ch-card-body">
                            <div class="ch-best-block">
                                <span class="ch-best-val"><?= $bestPct ?>%</span>
                                <span class="ch-best-lbl">Best</span>
                                <span class="ch-avg-val"><?= $avgPct ?>% avg</span>
                            </div>
                            <div class="ch-spark">
                                <?php foreach ($last8 as $sc):
                                    $h = $cs['max_possible'] > 0 ? max(4, round($sc / $cs['max_possible'] * 100)) : 4;
                                ?>
                                <div class="ch-bar" style="height:<?= $h ?>%"></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="ch-attempts"><?= $cs['attempts'] ?> attempt<?= $cs['attempts']!=1?'s':'' ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($session): ?>
            <div class="session-info">
                <span class="badge"><?= HANDS[$session['hand']] ?></span>
                <span><?= date('d M Y', strtotime($session['session_date'])) ?></span>
                <?php if ($session['description']): ?>
                    <span>- <?= htmlspecialchars($session['description']) ?></span>
                <?php endif; ?>
            </div>
            <?php elseif ($showAll): ?>
            <div class="session-info">
                <span><?= count($sessions) ?> sessions &nbsp;·&nbsp; <?= $rollCount ?> total bowls</span>
            </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-value"><?= $stats['total'] ?></span>
                    <span class="stat-label">Total Bowls</span>
                </div>
                <div class="stat-card highlight">
                    <span class="stat-value"><?= $stats['results'][8] ?? 0 ?></span>
                    <span class="stat-label">Centre</span>
                </div>
                <div class="stat-card highlight">
                    <span class="stat-value"><?= $avgScore ?></span>
                    <span class="stat-label">Avg Score</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= $efficiency ?>%</span>
                    <span class="stat-label">Efficiency</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= $totalScore ?></span>
                    <span class="stat-label">Total Score</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= $stats['touchers'] ?></span>
                    <span class="stat-label">Touchers</span>
                </div>
            </div>

            <!-- Overall Position Breakdown -->
            <div class="stats-section">
                <h2>Position Breakdown</h2>
                <?= renderPositionGrid($positionGroups, $stats['results'], $stats['total']) ?>
            </div>

            <!-- FH Position Breakdown -->
            <?php if ($hasDelivery): ?>
            <div class="stats-section">
                <h2>Forehand Breakdown <span class="grid-subtitle"><?= $fh['total'] ?> bowls</span></h2>
                <?= renderPositionGrid($positionGroups, $fh['results'], $fh['total']) ?>
            </div>

            <div class="stats-section">
                <h2>Backhand Breakdown <span class="grid-subtitle"><?= $bh['total'] ?> bowls</span></h2>
                <?= renderPositionGrid($positionGroups, $bh['results'], $bh['total']) ?>
            </div>
            <?php endif; ?>

            <!-- FH vs BH summary -->
            <?php if (!$hasDelivery): ?>
            <div class="stats-section stats-section--note">
                <p>Forehand / Backhand stats available from 19 Apr 2026. Select <strong>FH or BH</strong> before each bowl.</p>
            </div>
            <?php else:
                $fhM = deliveryMetrics($fh, $stats['total']);
                $bhM = deliveryMetrics($bh, $stats['total']);
            ?>
            <div class="stats-section">
                <h2>Forehand vs Backhand</h2>
                <div class="del-compare">
                    <div class="del-col del-col--fh">
                        <div class="del-col-title">Forehand</div>
                        <div class="del-col-bowls"><?= $fhM['bowls'] ?> <span>bowls</span></div>
                        <div class="del-col-pct-bar"><div style="width:<?= $fhM['pct'] ?>%"></div></div>
                        <div class="del-col-metrics">
                            <span><strong><?= $fhM['centre'] ?>%</strong> Centre</span>
                            <span><strong><?= $fhM['misses'] ?>%</strong> Misses</span>
                            <span><strong><?= $fhM['toucher'] ?>%</strong> Toucher</span>
                        </div>
                    </div>
                    <div class="del-divider"></div>
                    <div class="del-col del-col--bh">
                        <div class="del-col-title">Backhand</div>
                        <div class="del-col-bowls"><?= $bhM['bowls'] ?> <span>bowls</span></div>
                        <div class="del-col-pct-bar del-col-pct-bar--bh"><div style="width:<?= $bhM['pct'] ?>%"></div></div>
                        <div class="del-col-metrics">
                            <span><strong><?= $bhM['centre'] ?>%</strong> Centre</span>
                            <span><strong><?= $bhM['misses'] ?>%</strong> Misses</span>
                            <span><strong><?= $bhM['toucher'] ?>%</strong> Toucher</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- By End Length — position grids -->
            <?php
            $lengthLabels = [9 => 'Long End', 10 => 'Middle End', 11 => 'Short End'];
            $lengthColors = [9 => '#2d5016', 10 => '#4a7c2a', 11 => '#7aad52'];
            foreach ($lengthLabels as $code => $lengthLabel):
                $ld = $stats['by_length'][$code];
                if ($ld['total'] === 0) continue;
                $pct = $stats['total'] > 0 ? round(($ld['total'] / $stats['total']) * 100) : 0;
            ?>
            <div class="stats-section">
                <h2 style="color:<?= $lengthColors[$code] ?>"><?= $lengthLabel ?> <span class="grid-subtitle"><?= $ld['total'] ?> bowls · <?= $pct ?>%</span></h2>
                <?= renderPositionGrid($positionGroups, $ld['results'], $ld['total']) ?>

                <?php if ($hasDelivery && ($ld['fh']['total'] + $ld['bh']['total']) > 0): ?>
                <div class="length-del-grids">
                    <?php if ($ld['fh']['total'] > 0): ?>
                    <div class="ldg-col">
                        <div class="ldg-label">Forehand <span><?= $ld['fh']['total'] ?> bowls</span></div>
                        <?= renderPositionGrid($positionGroups, $ld['fh']['results'], $ld['fh']['total']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($ld['bh']['total'] > 0): ?>
                    <div class="ldg-col">
                        <div class="ldg-label">Backhand <span><?= $ld['bh']['total'] ?> bowls</span></div>
                        <?= renderPositionGrid($positionGroups, $ld['bh']['results'], $ld['bh']['total']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="action-bar">
                <a href="index.php" class="btn-secondary">Home</a>
                <?php if ($sessionId): ?>
                <a href="stats-full.php?id=<?= $sessionId ?>" class="btn-primary btn-laptop-only">See full stats on your laptop</a>
                <p class="pwa-laptop-note">More detailed stats available on your laptop at bowlstracker.co.za</p>
                <?php endif; ?>
            </div>

        <?php endif; ?>
        </main>
    </div>
<?php if ($showAll): ?>
<script>
async function shareStats() {
    const earned  = <?= count($earnedAwards) ?>;
    const total   = <?= count($awardDefs) ?>;
    const topScore = <?= round($bestAvg, 1) ?>;
    const sessions = <?= count($summaries) ?>;
    const bowls    = <?= $stats ? $stats['total'] : 0 ?>;

    const text = `I've earned ${earned}/${total} awards on BowlsTracker with a top score of ${topScore} pts/bowl across ${sessions} sessions (${bowls} bowls recorded). Track your lawn bowls practice at bowlstracker.co.za`;
    const url  = 'https://bowlstracker.co.za';

    if (navigator.share) {
        try {
            await navigator.share({ title: 'My BowlsTracker Stats', text, url });
        } catch(e) { /* cancelled */ }
    } else {
        const fb = 'https://www.facebook.com/sharer/sharer.php'
            + '?u=' + encodeURIComponent(url)
            + '&quote=' + encodeURIComponent(text);
        window.open(fb, '_blank', 'width=600,height=400');
    }
}
</script>
<?php endif; ?>
</body>
</html>
