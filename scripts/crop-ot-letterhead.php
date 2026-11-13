<?php

$path = __DIR__.'/../public/images/ot-attachment-letterhead.png';
$sourcePath = __DIR__.'/../docx/OT-attachment-report-extracted/word/media/image1.png';

if (is_file($sourcePath)) {
    copy($sourcePath, $path);
}

if (! is_file($path)) {
    fwrite(STDERR, "Missing letterhead source\n");
    exit(1);
}

$src = imagecreatefrompng($path);
if ($src === false) {
    fwrite(STDERR, "Cannot load PNG\n");
    exit(1);
}

$width = imagesx($src);
$height = imagesy($src);

// Match Word header crop (header1.xml a:srcRect t="9969" b="53478").
$top = (int) round($height * 0.09969);
$bottom = (int) round($height * 0.53478);
$cropHeight = $height - $top - $bottom;

if ($cropHeight < 1) {
    fwrite(STDERR, "Invalid crop\n");
    exit(1);
}

$dest = imagecrop($src, [
    'x' => 0,
    'y' => $top,
    'width' => $width,
    'height' => $cropHeight,
]);

imagealphablending($dest, false);
imagesavealpha($dest, true);
imagepng($dest, $path);
imagedestroy($src);
imagedestroy($dest);

echo "Cropped letterhead to {$width}x{$cropHeight}\n";
