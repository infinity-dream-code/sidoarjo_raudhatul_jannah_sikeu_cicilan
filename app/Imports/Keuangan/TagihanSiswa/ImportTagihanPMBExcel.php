<?php

namespace App\Imports\Keuangan\TagihanSiswa;

use App\Models\scctcust;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ImportTagihanPMBExcel implements ToCollection, WithHeadingRow
{
    public function __construct(private string $cacheKey = 'import_tagihan_pmb_excel')
    {
    }

    public function collection(Collection $collection): void
    {
        $processedData = Cache::get($this->cacheKey, []);
        if (!is_array($processedData)) {
            $processedData = [];
        }

        foreach ($collection as $row) {
            if ($row->filter()->isEmpty()) {
                continue;
            }

            $rowData = $row->toArray();
            $nodaftar = $this->normalizeCode($rowData['nodaftar'] ?? null);
            $rowData['status'] = 1;
            $status_ket = null;

            if ($nodaftar === '') {
                $rowData['status'] = 0;
                $status_ket = 'Nomor pendaftaran tidak boleh kosong';
            } else {
                $rowData['nodaftar'] = $nodaftar;
                $checkData = scctcust::where('NUM2ND', $nodaftar)->first();
                if (!$checkData) {
                    $rowData['status'] = 0;
                    $status_ket = "Nomor Pendaftaran : {$nodaftar} tidak ditemukan";
                }
            }

            $nominal = $rowData['nominal'] ?? null;
            if ($nominal === null || $nominal === '') {
                $rowData['status'] = 0;
                $status_ket = $this->appendKet($status_ket, 'Nominal tidak boleh kosong');
            }

            $rowData['keterangan'] = $status_ket;
            $processedData[] = $rowData;
        }

        Cache::put($this->cacheKey, $processedData, now()->addMinutes(60));
    }

    public function headingRow(): int
    {
        return 1;
    }

    private function normalizeCode(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value) && floor($value) == $value) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    private function appendKet(?string $current, string $message): string
    {
        if ($current === null || $current === '') {
            return $message;
        }

        return $current . ', ' . $message;
    }
}
