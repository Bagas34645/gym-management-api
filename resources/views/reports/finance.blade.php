@extends('reports.layout')

@section('content')
    <h1>Laporan Keuangan</h1>
    <p class="meta">
        Periode: {{ $report['from'] }} — {{ $report['to'] }}<br>
        Dibuat: {{ now()->format('Y-m-d H:i') }}
    </p>

    <p class="summary">
        <strong>Total Pendapatan:</strong> {{ $formatter->formatCurrency($report['total_revenue']) }}
    </p>

    <h2 class="section-title">Metode Pembayaran</h2>
    <table>
        <thead>
            <tr>
                <th>Metode</th>
                <th class="text-right">Pendapatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['by_payment_method'] as $method => $amount)
                <tr>
                    <td>{{ $formatter->paymentMethodLabel((string) $method) }}</td>
                    <td class="text-right">{{ $formatter->formatCurrency((float) $amount) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2 class="section-title">Tren Pendapatan Harian</h2>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th class="text-right">Pendapatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['timeline'] as $point)
                <tr>
                    <td>{{ $point['date'] }}</td>
                    <td class="text-right">{{ $formatter->formatCurrency((float) $point['revenue']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2 class="section-title">Detail Pembayaran</h2>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Anggota</th>
                <th class="text-right">Jumlah</th>
                <th>Metode</th>
                <th>Referensi</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['payments'] as $payment)
                <tr>
                    <td>{{ $payment->payment_date?->format('Y-m-d') ?? '-' }}</td>
                    <td>{{ $payment->user?->name ?? '-' }}</td>
                    <td class="text-right">{{ $formatter->formatCurrency((float) $payment->amount) }}</td>
                    <td>{{ $formatter->paymentMethodLabel((string) $payment->payment_method) }}</td>
                    <td>{{ $payment->reference_number ?? '-' }}</td>
                    <td>{{ $payment->status ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
