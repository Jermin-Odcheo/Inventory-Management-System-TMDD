<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';
require_once __DIR__ . '/report_query_helper.php';

$data = $_POST;
$columns = $data['columns'] ?? [];

$filters = [
    'specific_area' => $data['specific_area'],
    'building_loc' => $data['building_loc'],
    'date_from' => $data['date_from'],
    'date_to' => $data['date_to']
];

list($sql, $params) = buildReportQuery($columns, $filters);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$colIndex = 1;
foreach ($columns as $col) {
    $sheet->setCellValueByColumnAndRow($colIndex++, 1, ucwords(str_replace('_', ' ', $col)));
}

$rowIndex = 2;
foreach ($results as $row) {
    $colIndex = 1;
    foreach ($columns as $col) {
        $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $row[$col] ?? '');
    }
    $rowIndex++;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="report.xlsx"');
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
