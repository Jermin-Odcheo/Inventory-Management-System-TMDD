<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';
require_once __DIR__ . '/../../../../../control/libs/vendor/autoload.php';

use Dompdf\Dompdf;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Debug PDO connection
if (isset($pdo)) {
    error_log("PDO connection exists");
    
    // Check if the equipment_details table exists
    try {
        $tableCheckStmt = $pdo->query("SHOW TABLES LIKE 'equipment_details'");
        if ($tableCheckStmt->rowCount() > 0) {
            error_log("equipment_details table exists");
            
            // Check if the columns exist
            $columnCheckStmt = $pdo->query("DESCRIBE equipment_details");
            $columns_in_db = [];
            while ($row = $columnCheckStmt->fetch(PDO::FETCH_ASSOC)) {
                $columns_in_db[] = $row['Field'];
            }
            error_log("equipment_details columns: " . implode(", ", $columns_in_db));
            
            // Check if we have any records
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM equipment_details");
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
            error_log("equipment_details record count: $count");
            
            // Get a sample record
            $sampleStmt = $pdo->query("SELECT * FROM equipment_details LIMIT 1");
            $sample = $sampleStmt->fetch(PDO::FETCH_ASSOC);
            if ($sample) {
                error_log("Sample record: " . json_encode($sample));
            } else {
                error_log("No sample record found - table might be empty");
            }
        } else {
            error_log("equipment_details table does NOT exist!");
        }
    } catch (PDOException $e) {
        error_log("Error checking database structure: " . $e->getMessage());
    }
} else {
    error_log("PDO connection is not set");
}

$data = $_POST;
$columns = $data['columns'] ?? [];

// Debug POST data
error_log("POST data: " . json_encode($_POST));
error_log("Selected columns: " . json_encode($columns));

// Check table structure
$tableStructureSql = "DESCRIBE equipment_details";
try {
    $tableStructureStmt = $pdo->query($tableStructureSql);
    $tableColumns = $tableStructureStmt->fetchAll(PDO::FETCH_COLUMN);
    error_log("equipment_details columns: " . json_encode($tableColumns));
} catch (PDOException $e) {
    error_log("Error checking table structure: " . $e->getMessage());
}

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
    'location' => 'ed.location',
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

// Handle location filtering using the location field from equipment_details
if ($specific_area !== '' && $specific_area !== 'all') {
    $whereClauses[] = 'LOWER(ed.location) LIKE ?';
    $params[] = '%' . $specific_area . '%';
}

if ($building_loc !== '' && $building_loc !== 'all') {
    $whereClauses[] = 'LOWER(ed.location) LIKE ?';
    $params[] = '%' . $building_loc . '%';
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

// Add condition to only show enabled equipment
// Commenting out for debugging - this might be causing the issue
// $whereClauses[] = 'ed.is_disabled = 0';

$whereSQL = '';
if (!empty($whereClauses)) {
    $whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);
}

// Add debug log for the params and WHERE clause
error_log("WHERE clause: $whereSQL");
error_log("Query Params: " . json_encode($params));

// Add location to columns if not already present
if (!in_array('location', $columns)) {
    $columns[] = 'location';
    $selected_sql_columns[] = 'ed.location AS location';
}

// Rebuild the column list
$columnList = implode(", ", $selected_sql_columns);

// Check if equipment_status table exists
$checkStatusTableSql = "SHOW TABLES LIKE 'equipment_status'";
$checkStatusTableStmt = $pdo->query($checkStatusTableSql);
$statusTableExists = ($checkStatusTableStmt->rowCount() > 0);
error_log("equipment_status table exists: " . ($statusTableExists ? 'Yes' : 'No'));

// SIMPLIFIED QUERY APPROACH - Temporarily bypass all the complex logic
// and just get some data to display
$sql = "SELECT asset_tag, 
               CONCAT(asset_description_1, ' ', asset_description_2) AS asset_description,
               specifications, 
               brand, 
               model, 
               serial_number, 
               date_acquired, 
               location, 
               accountable_individual
        FROM equipment_details
        LIMIT 20";

error_log("Using simplified query: $sql");

$stmt = $pdo->prepare($sql);
if (!$stmt) {
    error_log("PDO Prepare Error: " . json_encode($pdo->errorInfo()));
    die('Database prepare error');
}
if (!$stmt->execute()) {
    error_log("PDO Execute Error: " . json_encode($stmt->errorInfo()));
    die('Database execute error');
}
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Update columns to match what we're actually retrieving
$columns = [
    'asset_tag', 
    'asset_description', 
    'specifications', 
    'brand', 
    'model', 
    'serial_number', 
    'date_acquired', 
    'location', 
    'accountable_individual'
];

// Debug output - log the number of results and the first row
error_log("Number of results: " . count($results));
if (!empty($results)) {
    error_log("First row: " . json_encode($results[0]));
} else {
    error_log("No results found even with simplified query!");
    
    // If we're still not getting results, output debugging info directly
    if ($export_type === 'pdf') {
        // Skip the PDF generation and output plain text for debugging
        header('Content-Type: text/html');
        echo "<h1>Debugging Information</h1>";
        echo "<p>No results found in the query. Here's some debugging information:</p>";
        
        echo "<h2>Database Connection</h2>";
        echo "<p>PDO connection: " . (isset($pdo) ? "Established" : "Not established") . "</p>";
        
        echo "<h2>Query Information</h2>";
        echo "<pre>$sql</pre>";
        
        echo "<h2>POST Data</h2>";
        echo "<pre>" . print_r($_POST, true) . "</pre>";
        
        echo "<h2>Database Tables</h2>";
        try {
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li>$table</li>";
            }
            echo "</ul>";
        } catch (Exception $e) {
            echo "<p>Error listing tables: " . $e->getMessage() . "</p>";
        }
        
        exit;
    }
}

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
    $html .= "<p><strong>Office/Area:</strong> $specific_area<br>
                <strong>Building/Location:</strong> $building_loc<br>
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
