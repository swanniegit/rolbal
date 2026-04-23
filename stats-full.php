<?php
require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/Session.php';
require_once __DIR__ . '/includes/Roll.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Template.php';

$sessionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$sessionId) { header('Location: stats.php'); exit; }

$session = Session::find($sessionId);
if (!$session) { header('Location: stats.php'); exit; }

$stats  = Roll::stats($sessionId);
$total  = $stats['total'];

$scoring  = [8=>10, 3=>7, 4=>7, 7=>5, 12=>5, 5=>3, 6=>3, 1=>2, 2=>2, 20=>0, 21=>0, 22=>0, 23=>0];
$missCodes = [20, 21, 22, 23];

$totalPoints = 0;
foreach ($stats['results'] as $code => $cnt) {
    $totalPoints += ($scoring[$code] ?? 0) * $cnt;
}
$totalPoints += $stats['touchers'] * 5;
$avgScore   = $total > 0 ? round($totalPoints / $total, 1) : 0;
$efficiency = $total > 0 ? round(($totalPoints / ($total * 10)) * 100) : 0;
$totalMisses = array_sum(array_intersect_key($stats['results'], array_flip($missCodes)));

$fh = $stats['by_delivery'][14];
$bh = $stats['by_delivery'][13];
$hasDelivery = ($fh['total'] + $bh['total']) > 0;

// Helper: background color based on hit frequency
function hmBg(int $count, int $max, bool $miss = false, bool $jack = false): string {
    if ($count === 0) return '#f0f0f0';
    $i = $count / max(1, $max);
    if ($jack)  return sprintf('rgba(45,80,22,%.2f)',  max(0.25, $i));
    if ($miss)  return sprintf('rgba(220,50,50,%.2f)', max(0.15, $i));
    return sprintf('rgba(45,80,22,%.2f)', max(0.1, $i));
}
function hmColor(int $count, int $max, bool $miss = false): string {
    $i = $max > 0 ? $count / $max : 0;
    return ($miss ? $i > 0.4 : $i > 0.55) ? '#fff' : 'var(--text)';
}

$maxCount = max(1, ...array_merge([1], array_values($stats['results'])));

// Data for JS charts
$posOrder  = [5, 7, 6, 3, 8, 4, 1, 12, 2]; // grid order
$allCodes  = array_merge($posOrder, $missCodes);
$posLabels = ['Long Left','Long Ctr','Long Right','Level Left','Centre','Level Right','Short Left','Short Ctr','Short Right','Miss Left','Miss Right','Miss Long','Miss Short'];
$posCounts = array_map(fn($c) => $stats['results'][$c] ?? 0, $allCodes);

$fhMiss = array_sum(array_intersect_key($fh['results'] ?? [], array_flip($missCodes)));
$bhMiss = array_sum(array_intersect_key($bh['results'] ?? [], array_flip($missCodes)));

$bl = $stats['by_length'];
$chartData = [
    'posLabels'   => $posLabels,
    'posCounts'   => $posCounts,
    'fhTotal'     => $fh['total'],
    'bhTotal'     => $bh['total'],
    'byLength'    => [
        ['label'=>'Long End',    'total'=>$bl[9]['total'],  'fh'=>$bl[9]['fh']['total'],  'bh'=>$bl[9]['bh']['total'],  'centre'=>($bl[9]['fh']['results'][8]??0)+($bl[9]['bh']['results'][8]??0)],
        ['label'=>'Middle End',  'total'=>$bl[10]['total'], 'fh'=>$bl[10]['fh']['total'], 'bh'=>$bl[10]['bh']['total'], 'centre'=>($bl[10]['fh']['results'][8]??0)+($bl[10]['bh']['results'][8]??0)],
        ['label'=>'Short End',   'total'=>$bl[11]['total'], 'fh'=>$bl[11]['fh']['total'], 'bh'=>$bl[11]['bh']['total'], 'centre'=>($bl[11]['fh']['results'][8]??0)+($bl[11]['bh']['results'][8]??0)],
    ],
    'scoreDist'   => [
        '10 – Centre'      => array_sum(array_intersect_key($stats['results'], array_flip([8]))),
        '7 – Level L/R'    => array_sum(array_intersect_key($stats['results'], array_flip([3,4]))),
        '5 – Long/Short Ctr' => array_sum(array_intersect_key($stats['results'], array_flip([7,12]))),
        '3 – Long L/R'     => array_sum(array_intersect_key($stats['results'], array_flip([5,6]))),
        '2 – Short L/R'    => array_sum(array_intersect_key($stats['results'], array_flip([1,2]))),
        '0 – Miss'         => $totalMisses,
    ],
];

Template::pageHead('Full Analysis', ['css/pages/stats-full.css']);
?>
<body>
<div class="sf-wrap">
    <header class="sf-header">
        <a href="stats.php?id=<?= $sessionId ?>" class="sf-back">&larr; Back</a>
        <div class="sf-header-info">
            <h1>Full Analysis</h1>
            <div class="sf-session-meta">
                <span class="badge"><?= HANDS[$session['hand']] ?></span>
                <?= date('d M Y', strtotime($session['session_date'])) ?>
                <?php if ($session['description']): ?>&nbsp;&mdash;&nbsp;<?= htmlspecialchars($session['description']) ?><?php endif; ?>
            </div>
        </div>
    </header>

    <main class="sf-main">

        <!-- Summary row -->
        <div class="sf-summary">
            <div class="sf-stat"><span class="sf-val"><?= $total ?></span><span class="sf-lbl">Bowls</span></div>
            <div class="sf-stat"><span class="sf-val"><?= $stats['touchers'] ?></span><span class="sf-lbl">Touchers</span></div>
            <div class="sf-stat"><span class="sf-val"><?= $stats['results'][8] ?? 0 ?></span><span class="sf-lbl">Centre</span></div>
            <div class="sf-stat"><span class="sf-val"><?= $totalMisses ?></span><span class="sf-lbl">Misses</span></div>
            <div class="sf-stat sf-hl"><span class="sf-val"><?= $avgScore ?></span><span class="sf-lbl">Avg Score</span></div>
            <div class="sf-stat sf-hl"><span class="sf-val"><?= $efficiency ?>%</span><span class="sf-lbl">Efficiency</span></div>
        </div>

        <!-- Heatmap + Position Distribution side-by-side on desktop -->
        <div class="sf-row-2col">

            <!-- Heatmap -->
            <section class="sf-card">
                <h2>Position Heatmap</h2>
                <?php
                $r = $stats['results'];
                $pct = fn($c) => $total > 0 ? round(($r[$c] ?? 0) / $total * 100) : 0;
                $cnt = fn($c) => $r[$c] ?? 0;
                ?>
                <div class="hm-grid">
                    <!-- Row 1: corners + top miss -->
                    <div class="hm-corner"></div>
                    <div class="hm-miss hm-top" style="background:<?= hmBg($cnt(22),$maxCount,true) ?>;color:<?= hmColor($cnt(22),$maxCount,true) ?>">
                        <span class="hm-miss-lbl">Too Long / Ditch</span>
                        <span class="hm-miss-val"><?= $cnt(22) ?> <em><?= $pct(22) ?>%</em></span>
                    </div>
                    <div class="hm-corner"></div>

                    <!-- Left miss (spans 3 rows) -->
                    <div class="hm-miss hm-left" style="grid-row:2/5;background:<?= hmBg($cnt(20),$maxCount,true) ?>;color:<?= hmColor($cnt(20),$maxCount,true) ?>">
                        <span class="hm-miss-lbl">&#8592; Left</span>
                        <span class="hm-miss-val"><?= $cnt(20) ?><br><em><?= $pct(20) ?>%</em></span>
                    </div>

                    <!-- Row 2 positions -->
                    <?php foreach ([5=>'Long L', 7=>'Long Ctr', 6=>'Long R'] as $code => $name):
                        $c = $cnt($code); $p = $pct($code); $isJ = false; ?>
                    <div class="hm-cell" style="background:<?= hmBg($c,$maxCount,false,$isJ) ?>;color:<?= hmColor($c,$maxCount) ?>">
                        <span class="hm-cn"><?= $name ?></span>
                        <span class="hm-cc"><?= $c ?></span>
                        <span class="hm-cp"><?= $p ?>%</span>
                    </div>
                    <?php endforeach; ?>

                    <!-- Right miss (spans 3 rows) -->
                    <div class="hm-miss hm-right" style="grid-row:2/5;background:<?= hmBg($cnt(21),$maxCount,true) ?>;color:<?= hmColor($cnt(21),$maxCount,true) ?>">
                        <span class="hm-miss-lbl">Right &#8594;</span>
                        <span class="hm-miss-val"><?= $cnt(21) ?><br><em><?= $pct(21) ?>%</em></span>
                    </div>

                    <!-- Row 3: Level row -->
                    <?php foreach ([3=>'Level L', 8=>'CENTRE', 4=>'Level R'] as $code => $name):
                        $c = $cnt($code); $p = $pct($code); $isJ = ($code === 8); ?>
                    <div class="hm-cell <?= $isJ ? 'hm-jack' : '' ?>" style="background:<?= hmBg($c,$maxCount,false,$isJ) ?>;color:<?= hmColor($c,$maxCount) ?>">
                        <span class="hm-cn"><?= $name ?></span>
                        <span class="hm-cc"><?= $c ?></span>
                        <span class="hm-cp"><?= $p ?>%</span>
                    </div>
                    <?php endforeach; ?>

                    <!-- Row 4: Short row -->
                    <?php foreach ([1=>'Short L', 12=>'Short Ctr', 2=>'Short R'] as $code => $name):
                        $c = $cnt($code); $p = $pct($code); ?>
                    <div class="hm-cell" style="background:<?= hmBg($c,$maxCount) ?>;color:<?= hmColor($c,$maxCount) ?>">
                        <span class="hm-cn"><?= $name ?></span>
                        <span class="hm-cc"><?= $c ?></span>
                        <span class="hm-cp"><?= $p ?>%</span>
                    </div>
                    <?php endforeach; ?>

                    <!-- Row 5: corners + bottom miss -->
                    <div class="hm-corner"></div>
                    <div class="hm-miss hm-bottom" style="background:<?= hmBg($cnt(23),$maxCount,true) ?>;color:<?= hmColor($cnt(23),$maxCount,true) ?>">
                        <span class="hm-miss-lbl">Too Short</span>
                        <span class="hm-miss-val"><?= $cnt(23) ?> <em><?= $pct(23) ?>%</em></span>
                    </div>
                    <div class="hm-corner"></div>
                </div>
                <p class="hm-legend"><span class="hm-leg-hit">&#9632;</span> Hit &nbsp;&nbsp; <span class="hm-leg-miss">&#9632;</span> Miss &nbsp;&mdash;&nbsp; Darker = more frequent</p>
            </section>

            <!-- Position distribution chart -->
            <section class="sf-card">
                <h2>Position Distribution</h2>
                <div class="sf-chart-wrap">
                    <canvas id="posChart"></canvas>
                </div>
            </section>
        </div>

        <!-- Delivery & Length row -->
        <div class="sf-row-2col">
            <!-- FH vs BH -->
            <section class="sf-card">
                <h2>Forehand vs Backhand</h2>
                <?php if (!$hasDelivery): ?>
                <p class="sf-note">Select FH or BH during play to unlock this chart.</p>
                <?php else: ?>
                <div class="sf-del-row">
                    <div class="sf-del-donut sf-chart-wrap--sm"><canvas id="delChart"></canvas></div>
                    <table class="sf-table">
                        <thead><tr><th></th><th>FH</th><th>BH</th></tr></thead>
                        <tbody>
                            <tr><td>Bowls</td><td><?= $fh['total'] ?></td><td><?= $bh['total'] ?></td></tr>
                            <tr><td>Centre</td>
                                <td><?= $fh['total']>0 ? round(($fh['results'][8]??0)/$fh['total']*100) : 0 ?>%</td>
                                <td><?= $bh['total']>0 ? round(($bh['results'][8]??0)/$bh['total']*100) : 0 ?>%</td>
                            </tr>
                            <tr><td>Toucher</td>
                                <td><?= $fh['total']>0 ? round($fh['touchers']/$fh['total']*100) : 0 ?>%</td>
                                <td><?= $bh['total']>0 ? round($bh['touchers']/$bh['total']*100) : 0 ?>%</td>
                            </tr>
                            <tr><td>Miss</td>
                                <td><?= $fh['total']>0 ? round($fhMiss/$fh['total']*100) : 0 ?>%</td>
                                <td><?= $bh['total']>0 ? round($bhMiss/$bh['total']*100) : 0 ?>%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

            <!-- By End Length -->
            <section class="sf-card">
                <h2>By End Length</h2>
                <div class="sf-chart-wrap">
                    <canvas id="lengthChart"></canvas>
                </div>
            </section>
        </div>

        <!-- Score Analysis -->
        <section class="sf-card">
            <h2>Score Analysis</h2>
            <div class="sf-score-summary">
                <div class="sf-ss-item"><div class="sf-ss-val"><?= $totalPoints ?></div><div class="sf-ss-lbl">Total Points</div></div>
                <div class="sf-ss-item"><div class="sf-ss-val"><?= $avgScore ?></div><div class="sf-ss-lbl">Avg per Bowl</div></div>
                <div class="sf-ss-item sf-hl"><div class="sf-ss-val"><?= $efficiency ?>%</div><div class="sf-ss-lbl">of Max Possible</div></div>
            </div>
            <div class="sf-chart-wrap--score">
                <canvas id="scoreChart"></canvas>
            </div>
            <p class="sf-note" style="margin-top:0.75rem">Scoring: Centre=10, Level L/R=7, Long/Short Ctr=5, Long L/R=3, Short L/R=2, Miss=0, Toucher bonus=+5</p>
        </section>

        <div class="sf-actions">
            <a href="stats.php?id=<?= $sessionId ?>" class="btn-secondary">Summary View</a>
            <a href="game.php?id=<?= $sessionId ?>" class="btn-primary">Continue Game</a>
        </div>

    </main>
</div>

<script src="js/chart.min.js"></script>
<script>
const D = <?= json_encode($chartData) ?>;
const G1 = '#2d5016', G2 = '#7aad52', RED = '#e53935';
Chart.defaults.font.family = '-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif';
Chart.defaults.color = '#555';

// Position distribution (horizontal bar)
new Chart(document.getElementById('posChart'), {
    type: 'bar',
    data: {
        labels: D.posLabels,
        datasets: [{
            data: D.posCounts,
            backgroundColor: [...Array(9).fill(G1), ...Array(4).fill(RED)],
            borderRadius: 4,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { precision: 0 } },
            y: { ticks: { font: { size: 11 } } }
        }
    }
});

// Delivery doughnut
<?php if ($hasDelivery): ?>
new Chart(document.getElementById('delChart'), {
    type: 'doughnut',
    data: {
        labels: ['Forehand','Backhand'],
        datasets: [{ data: [D.fhTotal, D.bhTotal], backgroundColor: [G1, G2], borderWidth: 3 }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
});
<?php endif; ?>

// By End Length grouped bar
new Chart(document.getElementById('lengthChart'), {
    type: 'bar',
    data: {
        labels: D.byLength.map(l => l.label),
        datasets: <?php if ($hasDelivery): ?>[
            { label: 'Forehand',  data: D.byLength.map(l=>l.fh),     backgroundColor: G1,     borderRadius: 4 },
            { label: 'Backhand',  data: D.byLength.map(l=>l.bh),     backgroundColor: G2,     borderRadius: 4 },
            { label: 'Centre hits', data: D.byLength.map(l=>l.centre), backgroundColor: '#f5b800', borderRadius: 4 },
        ]<?php else: ?>[
            { label: 'Bowls', data: D.byLength.map(l=>l.total), backgroundColor: G1, borderRadius: 4 },
            { label: 'Centre hits', data: D.byLength.map(l=>l.centre), backgroundColor: '#f5b800', borderRadius: 4 },
        ]<?php endif; ?>
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { boxWidth: 12 } } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});

// Score distribution
new Chart(document.getElementById('scoreChart'), {
    type: 'bar',
    data: {
        labels: Object.keys(D.scoreDist),
        datasets: [{
            data: Object.values(D.scoreDist),
            backgroundColor: ['#1b5e20','#2d5016','#4a7c2a','#7aad52','#aed581','#ef9a9a'],
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    }
});
</script>
</body>
</html>
