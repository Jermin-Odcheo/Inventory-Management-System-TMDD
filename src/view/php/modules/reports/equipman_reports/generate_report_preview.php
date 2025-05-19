<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';
require_once __DIR__ . '/../../../../../control/libs/dompdf/vendor/autoload.php';

use Dompdf\Dompdf;

$data = $_POST;
$columns = $data['columns'] ?? [];

$html = '<h3>Preview: ' . htmlspecialchars($data['doc_type']) . ' Report</h3>';
$html .= '<p><strong>Office:</strong> ' . htmlspecialchars($data['specific_area']) . '</p>';
$html .= '<p><strong>Location:</strong> ' . htmlspecialchars($data['building_loc']) . '</p>';
$html .= '<p><strong>Timeline:</strong> ' . $data['date_from'] . ' to ' . $data['date_to'] . '</p>';
$html .= '<p><strong>Prepared By:</strong> ' . htmlspecialchars($data['prepared_by']) . '<br><strong>' . htmlspecialchars($data['role_department']) . '</strong></p>';

$html .= '<table border="1" cellpadding="5"><thead><tr>';
foreach ($columns as $col) {
    $html .= "<th>$col</th>";
}
$html .= '</tr></thead><tbody>';
// Just a preview row
$html .= '<tr>';
foreach ($columns as $col) {
    $html .= '<td>Sample Data</td>';
}
$html .= '</tr></tbody></table>';

$dompdf = new Dompdf();
$paperSize = $data['paper_size'] === 'legal' ? 'legal' : 'letter';
$dompdf->loadHtml($html);
$dompdf->setPaper($paperSize, 'portrait');
$dompdf->render();
$dompdf->stream("preview.pdf", ["Attachment" => false]);
exit;
