<?php

/**
 * One-off script to generate PWA icons from the BACS logo.
 * Run: php database/generate_pwa_icons.php
 */

$source = __DIR__ . '/../public/images/bacs_logo_no_bg.png';
$outDir = __DIR__ . '/../public/images';

if (! extension_loaded('gd')) {
    fwrite(STDERR, "GD extension required.\n");
    exit(1);
}

if (! is_file($source)) {
    fwrite(STDERR, "Source logo not found: {$source}\n");
    exit(1);
}

function resizeIcon(string $source, string $dest, int $size, bool $maskable = false): void
{
    $img = imagecreatefrompng($source);
    if ($img === false) {
        throw new RuntimeException('Unable to read source PNG.');
    }

    $srcW = imagesx($img);
    $srcH = imagesy($img);
    $canvas = imagecreatetruecolor($size, $size);
    imagesavealpha($canvas, true);

    $bg = $maskable
        ? imagecolorallocate($canvas, 4, 20, 15)
        : imagecolorallocatealpha($canvas, 0, 0, 0, 127);
    imagefill($canvas, 0, 0, $bg);

    $padding = $maskable ? (int) round($size * 0.12) : (int) round($size * 0.08);
    $inner = $size - ($padding * 2);
    $scale = min($inner / $srcW, $inner / $srcH);
    $w = (int) round($srcW * $scale);
    $h = (int) round($srcH * $scale);
    $x = (int) round(($size - $w) / 2);
    $y = (int) round(($size - $h) / 2);

    imagecopyresampled($canvas, $img, $x, $y, 0, 0, $w, $h, $srcW, $srcH);
    imagepng($canvas, $dest, 9);
    imagedestroy($canvas);
    imagedestroy($img);
}

$sizes = [
    'icon-192.png' => 192,
    'icon-512.png' => 512,
    'icon-maskable-512.png' => 512,
];

foreach ($sizes as $name => $size) {
    $dest = $outDir . '/' . $name;
    resizeIcon($source, $dest, $size, str_contains($name, 'maskable'));
    echo "Created {$dest}\n";
}

echo "Done.\n";
