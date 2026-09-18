<?php

namespace App\Support;

use App\Models\mst_kelas;
use App\Models\mst_sekolah;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Import siswa: Excel UNIT = mst_sekolah.DESC01 + mst_kelas.unit
 * Excel KELAS = mst_kelas.jenjang
 * Excel KELOMPOK = mst_kelas.kelas
 * mst_kelas.kelompok = mst_sekolah.CODE01 (increment, contoh 104 → 105)
 */
class EnsureImportSchoolClass
{
    /** @var array<string, mst_sekolah> */
    private static array $sekolahMemo = [];

    /** @var array<string, mst_kelas> */
    private static array $kelasMemo = [];

    public static function resetMemo(): void
    {
        self::$sekolahMemo = [];
        self::$kelasMemo = [];
    }

    /**
     * @return array{0: ?mst_sekolah, 1: ?mst_kelas}
     */
    public static function resolve(?string $unit, mixed $jenjang, ?string $kelompok): array
    {
        $unit = trim((string) $unit);
        $jenjangText = trim((string) $jenjang);
        $kelompok = trim((string) $kelompok);

        if ($unit === '' || $jenjangText === '' || $kelompok === '') {
            return [null, null];
        }

        $sekolah = self::ensureSekolah($unit);
        $kelas = $sekolah ? self::ensureKelas($unit, $jenjangText, $kelompok, $sekolah) : null;

        return [$sekolah, $kelas];
    }

    public static function ensureSekolah(string $unit): ?mst_sekolah
    {
        $unit = trim($unit);
        if ($unit === '') {
            return null;
        }

        $memoKey = strtoupper($unit);
        if (isset(self::$sekolahMemo[$memoKey])) {
            return self::$sekolahMemo[$memoKey];
        }

        $existing = mst_sekolah::query()
            ->where(function ($query) use ($unit) {
                $query->whereRaw('UPPER(TRIM(DESC01)) = ?', [strtoupper($unit)])
                    ->orWhereRaw('CAST(CODE01 AS CHAR) = ?', [$unit]);
            })
            ->first();

        if ($existing) {
            self::$sekolahMemo[$memoKey] = $existing;

            return $existing;
        }

        $kelasUnit = mst_kelas::query()
            ->whereRaw('UPPER(TRIM(unit)) = ?', [strtoupper($unit)])
            ->whereNotNull('kelompok')
            ->orderBy('id')
            ->first();
        if ($kelasUnit) {
            $fromKelas = mst_sekolah::query()
                ->whereRaw('CAST(CODE01 AS CHAR) = ?', [trim((string) $kelasUnit->kelompok)])
                ->first();
            if ($fromKelas) {
                self::$sekolahMemo[$memoKey] = $fromKelas;

                return $fromKelas;
            }
        }

        $code = self::nextSekolahCode();
        $idColumn = self::sekolahIdColumn();

        $sekolah = new mst_sekolah();
        $sekolah->CODE01 = $code;
        $sekolah->DESC01 = $unit;
        if ($idColumn) {
            $sekolah->setKeyName($idColumn);
            $sekolah->{$idColumn} = (int) (mst_sekolah::query()->max($idColumn) ?? 0) + 1;
        }
        $sekolah->save();
        $sekolah = $sekolah->fresh() ?? $sekolah;

        Log::info('import_siswa.auto_create_sekolah', [
            'CODE01' => $code,
            'DESC01' => $unit,
            'id' => $sekolah->getKey(),
        ]);

        self::$sekolahMemo[$memoKey] = $sekolah;

        return $sekolah;
    }

    public static function ensureKelas(
        string $unit,
        string $jenjang,
        string $kelompok,
        mst_sekolah $sekolah,
    ): ?mst_kelas {
        $jenjangValue = is_numeric($jenjang) ? (string) (int) $jenjang : trim($jenjang);
        $schoolCode = trim((string) $sekolah->CODE01);
        $memoKey = strtoupper($unit.'|'.$jenjangValue.'|'.$kelompok.'|'.$schoolCode);

        if (isset(self::$kelasMemo[$memoKey])) {
            return self::$kelasMemo[$memoKey];
        }

        $existing = mst_kelas::query()
            ->whereRaw('UPPER(TRIM(unit)) = ?', [strtoupper($unit)])
            ->whereRaw('UPPER(TRIM(jenjang)) = ?', [strtoupper($jenjangValue)])
            ->whereRaw('UPPER(TRIM(kelas)) = ?', [strtoupper($kelompok)])
            ->where(function ($query) use ($schoolCode) {
                $query->where('kelompok', $schoolCode)
                    ->orWhereRaw('CAST(kelompok AS CHAR) = ?', [$schoolCode]);
            })
            ->first();

        if (!$existing) {
            $existing = mst_kelas::findForImport($unit, $jenjangValue, $kelompok);
        }

        if ($existing) {
            self::$kelasMemo[$memoKey] = $existing;

            return $existing;
        }

        $nextId = (int) (mst_kelas::query()->max('id') ?? 0) + 1;

        $kelas = new mst_kelas();
        $kelas->id = $nextId;
        $kelas->unit = $unit;
        $kelas->jenjang = $jenjangValue;
        $kelas->kelas = $kelompok;
        $kelas->kelompok = $schoolCode;
        $kelas->save();
        $kelas = $kelas->fresh() ?? $kelas;

        Log::info('import_siswa.auto_create_kelas', [
            'id' => $kelas->id,
            'unit' => $unit,
            'jenjang' => $jenjangValue,
            'kelas' => $kelompok,
            'kelompok' => $schoolCode,
        ]);

        self::$kelasMemo[$memoKey] = $kelas;

        return $kelas;
    }

    private static function sekolahIdColumn(): ?string
    {
        static $column = false;
        if ($column !== false) {
            return $column;
        }

        $schema = Schema::connection('DATA_MYSQL');
        if ($schema->hasColumn('mst_sekolah', 'id')) {
            $column = 'id';
        } elseif ($schema->hasColumn('mst_sekolah', 'urut')) {
            $column = 'urut';
        } else {
            $column = null;
        }

        return $column;
    }

    private static function nextSekolahCode(): string
    {
        $max = 100;
        foreach (mst_sekolah::query()->pluck('CODE01') as $code) {
            $digits = trim((string) $code);
            if ($digits !== '' && ctype_digit($digits)) {
                $max = max($max, (int) $digits);
            }
        }

        $next = $max + 1;
        while (mst_sekolah::query()->where('CODE01', (string) $next)->exists()) {
            $next++;
        }

        return (string) $next;
    }
}
