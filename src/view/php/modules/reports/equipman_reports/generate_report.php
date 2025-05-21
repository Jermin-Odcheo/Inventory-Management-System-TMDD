<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';
require_once __DIR__ . '/../../../../../control/libs/vendor/autoload.php';
require_once __DIR__ . '/../../../../../control/libs/phpoffice/vendor/autoload.php';

use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

function exportEquipmentReport(array $data, PDO $pdo, string $exportType = 'pdf'): void
{

    // After extracting $data
    $data = $_POST;
    $columns = $data['columns'] ?? [];
    $specific_area = trim($data['specific_area'] ?? '');
    $building_loc = trim($data['building_loc'] ?? '');
    $date_from = trim($data['date_from'] ?? '');
    $date_to = trim($data['date_to'] ?? '');
    $prepared_by = htmlspecialchars($data['prepared_by'] ?? '');
    $role_department = htmlspecialchars($data['role_department'] ?? '');
    $prep_date = htmlspecialchars($data['prepared_date'] ?? '');
    $export_type = $_GET['export_type'] ?? 'pdf';

    // Debug POST data
    error_log("POST data: " . json_encode($_POST));
    error_log("Input Filters - building_loc: '$building_loc', specific_area: '$specific_area', date_from: '$date_from', date_to: '$date_to'");
    error_log("Selected columns: " . json_encode($columns));

    // Validate inputs
    if ($building_loc === '') {
        error_log("Warning: building_loc is empty, setting to 'all'");
        $building_loc = 'all';
    }
    if ($specific_area === '') {
        error_log("Warning: specific_area is empty, setting to 'all'");
        $specific_area = 'all';
    }

    // Validate and handle dates
    $date_filter_active = false;
    $date_from_valid = !empty($date_from) && strtotime($date_from);
    $date_to_valid = !empty($date_to) && strtotime($date_to);

    if ($date_from_valid) {
        $date_from = date('Y-m-d', strtotime($date_from));
        $date_filter_active = true;
    }

    if ($date_to_valid) {
        $date_to = date('Y-m-d', strtotime($date_to));
        $date_filter_active = true;
    }

    error_log("Date filter active: " . ($date_filter_active ? 'Yes' : 'No'));
    error_log("Processed Dates - date_from: " . ($date_from_valid ? $date_from : 'Not set') . 
              ", date_to: " . ($date_to_valid ? $date_to : 'Not set'));
    
    // Map friendly column names to SQL columns
    $column_map = [
        'asset_tag' => 'ed.asset_tag',
        'asset_description' => "COALESCE(CONCAT(ed.asset_description_1, ' ', ed.asset_description_2), '')",
        'spec_brand_model' => "COALESCE(CONCAT(ed.specifications, ' / ', ed.brand, ' / ', ed.model), '')",
        'serial_number' => 'ed.serial_number',
        'date_acquired' => 'ed.date_acquired',
        'invoice_no' => 'ed.invoice_no',
        'receiving_report' => 'ed.rr_no',
        'building_location' => "COALESCE(el.building_loc, ed.location, '')",
        'specific_area' => "COALESCE(el.specific_area, '')",
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
    }
    if (empty($selected_sql_columns)) {
        $selected_sql_columns = [
            'ed.asset_tag AS asset_tag',
            "COALESCE(CONCAT(ed.asset_description_1, ' ', ed.asset_description_2), '') AS asset_description",
            "COALESCE(el.building_loc, ed.location, '') AS building_location",
            "COALESCE(el.specific_area, '') AS specific_area"
        ];
        $columns = ['asset_tag', 'asset_description', 'building_location', 'specific_area'];
    }
    $columnList = implode(", ", $selected_sql_columns);

    $whereClauses = [];
    $params = [];

    if ($building_loc !== '' && $building_loc !== 'all') {
        $whereClauses[] = 'COALESCE(el.building_loc, ed.location) = ?';
        $params[] = $building_loc;
    } else {
        error_log("No building_loc filter applied (value: '$building_loc')");
    }

    if ($specific_area !== '' && $specific_area !== 'all') {
        $whereClauses[] = 'COALESCE(el.specific_area, \'\') = ?';
        $params[] = $specific_area;
    } else {
        error_log("No specific_area filter applied (value: '$specific_area')");
    }

    // Only add date filtering if at least one date is provided
    if ($date_filter_active) {
        // Build the date filter condition
        if ($date_from_valid && $date_to_valid) {
            // Both dates provided - filter between them
            $whereClauses[] = '(
                (ed.date_created BETWEEN ? AND ?) OR 
                (ed.date_acquired BETWEEN ? AND ?) OR
                (ed.date_modified BETWEEN ? AND ?) OR
                (es.date_created BETWEEN ? AND ?)
            )';
            $params[] = $date_from;
            $params[] = $date_to;
            $params[] = $date_from;
            $params[] = $date_to;
            $params[] = $date_from;
            $params[] = $date_to;
            $params[] = $date_from;
            $params[] = $date_to;
        } elseif ($date_from_valid) {
            // Only from date provided - filter >= from date
            $whereClauses[] = '(
                ed.date_created >= ? OR 
                ed.date_acquired >= ? OR
                ed.date_modified >= ? OR
                es.date_created >= ?
            )';
            $params[] = $date_from;
            $params[] = $date_from;
            $params[] = $date_from;
            $params[] = $date_from;
        } elseif ($date_to_valid) {
            // Only to date provided - filter <= to date
            $whereClauses[] = '(
                ed.date_created <= ? OR 
                ed.date_acquired <= ? OR
                ed.date_modified <= ? OR
                es.date_created <= ?
            )';
            $params[] = $date_to;
            $params[] = $date_to;
            $params[] = $date_to;
            $params[] = $date_to;
        }
    }

    $whereClauses[] = 'ed.is_disabled = 0';

    $whereSQL = '';
    if (!empty($whereClauses)) {
        $whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);
    }

    $sql = "SELECT $columnList
            FROM equipment_details ed
            LEFT JOIN (
                SELECT asset_tag, building_loc, specific_area
                FROM equipment_location
                WHERE is_disabled = 0
                AND date_created = (
                    SELECT MAX(date_created)
                    FROM equipment_location el2
                    WHERE el2.asset_tag = equipment_location.asset_tag AND el2.is_disabled = 0
                )
            ) el ON ed.asset_tag = el.asset_tag
            LEFT JOIN equipment_status es ON ed.asset_tag = es.asset_tag AND es.is_disabled = 0
            $whereSQL";

    error_log("SQL Query: $sql");
    error_log("Query Params: " . json_encode($params));

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

    error_log("Number of results: " . count($results));
    if (!empty($results)) {
        error_log("First row: " . json_encode($results[0]));
    } else {
        error_log("No results returned");
    }

    switch (strtolower($exportType)) {
        case 'pdf':
            $html = '<h3>Equipment Report</h3>';
            $html .= "<p><strong>Office/Laboratory:</strong> " . htmlspecialchars($specific_area === 'all' ? 'All Areas' : $specific_area) . "</p>";
            $html .= "<p><strong>Location:</strong> " . htmlspecialchars($building_loc === 'all' ? 'All Locations' : $building_loc) . "</p>";
            $html .= "<p><strong>Date Range:</strong> " . 
                    ($date_filter_active ? 
                        ($date_from_valid && $date_to_valid ? "$date_from to $date_to" : 
                         ($date_from_valid ? "From $date_from" : "Until $date_to")) : 
                        "All dates") . "</p>";
            $html .= "<p><strong>Prepared By:</strong> $prepared_by<br><strong>$role_department</strong><br><strong>Date:</strong> $prep_date</p>";
            $html .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; font-size: 10px; width: 100%;">';
            $html .= '<tr style="background-color:#eee;">';
            foreach ($columns as $col) {
                $html .= '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $col))) . '</th>';
            }
            $html .= '</tr>';
            if (!empty($results)) {
                foreach ($results as $row) {
                    $html .= '<tr>';
                    foreach ($columns as $col) {
                        $value = $row[$col] ?? '';
                        $html .= '<td>' . htmlspecialchars($value) . '</td>';
                    }
                    $html .= '</tr>';
                }
            } else {
                $html .= '<tr><td colspan="' . count($columns) . '" style="text-align:center;">No data found.</td></tr>';
            }
            $html .= '</table>';

            $dompdf = new Dompdf(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper($data['paper_size'] ?? 'letter', $data['orientation'] ?? 'landscape');
            $dompdf->render();
            $dompdf->stream("Equipment_Report_" . date('Ymd') . ".pdf", ["Attachment" => true]);
            break;

        case 'excel':
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $colIndex = 1;
            foreach ($columns as $col) {
                $sheet->setCellValueByColumnAndRow($colIndex, 1, ucwords(str_replace('_', ' ', $col)));
                $colIndex++;
            }
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
            $section->addText("Office/Laboratory: " . htmlspecialchars($specific_area === 'all' ? 'All Areas' : $specific_area));
            $section->addText("Location: " . htmlspecialchars($building_loc === 'all' ? 'All Locations' : $building_loc));
            $section->addText("Date Range: " . 
                    ($date_filter_active ? 
                        ($date_from_valid && $date_to_valid ? "$date_from to $date_to" : 
                         ($date_from_valid ? "From $date_from" : "Until $date_to")) : 
                        "All dates"));
            $section->addText("Prepared By: $prepared_by");
            $section->addText("Role/Department: $role_department");
            $section->addText("Date Prepared: $prep_date");
            $section->addTextBreak(1);
            $tableStyle = ['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 50];
            $firstRowStyle = ['bgColor' => 'CCCCCC'];
            $phpWord->addTableStyle('Equipment Table', $tableStyle, $firstRowStyle);
            $table = $section->addTable('Equipment Table');
            $table->addRow();
            foreach ($columns as $col) {
                $table->addCell(1750)->addText(ucwords(str_replace('_', ' ', $col)), ['bold' => true]);
            }
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

exportEquipmentReport($_POST, $pdo, $_GET['export_type'] ?? 'pdf');