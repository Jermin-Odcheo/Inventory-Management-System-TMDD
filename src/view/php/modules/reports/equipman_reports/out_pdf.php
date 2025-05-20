<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';
require_once __DIR__ . '/../../../../../control/libs/dompdf/vendor/autoload.php';
require_once __DIR__ . '/report_query_helper.php';

use Dompdf\Dompdf;

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

$html = '<html><head><style>@page { size: letter landscape; margin: 20px; }
body { font-family: Arial; font-size: 9px; }
th, td { border: 1px solid #000; padding: 3px; }</style></head><body>';
$html .= "<h3>Document Type: " . htmlspecialchars($data['doc_type']) . "</h3>";
$html .= "<p><strong>Office:</strong> {$filters['specific_area']}<br><strong>Location:</strong> {$filters['building_loc']}<br><strong>Timeline:</strong> {$filters['date_from']} to {$filters['date_to']}<br><strong>Prepared By:</strong> $prepared_by - $role_department</p>";
$html .= '<table><thead><tr>';
foreach ($columns as $col) {
    $html .= '<th>' . ucwords(str_replace('_', ' ', $col)) . '</th>';
}
$html .= '</tr></thead><tbody>';
foreach ($results as $row) {
    $html .= '<tr>';
    foreach ($columns as $col) {
        $html .= '<td>' . htmlspecialchars($row[$col] ?? '') . '</td>';
    }
    $html .= '</tr>';
}
$html .= '</tbody></table></body></html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('letter', 'landscape');
$dompdf->render();
$dompdf->stream("report.pdf", ["Attachment" => true]);
exit;
