<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Laporan')</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 4px;
        }

        .meta {
            color: #555;
            margin-bottom: 16px;
        }

        .summary {
            margin-bottom: 12px;
        }

        .section-title {
            font-size: 13px;
            margin: 16px 0 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            text-align: left;
        }

        th {
            background: #f3f3f3;
        }

        .text-right {
            text-align: right;
        }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
