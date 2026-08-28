<?php

namespace App\Services;

use App\Models\Employee;
use App\Support\DtrPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\Response;

class OfficialDtrPdfService
{
    public function __construct(private readonly OfficialDtrService $dtr) {}

    public function download(Employee $employee, DtrPeriod $period, array $days): Response
    {
        return $this->respond($employee, $period, $days, inline: false);
    }

    public function stream(Employee $employee, DtrPeriod $period, array $days): Response
    {
        return $this->respond($employee, $period, $days, inline: true);
    }

    /**
     * @param  list<\App\Support\DtrDayRow>  $days
     */
    private function respond(Employee $employee, DtrPeriod $period, array $days, bool $inline): Response
    {
        $filename = $this->filename($employee, $period);
        $path = $this->render($employee, $period, $days);
        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  list<\App\Support\DtrDayRow>  $days
     */
    public function render(Employee $employee, DtrPeriod $period, array $days): string
    {
        $payload = $this->dtr->pdfPayload($employee, $period, $days);
        $dir = storage_path('app/dtr-pdf');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $jsonPath = $dir.DIRECTORY_SEPARATOR.uniqid('dtr-', true).'.json';
        $outPath = $dir.DIRECTORY_SEPARATOR.uniqid('dtr-out-', true).'.pdf';
        file_put_contents($jsonPath, json_encode($payload, JSON_UNESCAPED_UNICODE));

        try {
            if ($this->overlay($jsonPath, $outPath) && is_file($outPath) && filesize($outPath) > 1000) {
                return $outPath;
            }

            return $this->renderFallback($payload, $outPath);
        } finally {
            if (is_file($jsonPath)) {
                @unlink($jsonPath);
            }
        }
    }

    private function overlay(string $jsonPath, string $outPath): bool
    {
        $script = base_path('scripts/generate-official-dtr.mjs');
        $template = resource_path('forms/daily-time-record.pdf');

        if (! is_file($script) || ! is_file($template)) {
            return false;
        }

        $result = Process::timeout(45)->run([
            $this->nodeBinary(),
            $script,
            $template,
            $jsonPath,
            $outPath,
        ]);

        if (! $result->successful()) {
            Log::warning('Official DTR PDF overlay failed; using structural fallback.', [
                'error' => $result->errorOutput() ?: $result->output(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderFallback(array $payload, string $outPath): string
    {
        $pdf = Pdf::loadView('reports.pdf.official-dtr', [
            'employeeName' => $payload['employee_name'],
            'department' => $payload['department'],
            'cutoff' => $payload['cutoff'],
            'rows' => $payload['rows'],
        ])->setPaper('a4', 'portrait')
            ->setOption('dpi', 96)
            ->setOption('defaultFont', 'DejaVu Sans');

        file_put_contents($outPath, $pdf->output());

        return $outPath;
    }

    private function filename(Employee $employee, DtrPeriod $period): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $employee->fullName()) ?: 'employee';

        return 'DTR-'.$slug.'-'.$period->start.'-'.$period->end.'.pdf';
    }

    private function nodeBinary(): string
    {
        $configured = (string) config('dtr.node_binary', 'node');

        return $configured !== '' ? $configured : 'node';
    }
}
