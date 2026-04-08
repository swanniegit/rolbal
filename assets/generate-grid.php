<?php
/**
 * Generate position grid PNG for WhatsApp
 * Run once: php generate-grid.php
 */

$width = 400;
$height = 440;

$img = imagecreatetruecolor($width, $height);

// Colors
$white = imagecolorallocate($img, 255, 255, 255);
$green = imagecolorallocate($img, 45, 80, 22);
$greenLight = imagecolorallocate($img, 74, 124, 42);
$yellow = imagecolorallocate($img, 245, 184, 0);
$gray = imagecolorallocate($img, 200, 200, 200);
$grayLight = imagecolorallocate($img, 240, 240, 240);
$black = imagecolorallocate($img, 30, 30, 30);
$textMuted = imagecolorallocate($img, 100, 100, 100);

// Fill background
imagefill($img, 0, 0, $white);

// Grid settings
$startX = 40;
$startY = 50;
$cellW = 100;
$cellH = 75;
$gap = 8;

// Draw miss zones
// Too Long (top)
imagefilledrectangle($img, $startX, 10, $startX + 3*$cellW + 2*$gap, 40, $grayLight);
imagerectangle($img, $startX, 10, $startX + 3*$cellW + 2*$gap, 40, $gray);
imagestring($img, 3, $startX + 100, 18, "Too Long / Ditch", $textMuted);

// Too Short (bottom)
$bottomY = $startY + 3*$cellH + 2*$gap + 10;
imagefilledrectangle($img, $startX, $bottomY, $startX + 3*$cellW + 2*$gap, $bottomY + 30, $grayLight);
imagerectangle($img, $startX, $bottomY, $startX + 3*$cellW + 2*$gap, $bottomY + 30, $gray);
imagestring($img, 3, $startX + 130, $bottomY + 8, "Too Short", $textMuted);

// Too Far Left
imagefilledrectangle($img, 5, $startY, 30, $startY + 3*$cellH + 2*$gap, $grayLight);
imagerectangle($img, 5, $startY, 30, $startY + 3*$cellH + 2*$gap, $gray);
imagestringup($img, 2, 10, $startY + 160, "Too Far Left", $textMuted);

// Too Far Right
$rightX = $startX + 3*$cellW + 2*$gap + 10;
imagefilledrectangle($img, $rightX, $startY, $rightX + 25, $startY + 3*$cellH + 2*$gap, $grayLight);
imagerectangle($img, $rightX, $startY, $rightX + 25, $startY + 3*$cellH + 2*$gap, $gray);
imagestringup($img, 2, $rightX + 5, $startY + 165, "Too Far Right", $textMuted);

// Position labels
$labels = [
    [0, 0, 'Long Left'],
    [1, 0, 'Long Centre'],
    [2, 0, 'Long Right'],
    [0, 1, 'Level Left'],
    [1, 1, 'Centre'],
    [2, 1, 'Level Right'],
    [0, 2, 'Short Left'],
    [1, 2, 'Short Centre'],
    [2, 2, 'Short Right'],
];

// Draw cells
foreach ($labels as $cell) {
    $col = $cell[0];
    $row = $cell[1];
    $label = $cell[2];

    $x1 = $startX + $col * ($cellW + $gap);
    $y1 = $startY + $row * ($cellH + $gap);
    $x2 = $x1 + $cellW;
    $y2 = $y1 + $cellH;

    // Centre cell is yellow
    if ($label === 'Centre') {
        imagefilledrectangle($img, $x1, $y1, $x2, $y2, $yellow);
        imagerectangle($img, $x1, $y1, $x2, $y2, $green);
    } else {
        imagefilledrectangle($img, $x1, $y1, $x2, $y2, $white);
        imagerectangle($img, $x1, $y1, $x2, $y2, $green);
        imagerectangle($img, $x1+1, $y1+1, $x2-1, $y2-1, $greenLight);
    }

    // Text centering
    $textWidth = strlen($label) * 6;
    $textX = $x1 + ($cellW - $textWidth) / 2;
    $textY = $y1 + ($cellH - 8) / 2;

    imagestring($img, 3, $textX, $textY, $label, $black);
}

// Title
imagestring($img, 4, 150, $height - 25, "Select bowl position", $textMuted);

// Save
imagepng($img, __DIR__ . '/position-grid.png');
imagedestroy($img);

echo "Generated position-grid.png\n";
