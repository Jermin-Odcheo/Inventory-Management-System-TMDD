<?php
function getColumnMap() {
    return [
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
}

function buildReportQuery($columns, $filters) {
    $column_map = getColumnMap();

    $selected_sql_columns = [];
    foreach ($columns as $col) {
        if (isset($column_map[$col])) {
            $selected_sql_columns[] = $column_map[$col] . " AS `$col`";
        }
    }

    $columnList = implode(", ", $selected_sql_columns);

    $sql = "SELECT $columnList
            FROM equipment_details ed
            LEFT JOIN equipment_status es ON ed.asset_tag = es.asset_tag
            LEFT JOIN equipment_location el ON ed.asset_tag = el.asset_tag
            LEFT JOIN charge_invoice ci ON ed.invoice_no = ci.invoice_no
            LEFT JOIN receive_report rr ON ed.rr_no = rr.rr_no
            WHERE el.specific_area = ?
            AND el.building_loc = ?
            AND ed.date_created BETWEEN ? AND ?";

    $params = [
        $filters['specific_area'],
        $filters['building_loc'],
        $filters['date_from'],
        $filters['date_to']
    ];

    return [$sql, $params];
}
