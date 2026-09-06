<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SmUserCredentialLink extends Model
{
    protected $connection = "DATA_MYSQL";

    protected $table = "sm_user_credential_link";

    protected $fillable = [
        "token",
        "no_cust",
        "custid",
        "tahun_akademik",
        "expires_at",
        "used_at",
        "created_by",
    ];

    protected $casts = [
        "expires_at" => "datetime",
        "used_at" => "datetime",
    ];

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function normalizeNoCust(mixed $raw): string
    {
        $digits = preg_replace('/\D/', '', (string) $raw);
        if ($digits === '') {
            return '';
        }

        $prefix = preg_replace('/\D/', '', (string) config('app.nova', '797783'));
        if ($prefix !== '' && str_starts_with($digits, $prefix) && strlen($digits) > strlen($prefix)) {
            return substr($digits, strlen($prefix));
        }

        return $digits;
    }

    public static function webTagihanBaseUrl(): string
    {
        return rtrim((string) config('app.url_web_tagihan'), '/');
    }

    public static function webTagihanUrl(string $token): string
    {
        return self::webTagihanBaseUrl() . '/' . $token;
    }

    public function fullUrl(): string
    {
        return self::webTagihanUrl((string) $this->token);
    }

    public static function loginMessage(string $url, string $namaSiswa = '', string $nis = ''): string
    {
        $nama = trim($namaSiswa) !== '' ? trim($namaSiswa) : 'siswa';
        $nisLine = trim($nis) !== '' ? "NIS: " . trim($nis) . "\n" : '';

        return "Assalamu'alaikum wr. wb.\n\n"
            . "Yth. Orang Tua/Wali siswa *{$nama}*\n"
            . $nisLine
            . "\nBerikut link untuk cek & bayar tagihan secara otomatis (tanpa password):\n"
            . "{$url}\n\n"
            . "Catatan:\n"
            . "- Link berlaku 24 jam\n"
            . "- Hanya dapat digunakan sekali\n\n"
            . "Terima kasih.";
    }

    public static function tableCellHtml(
        ?self $link,
        int $custId,
        bool $hasNis,
        ?string $noWa = null,
        string $namaSiswa = '',
        string $nis = '',
    ): string {
        if (!$hasNis) {
            return '<span class="text-muted small">Tidak ada NIS</span>';
        }

        if ($link) {
            $safeUrl = e($link->fullUrl());
            $displayUrl = e(self::webTagihanBaseUrl() . '/' . substr((string) $link->token, 0, 12) . '…');
            $custIdAttr = e((string) $custId);
            $message = self::loginMessage($link->fullUrl(), $namaSiswa, $nis !== '' ? $nis : (string) $link->no_cust);
            $waUrl = \App\Support\WhatsappTagihan::waMeUrl($noWa, $message);
            $safeWaUrl = $waUrl ? e($waUrl) : '';
            $safePhone = e(trim((string) $noWa));

            $waButton = $waUrl
                ? '<a href="' . $safeWaUrl . '" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-success btn-kirim-wa-link-tagihan" title="Kirim ke WhatsApp ortu">'
                    . '<i class="ri-whatsapp-line me-1"></i>Kirim WA</a>'
                : '<button type="button" class="btn btn-sm btn-outline-success btn-kirim-wa-link-tagihan-disabled" data-no-wa="' . $safePhone . '" title="No WA ortu belum diisi">'
                    . '<i class="ri-whatsapp-line me-1"></i>Kirim WA</button>';

            return '<div class="link-tagihan-box text-start">'
                . '<div class="link-tagihan-url text-truncate" title="' . $safeUrl . '">'
                . '<i class="ri-links-line me-1 text-primary"></i>'
                . '<span class="link-tagihan-text">' . $displayUrl . '</span>'
                . '</div>'
                . '<div class="d-flex flex-wrap gap-1 mt-1">'
                . '<button type="button" class="btn btn-sm btn-primary btn-copy-link-tagihan" data-url="' . $safeUrl . '" title="Salin link">'
                . '<i class="ri-file-copy-line me-1"></i>Salin</button>'
                . $waButton
                . '<button type="button" class="btn btn-sm btn-warning btn-perbarui-link-tagihan" data-id="' . $custIdAttr . '" data-url="' . $safeUrl . '" title="Perbarui link">'
                . '<i class="ri-refresh-line me-1"></i>Perbarui</button>'
                . '</div>'
                . '</div>';
        }

        return '<button type="button" class="btn btn-sm btn-success btn-buat-link-tagihan" data-id="' . e((string) $custId) . '">'
            . '<i class="ri-link me-1"></i>Buat Link</button>';
    }

    public function scopeActive($query)
    {
        return $query
            ->whereNull('used_at')
            ->where('expires_at', '>', Carbon::now());
    }
}
