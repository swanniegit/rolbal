<?php
/**
 * Page Head Component - HTML doctype and head section
 *
 * @param string $pageTitle - Page title (appended to "BowlsTracker - ")
 * @param array $css - Additional CSS files to include (relative to root)
 * @param string $themeColor - Theme color (default: #2d5016)
 */

$pageTitle = $pageTitle ?? '';
$css = $css ?? [];
$themeColor = $themeColor ?? '#2d5016';
$fullTitle = $pageTitle ? "BowlsTracker - $pageTitle" : 'BowlsTracker';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?= htmlspecialchars($themeColor) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?= htmlspecialchars($fullTitle) ?></title>
    <link rel="manifest" href="<?= $manifestPath ?? 'manifest.json' ?>">
    <link rel="stylesheet" href="<?= $cssBasePath ?? '' ?>css/styles.css">
<?php foreach ($css as $cssFile): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssFile) ?>">
<?php endforeach; ?>
</head>
