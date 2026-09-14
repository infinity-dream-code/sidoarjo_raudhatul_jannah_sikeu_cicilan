<?php

namespace App\Support;

use App\Models\mst_kelas;
use App\Models\mst_sekolah;
use Illuminate\Support\Facades\Log;

/**
 * Saat import siswa, unit/kelas Excel yang belum ada di master
 * dibuatkan otomatis: mst_sekolah (jika perlu) + mst_kelas.
 */
class EnsureImportSchoolClass
{
    public static function resolve(
        ?string $unit,
        mixed $jenjang,
        ?string $kelompok,
        ?mst_sekolah $preferredSekolah = null,
    ): array {
        $unit = trim((string) $unit);
        $jenjangText = trim((string) $jenjang);
        $kelompok = trim((string) $kelompok);

        if ($unit === '' || $jenjangText === '' || $kelompok === '') {
            return [null, null];
        }

        $sekolah = self::ensureSekolah($unit, $preferredSekolah);
        $kelas = self::ensureKelas($unit, $jenjangText, $kelompok, $sekolah);

        return [$sekolah, $kelas];
    }

    public static function ensureSekolah(?string $unit, ?mst_sekolah $preferred = null): ?mst_sekolah
    {
        $unit = trim((string) $unit);
        $scopedCode = SchoolScope::codeFromUser();
        if ($scopedCode) {
            $scoped = mst_sekolah::query()->where('CODE01', $scopedCode)->first();
            if ($scoped) {
                return $scoped;
            }
        }

        $fromUnit = $unit !== '' ? self::findSekolahByUnit($unit) : null;
        if (!$fromUnit && $unit !== '') {
            $fromUnit = self::createSekolah($unit);
        }

        return $preferred ?? $fromUnit;
    }

    public static function ensureKelas(
        string $unit,
        string $jenjang,
        string $kelompok,
        ?mst_sekolah $sekolah,
    ): ?mst_kelas {
        $existing = mst_kelas::findForImport($unit, $jenjang, $kelompok);
        if ($existing) {
            return $existing;
        }

        if (!$sekolah) {
            return null;
        }

        $jenjangValue = is_numeric($jenjang) ? (string) (int) $jenjang : $jenjang;
        $schoolCode = trim((string) $sekolah->CODE01);

        $duplicate = mst_kelas::query()
            ->whereRaw('UPPER(TRIM(unit)) = ?', [strtoupper($unit)])
            ->whereRaw('UPPER(TRIM(jenjang)) = ?', [strtoupper($jenjangValue)])
            ->whereRaw('UPPER(TRIM(kelas)) = ?', [strtoupper($kelompok)])
            ->when($schoolCode !== '', fn ($q) => $q->where('kelompok', $schoolCode))
            ->first();
        if ($duplicate) {
            return $duplicate;
        }

        $nextId = (int) (mst_kelas::query()->max('id') ?? 0) + 1;

        $kelas = new mst_kelas();
        $kelas->id = $nextId;
        $kelas->unit = $unit;
        $kelas->jenjang = $jenjangValue;
        $kelas->kelas = $kelompok;
        $kelas->kelompok = $schoolCode;
        $kelas->save();

        Log::info('import_siswa.auto_create_kelas', [
            'id' => $kelas->id,
            'unit' => $unit,
            'jenjang' => $jenjangValue,
            'kelas' => $kelompok,
            'sekolah' => $schoolCode,
        ]);

        return $kelas->fresh() ?? $kelas;
    }

    private static function createSekolah(string $unit): mst_sekolah
    {
        $code = self::nextSekolahCode($unit);
        $urut = (int) (mst_sekolah::query()->max('urut') ?? 0) + 1;

        $sekolah = new mst_sekolah();
        $sekolah->urut = $urut;
        $sekolah->CODE01 = $code;
        $sekolah->DESC01 = $unit;
        $sekolah->save();

        Log::info('import_siswa.auto_create_sekolah', [
            'CODE01' => $code,
            'DESC01' => $unit,
            'urut' => $urut,
        ]);

        return $sekolah->fresh() ?? $sekolah;
    }

    private static function findSekolahByUnit(string $unit): ?mst_sekolah
    {
        return mst_sekolah::query()
            ->where(function ($query) use ($unit) {
                $query->where('CODE01', $unit)
                    ->orWhereRaw('UPPER(TRIM(DESC01)) = ?', [strtoupper($unit)])
                    ->orWhere('DESC01', 'like', '%' . $unit . '%');
            })
            ->first();
    }

    private static function nextSekolahCode(string $unitName): string
    {
        $slug = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $unitName));
        if ($slug !== '' && strlen($slug) <= 20 && !mst_sekolah::query()->where('CODE01', $slug)->exists()) {
            return $slug;
        }

        $numericMax = mst_sekolah::query()
            ->pluck('CODE01')
            ->filter(fn ($code) => ctype_digit(trim((string) $code)))
            ->map(fn ($code) => (int) $code)
            ->max();

        if ($numericMax) {
            $next = (string) ($numericMax + 1);
            if (!mst_sekolah::query()->where('CODE01', $next)->exists()) {
                return $next;
            }
        }

        $n = 1;
        do {
            $code = 'U' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            $n++;
        } while (mst_sekolah::query()->where('CODE01', $code)->exists());

        return $code;
    }
}
