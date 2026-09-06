<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class scctcust extends Model
{
    protected $connection = "DATA_MYSQL";

    protected $table = 'scctcust';

    protected $primaryKey = 'CUSTID';

    public $timestamps = false;

    public $incrementing = false;

    public static function vaPrefix(): string
    {
        $raw = preg_replace('/\D/', '', (string) config('app.nova', '797783'));

        return $raw !== '' ? $raw : '797783';
    }

    public static function vaTotalLength(): int
    {
        return 16;
    }

    public static function showVAMTS($nis): string
    {
        return self::showVA($nis);
    }

    public static function showVAMA($nis): string
    {
        return self::showVA($nis);
    }

    public static function showVASpp($nis): string
    {
        return self::showVA($nis);
    }

    public static function showVASaku($nis): string
    {
        return self::showVA($nis);
    }

    public static function showVA($nis): string
    {
        return self::formatVA(self::vaPrefix(), $nis);
    }

    public static function formatVA(string $prefix, mixed $nis): string
    {
        $prefixDigits = preg_replace('/\D/', '', $prefix);
        $nisDigits = preg_replace('/\D/', '', (string) $nis);

        if ($prefixDigits === '' || $nisDigits === '' || $nisDigits === '-') {
            return '';
        }

        $suffixLength = max(1, self::vaTotalLength() - strlen($prefixDigits));

        return $prefixDigits . str_pad($nisDigits, $suffixLength, '0', STR_PAD_LEFT);
    }

    public static function nextCustId(): int
    {
        $max = self::query()->max('CUSTID');

        return ((int) $max) + 1;
    }

    public function rawNis(): string
    {
        foreach ($this->getAttributes() as $name => $value) {
            if (strcasecmp((string) $name, 'nocust') !== 0) {
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '' && $value !== '-') {
                return $value;
            }
        }

        foreach ($this->getAttributes() as $name => $value) {
            if (strcasecmp((string) $name, 'num2nd') !== 0) {
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '' && $value !== '-') {
                return $value;
            }
        }

        return '';
    }

    protected $guarded = [];
}
