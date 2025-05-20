<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';

require_once __DIR__ . '/../../../../../control/libs/vendor/autoload.php';
require_once __DIR__ . '/../../../../../control/libs/phpoffice/vendor/autoload.php';

use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

/**
 * Export equipment report in PDF, Excel, or Word format.
 *
 * @param array $data         POST data including filters, columns, etc.
 * @param PDO   $pdo          PDO database connection.
 * @param string $exportType  'pdf'|'excel'|'docs'
 * @return void
 */
function exportEquipmentReport(array $data, PDO $pdo, string $exportType = 'pdf'): void {
    // Sanitize and prepare variables from $data
    $columns = $data['columns'] ?? [];
    $specific_area = $data['specific_area'] ?? '';
    $building_loc = $data['building_loc'] ?? '';
    $date_from = $data['date_from'] ?? '';
    $date_to = $data['date_to'] ?? '';
    $prepared_by = htmlspecialchars($data['prepared_by'] ?? '');
    $role_department = htmlspecialchars($data['role_department'] ?? '');
    $prep_date = htmlspecialchars($data['prepared_date'] ?? '');

    // Map friendly column names to SQL columns
    $column_map = [
        'asset_tag' => 'ed.asset_tag',
        'asset_description' => "CONCAT(ed.asset_description_1, ' ', ed.asset_description_2)",
        'spec_brand_model' => "CONCAT(ed.specifications, ' / ', ed.brand, ' / ', ed.model)",
        'serial_number' => 'ed.serial_number',
        'date_acquired' => 'ed.date_acquired',
        'invoice_no' => 'ed.invoice_no',
        'receiving_report' => 'ed.rr_no',
        'building_location' => 'el.building_loc',
        'accountable_individual' => 'ed.accountable_individual',
        'remarks' => 'ed.remarks',
        'date_created' => 'ed.date_created',
        'last_date_modified' => 'ed.date_modified',
        'equipment_status' => 'es.status',
        'action_taken' => 'es.action',
        'status_date_creation' => 'es.date_created',
        'status_remarks' => 'es.remarks'
    ];

    // Prepare SQL SELECT columns
    $selected_sql_columns = [];
    if (!empty($columns)) {
        foreach ($columns as $col) {
            if (isset($column_map[$col])) {
                $selected_sql_columns[] = $column_map[$col] . " AS `$col`";
            }
        }
    }
    if (empty($selected_sql_columns)) {
        // default columns if none selected
        $selected_sql_columns[] = 'ed.asset_tag AS asset_tag';
        $selected_sql_columns[] = "CONCAT(ed.asset_description_1, ' ', ed.asset_description_2) AS asset_description";
        $columns = ['asset_tag', 'asset_description'];
    }
    $columnList = implode(", ", $selected_sql_columns);

    // Build SQL query
    $sql = "SELECT $columnList
            FROM equipment_details ed
            LEFT JOIN equipment_status es ON ed.asset_tag = es.asset_tag
            LEFT JOIN equipment_location el ON ed.asset_tag = el.asset_tag
                AND el.specific_area = ?
                AND el.building_loc = ?
            LEFT JOIN charge_invoice ci ON ed.invoice_no = ci.invoice_no
            LEFT JOIN receive_report rr ON ed.rr_no = rr.rr_no
            WHERE ed.date_created BETWEEN ? AND ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$specific_area, $building_loc, $date_from, $date_to]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    switch (strtolower($exportType)) {
        case 'pdf':
            $html = '<h3>Equipment Report</h3>';
            $html .= "<p><strong>Office:</strong> $specific_area</p>";
            $html .= "<p><strong>Location:</strong> $building_loc</p>";
            $html .= "<p><strong>Date Range:</strong> $date_from to $date_to</p>";
            $html .= "<p><strong>Prepared By:</strong> $prepared_by<br><strong>$role_department</strong><br><strong>Date:</strong> $prep_date</p>";
            $html .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; font-size: 10px; width: 100%;">';
            // Header row
            $html .= '<tr style="background-color:#eee;">';
            foreach ($columns as $col) {
                $html .= '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $col))) . '</th>';
            }
            $html .= '</tr>';
            // Data rows
            if (!empty($results)) {
                foreach ($results as $row) {
                    $html .= '<tr>';
                    foreach ($columns as $col) {
                        $html .= '<td>' . htmlspecialchars($row[$col] ?? '') . '</td>';
                    }
                    $html .= '</tr>';
                }
            } else {
                $html .= '<tr><td colspan="' . count($columns) . '" style="text-align:center;">No data found.</td></tr>';
            }
            $html .= '</table>';

            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('letter', 'landscape');
            $dompdf->render();
            $dompdf->stream("Equipment_Report.pdf", ["Attachment" => true]);
            break;

        case 'excel':
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Header
            $colIndex = 1;
            foreach ($columns as $col) {
                $sheet->setCellValueByColumnAndRow($colIndex, 1, ucwords(str_replace('_', ' ', $col)));
                $colIndex++;
            }

            // Data rows
            $rowIndex = 2;
            foreach ($results as $row) {
                $colIndex = 1;
                foreach ($columns as $col) {
                    $sheet->setCellValueByColumnAndRow($colIndex, $rowIndex, $row[$col] ?? '');
                    $colIndex++;
                }
                $rowIndex++;
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="Equipment_Report.xlsx"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            break;

        case 'docs':
            $phpWord = new PhpWord();
            $section = $phpWord->addSection();

            $section->addText("Equipment Report", ['bold' => true, 'size' => 16]);
            $section->addText("Office: $specific_area");
            $section->addText("Location: $building_loc");
            $section->addText("Date Range: $date_from to $date_to");
            $section->addText("Prepared By: $prepared_by");
            $section->addText("Role/Department: $role_department");
            $section->addText("Date Prepared: $prep_date");
            $section->addTextBreak(1);

            $tableStyle = ['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 50];
            $firstRowStyle = ['bgColor' => 'CCCCCC'];
            $phpWord->addTableStyle('Equipment Table', $tableStyle, $firstRowStyle);
            $table = $section->addTable('Equipment Table');

            // Header row
            $table->addRow();
            foreach ($columns as $col) {
                $table->addCell(1750)->addText(ucwords(str_replace('_', ' ', $col)), ['bold' => true]);
            }

            // Data rows
            if (!empty($results)) {
                foreach ($results as $row) {
                    $table->addRow();
                    foreach ($columns as $col) {
                        $table->addCell(1750)->addText($row[$col] ?? '');
                    }
                }
            } else {
                $table->addRow();
                $table->addCell(1750 * count($columns))->addText("No data found.", ['italic' => true]);
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment;filename="Equipment_Report.docx"');
            header('Cache-Control: max-age=0');

            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save('php://output');
            break;

        default:
            http_response_code(400);
            echo "Invalid export type specified.";
            exit;
    }
}
