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
    'building_loc' => 'Building Location',
    'floor_n' => 'Floor',
    'specific_area' => 'Specific Area',
    'person_responsible' => 'Person Responsible',
    'device_state' => 'Device State',
    'remarks' => 'Remarks'
];

// Prepare dropdown filter values
$filterValues = [
    'building_loc' => [],
    'floor_n' => [],
    'specific_area' => [],
    'device_state' => []
];

$filterQuery = "SELECT NewVal FROM audit_log WHERE Module = 'Equipment Location' AND Status = 'Successful' AND Action IN ('modified', 'create')";
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

// Handle filters
$filters = [
    'building_loc' => $_GET['building_loc'] ?? '',
    'floor_n' => $_GET['floor_n'] ?? '',
    'specific_area' => $_GET['specific_area'] ?? '',
    'device_state' => $_GET['device_state'] ?? '',
    'search' => $_GET['search'] ?? ''
];

$conditions = [
    "audit_log.Module = 'Equipment Location'",
    "audit_log.Status = 'Successful'",
    "audit_log.Action IN ('modified', 'create')"
];

// Add filter-based conditions
foreach (['building_loc', 'floor_n', 'specific_area', 'device_state'] as $key) {
    if (!empty($filters[$key])) {
        $conditions[] = "NewVal LIKE :$key";
    }
}

// Add search condition across all fields
if (!empty($filters['search'])) {
    $searchConditions = [];
    foreach ($fieldsToShow as $key => $label) {
        $searchConditions[] = "NewVal LIKE :search_$key";
    }
    $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
}

$whereClause = implode(" AND ", $conditions);
$query = "SELECT * FROM audit_log WHERE $whereClause ORDER BY TrackID DESC";
$stmt = $pdo->prepare($query);

// Bind filter parameters
foreach (['building_loc', 'floor_n', 'specific_area', 'device_state'] as $key) {
    if (!empty($filters[$key])) {
        $stmt->bindValue(":$key", '%"' . $key . '":"' . $filters[$key] . '%');
    }
}

// Bind search parameters
if (!empty($filters['search'])) {
    foreach ($fieldsToShow as $key => $label) {
        $stmt->bindValue(":search_$key", '%' . $filters['search'] . '%');
    }
}

$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Styles & Scripts -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<link href="../../../styles/css/equipment-manager.css" rel="stylesheet">

<style>
    .btn-group .btn.active {
        background-color: #0d6efd;
        color: white;
    }
</style>

<div class="container-fluid" style="margin-left: 270px; padding-top: 100px; padding-left: 100px;">
    <h2 class="mb-4">Equipment Location Audit Logs</h2>

    <!-- Filter Form -->
    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-2">
            <input type="text" name="search" class="form-control" placeholder="Asset/Person" value="<?= htmlspecialchars($filters['search']) ?>">
        </div>
        <?php foreach ($filterValues as $key => $options): ?>
            <div class="col-md-2">
                <select name="<?= $key ?>" class="form-select">
                    <option value="">All <?= $fieldsToShow[$key] ?></option>
                    <?php foreach ($options as $val): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $filters[$key] === $val ? 'selected' : '' ?>>
                            <?= htmlspecialchars($val) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>
        <div class="col-md-2 d-grid">
            <button type="submit" class="btn btn-primary">Filter</button>
        </div>
        <div class="col-md-2 d-grid">
            <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-secondary">Clear</a>
        </div>
        <div class="col-md-2 d-grid">
            <a href="equipment_location.php" class="btn btn-secondary">Edit Equipment Location</a>
        </div>
    </form>

    <!-- Table -->
    <div class="table-responsive">
        <table id="auditLogTable" class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <?php foreach ($fieldsToShow as $label): ?>
                        <th><?= htmlspecialchars($label) ?></th>
                    <?php endforeach; ?>
                    <th>Modified Time</th>
                </tr>
            </thead>
            <tbody>
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

    <!-- Pagination Controls -->
    <div class="row justify-content-end">
        <div class="col-auto">
            <div class="pagination-container mt-2">
                <span class="pagination-label me-2">Items per page:</span>
                <div class="btn-group" role="group" aria-label="Items per page">
                    <button type="button" class="btn btn-outline-secondary btn-sm items-per-page" data-length="10">10</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm items-per-page" data-length="20">20</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm items-per-page" data-length="30">30</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm items-per-page" data-length="50">50</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DataTable Script -->
<script>
let auditTable;
document.addEventListener("DOMContentLoaded", function () {
    auditTable = $('#auditLogTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthChange: false,
        ordering: true,
        paging: true,
        info: true,
        dom: 'lrtip',
        language: {
            search: "Search all fields:",
            searchPlaceholder: "Type any keyword...",
            paginate: {
                previous: "Previous",
                next: "Next"
            }
        }
    });

    // Default active button (10)
    document.querySelectorAll('.items-per-page').forEach(btn => {
        if (parseInt(btn.getAttribute('data-length')) === 10) {
            btn.classList.add('active');
        }
    });

    // Handle button clicks
    document.querySelectorAll('.items-per-page').forEach(btn => {
        btn.addEventListener('click', function () {
            const newLength = parseInt(this.getAttribute('data-length'));
            auditTable.page.len(newLength).draw();

            document.querySelectorAll('.items-per-page').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        });
    });
});
</script>
