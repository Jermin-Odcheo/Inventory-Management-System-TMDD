<?php
/**
 * Generate Report Module
 *
 * This file provides the core functionality for generating equipment reports in the system. It handles the processing of report parameters, data retrieval from the database, and report generation in various formats. The module supports multiple report types, filtering options, and export capabilities while ensuring data accuracy and user authorization.
 *
 * @package    InventoryManagementSystem
 * @subpackage Reports
 * @author     TMDD Interns 25'
 */

require_once __DIR__ . '/../../../../../../config/ims-tmdd.php'; // Include the database connection file, providing the $pdo object.
require_once __DIR__ . '/../../../../../control/libs/vendor/autoload.php'; // Autoload Composer dependencies for libraries.

// Import necessary classes from external libraries.
use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Paper;
use PhpOffice\PhpWord\Settings;

/**
 * @brief Exports an equipment report based on provided data and export type.
 * @param array $data An associative array containing report parameters (columns, filters, preparation details).
 * @param PDO $pdo The PDO database connection object.
 * @param string $exportType The desired export format ('pdf', 'excel', 'word'). Defaults to 'pdf'.
 * @return void Outputs the generated report directly to the browser and exits.
 */
function exportEquipmentReport(array $data, PDO $pdo, string $exportType = 'pdf'): void
{
    // Extract report parameters from the $data array (which comes from $_POST).
    $data = $_POST; // Re-assign $_POST to $data for direct access.
    $columns = $data['columns'] ?? [];
    $specific_area = trim($data['specific_area'] ?? '');
    $building_loc = trim($data['building_loc'] ?? '');
    $date_from = trim($data['date_from'] ?? '');
    $date_to = trim($data['date_to'] ?? '');
    $prepared_by = htmlspecialchars($data['prepared_by'] ?? '');
    $role_department = htmlspecialchars($data['role_department'] ?? '');
    $prep_date = htmlspecialchars($data['prepared_date'] ?? '');
    $export_type = $_GET['export_type'] ?? 'pdf'; // Get export type from GET for download.
    $paper_size = trim($data['paper_size'] ?? 'letter'); // Default to letter if not specified.

    // Debugging: Log received POST data and input filters.
    error_log("POST data: " . json_encode($_POST));
    error_log("Input Filters - building_loc: '$building_loc', specific_area: '$specific_area', date_from: '$date_from', date_to: '$date_to'");
    error_log("Selected columns: " . json_encode($columns));

    // Validate and handle location inputs.
    if ($building_loc === '') {
        error_log("Warning: building_loc is empty, setting to 'all'");
        $building_loc = 'all';
    }
    if ($specific_area === '') {
        error_log("Warning: specific_area is empty, setting to 'all'");
        $specific_area = 'all';
    }

    // Validate and handle date inputs for filtering.
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

    // Map friendly column names from frontend to actual SQL column expressions.
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

    // Build the list of selected SQL columns for the SELECT clause.
    $selected_sql_columns = [];
    if (!empty($columns)) {
        foreach ($columns as $col) {
            if (isset($column_map[$col])) {
                $selected_sql_columns[] = $column_map[$col] . " AS `$col`";
            }
        }
    }
    // If no columns are selected, default to a basic set.
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

    // Build the WHERE clauses and parameters for the SQL query.
    $whereClauses = [];
    $params = [];

    // Add building location filter if specified.
    if ($building_loc !== '' && $building_loc !== 'all') {
        $whereClauses[] = 'COALESCE(el.building_loc, ed.location) = ?';
        $params[] = $building_loc;
    } else {
        error_log("No building_loc filter applied (value: '$building_loc')");
    }

    // Add specific area filter if specified.
    if ($specific_area !== '' && $specific_area !== 'all') {
        $whereClauses[] = 'COALESCE(el.specific_area, \'\') = ?';
        $params[] = $specific_area;
    } else {
        error_log("No specific_area filter applied (value: '$specific_area')");
    }

    // Add date filtering if at least one date is provided.
    if ($date_filter_active) {
        if ($date_from_valid && $date_to_valid) {
            // Both dates provided - filter between them for all relevant date columns.
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
            // Only from date provided - filter >= from date for all relevant date columns.
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
            // Only to date provided - filter <= to date for all relevant date columns.
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

    // Always filter out disabled equipment details.
    $whereClauses[] = 'ed.is_disabled = 0';

    // Combine WHERE clauses.
    $whereSQL = '';
    if (!empty($whereClauses)) {
        $whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);
    }

    // Construct the main SQL query.
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

    // Debugging: Log the final SQL query and parameters.
    error_log("SQL Query: $sql");
    error_log("Query Params: " . json_encode($params));

    // Prepare and execute the SQL query.
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

    // Debugging: Log the number of results and the first row (if any).
    error_log("Number of results: " . count($results));
    if (!empty($results)) {
        error_log("First row: " . json_encode($results[0]));
    } else {
        error_log("No results returned");
    }

    // Handle report generation based on the specified export type.
    switch (strtolower($exportType)) {
        case 'pdf':
            /**
             * Generates a PDF report using Dompdf.
             * Includes report header information and a table of results.
             */
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

            // Set paper size and orientation.
            if ($paper_size === 'legal') {
                $dompdf->setPaper('legal', 'landscape'); // 8.5" x 14"
            } else {
                $dompdf->setPaper('letter', 'landscape'); // 8.5" x 11"
            }

            $dompdf->render();
            // Stream the PDF to the browser for download.
            $dompdf->stream("Equipment_Report_" . date('Ymd') . ".pdf", ["Attachment" => true]);
            break;

        case 'excel':
            /**
             * Generates an Excel report using PhpSpreadsheet.
             * Includes report header information and a table of results.
             */
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Add report header information.
            $sheet->setCellValue('A1', 'Equipment Report');
            $sheet->setCellValue('A2', 'Office/Laboratory:');
            $sheet->setCellValue('B2', $specific_area === 'all' ? 'All Areas' : $specific_area);
            $sheet->setCellValue('A3', 'Location:');
            $sheet->setCellValue('B3', $building_loc === 'all' ? 'All Locations' : $building_loc);
            $sheet->setCellValue('A4', 'Date Range:');
            $sheet->setCellValue('B4', $date_filter_active ?
                ($date_from_valid && $date_to_valid ? "$date_from to $date_to" :
                ($date_from_valid ? "From $date_from" : "Until $date_to")) :
                "All dates");
            $sheet->setCellValue('A5', 'Prepared By:');
            $sheet->setCellValue('B5', $prepared_by);
            $sheet->setCellValue('A6', $role_department);
            $sheet->setCellValue('A7', 'Date Prepared:');
            $sheet->setCellValue('B7', $prep_date);

            // Format header cells.
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A2:A7')->getFont()->setBold(true);

            // Add table headers at row 9.
            $rowIndex = 9;
            $colIndex = 1;
            foreach ($columns as $col) {
                $sheet->setCellValueByColumnAndRow($colIndex, $rowIndex, ucwords(str_replace('_', ' ', $col)));
                $colIndex++;
            }

            // Style the header row.
            $headerRange = 'A' . $rowIndex . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($columns)) . $rowIndex;
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('CCCCCC');

            // Add data starting at row 10.
            $rowIndex++;
            foreach ($results as $row) {
                $colIndex = 1;
                foreach ($columns as $col) {
                    $sheet->setCellValueByColumnAndRow($colIndex, $rowIndex, $row[$col] ?? '');
                    $colIndex++;
                }
                $rowIndex++;
            }

            // Auto-size columns for better readability.
            foreach (range(1, count($columns)) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }

            // Set HTTP headers for Excel download.
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="Equipment_Report.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output'); // Save the spreadsheet to output.
            break;

        case 'word':
            /**
             * Generates a Word report using PhpWord.
             * Includes report header information and a table of results.
             */
            $phpWord = new PhpWord();

            $section = $phpWord->addSection([
                'paperSize'   => ucfirst($paper_size),   // "Letter" or "Legal"
                'orientation' => 'landscape',
            ]);
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
            $section->addTextBreak(1); // Add a line break.

            // Calculate optimal column width for the Word table.
            $pageWidth = 9072; // Width of landscape A4 page in twips (excluding margins).
            $marginSpace = 1440; // Typical margins (720 points per side).
            $availableWidth = $pageWidth - $marginSpace;
            $columnCount = count($columns);

            $tableStyle = [
                'borderSize' => 6,
                'borderColor' => '999999',
                'cellMargin' => 100,
                'width' => 100, // 100% of page width.
                'unit' => 'pct'
            ];
            $firstRowStyle = ['bgColor' => 'CCCCCC'];
            $phpWord->addTableStyle('Equipment Table', $tableStyle, $firstRowStyle);
            $table = $section->addTable('Equipment Table');

            // Add header row to the Word table.
            $table->addRow();
            foreach ($columns as $col) {
                $cell = $table->addCell(null, ['width' => 100 / $columnCount . '%']);
                $cell->addText(ucwords(str_replace('_', ' ', $col)), ['bold' => true]);
            }

            // Add data rows to the Word table.
            if (!empty($results)) {
                foreach ($results as $row) {
                    $table->addRow();
                    foreach ($columns as $col) {
                        $cell = $table->addCell(null, ['width' => 100 / $columnCount . '%']);
                        $cell->addText($row[$col] ?? '');
                    }
                }
            } else {
                $table->addRow();
                // Add a "No data found" message if results are empty.
                $table->addCell(null, ['gridSpan' => $columnCount])->addText("No data found.", ['italic' => true]);
            }

            // Set HTTP headers for Word document download.
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment;filename="Equipment_Report.docx"');
            header('Cache-Control: max-age=0');
            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save('php://output'); // Save the Word document to output.
            break;

        default:
            // Handle invalid export types.
            http_response_code(400);
            echo "Invalid export type specified.";
            exit;
    }
}

// Call the export function with POST data, PDO connection, and export type from GET.
exportEquipmentReport($_POST, $pdo, $_GET['export_type'] ?? 'pdf');
