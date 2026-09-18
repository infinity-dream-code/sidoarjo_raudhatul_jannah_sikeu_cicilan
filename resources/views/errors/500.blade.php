<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Memuat ulang</title>
</head>
<body>
<script>
    (function () {
        var key = 'sikeu_500_reload';
        try {
            if (!sessionStorage.getItem(key)) {
                sessionStorage.setItem(key, '1');
                window.location.reload();
                return;
            }
            sessionStorage.removeItem(key);
        } catch (e) {}
    })();
</script>
<div style="font-family: system-ui, sans-serif; padding: 2rem; text-align: center;">
    <h1 style="font-size: 1.25rem;">Terjadi gangguan sementara</h1>
    <p>Silakan muat ulang halaman.</p>
</div>
</body>
</html>
