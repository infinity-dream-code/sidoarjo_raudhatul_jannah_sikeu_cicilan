<?php

namespace App\Imports\Keuangan\TagihanSiswa;

use App\Models\scctcust;
use App\Support\ExcelImportSheet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ImportTagihanExcel implements WithMultipleSheets, ToCollection, WithHeadingRow
{
    public function __construct(
        private string $cacheKey = 'import_tagihan_excel',
        private int $sheetIndex = 0,
    ) {
    }

    public function sheets(): array
    {
        return [
            $this->sheetIndex => $this,
        ];
    }

    public function collection(Collection $collection): void
    {
        $processedData = [];

        foreach ($collection as $row) {
            if ($row->filter()->isEmpty()) {
                continue;
            }

            $rowData = $row->toArray();
            $nis = ExcelImportSheet::normalizeId($rowData['nis'] ?? null);
            if ($nis === '' || strcasecmp($nis, 'nis') === 0) {
                continue;
            }
            if (ExcelImportSheet::isTemplateSampleNis($nis)) {
                continue;
            }

            $rowData['nis'] = $nis;
            $rowData['status'] = 1;
            $status_ket = null;

            $checkData = scctcust::where('NOCUST', $nis)->first();
            if (!$checkData) {
                $rowData['status'] = 0;
                $status_ket = "NIS {$nis} tidak ditemukan";
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

    private function appendKet(?string $current, string $message): string
    {
        if ($current === null || $current === '') {
            return $message;
        }

        return $current . ', ' . $message;
    }
}
