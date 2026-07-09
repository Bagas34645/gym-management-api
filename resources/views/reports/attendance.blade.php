@extends('reports.layout')

@section('content')
    <h1>Laporan Absensi</h1>
    <p class="meta">
        Periode: {{ $report['from'] }} — {{ $report['to'] }}<br>
        Dibuat: {{ now()->format('Y-m-d H:i') }}
    </p>

    <p class="summary"><strong>Total Record:</strong> {{ $report['total'] }}</p>

    <h2 class="section-title">Tren Absensi Harian</h2>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th class="text-right">Jumlah Absensi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['timeline'] as $point)
                <tr>
                    <td>{{ $point['date'] }}</td>
                    <td class="text-right">{{ $point['value'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2 class="section-title">Detail Absensi</h2>
    <table>
        <thead>
            <tr>
                <th>Anggota</th>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Lokasi</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['records'] as $record)
                <tr>
                    <td>{{ $record->user?->name ?? '-' }}</td>
                    <td>{{ $record->check_in_time?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td>{{ $record->check_out_time?->format('Y-m-d H:i') ?? '-' }}</td>
                    <td>{{ $record->location ?? '-' }}</td>
                    <td>{{ $record->verification_status ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
