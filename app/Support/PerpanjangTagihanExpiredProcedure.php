<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerpanjangTagihanExpiredProcedure
{
    /**
     * Memanggil stored procedure PerpanjangTagihanExpired di DATA_MYSQL.
     *
     * @return array{updated: int, exp_date: string|null}
     */
    public static function call(?string $code01 = null): array
    {
        $code01 = trim((string) ($code01 ?? ''));
        $code01Param = $code01 !== '' ? $code01 : null;

        $pdo = DB::connection('DATA_MYSQL')->getPdo();
        $stmt = $pdo->prepare('CALL PerpanjangTagihanExpired(?)');
        $stmt->execute([$code01Param]);

        $updated = 0;
        $expDate = null;

        try {
            do {
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                if (!empty($rows[0])) {
                    $row = $rows[0];
                    if (array_key_exists('updated_count', $row)) {
                        $updated = (int) $row['updated_count'];
                    }
                    if (!empty($row['new_exp_date'])) {
                        $expDate = (string) $row['new_exp_date'];
                    }
                }
            } while ($stmt->nextRowset());
        } catch (\PDOException) {
            // Result set residual.
        }
        $stmt->closeCursor();

        Log::info('perpanjang-tagihan-expired.procedure.ok', [
            'code01' => $code01Param,
            'updated' => $updated,
            'exp_date' => $expDate,
        ]);

        return [
            'updated' => $updated,
            'exp_date' => $expDate,
        ];
    }

    /**
     * Preview tanggal target (sama aturan SP: ≤20 bulan ini, >20 bulan depan).
     */
    public static function resolveAutoExpDate(?\DateTimeInterface $from = null): Carbon
    {
        $from = Carbon::parse($from ?? now())->startOfDay();

        if ($from->day <= 20) {
            return $from->copy()->day(20)->endOfDay();
        }

        return $from->copy()->addMonthNoOverflow()->day(20)->endOfDay();
    }
}
