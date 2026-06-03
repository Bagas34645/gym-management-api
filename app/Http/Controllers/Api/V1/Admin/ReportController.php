<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\AttendanceRecord;
use App\Models\PaymentRecord;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function members(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        $members = User::query()
            ->where('role', 'member')
            ->whereBetween('created_at', [$from, $to])
            ->with('activeMembership.package')
            ->get();

        return $this->success(['from' => $from, 'to' => $to, 'total' => $members->count(), 'members' => $members]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        $records = AttendanceRecord::query()
            ->with('user:id,name')
            ->whereBetween('check_in_time', [$from, $to.' 23:59:59'])
            ->orderByDesc('check_in_time')
            ->get();

        return $this->success(['from' => $from, 'to' => $to, 'total' => $records->count(), 'records' => $records]);
    }

    public function finance(Request $request): JsonResponse
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        $query = PaymentRecord::query()
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$from, $to]);

        if ($method = $request->get('payment_method')) {
            $query->where('payment_method', $method);
        }

        $total = (float) $query->sum('amount');

        $byMethod = PaymentRecord::query()
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$from, $to])
            ->selectRaw('payment_method, sum(amount) as revenue')
            ->groupBy('payment_method')
            ->pluck('revenue', 'payment_method');

        $timeline = PaymentRecord::query()
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$from, $to])
            ->selectRaw('payment_date as date, sum(amount) as revenue')
            ->groupBy('payment_date')
            ->orderBy('payment_date')
            ->get();

        return $this->success([
            'total_revenue' => $total,
            'by_payment_method' => $byMethod,
            'timeline' => $timeline,
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

        $filename = "report-{$data['report_type']}-".now()->format('Y-m-d-His');
        $path = "exports/{$filename}";

        if ($data['format'] === 'pdf') {
            $html = view('reports.generic', [
                'title' => ucfirst($data['report_type']).' Report',
                'from' => $from,
                'to' => $to,
            ])->render();
            $pdf = Pdf::loadHTML($html);
            Storage::disk('public')->put("{$path}.pdf", $pdf->output());
            $path .= '.pdf';
        } else {
            $content = "Report,{$data['report_type']}\nFrom,{$from}\nTo,{$to}\n";
            Storage::disk('public')->put("{$path}.csv", $content);
            $path .= '.csv';
        }

        $fullPath = Storage::disk('public')->path($path);

        return $this->success([
            'download_url' => Storage::disk('public')->url($path),
            'expires_at' => now()->addHour()->toIso8601String(),
            'file_size_kb' => (int) (filesize($fullPath) / 1024),
        ]);
    }
}
