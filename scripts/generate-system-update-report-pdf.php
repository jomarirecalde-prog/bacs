<?php

declare(strict_types=1);

use Barryvdh\DomPDF\Facade\Pdf;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$relativePath = 'docs/BACS-System-Update-Report-2026-11-13-14.pdf';
$fullPath = base_path($relativePath);

$pdf = Pdf::loadView('docs.system-update-report-2026-11-14')
    ->setPaper('a4', 'portrait')
    ->setOption('isRemoteEnabled', false)
    ->setOption('dpi', 96)
    ->setOption('defaultFont', 'DejaVu Sans');

if (! is_dir(dirname($fullPath))) {
    mkdir(dirname($fullPath), 0755, true);
}

file_put_contents($fullPath, $pdf->output());

echo "Wrote: {$fullPath}\n";
