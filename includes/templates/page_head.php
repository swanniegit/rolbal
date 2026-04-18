<?php
/**
 * Page Head Component - HTML doctype and head section
 *
 * @param string $pageTitle - Page title (appended to "BowlsTracker - ")
 * @param array $css - Additional CSS files to include (relative to root)
 * @param string $themeColor - Theme color (default: #2d5016)
 */

require_once __DIR__ . '/../Auth.php';

$pageTitle = $pageTitle ?? '';
$css = $css ?? [];
$themeColor = $themeColor ?? '#2d5016';
$fullTitle = $pageTitle ? "BowlsTracker - $pageTitle" : 'BowlsTracker';
$csrfToken = Auth::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="<?= htmlspecialchars($themeColor) ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title><?= htmlspecialchars($fullTitle) ?></title>
    <link rel="manifest" href="<?= $manifestPath ?? 'manifest.json' ?>">
    <link rel="stylesheet" href="<?= $cssBasePath ?? '' ?>css/styles.css?v=2">
<?php foreach ($css as $cssFile): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssFile) ?>?v=2">
<?php endforeach; ?>
</head>
