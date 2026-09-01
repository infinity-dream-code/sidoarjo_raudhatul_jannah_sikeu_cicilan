<?php

namespace App\Support;

use App\Models\WaTemplate;
use Throwable;

class WhatsappTagihan
{
    public const KODE_TAGIHAN = "tagihan";

    public static function placeholders(): array
    {
        return [
            "{nama}" => "Nama siswa",
            "{nis}" => "NIS",
            "{no_daftar}" => "No pendaftaran",
            "{kelas}" => "Kelas",
            "{kelompok}" => "Kelompok",
            "{unit}" => "Unit",
            "{nama_tagihan}" => "Nama tagihan",
            "{periode}" => "Periode",
            "{jumlah_tagihan}" => "Jumlah tagihan",
            "{terbayar}" => "Jumlah terbayar",
            "{sisa_tagihan}" => "Sisa tagihan",
            "{no_va}" => "Nomor VA",
        ];
    }

    public static function defaultTemplate(): string
    {
        return "Assalamu'alaikum wr. wb.\n\n"
            . "Yth. Wali siswa *{nama}*\n"
            . "NIS: {nis}\n"
            . "Kelas: {kelas}\n\n"
            . "Kami informasikan tagihan:\n"
            . "*{nama_tagihan}*\n"
            . "Periode: {periode}\n\n"
            . "Jumlah tagihan: Rp {jumlah_tagihan}\n"
            . "Sudah dibayar: Rp {terbayar}\n"
            . "Sisa tagihan: Rp {sisa_tagihan}\n\n"
            . "Mohon dapat segera diselesaikan. Terima kasih.";
    }

    public static function activeTemplate(?string $kode = self::KODE_TAGIHAN): string
    {
        try {
            $query = WaTemplate::query()->where("is_active", 1);

            if ($kode) {
                $byKode = (clone $query)->where("kode", $kode)->value("template");
                if (is_string($byKode) && trim($byKode) !== "") {
                    return $byKode;
                }
            }

            $any = $query->orderBy("id")->value("template");
            if (is_string($any) && trim($any) !== "") {
                return $any;
            }
        } catch (Throwable) {
            // Tabel belum ada / belum di-create di Navicat.
        }

        return self::defaultTemplate();
    }

    public static function formatPhone(?string $raw): ?string
    {
        $digits = preg_replace("/\D+/", "", (string) $raw);
        if ($digits === null || $digits === "") {
            return null;
        }

        if (str_starts_with($digits, "0")) {
            $digits = "62" . substr($digits, 1);
        } elseif (str_starts_with($digits, "8")) {
            $digits = "62" . $digits;
        }

        if (!str_starts_with($digits, "62") || strlen($digits) < 10) {
            return null;
        }

        return $digits;
    }

    public static function formatRupiah(mixed $value): string
    {
        return number_format((float) $value, 0, ",", ".");
    }

    public static function applyTemplate(string $template, array $vars): string
    {
        $repl = [];
        foreach ($vars as $key => $value) {
            $repl["{" . $key . "}"] = (string) ($value ?? "-");
        }

        return strtr($template, $repl);
    }

    public static function waMeUrl(?string $phone, string $message): ?string
    {
        $formatted = self::formatPhone($phone);
        if (!$formatted) {
            return null;
        }

        return "https://wa.me/" . $formatted . "?text=" . rawurlencode($message);
    }
}
