<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';
require_once __DIR__ . '/../../../../../control/libs/dompdf/vendor/autoload.php';

use Dompdf\Dompdf;

$data = $_POST;
$columns = $data['columns'] ?? [];

$specific_area = $data['specific_area'];
$building_loc = $data['building_loc'];
$date_from = $data['date_from'];
$date_to = $data['date_to'];

$prepared_by = htmlspecialchars($data['prepared_by']);
$role_department = htmlspecialchars($data['role_department']);

// Map UI column labels to actual SQL columns
$column_map = [
    'asset_tag' => 'ed.asset_tag',
    'property_number' => 'ed.property_number',
    'equipment_name' => 'ed.equipment_name',
    'brand' => 'ed.brand',
    'model' => 'ed.model',
    'serial_number' => 'ed.serial_number',
    'acquisition_date' => 'ed.acquisition_date',
    'date_created' => 'ed.date_created',
    'status' => 'es.status',
    'location_name' => 'el.location_name',
    'room_number' => 'el.room_number',
    'floor' => 'el.floor',
    'building_loc' => 'ed.building_loc',
    'specific_area' => 'ed.specific_area',
    'invoice_no' => 'ei.invoice_no',
    'asset_description' => 'ei.asset_description',
];

$selected_sql_columns = [];
foreach ($columns as $col) {
    if (isset($column_map[$col])) {
        $selected_sql_columns[] = $column_map[$col] . " AS `$col`";
    }
}

$columnList = implode(", ", $selected_sql_columns);

// Construct SQL with necessary joins
$sql = "SELECT $columnList
        FROM equipment_details ed
        LEFT JOIN equipment_status es ON ed.status_id = es.id
        LEFT JOIN equipment_location el ON ed.location_id = el.id
        LEFT JOIN equipment_invoice ei ON ed.invoice_no = ei.id
        WHERE ed.specific_area = ? AND ed.building_loc = ? 
        AND ed.date_created BETWEEN ? AND ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$specific_area, $building_loc, $date_from, $date_to]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// HTML + Styling
$html = '
<!DOCTYPE html>
<html>
<head>
  <style>
    @page {
      size: letter landscape;
      margin: 20px;
    }
    body {
      font-family: Arial, sans-serif;
      font-size: 9px;
    }
    h3, p {
      margin: 4px 0;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }
    th, td {
      border: 1px solid #000;
      padding: 2px 3px;
      text-align: left;
      vertical-align: top;
      word-wrap: break-word;
      overflow-wrap: break-word;
      word-break: break-word;
    }
    th {
      background-color: #f0f0f0;
    }
  </style>
</head>
<body>
';

$html .= '<h3>Preview: ' . htmlspecialchars($data['doc_type']) . ' Report</h3>';
$html .= '<p><strong>Office:</strong> ' . htmlspecialchars($specific_area) . '</p>';
$html .= '<p><strong>Location:</strong> ' . htmlspecialchars($building_loc) . '</p>';
$html .= '<p><strong>Timeline:</strong> ' . htmlspecialchars($date_from) . ' to ' . htmlspecialchars($date_to) . '</p>';
$html .= '<p><strong>Prepared By:</strong> ' . $prepared_by . '<br><strong>' . $role_department . '</strong></p>';

// Table Header
$html .= '<table><thead><tr>';
foreach ($columns as $col) {
    $label = ucwords(str_replace('_', ' ', $col));
    $html .= "<th>" . htmlspecialchars($label) . "</th>";
}
$html .= '</tr></thead><tbody>';

// Table Data
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

// Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'landscape');
$dompdf->render();
$dompdf->stream("preview.pdf", ["Attachment" => false]);
exit;
