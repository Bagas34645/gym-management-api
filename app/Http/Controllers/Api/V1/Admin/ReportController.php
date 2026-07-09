<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Exports\ReportExport;
use App\Http\Controllers\Api\V1\Controller;
use App\Services\Reports\ReportDataService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private readonly ReportDataService $reportData) {}

    public function members(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        $report = $this->reportData->membersReport($from, $to);

        return $this->success([
            'from' => $report['from'],
            'to' => $report['to'],
            'total' => $report['total'],
            'members' => $report['members'],
            'timeline' => $report['timeline'],
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        $report = $this->reportData->attendanceReport($from, $to);

        return $this->success([
            'from' => $report['from'],
            'to' => $report['to'],
            'total' => $report['total'],
            'records' => $report['records'],
            'timeline' => $report['timeline'],
        ]);
    }

    public function finance(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        $report = $this->reportData->financeReport($from, $to);

        if ($method = $request->get('payment_method')) {
            $report['by_payment_method'] = collect($report['by_payment_method'])
                ->only([$method])
                ->all();
            $report['payments'] = $report['payments']
                ->where('payment_method', $method)
                ->values();
            $report['total_revenue'] = (float) collect($report['by_payment_method'])->sum();
            $report['timeline'] = $report['payments']
                ->groupBy(fn ($payment) => $payment->payment_date?->toDateString())
                ->map(fn ($group, $date) => [
                    'date' => (string) $date,
                    'revenue' => (float) $group->sum('amount'),
                ])
                ->sortKeys()
                ->values()
                ->all();
        }

        return $this->success([
            'total_revenue' => $report['total_revenue'],
            'by_payment_method' => $report['by_payment_method'],
            'timeline' => $report['timeline'],
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $data = $request->validate([
            'report_type' => ['required', 'in:members,attendance,finance'],
            'format' => ['required', 'in:pdf,excel'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();

        if ($from > $to) {
            throw new ApiException('Rentang tanggal tidak valid untuk laporan', ErrorCode::ReportInvalidRange, 400);
        }

        $report = match ($data['report_type']) {
            'members' => $this->reportData->membersReport($from, $to),
            'attendance' => $this->reportData->attendanceReport($from, $to),
            'finance' => $this->reportData->financeReport($from, $to),
        };

        $filename = "report-{$data['report_type']}-".now()->format('Y-m-d-His');
        $path = "exports/{$filename}";

        if ($data['format'] === 'pdf') {
            $view = match ($data['report_type']) {
                'members' => 'reports.members',
                'attendance' => 'reports.attendance',
                'finance' => 'reports.finance',
            };

            $html = view($view, [
                'report' => $report,
                'formatter' => $this->reportData,
            ])->render();

            $pdf = Pdf::loadHTML($html);
            Storage::disk('public')->put("{$path}.pdf", $pdf->output());
            $path .= '.pdf';
        } else {
            $rows = match ($data['report_type']) {
                'members' => $this->reportData->membersExcelRows($report),
                'attendance' => $this->reportData->attendanceExcelRows($report),
                'finance' => $this->reportData->financeExcelRows($report),
            };

            $sheetTitle = match ($data['report_type']) {
                'members' => 'Anggota',
                'attendance' => 'Absensi',
                'finance' => 'Keuangan',
            };

            Excel::store(new ReportExport($rows, $sheetTitle), "{$path}.xlsx", 'public');
            $path .= '.xlsx';
        }

        $fullPath = Storage::disk('public')->path($path);

        return $this->success([
            'download_url' => Storage::disk('public')->url($path),
            'expires_at' => now()->addHour()->toIso8601String(),
            'file_size_kb' => (int) (filesize($fullPath) / 1024),
        ]);
    }
}
