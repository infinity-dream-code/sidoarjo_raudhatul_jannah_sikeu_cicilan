<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InputTagihanProcedure
{
    /**
     * Memanggil stored procedure InputTagihan di DATA_MYSQL.
     *
     * InputTagihan(
     *   p_NOCUST, p_NOMINAL, p_NMTagihan, p_BILLPERIOD, p_BTA, p_isNYICIL
     * )
     *
     * ExpDate diisi otomatis oleh procedure (tgl 20).
     */
    public static function call(
        string $nocust,
        int $nominal,
        string $nmTagihan,
        string $billPeriod,
        ?string $bta = null,
        string|int $isNyicil = '0',
    ): void {
        $nocust = trim($nocust);
        $nmTagihan = trim($nmTagihan);
        $billPeriod = preg_replace('/\D+/', '', (string) $billPeriod) ?? '';
        $bta = trim((string) ($bta ?? $billPeriod));
        $isNyicil = (string) ((int) $isNyicil);

        if ($nocust === '' || $nmTagihan === '' || strlen($billPeriod) !== 6 || $nominal < 0) {
            throw new \InvalidArgumentException('Parameter InputTagihan tidak valid.');
        }

        // Procedure membatasi p_NMTagihan VARCHAR(30)
        if (mb_strlen($nmTagihan) > 30) {
            $nmTagihan = mb_substr($nmTagihan, 0, 30);
        }

        $pdo = DB::connection('DATA_MYSQL')->getPdo();
        $stmt = $pdo->prepare('CALL InputTagihan(?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $nocust,
            $nominal,
            $nmTagihan,
            $billPeriod,
            $bta,
            $isNyicil,
        ]);

        try {
            do {
                $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } while ($stmt->nextRowset());
        } catch (\PDOException) {
            // Result set residual — CALL sudah dieksekusi.
        }
        $stmt->closeCursor();

        Log::info('input-tagihan.procedure.ok', [
            'nocust' => $nocust,
            'nominal' => $nominal,
            'tagihan' => $nmTagihan,
            'periode' => $billPeriod,
            'is_nyicil' => $isNyicil,
        ]);
    }

    /**
     * Samakan dengan InputTagihan:
     * hari 1-26 -> tgl 20 bulan ini; hari 27-31 -> tgl 20 bulan depan.
     */
    public static function resolveAutoExpDate(?\DateTimeInterface $from = null): \Illuminate\Support\Carbon
    {
        $from = \Illuminate\Support\Carbon::parse($from ?? now())->startOfDay();

        if ($from->day >= 27) {
            return $from->copy()->addMonthNoOverflow()->day(20)->endOfDay();
        }

        return $from->copy()->day(20)->endOfDay();
    }
}
