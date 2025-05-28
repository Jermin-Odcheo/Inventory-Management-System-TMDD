<?php
session_start();
require '../../../../../config/ims-tmdd.php';
include '../../general/header.php';
include '../../general/sidebar.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

$rbac = new RBACService($pdo, $_SESSION['user_id']);
if (!$rbac->hasPrivilege('Equipment Management', 'View')) {
    echo '
    <div class="container d-flex justify-content-center align-items-center" style="height:70vh; padding-left:300px">
        <div class="alert alert-danger text-center">
            <h1><i class="bi bi-shield-lock"></i> Access Denied</h1>
            <p class="mb-0">You do not have permission to view this page.</p>
        </div>
    </div>';
    exit();
}

$fieldsToShow = [
    'asset_tag' => 'Asset Tag',
    'status' => 'Status',
    'action' => 'Action',
    'remarks' => 'Remarks'
];

// Prepare dropdown filter values
$filterValues = [
    'status' =>  [],
];

$filterQuery = "SELECT NewVal FROM audit_log WHERE Module = 'Equipment Status' AND Status = 'Successful' AND Action IN ('modified', 'create')";
$filterStmt = $pdo->prepare($filterQuery);
$filterStmt->execute();
while ($row = $filterStmt->fetch(PDO::FETCH_ASSOC)) {
    $values = json_decode($row['NewVal'], true);
    foreach ($filterValues as $key => &$arr) {
        if (!empty($values[$key]) && !in_array($values[$key], $arr)) {
            $arr[] = $values[$key];
        }
    }
}
// Get filters
$filters = [
    'status' =>  $_GET['status' ] ?? '',
    'search' => $_GET['search'] ?? '',
    'date_filter_type' => $_GET['date_filter_type'] ?? '',
];

// Base conditions
$conditions = [
    "audit_log.Module = 'Equipment Status'",
    "audit_log.Status = 'Successful'",
    "audit_log.Action IN ('modified', 'create')",
];

// Add text filters
foreach (['status'] as $key) {
    if (!empty($filters[$key])) {
        $conditions[] = "NewVal LIKE :$key";
    }
}

// Add search condition
if (!empty($filters['search'])) {
    $searchParts = [];
    foreach ($fieldsToShow as $key => $label) {
        $searchParts[] = "NewVal LIKE :search_$key";
    }
    $conditions[] = "(" . implode(" OR ", $searchParts) . ")";
}

// Add date filters based on filter type
$params = [];
switch ($filters['date_filter_type']) {
    case 'mdy':
        if (!empty($_GET['date_from'])) {
            $conditions[] = "audit_log.Date_Time >= :date_from";
            $params[':date_from'] = $_GET['date_from'] . " 00:00:00";
        }
        if (!empty($_GET['date_to'])) {
            $conditions[] = "audit_log.Date_Time <= :date_to";
            $params[':date_to'] = $_GET['date_to'] . " 23:59:59";
        }
        break;
    case 'month':
        if (!empty($_GET['month_from'])) {
            $conditions[] = "DATE_FORMAT(audit_log.Date_Time, '%Y-%m') >= :month_from";
            $params[':month_from'] = $_GET['month_from'];
        }
        if (!empty($_GET['month_to'])) {
            $conditions[] = "DATE_FORMAT(audit_log.Date_Time, '%Y-%m') <= :month_to";
            $params[':month_to'] = $_GET['month_to'];
        }
        break;
    case 'year':
        if (!empty($_GET['year_from'])) {
            $conditions[] = "YEAR(audit_log.Date_Time) >= :year_from";
            $params[':year_from'] = $_GET['year_from'];
        }
        if (!empty($_GET['year_to'])) {
            $conditions[] = "YEAR(audit_log.Date_Time) <= :year_to";
            $params[':year_to'] = $_GET['year_to'];
        }
        break;
    case 'month_year':
        if (!empty($_GET['month_year_from'])) {
            $conditions[] = "DATE_FORMAT(audit_log.Date_Time, '%Y-%m') >= :month_year_from";
            $params[':month_year_from'] = $_GET['month_year_from'];
        }
        if (!empty($_GET['month_year_to'])) {
            $conditions[] = "DATE_FORMAT(audit_log.Date_Time, '%Y-%m') <= :month_year_to";
            $params[':month_year_to'] = $_GET['month_year_to'];
        }
        break;
}

// Build query
$whereClause = implode(" AND ", $conditions);
$sql = "SELECT * FROM audit_log WHERE $whereClause ORDER BY TrackID DESC";
$stmt = $pdo->prepare($sql);

// Bind text filter params
foreach (['status'] as $key) {
    if (!empty($filters[$key])) {
        $stmt->bindValue(":$key", '%"' . $key . '":"' . $filters[$key] . '%');
    }
}

// Bind search params
if (!empty($filters['search'])) {
    foreach ($fieldsToShow as $key => $label) {
        $stmt->bindValue(":search_$key", '%' . $filters['search'] . '%');
    }
}

// Bind date filter params
foreach ($params as $param => $value) {
    $stmt->bindValue($param, $value);
}

$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<head>
    <!-- Styles & Scripts -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .filter-container {
            width: 100%;
            padding: 0;
            margin: 0 auto;
            box-sizing: border-box;
        }

        form.row.g-3 {
            width: 100%;
            margin: 0;
            row-gap: 1rem;
        }

        form .form-select {
            width: 100% !important;
            max-width: 100% !important;
        }

        form .form-control:not(.input-group > .form-control) {
            width: 100% !important;
            max-width: 100% !important;
        }


        @media (max-width: 576px) {
            form .col-12 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }
    </style>

    <div class="container-fluid" style="margin-top: 20px; padding-right: 30px">
</head>

<body>
    <div class="main-container">
        <header class="main-header">
            <h1> Equipment Location Change Logs</h1>
        </header>

        <section class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h2><i class="bi bi-list-task"></i> List of Equipment Locations</h2>
            </div>
            <div class="card-body">
                <div class="container-fluid px-0">
                    <!-- Filter div-->
                    <div class="filter-container" id="filterContainer">
                        <!-- Filter Form -->
                        <form method="GET" class="row g-3 align-items-end mb-4 bg-light p-3 rounded shadow-sm">
                            <?php foreach ($filterValues as $key => $options): ?>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <label class="form-label fw-semibold"><?= $fieldsToShow[$key] ?></label>
                                    <select name="<?= $key ?>" class="form-select shadow-sm">
                                        <option value="">All <?= $fieldsToShow[$key] ?></option>
                                        <?php foreach ($options as $val): ?>
                                            <option value="<?= htmlspecialchars($val) ?>" <?= $filters[$key] === $val ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($val) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>

                            <!-- Search bar -->
                            <div class="col-12 col-sm-6 col-md-3">
                                <label class="form-label fw-semibold">Search</label>
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" name="search" class="form-control" placeholder="Search keyword..." value="<?= htmlspecialchars($filters['search']) ?>">
                                </div>
                            </div>

                            <!-- Date Range selector -->
                            <div class="col-12 col-md-3">
                                <label class="form-label fw-semibold">Date Filter Type</label>
                                <select id="dateFilterType" name="date_filter_type" class="form-select shadow-sm">
                                    <option value="" <?= empty($filters['date_filter_type']) ? 'selected' : '' ?>>-- Select Type --</option>
                                    <option value="mdy" <?= $filters['date_filter_type'] === 'mdy' ? 'selected' : '' ?>>Month-Day-Year Range</option>
                                    <option value="month" <?= $filters['date_filter_type'] === 'month' ? 'selected' : '' ?>>Month Range</option>
                                    <option value="year" <?= $filters['date_filter_type'] === 'year' ? 'selected' : '' ?>>Year Range</option>
                                    <option value="month_year" <?= $filters['date_filter_type'] === 'month_year' ? 'selected' : '' ?>>Month-Year Range</option>
                                </select>

                            </div>

                            <!-- MDY Range -->
                            <div class="col-12 col-md-3 date-filter date-mdy d-none">
                                <label class="form-label fw-semibold">Date From</label>
                                <input type="date" name="date_from" class="form-control shadow-sm"
                                    value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>"
                                    placeholder="Start Date (YYYY-MM-DD)">
                            </div>
                            <div class="col-12 col-md-3 date-filter date-mdy d-none">
                                <label class="form-label fw-semibold">Date To</label>
                                <input type="date" name="date_to" class="form-control shadow-sm"
                                    value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                    placeholder="End Date (YYYY-MM-DD)">
                            </div>

                            <!-- Month Range -->
                            <div class="col-12 col-md-3 date-filter date-month d-none">
                                <label class="form-label fw-semibold">Month From</label>
                                <input type="month" name="month_from" class="form-control shadow-sm"
                                    value="<?= htmlspecialchars($_GET['month_from'] ?? '') ?>"
                                    placeholder="e.g., 2023-01">
                            </div>
                            <div class="col-12 col-md-3 date-filter date-month d-none">
                                <label class="form-label fw-semibold">Month To</label>
                                <input type="month" name="month_to" class="form-control shadow-sm"
                                    value="<?= htmlspecialchars($_GET['month_to'] ?? '') ?>"
                                    placeholder="e.g., 2023-12">
                            </div>

                            <!-- Year Range -->
                            <div class="col-12 col-md-3 date-filter date-year d-none">
                                <label class="form-label fw-semibold">Year From</label>
                                <input type="number" name="year_from" class="form-control shadow-sm"
                                    min="1900" max="2100"
                                    placeholder="e.g., 2023"
                                    value="<?= htmlspecialchars($_GET['year_from'] ?? '') ?>">
                            </div>
                            <div class="col-12 col-md-3 date-filter date-year d-none">
                                <label class="form-label fw-semibold">Year To</label>
                                <input type="number" name="year_to" class="form-control shadow-sm"
                                    min="1900" max="2100"
                                    placeholder="e.g., 2025"
                                    value="<?= htmlspecialchars($_GET['year_to'] ?? '') ?>">
                            </div>

                            <!-- Month-Year Range -->
                            <div class="col-12 col-md-3 date-filter date-month_year d-none">
                                <label class="form-label fw-semibold">From (MM-YYYY)</label>
                                <input type="month" name="month_year_from" class="form-control shadow-sm"
                                    value="<?= htmlspecialchars($_GET['month_year_from'] ?? '') ?>"
                                    placeholder="e.g., 2023-01">
                            </div>
                            <div class="col-12 col-md-3 date-filter date-month_year d-none">
                                <label class="form-label fw-semibold">To (MM-YYYY)</label>
                                <input type="month" name="month_year_to" class="form-control shadow-sm"
                                    value="<?= htmlspecialchars($_GET['month_year_to'] ?? '') ?>"
                                    placeholder="e.g., 2023-12">
                            </div>

                            <!-- Buttons-->
                            <div class="col-6 col-md-2 d-grid">
                                <button type="submit" class="btn btn-dark"><i class="bi bi-funnel"></i> Filter</button>
                            </div>

                            <div class="col-6 col-md-2 d-grid">
                                <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary shadow-sm"><i class="bi bi-x-circle"></i> Clear</a>
                            </div>

                            <div class="col-12 col-md-3 d-grid">
                                <a href="equipment_status.php" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Edit Equipment Status</a>
                            </div>
                        </form>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive" id="table">
                        <table id="elTable" class="table">
                            <thead>
                                <tr>
                                    <?php foreach ($fieldsToShow as $label): ?>
                                        <th><?= htmlspecialchars($label) ?></th>
                                    <?php endforeach; ?>
                                    <th>Modified Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($auditLogs)): ?>
                                    <?php foreach ($auditLogs as $log): ?>
                                        <?php $newValues = json_decode($log['NewVal'], true); ?>
                                        <tr>
                                            <?php foreach ($fieldsToShow as $key => $label): ?>
                                                <td><?= isset($newValues[$key]) ? htmlspecialchars($newValues[$key]) : '' ?></td>
                                            <?php endforeach; ?>
                                            <td><?= date("Y-m-d H:i:s", strtotime($log['Date_Time'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="16" class="text-center py-4">
                                            <div class="alert alert-info mb-0">
                                                <i class="bi bi-info-circle me-2"></i> No Equipment Location found. Click on "Create Equipment" to add a new entry.
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($auditLogs as $log): ?>
                                    <?php $newValues = json_decode($log['NewVal'], true); ?>
                                    <tr>
                                        <?php foreach ($fieldsToShow as $key => $label): ?>
                                            <td><?= isset($newValues[$key]) ? htmlspecialchars($newValues[$key]) : '' ?></td>
                                        <?php endforeach; ?>
                                        <td><?= date("Y-m-d H:i:s", strtotime($log['Date_Time'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
</body>

<script>
    // date-time filter script
    document.addEventListener('DOMContentLoaded', function() {
        const filterType = document.getElementById('dateFilterType');
        const allDateFilters = document.querySelectorAll('.date-filter');

        function updateDateFields() {
            allDateFilters.forEach(field => field.classList.add('d-none'));
            if (!filterType.value) return;

            const selected = document.querySelectorAll('.date-' + filterType.value);
            selected.forEach(field => field.classList.remove('d-none'));
        }

        filterType.addEventListener('change', updateDateFields);
        updateDateFields(); // initial load
    });
</script>