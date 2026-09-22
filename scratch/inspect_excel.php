<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = 'c:/KKP/DATA_PEGAWAI_DENGAN_STATUS.xlsx';
if (!file_exists($filePath)) {
    echo "File not found: $filePath\n";
    exit;
}

$spreadsheet = IOFactory::load($filePath);
$sheetNames = $spreadsheet->getSheetNames();
echo "Sheet names: " . implode(', ', $sheetNames) . "\n\n";

foreach ($sheetNames as $sheetName) {
    echo "--- Sheet: $sheetName ---\n";
    $sheet = $spreadsheet->getSheetByName($sheetName);
    for ($r = 1; $r <= 4; $r++) {
        $rowVals = [];
        for ($col = 'A'; $col <= 'Z'; $col++) {
            $val = $sheet->getCell($col . $r)->getValue();
            if ($val !== null && trim((string)$val) !== '') {
                $rowVals[] = "$col: $val";
            }
        }
        if (!empty($rowVals)) {
            echo "Row $r: " . implode(' | ', $rowVals) . "\n";
        }
    }
    echo "\n";
}
