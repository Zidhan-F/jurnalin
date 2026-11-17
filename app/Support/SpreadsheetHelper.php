<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

final class SpreadsheetHelper
{
    /**
     * Membaca berkas .xlsx atau .csv menjadi array baris asosiatif berdasarkan header baris pertama.
     *
     * @return list<array<string, string>>
     */
    public static function readAssociativeRows(string $filePath, ?string $extension = null): array
    {
        $ext = strtolower($extension ?? pathinfo($filePath, PATHINFO_EXTENSION));

        $rawRows = match ($ext) {
            'xlsx' => self::readXlsxRows($filePath),
            'csv', 'txt' => self::readCsvRows($filePath),
            default => throw new RuntimeException("Format berkas .{$ext} tidak didukung. Gunakan .xlsx atau .csv."),
        };

        if (count($rawRows) < 2) {
            return [];
        }

        $headers = array_map(
            fn (?string $h): string => strtolower(trim((string) preg_replace('/[^a-zA-Z0-9_]+/', '_', (string) $h), '_')),
            $rawRows[0]
        );

        $rows = [];
        for ($i = 1; $i < count($rawRows); $i++) {
            $values = $rawRows[$i];
            if (count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $colIndex => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = trim((string) ($values[$colIndex] ?? ''));
            }
            $rows[] = $assoc;
        }

        return $rows;
    }

    /**
     * Membuat berkas .xlsx valid menggunakan ZipArchive bawaan PHP.
     *
     * @param  list<list<string|int|float>>  $rows
     */
    public static function writeXlsx(string $outputPath, array $rows): void
    {
        $zip = new ZipArchive();
        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat arsip XLSX.');
        }

        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML);

        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);

        $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Data" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML);

        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML);

        $sheetRowsXml = '';
        foreach ($rows as $rowIndex => $row) {
            $rNum = $rowIndex + 1;
            $cellsXml = '';
            foreach ($row as $colIndex => $value) {
                $colLetter = self::columnLetter($colIndex);
                $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cellsXml .= "<c r=\"{$colLetter}{$rNum}\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
            }
            $sheetRowsXml .= "<row r=\"{$rNum}\">{$cellsXml}</row>";
        }

        $worksheetXml = <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>{$sheetRowsXml}</sheetData>
</worksheet>
XML;

        $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
        $zip->close();
    }

    /**
     * @return list<list<string>>
     */
    private static function readCsvRows(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Tidak dapat membuka berkas CSV.');
        }

        $firstLine = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = str_contains($firstLine, ';') && ! str_contains($firstLine, ',') ? ';' : ',';

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($data === [null]) {
                continue;
            }
            if (count($rows) === 0 && isset($data[0])) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]) ?? '';
            }
            $rows[] = array_map(fn ($v) => (string) ($v ?? ''), $data);
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private static function readXlsxRows(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new RuntimeException('Berkas XLSX tidak valid atau rusak.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sx = simplexml_load_string($sharedXml);
            if ($sx instanceof SimpleXMLElement) {
                foreach ($sx->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } else {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                        $sharedStrings[] = $text;
                    }
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('Worksheet pertama tidak ditemukan di dalam XLSX.');
        }

        $xml = simplexml_load_string($sheetXml);
        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('Gagal mem-parsing isi worksheet XLSX.');
        }

        $rows = [];
        foreach ($xml->sheetData->row as $rowNode) {
            $rowData = [];
            $maxCol = -1;

            foreach ($rowNode->c as $cell) {
                $ref = (string) $cell['r'];
                $colIdx = self::columnIndexFromRef($ref);
                $maxCol = max($maxCol, $colIdx);
                $type = (string) $cell['t'];

                $value = match ($type) {
                    's' => $sharedStrings[(int) $cell->v] ?? '',
                    'inlineStr' => (string) ($cell->is->t ?? ''),
                    default => (string) ($cell->v ?? ''),
                };

                $rowData[$colIdx] = $value;
            }

            if ($maxCol >= 0) {
                $normalized = [];
                for ($c = 0; $c <= $maxCol; $c++) {
                    $normalized[] = $rowData[$c] ?? '';
                }
                $rows[] = $normalized;
            }
        }

        return $rows;
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';
        $n = $index + 1;
        while ($n > 0) {
            $mod = ($n - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $n = intdiv($n - $mod, 26);
        }

        return $letter;
    }

    private static function columnIndexFromRef(string $ref): int
    {
        if (! preg_match('/^([A-Z]+)/i', $ref, $matches)) {
            return 0;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }
}
