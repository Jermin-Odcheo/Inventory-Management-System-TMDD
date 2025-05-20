<?php 
// generate_report_preview.php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';
require_once __DIR__ . '/../../../../../control/libs/dompdf/vendor/autoload.php';

use Dompdf\Dompdf;

$data = $_POST;
file_put_contents('debug_post.txt', print_r($_POST, true));

// Handle POST data
$columns = $data['columns'] ?? [];

$specific_area = $data['specific_area'];
$building_loc = $data['building_loc'];
$date_from = $data['date_from'];
$date_to = $data['date_to'];

$prepared_by = htmlspecialchars($data['prepared_by']);
$role_department = htmlspecialchars($data['role_department']);
$prep_date = htmlspecialchars($data['prepared_date']); // corrected name to match form

// Column SQL mapping
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

// ✅ Fixed JOIN conditions — moved filters inside LEFT JOIN to preserve unmatched entries
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

// Start PDF HTML
$html = '
<!DOCTYPE html>
<html>
<head>
  <style>
    @page { size: letter landscape; margin: 20px; }
    body { font-family: Arial, sans-serif; font-size: 9px; }
    h3, p { margin: 4px 0; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td {
      border: 1px solid #000;
      padding: 2px 3px;
      text-align: left;
      vertical-align: top;
      word-wrap: break-word;
    }
    th { background-color: #f0f0f0; }
  </style>
</head>
<body>
';

$html .= '<h3>Preview: ' . htmlspecialchars($data['docTypeSelect'] ?? 'Custom') . ' Report</h3>';
$html .= '<p><strong>Office:</strong> ' . htmlspecialchars($specific_area) . '</p>';
$html .= '<p><strong>Location:</strong> ' . htmlspecialchars($building_loc) . '</p>';
$html .= '<p><strong>Timeline:</strong> ' . htmlspecialchars($date_from) . ' to ' . htmlspecialchars($date_to) . '</p>';
$html .= '<p><strong>Prepared By:</strong> ' . $prepared_by . '<br><strong>' . $role_department . '</strong><br><strong>Date:</strong> ' . $prep_date . '</p>';

// Build Table Header
$html .= '<table><thead><tr>';
foreach ($columns as $col) {
    $label = ucwords(str_replace('_', ' ', $col));
    $html .= "<th>" . htmlspecialchars($label) . "</th>";
}
$html .= '</tr></thead><tbody>';

// Table Rows
if (!empty($results)) {
    foreach ($results as $row) {
        $html .= '<tr>';
        foreach ($columns as $col) {
            $value = isset($row[$col]) ? htmlspecialchars($row[$col]) : '';
            $html .= "<td>$value</td>";
        }
        $html .= '</tr>';
    }
} else {
    $html .= '<tr><td colspan="' . count($columns) . '" style="text-align:center;">No data available for selected filters.</td></tr>';
}

$html .= '</tbody></table>';
$html .= '</body></html>';

// Generate DOMPDF preview
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'landscape');
$dompdf->render();
$dompdf->stream("preview.pdf", ["Attachment" => false]);
exit;