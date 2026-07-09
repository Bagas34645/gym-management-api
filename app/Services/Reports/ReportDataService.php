<?php

namespace App\Services\Reports;

use App\Models\AttendanceRecord;
use App\Models\PaymentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportDataService
{
    public const PAYMENT_METHOD_LABELS = [
        'transfer' => 'Transfer',
        'cash' => 'Tunai',
        'qris' => 'QRIS',
        'midtrans' => 'Midtrans',
    ];

    public function membersReport(string $from, string $to): array
    {
        $members = User::query()
            ->where('role', 'member')
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->with('activeMembership.package')
            ->orderBy('name')
            ->get();

        $timeline = User::query()
            ->where('role', 'member')
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->selectRaw('DATE(created_at) as date, count(*) as value')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'value' => (int) $row->value,
            ]);

        return [
            'from' => $from,
            'to' => $to,
            'total' => $members->count(),
            'members' => $members,
            'timeline' => $timeline,
        ];
    }

    public function attendanceReport(string $from, string $to): array
    {
        $records = AttendanceRecord::query()
            ->with('user:id,name')
            ->whereBetween('check_in_time', [$from, $to.' 23:59:59'])
            ->orderByDesc('check_in_time')
            ->get();

        $timeline = AttendanceRecord::query()
            ->whereBetween('check_in_time', [$from, $to.' 23:59:59'])
            ->selectRaw('DATE(check_in_time) as date, count(*) as value')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'value' => (int) $row->value,
            ]);

        return [
            'from' => $from,
            'to' => $to,
            'total' => $records->count(),
            'records' => $records,
            'timeline' => $timeline,
        ];
    }

    public function financeReport(string $from, string $to): array
    {
        $payments = PaymentRecord::query()
            ->with('user:id,name')
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$from, $to])
            ->orderByDesc('payment_date')
            ->get();

        $total = (float) $payments->sum('amount');

        $byMethod = PaymentRecord::query()
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$from, $to])
            ->select('payment_method')
            ->selectRaw('SUM(amount) as revenue')
            ->groupBy('payment_method')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->payment_method => (float) $row->revenue]);

        $dateExpression = $this->paymentDateExpression();

        $timeline = PaymentRecord::query()
            ->where('status', 'completed')
            ->whereBetween('payment_date', [$from, $to])
            ->selectRaw("{$dateExpression} as date, SUM(amount) as revenue")
            ->groupByRaw($dateExpression)
            ->orderByRaw($dateExpression)
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'revenue' => (float) $row->revenue,
            ])
            ->values();

        return [
            'from' => $from,
            'to' => $to,
            'total_revenue' => $total,
            'by_payment_method' => $byMethod->all(),
            'timeline' => $timeline,
            'payments' => $payments,
        ];
    }

    public function membersExcelRows(array $report): array
    {
        $rows = [
            ['Laporan Anggota'],
            ['Periode', "{$report['from']} — {$report['to']}"],
            ['Total Anggota', $report['total']],
            [],
            ['Tanggal', 'Anggota Baru'],
        ];

        foreach ($report['timeline'] as $point) {
            $rows[] = [$point['date'], $point['value']];
        }

        $rows[] = [];
        $rows[] = ['Nama', 'Email', 'Telepon', 'Paket', 'Status Keanggotaan', 'Terdaftar'];

        foreach ($report['members'] as $member) {
            $rows[] = [
                $member->name,
                $member->email,
                $member->phone,
                $member->activeMembership?->package?->name ?? '-',
                $member->activeMembership?->status ?? '-',
                $member->created_at?->format('Y-m-d H:i') ?? '-',
            ];
        }

        return $rows;
    }

    public function attendanceExcelRows(array $report): array
    {
        $rows = [
            ['Laporan Absensi'],
            ['Periode', "{$report['from']} — {$report['to']}"],
            ['Total Record', $report['total']],
            [],
            ['Tanggal', 'Jumlah Absensi'],
        ];

        foreach ($report['timeline'] as $point) {
            $rows[] = [$point['date'], $point['value']];
        }

        $rows[] = [];
        $rows[] = ['Anggota', 'Check-in', 'Check-out', 'Lokasi', 'Status Verifikasi'];

        foreach ($report['records'] as $record) {
            $rows[] = [
                $record->user?->name ?? '-',
                $record->check_in_time?->format('Y-m-d H:i') ?? '-',
                $record->check_out_time?->format('Y-m-d H:i') ?? '-',
                $record->location ?? '-',
                $record->verification_status ?? '-',
            ];
        }

        return $rows;
    }

    public function financeExcelRows(array $report): array
    {
        $rows = [
            ['Laporan Keuangan'],
            ['Periode', "{$report['from']} — {$report['to']}"],
            ['Total Pendapatan', $report['total_revenue']],
            [],
            ['Metode Pembayaran', 'Pendapatan'],
        ];

        foreach ($report['by_payment_method'] as $method => $amount) {
            $rows[] = [$this->paymentMethodLabel((string) $method), $amount];
        }

        $rows[] = [];
        $rows[] = ['Tanggal', 'Pendapatan Harian'];

        foreach ($report['timeline'] as $point) {
            $rows[] = [$point['date'], $point['revenue']];
        }

        $rows[] = [];
        $rows[] = ['Tanggal', 'Anggota', 'Jumlah', 'Metode', 'Referensi', 'Status'];

        foreach ($report['payments'] as $payment) {
            $rows[] = [
                $payment->payment_date?->format('Y-m-d') ?? '-',
                $payment->user?->name ?? '-',
                (float) $payment->amount,
                $this->paymentMethodLabel((string) $payment->payment_method),
                $payment->reference_number ?? '-',
                $payment->status ?? '-',
            ];
        }

        return $rows;
    }

    public function paymentMethodLabel(string $method): string
    {
        return self::PAYMENT_METHOD_LABELS[$method] ?? $method;
    }

    public function formatCurrency(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function paymentDateExpression(): string
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'pgsql'
            ? '(payment_date)::date'
            : 'DATE(payment_date)';
    }
}
