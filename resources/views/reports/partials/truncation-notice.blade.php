@if (!empty($report['detail_truncated']))
    <p class="notice">
        Menampilkan {{ $report['detail_shown'] }} dari {{ $report['detail_total'] }} record.
        Gunakan export Excel untuk data lengkap.
    </p>
@endif
