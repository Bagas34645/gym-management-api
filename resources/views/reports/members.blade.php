@extends('reports.layout')

@section('content')
    <h1>Laporan Anggota</h1>
    <p class="meta">
        Periode: {{ $report['from'] }} — {{ $report['to'] }}<br>
        Dibuat: {{ now()->format('Y-m-d H:i') }}
    </p>

    <p class="summary"><strong>Total Anggota:</strong> {{ $report['total'] }}</p>

    <h2 class="section-title">Tren Pendaftaran Harian</h2>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th class="text-right">Anggota Baru</th>
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

    <h2 class="section-title">Daftar Anggota</h2>
    <table>
        <thead>
            <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Telepon</th>
                <th>Paket</th>
                <th>Status</th>
                <th>Terdaftar</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['members'] as $member)
                <tr>
                    <td>{{ $member->name }}</td>
                    <td>{{ $member->email }}</td>
                    <td>{{ $member->phone }}</td>
                    <td>{{ $member->activeMembership?->package?->name ?? '-' }}</td>
                    <td>{{ $member->activeMembership?->status ?? '-' }}</td>
                    <td>{{ $member->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
