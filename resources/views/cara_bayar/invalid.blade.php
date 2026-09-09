<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Link tidak valid</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #f3f4f6;
            color: #1f2937;
        }
        .box {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 28px 24px;
            max-width: 420px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="box">
    <h1 style="margin-top:0;font-size:1.2rem;">Link tidak valid</h1>
    <p style="color:#6b7280;">{{ $message ?? 'Halaman cara bayar tidak ditemukan.' }}</p>
</div>
</body>
</html>
