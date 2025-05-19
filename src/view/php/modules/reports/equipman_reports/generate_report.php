<?php
use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../../../../control/libs/dompdf/vendor/autoload.php';
require_once __DIR__ . '/../../../../config/ims-tmdd.php';  // Your PDO connection

// Start session if you want to get user info from session
session_start();

// Helper function to safely get POST data
function post($key, $default = null) {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

// Retrieve form data
$documentType = post('document_type'); // 'summary' or 'detailed'
$specificArea = post('specific_area'); // string - filter header
$buildingLoc = post('building_loc');   // string - filter header
$dateFrom = post('date_from');         // string yyyy-mm-dd
$dateTo = post('date_to');             // string yyyy-mm-dd

// Checkbox fields to include
$columns = post('columns', []); // array of checked columns

// Validate mandatory header fields
if (!$specificArea || !$buildingLoc || !$dateFrom || !$dateTo) {
    die('Missing required header fields.');
}

// Mandatory columns (header info) always included
$mandatoryHeaderCols = ['specific_area', 'building_loc', 'timeline'];

// Mapping checkbox keys to SQL fields and column labels
$columnMap = [
    'last_modified' => ['label' => 'Last Modified Date', 'fields' => ['last_modified']],
    'date_created' => ['label' => 'Date Created', 'fields' => ['date_created']],
    'asset_tag' => ['label' => 'Asset Tag', 'fields' => ['asset_tag']],
    'asset_description' => ['label' => 'Asset Description', 'fields' => ['asset_description_1', 'asset_description_2']],
    'spec_brand_model' => ['label' => 'Specifications / Brand / Model', 'fields' => ['specifications', 'brand', 'model']],
    'serial_number' => ['label' => 'Serial Number', 'fields' => ['serial_number']],
    'date_acquired' => ['label' => 'Date Acquired', 'fields' => ['date_acquired']],
    'invoice_no' => ['label' => 'Invoice No', 'fields' => ['invoice_no']],
    'receiving_report' => ['label' => 'Receiving Report', 'fields' => ['receiving_report']],
    'building_location' => ['label' => 'Building Location', 'fields' => ['building_location']],
    'accountable_individual' => ['label' => 'Accountable Individual', 'fields' => ['accountable_individual']],
    'remarks' => ['label' => 'Remarks', 'fields' => ['remarks']],
    'equipment_status' => ['label' => 'Equipment Status', 'fields' => ['status']],
    'action_taken' => ['label' => 'Action Taken', 'fields' => ['action_taken']],
    'status_date_creation' => ['label' => 'Status Date Creation', 'fields' => ['status_date_creation']],
    'status_remarks' => ['label' => 'Status Remarks', 'fields' => ['status_remarks']],
];

// Build SELECT fields dynamically
$selectFields = [];
foreach ($columns as $colKey) {
    if (isset($columnMap[$colKey])) {
        foreach ($columnMap[$colKey]['fields'] as $field) {
            $selectFields[$field] = true;
        }
    }
}

// Add mandatory fields for grouping & filtering
$selectFields['specific_area'] = true;
$selectFields['building_loc'] = true;
$selectFields['date_created'] = true;  // to filter timeline, assumed in equipment_details

// Build the SELECT list string
$selectList = implode(', ', array_keys($selectFields));

// Prepare SQL query with JOIN for Equipment Status (if status fields requested)
$joinStatus = false;
$statusFields = ['status', 'action_taken', 'status_date_creation', 'status_remarks'];
foreach ($columns as $colKey) {
    if (in_array($colKey, ['equipment_status', 'action_taken', 'status_date_creation', 'status_remarks'])) {
        $joinStatus = true;
        break;
    }
}

$sql = "SELECT ed.$selectList";
if ($joinStatus) {
    // Join Equipment Status table (assuming foreign key is equipment_id)
    $sql .= ", es.status, es.action_taken, es.status_date_creation, es.status_remarks ";
}
$sql .= " FROM equipment_details ed ";
if ($joinStatus) {
    $sql .= " LEFT JOIN equipment_status es ON ed.equipment_id = es.equipment_id ";
}
$sql .= " WHERE ed.specific_area = :specific_area AND ed.building_loc = :building_loc ";
$sql .= " AND ed.date_created BETWEEN :date_from AND :date_to ";
$sql .= " ORDER BY ed.specific_area, ed.building_loc, ed.date_created ASC";

// Prepare statement
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':specific_area' => $specificArea,
    ':building_loc' => $buildingLoc,
    ':date_from' => $dateFrom,
    ':date_to' => $dateTo,
]);

$data = $stmt->fetchAll();

// Group data by specific_area and building_loc (should all be same since filtered but for safe measure)
$grouped = [];
foreach ($data as $row) {
    $area = $row['specific_area'];
    $loc = $row['building_loc'];
    if (!isset($grouped[$area])) {
        $grouped[$area] = [];
    }
    if (!isset($grouped[$area][$loc])) {
        $grouped[$area][$loc] = [];
    }
    $grouped[$area][$loc][] = $row;
}

// Get current user name & role from session for Prepared By (adjust to your session keys)
$preparedByName = isset($_SESSION['user_firstname'], $_SESSION['user_lastname'])
    ? $_SESSION['user_firstname'] . ' ' . $_SESSION['user_lastname']
    : '________________________';

$preparedByRole = isset($_SESSION['user_role_title'])
    ? $_SESSION['user_role_title']
    : '________________________';

// Document date
$docDate = date('F j, Y');

// Build the HTML for the PDF
$html = '<html><head>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
    h2, h3 { text-align: center; margin: 5px 0; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th, td { border: 1px solid #000; padding: 5px; }
    th { background-color: #f0f0f0; }
    .header-section { margin-bottom: 20px; }
    .footer { margin-top: 40px; }
    .signature-line { border-bottom: 1px solid #000; width: 300px; margin-bottom: 5px; }
</style>
</head><body>';

// Header
$html .= "<h2>Equipment Report</h2>";
$html .= "<div class='header-section'>";
$html .= "<strong>Laboratory/Office:</strong> " . htmlspecialchars($specificArea) . "<br>";
$html .= "<strong>Location:</strong> " . htmlspecialchars($buildingLoc) . "<br>";
$html .= "<strong>Timeline:</strong> " . htmlspecialchars($dateFrom) . " to " . htmlspecialchars($dateTo) . "<br>";
$html .= "</div>";

// Loop over grouped data and create tables
foreach ($grouped as $area => $locations) {
    foreach ($locations as $loc => $rows) {
        $html .= "<h3>Specific Area: " . htmlspecialchars($area) . " | Location: " . htmlspecialchars($loc) . "</h3>";

        // Table header row
        $html .= "<table><thead><tr>";
        foreach ($columns as $colKey) {
            if (isset($columnMap[$colKey])) {
                $html .= "<th>" . $columnMap[$colKey]['label'] . "</th>";
            }
        }
        $html .= "</tr></thead><tbody>";

        // Table rows
        foreach ($rows as $row) {
            $html .= "<tr>";
            foreach ($columns as $colKey) {
                if (!isset($columnMap[$colKey])) continue;

                $cellValue = '';

                // Special combined columns
                if ($colKey === 'asset_description') {
                    $cellValue = trim($row['asset_description_1'] . ' ' . $row['asset_description_2']);
                } elseif ($colKey === 'spec_brand_model') {
                    $cellValue = trim($row['specifications'] . ' / ' . $row['brand'] . ' / ' . $row['model']);
                } elseif ($colKey === 'equipment_status') {
                    $cellValue = $row['status'] ?? '';
                } elseif ($colKey === 'action_taken') {
                    $cellValue = $row['action_taken'] ?? '';
                } elseif ($colKey === 'status_date_creation') {
                    $cellValue = $row['status_date_creation'] ?? '';
                } elseif ($colKey === 'status_remarks') {
                    $cellValue = $row['status_remarks'] ?? '';
                } else {
                    // Regular single field
                    $field = $columnMap[$colKey]['fields'][0];
                    $cellValue = $row[$field] ?? '';
                }

                $html .= "<td>" . htmlspecialchars($cellValue) . "</td>";
            }
            $html .= "</tr>";
        }

        $html .= "</tbody></table>";
    }
}

// Footer with Prepared By info
$html .= "<div class='footer'>";
$html .= "<p><strong>Prepared By:</strong></p>";
$html .= "<p class='signature-line'>{$preparedByName}</p>";
$html .= "<p>{$preparedByRole}</p>";
$html .= "<p><strong>Date:</strong> {$docDate}</p>";
$html .= "</div>";

$html .= "</body></html>";

// Instantiate Dompdf and render PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

// Set paper size
$paperSize = (post('paper_size') === 'legal') ? 'legal' : 'letter';
$dompdf->setPaper($paperSize, 'portrait');

$dompdf->loadHtml($html);
$dompdf->render();

// Output PDF to browser (force download)
$filename = 'equipment_report_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => 1]);

exit();
