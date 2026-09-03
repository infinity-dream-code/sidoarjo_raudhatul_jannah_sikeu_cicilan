<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class scctva extends Model
{
    protected $connection = "DATA_MYSQL";

    protected $table = "scctva";

    protected $primaryKey = "ID";

    public $timestamps = false;

    protected $guarded = [];

    /**
     * Nonaktifkan VA aktif (STATUS=0) untuk siswa ini, berdasarkan NOCUST dan/atau CUSTID.
     */
    public static function deactivateForStudent(?string $nocust = null, mixed $custId = null): int
    {
        $nocust = trim((string) $nocust);
        $custId = ($custId === null || $custId === '') ? null : $custId;

        if (($nocust === '' || $nocust === '-') && $custId === null) {
            return 0;
        }

        $updated = static::query()
            ->where(function ($query) use ($nocust, $custId) {
                if ($nocust !== '' && $nocust !== '-') {
                    $query->where('NOCUST', $nocust);
                }
                if ($custId !== null) {
                    $query->orWhere('CUSTID', $custId);
                }
            })
            ->where('STATUS', '!=', 0)
            ->update(['STATUS' => 0]);

        if ($updated > 0) {
            Log::info('scctva.deactivated', [
                'nocust' => $nocust !== '' ? $nocust : null,
                'custid' => $custId,
                'rows' => $updated,
            ]);
        }

        return $updated;
    }
}
