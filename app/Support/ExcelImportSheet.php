<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelImportSheet
{
    /**
     * Pilih 1 sheet untuk diimpor: yang paling banyak baris data asli,
     * bukan sheet contoh template. Tie-break: sheet aktif, lalu sheet terakhir.
     *
     * @return array{index: int, headings: array<int, string>, usable: int, name: string}
     */
    public static function pickBest(
        string $path,
        array $requiredHeadings,
        string $idHeading,
        ?callable $skipId = null,
    ): array {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        try {
            $activeIndex = $spreadsheet->getActiveSheetIndex();
            $required = array_map(
                static fn ($heading) => strtolower(trim((string) $heading)),
                $requiredHeadings
            );
            $idHeading = strtolower(trim($idHeading));

            $candidates = [];
            foreach ($spreadsheet->getAllSheets() as $index => $sheet) {
                $rows = $sheet->toArray(null, true, true, false);
                $headings = [];
                if (isset($rows[0]) && is_array($rows[0])) {
                    $headings = array_map(
                        static fn ($heading) => strtolower(trim((string) $heading)),
                        $rows[0]
                    );
                }

                $missing = array_values(array_diff($required, $headings));
                $idCol = array_search($idHeading, $headings, true);
                $usable = 0;

                if ($missing === [] && $idCol !== false) {
                    foreach (array_slice($rows, 1) as $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        $id = self::normalizeId($row[$idCol] ?? null);
                        if ($id === '' || strcasecmp($id, $idHeading) === 0) {
                            continue;
                        }
                        if ($skipId && $skipId($id)) {
                            continue;
                        }

                        $usable++;
                    }
                }

                $candidates[] = [
                    'index' => $index,
                    'name' => $sheet->getTitle(),
                    'headings' => $headings,
                    'missing' => $missing,
                    'usable' => $usable,
                    'isActive' => $index === $activeIndex,
                ];
            }

            $valid = array_values(array_filter(
                $candidates,
                static fn (array $candidate) => $candidate['missing'] === []
            ));

            if ($valid === []) {
                throw new \RuntimeException(
                    'Tidak dapat membaca judul kolom dari file. Pastikan file memiliki header yang sesuai.'
                );
            }

            usort($valid, static function (array $a, array $b) {
                if ($a['usable'] !== $b['usable']) {
                    return $b['usable'] <=> $a['usable'];
                }
                if ($a['isActive'] !== $b['isActive']) {
                    return $a['isActive'] ? -1 : 1;
                }

                return $b['index'] <=> $a['index'];
            });

            return $valid[0];
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    public static function isTemplateSampleNis(string $nis): bool
    {
        return (bool) preg_match('/^99999999\d+$/', $nis);
    }

    public static function normalizeId(mixed $value): string
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
}
