<?php
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';
require_once __DIR__ . '/report_query_helper.php';

$data = $_POST;
$columns = $data['columns'] ?? [];
$prepared_by = htmlspecialchars($data['prepared_by']);
$role_department = htmlspecialchars($data['role_department']);

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

$phpWord = new PhpWord();
$section = $phpWord->addSection();

$section->addText("Document Type: " . htmlspecialchars($data['doc_type']));
$section->addText("Office: {$filters['specific_area']}");
$section->addText("Location: {$filters['building_loc']}");
$section->addText("Timeline: {$filters['date_from']} to {$filters['date_to']}");
$section->addText("Prepared by: $prepared_by - $role_department");

$table = $section->addTable();
$table->addRow();
foreach ($columns as $col) {
    $table->addCell()->addText(ucwords(str_replace('_', ' ', $col)));
}

foreach ($results as $row) {
    $table->addRow();
    foreach ($columns as $col) {
        $table->addCell()->addText($row[$col] ?? '');
    }
}

header("Content-Description: File Transfer");
header('Content-Disposition: attachment; filename="report.docx"');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save("php://output");
exit;
