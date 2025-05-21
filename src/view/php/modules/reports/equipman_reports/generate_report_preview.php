<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';
require_once __DIR__ . '/../../../../../control/libs/vendor/autoload.php';

use Dompdf\Dompdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$data = $_POST;
$columns = $data['columns'] ?? [];

$specific_area = strtolower(trim($data['specific_area'] ?? ''));
$building_loc = strtolower(trim($data['building_loc'] ?? ''));
$date_from = $data['date_from'] ?? '';
$date_to = $data['date_to'] ?? '';
$prepared_by = htmlspecialchars($data['prepared_by'] ?? '');
$role_department = htmlspecialchars($data['role_department'] ?? '');
$prep_date = htmlspecialchars($data['prepared_date'] ?? '');
$export_type = $_GET['export_type'] ?? 'pdf';

$column_map = [
    'asset_tag' => 'ed.asset_tag',
    'asset_description' => "CONCAT(ed.asset_description_1, ' ', ed.asset_description_2)",
    'spec_brand_model' => "CONCAT(ed.specifications, ' / ', ed.brand, ' / ', ed.model)",
    'serial_number' => 'ed.serial_number',
    'date_acquired' => 'ed.date_acquired',
    'invoice_no' => 'ed.invoice_no',
    'receiving_report' => 'ed.rr_no',
    'accountable_individual' => 'ed.accountable_individual',
    'remarks' => 'ed.remarks',
    'date_created' => 'ed.date_created',
    'last_date_modified' => 'ed.date_modified',
    'equipment_status' => 'es.status',
    'action_taken' => 'es.action',
    'status_date_creation' => 'es.date_created',
    'status_remarks' => 'es.remarks'
];

$selected_sql_columns = [];
if (!empty($columns)) {
    foreach ($columns as $col) {
        if (isset($column_map[$col])) {
            $selected_sql_columns[] = $column_map[$col] . " AS `$col`";
        }
    }
} else {
    $selected_sql_columns[] = 'ed.asset_tag AS asset_tag';
    $selected_sql_columns[] = "CONCAT(ed.asset_description_1, ' ', ed.asset_description_2) AS asset_description";
    $columns = ['asset_tag', 'asset_description'];
}

$columnList = implode(", ", $selected_sql_columns);

// Build WHERE conditions dynamically
$whereClauses = [];
$params = [];

// Only filter if specific_area is not empty and not 'all'
if ($specific_area !== '' && $specific_area !== 'all') {
    $whereClauses[] = 'LOWER(el.specific_area) = ?';
    $params[] = $specific_area;
}

// Only filter if building_loc is not empty and not 'all'
if ($building_loc !== '' && $building_loc !== 'all') {
    $whereClauses[] = 'LOWER(el.building_loc) = ?';
    $params[] = $building_loc;
}

// Validate and set default date range if missing
if (!$date_from) {
    $date_from = '1900-01-01'; // very old date to include all
}
if (!$date_to) {
    $date_to = date('Y-m-d'); // today
}
$whereClauses[] = 'ed.date_created BETWEEN ? AND ?';
$params[] = $date_from;
$params[] = $date_to;

$whereSQL = '';
if (!empty($whereClauses)) {
    $whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);
}

// Add debug log for the params and WHERE clause
error_log("WHERE clause: $whereSQL");
error_log("Query Params: " . json_encode($params));

// For debugging: add el.specific_area and el.building_loc to the selected columns temporarily
if (!in_array('specific_area', $columns)) {
    $columns[] = 'specific_area';
    $selected_sql_columns[] = 'el.specific_area AS specific_area';
}
if (!in_array('building_loc', $columns)) {
    $columns[] = 'building_loc';
    $selected_sql_columns[] = 'el.building_loc AS building_loc';
}

// Rebuild the column list
$columnList = implode(", ", $selected_sql_columns);

$sql = "SELECT $columnList
        FROM equipment_details ed
        LEFT JOIN equipment_status es ON ed.asset_tag = es.asset_tag
        LEFT JOIN equipment_location el ON ed.asset_tag = el.asset_tag
        LEFT JOIN charge_invoice ci ON ed.invoice_no = ci.invoice_no
        LEFT JOIN receive_report rr ON ed.rr_no = rr.rr_no
        $whereSQL";

// Debug output - temporarily log SQL and params
error_log("Generated SQL: $sql");

$stmt = $pdo->prepare($sql);
if (!$stmt) {
    error_log("PDO Prepare Error: " . json_encode($pdo->errorInfo()));
    die('Database prepare error');
}
if (!$stmt->execute($params)) {
    error_log("PDO Execute Error: " . json_encode($stmt->errorInfo()));
    die('Database execute error');
}
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Optional: dump results for debugging, comment out in production
// var_dump($results); exit;
function createTable($columns, $results) {
    $html = '<table><thead><tr>';
    foreach ($columns as $col) {
        $html .= '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $col))) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    if (!empty($results)) {
        foreach ($results as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $html .= '<td>' . htmlspecialchars($row[$col] ?? '') . '</td>';
            }
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="' . count($columns) . '">No data found.</td></tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

if ($export_type === 'pdf') {
    $html = "<html><head><style>
      @page { size: letter landscape; margin: 20px; }
      body { font-family: Arial; font-size: 10px; }
      table { width: 100%; border-collapse: collapse; }
      th, td { border: 1px solid #000; padding: 4px; }
      th { background: #f0f0f0; }
    </style></head><body>";
    $html .= "<h3>" . htmlspecialchars($data['docTypeSelect'] ?? 'Custom') . " Report</h3>";
    $html .= "<p><strong>Office:</strong> $specific_area<br>
                <strong>Location:</strong> $building_loc<br>
                <strong>Timeline:</strong> $date_from to $date_to<br>
                <strong>Prepared By:</strong> $prepared_by<br>
                <strong>$role_department</strong><br>
                <strong>Date:</strong> $prep_date</p>";
    $html .= createTable($columns, $results);
    $html .= "</body></html>";

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'landscape');
    $dompdf->render();
    $dompdf->stream("equipment_report.pdf", ["Attachment" => false]);
    exit;

} elseif ($export_type === 'word') {
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    $section->addText("{$data['docTypeSelect']} Report", ['bold' => true]);
    $section->addText("Office: $specific_area");
    $section->addText("Location: $building_loc");
    $section->addText("Timeline: $date_from to $date_to");
    $section->addText("Prepared By: $prepared_by");
    $section->addText("$role_department");
    $section->addText("Date: $prep_date");

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

    header("Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
    header("Content-Disposition: attachment; filename=equipment_report.docx");
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save("php://output");
    exit;

} elseif ($export_type === 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(array_map(fn($col) => ucwords(str_replace('_', ' ', $col)), $columns));
    $rowNum = 2;
    foreach ($results as $row) {
        $line = [];
        foreach ($columns as $col) {
            $line[] = $row[$col] ?? '';
        }
        $sheet->fromArray($line, NULL, "A$rowNum");
        $rowNum++;
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="equipment_report.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
