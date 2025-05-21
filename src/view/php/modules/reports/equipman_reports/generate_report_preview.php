<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';
require_once __DIR__ . '/../../../../../control/libs/vendor/autoload.php';

use Dompdf\Dompdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Debug PDO connection
if (!isset($pdo)) {
    error_log("PDO connection is not set");
    die('Database connection error');
}
error_log("PDO connection exists");

// Check for available date ranges in the database
try {
    $dateRangeStmt = $pdo->query("
        SELECT 
            MIN(date_created) as min_created,
            MAX(date_created) as max_created,
            MIN(date_acquired) as min_acquired,
            MAX(date_acquired) as max_acquired,
            MIN(date_modified) as min_modified,
            MAX(date_modified) as max_modified,
            COUNT(*) as total_records
        FROM equipment_details 
        WHERE is_disabled = 0
    ");
    $dateRanges = $dateRangeStmt->fetch(PDO::FETCH_ASSOC);
    error_log("Available date ranges: " . json_encode($dateRanges));
} catch (PDOException $e) {
    error_log("Error checking date ranges: " . $e->getMessage());
}

// Check table structure
try {
    $tables = ['equipment_details', 'equipment_location', 'equipment_status'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() === 0) {
            error_log("$table table does NOT exist!");
            die("$table table missing");
        }
        error_log("$table table exists");

        // Check if table has data
        $countStmt = $pdo->query("SELECT COUNT(*) FROM $table WHERE is_disabled = 0");
        $count = $countStmt->fetchColumn();
        error_log("$table has $count active records");
    }
} catch (PDOException $e) {
    error_log("Error checking database structure: " . $e->getMessage());
    die('Database structure error');
}

$data = $_POST;
$columns = $data['columns'] ?? [];
$specific_area = trim($data['specific_area'] ?? '');
$building_loc = trim($data['building_loc'] ?? '');
$date_from = $data['date_from'] ?? '';
$date_to = $data['date_to'] ?? '';
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

// Column mapping
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

// Prepare selected columns
$selected_sql_columns = [];
if (!empty($columns)) {
    error_log("Processing columns: " . json_encode($columns));
    foreach ($columns as $col) {
        if (isset($column_map[$col])) {
            $selected_sql_columns[] = $column_map[$col] . " AS `$col`";
        } else {
            error_log("Warning: Unknown column requested: " . $col);
        }
    }
} else {
    error_log("No columns specified, using defaults");
    $selected_sql_columns = [
        'ed.asset_tag AS asset_tag',
        "COALESCE(CONCAT(ed.asset_description_1, ' ', ed.asset_description_2), '') AS asset_description",
        "COALESCE(el.building_loc, ed.location, '') AS building_location",
        "COALESCE(el.specific_area, '') AS specific_area"
    ];
    $columns = ['asset_tag', 'asset_description', 'building_location', 'specific_area'];
}
$columnList = implode(", ", $selected_sql_columns);

// Build WHERE conditions dynamically
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

// Build SQL query
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

// Log query for debugging
error_log("SQL Query: $sql");
error_log("Query Params: " . json_encode($params));

try {
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

    // Log results
    error_log("Number of results: " . count($results));
    if (!empty($results)) {
        error_log("First row: " . json_encode($results[0]));
    } else {
        error_log("No results returned");
    }
} catch (Exception $e) {
    error_log("Exception in report generation: " . $e->getMessage());
    die('Error executing query: ' . $e->getMessage());
}

// Function to create table HTML
function createTable($columns, $results)
{
    if (empty($columns)) {
        error_log("Error: No columns provided to createTable function");
        return '<div class="alert alert-danger">Error: No columns selected for report</div>';
    }

    $html = '<table border="1" cellpadding="4" cellspacing="0" style="width:100%; border-collapse: collapse;"><thead><tr>';
    foreach ($columns as $col) {
        $html .= '<th style="background-color:#f0f0f0; font-weight:bold;">' . htmlspecialchars(ucwords(str_replace('_', ' ', $col))) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    if (!empty($results)) {
        error_log("Creating table with " . count($results) . " rows");
        foreach ($results as $row) {
            $html .= '<tr>';
            foreach ($columns as $col) {
                $html .= '<td>' . htmlspecialchars($row[$col] ?? '') . '</td>';
            }
            $html .= '</tr>';
        }
    } else {
        error_log("No results to display in table");
        $html .= '<tr><td colspan="' . count($columns) . '" style="text-align:center; padding:20px;">No data found matching your criteria. Try adjusting your filters.</td></tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

// Handle export type
if ($export_type === 'pdf') {
    try {
        error_log("Generating PDF report");
        $html = "<html><head><style>
          @page { size: " . ($data['paper_size'] ?? 'letter') . " " . ($data['orientation'] ?? 'landscape') . "; margin: 20px; }
          body { font-family: Arial; font-size: 10px; }
          table { width: 100%; border-collapse: collapse; }
          th, td { border: 1px solid #000; padding: 4px; }
          th { background: #f0f0f0; }
        </style></head><body>";
        $html .= "<h3>" . htmlspecialchars($data['docTypeSelect'] ?? 'Custom') . " Report</h3>";
        $html .= "<p><strong>Office/Laboratory:</strong> " . htmlspecialchars($specific_area === 'all' ? 'All Areas' : $specific_area) . "<br>
                    <strong>Location:</strong> " . htmlspecialchars($building_loc === 'all' ? 'All Locations' : $building_loc) . "<br>
                    <strong>Timeline:</strong> " . 
                    ($date_filter_active ? 
                        ($date_from_valid && $date_to_valid ? "$date_from to $date_to" : 
                         ($date_from_valid ? "From $date_from" : "Until $date_to")) : 
                        "All dates") . "<br>
                    <strong>Prepared By:</strong> $prepared_by<br>
                    <strong>$role_department</strong><br>
                    <strong>Date:</strong> $prep_date</p>";
        $html .= createTable($columns, $results);
        $html .= "</body></html>";

        error_log("HTML generated, length: " . strlen($html));

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper($data['paper_size'] ?? 'letter', $data['orientation'] ?? 'landscape');
        error_log("Starting PDF rendering");
        $dompdf->render();
        error_log("PDF rendered successfully");
        $dompdf->stream("equipment_report_preview.pdf", ["Attachment" => false]);
        exit;
    } catch (Exception $e) {
        error_log("Error generating PDF: " . $e->getMessage());
        echo "<div style='color:red; padding:20px; border:1px solid red;'>
              Error generating PDF report: " . htmlspecialchars($e->getMessage()) .
            "<br><br>Please check the server logs for more details.</div>";
        exit;
    }
} elseif ($export_type === 'word') {
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    $section->addText($data['docTypeSelect'] ?? 'Custom Report', ['bold' => true]);
    $section->addText("Office/Laboratory: " . htmlspecialchars($specific_area === 'all' ? 'All Areas' : $specific_area));
    $section->addText("Location: " . htmlspecialchars($building_loc === 'all' ? 'All Locations' : $building_loc));
    $section->addText("Timeline: $date_from to $date_to");
    $section->addText("Prepared By: $prepared_by");
    $section->addText($role_department);
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
    header("Content-Disposition: attachment; filename=equipment_report_preview.docx");
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
    header('Content-Disposition: attachment;filename="equipment_report_preview.xlsx"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
