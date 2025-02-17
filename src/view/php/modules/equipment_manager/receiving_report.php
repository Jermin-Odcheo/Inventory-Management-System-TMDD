<?php
// receiving_report.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed
//
//// Check for admin privileges (you should implement your privilege check).
//if (!isset($_SESSION['user_id'])) {
//    header("Location: add_user.php");
//    exit();
//}
//
//// Set the audit log session variables for MySQL triggers.
//if (isset($_SESSION['user_id'])) {
//    // Use the logged-in user's ID.
//    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
//} else {
//    $pdo->exec("SET @current_user_id = NULL");
//}

// Set IP address; adjust as needed if you use a proxy, etc.
$ipAddress = $_SERVER['REMOTE_ADDR'];
$pdo->exec("SET @current_ip = '" . $ipAddress . "'");

// Initialize messages
$errors = [];
$success = "";

// Retrieve any session messages from previous requests
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// ------------------------
// DELETE RECEIVING REPORT
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM receivingreportform WHERE ReceivingReportFormID = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Receiving Report deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['errors'] = ["Error deleting Receiving Report: " . $e->getMessage()];
    }
    header("Location: receiving_report.php");
    exit;
}

// ------------------------
// PROCESS FORM SUBMISSIONS (Add / Update)
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form input (without the ReceivingReportFormID)
    $ReceivingReportNumber         = trim($_POST['ReceivingReportNumber'] ?? '');
    $AccountableIndividual         = trim($_POST['AccountableIndividual'] ?? '');
    $PurchaseOrderNumber           = trim($_POST['PurchaseOrderNumber'] ?? '');
    $AccountableIndividualLocation = trim($_POST['AccountableIndividualLocation'] ?? '');

    // Validate required fields
    if (empty($ReceivingReportNumber) || empty($AccountableIndividual) || empty($PurchaseOrderNumber) || empty($AccountableIndividualLocation)) {
        $_SESSION['errors'] = ["Please fill in all required fields."];
        header("Location: receiving_report.php");
        exit;
    }

    // Check if the form is for "Add" or "Update"
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        try {
            // Do not include ReceivingReportFormID in the INSERT query so that it auto-increments.
            $stmt = $pdo->prepare("INSERT INTO receivingreportform (ReceivingReportNumber, AccountableIndividual, PurchaseOrderNumber, AccountableIndividualLocation)
                                   VALUES (?, ?, ?, ?)");
            $stmt->execute([$ReceivingReportNumber, $AccountableIndividual, $PurchaseOrderNumber, $AccountableIndividualLocation]);
            $_SESSION['success'] = "Receiving Report has been added successfully.";
        } catch (PDOException $e) {
            $_SESSION['errors'] = ["Error adding Receiving Report: " . $e->getMessage()];
        }
        header("Location: receiving_report.php");
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("UPDATE receivingreportform 
                                   SET ReceivingReportNumber = ?, AccountableIndividual = ?, PurchaseOrderNumber = ?, AccountableIndividualLocation = ?
                                   WHERE ReceivingReportFormID = ?");
            $stmt->execute([$ReceivingReportNumber, $AccountableIndividual, $PurchaseOrderNumber, $AccountableIndividualLocation, $id]);
            $_SESSION['success'] = "Receiving Report has been updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['errors'] = ["Error updating Receiving Report: " . $e->getMessage()];
        }
        header("Location: receiving_report.php");
        exit;
    }
}

// ------------------------
// LOAD RECEIVING REPORT DATA FOR EDITING (if applicable)
// ------------------------
$editReceivingReport = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM receivingreportform WHERE ReceivingReportFormID = ?");
        $stmt->execute([$id]);
        $editReceivingReport = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$editReceivingReport) {
            $_SESSION['errors'] = ["Receiving Report not found for editing."];
            header("Location: receiving_report.php");
            exit;
        }
    } catch (PDOException $e) {
        $errors[] = "Error loading Receiving Report for editing: " . $e->getMessage();
    }
}

// ------------------------
// RETRIEVE ALL RECEIVING REPORTS
// ------------------------
try {
    $stmt = $pdo->query("SELECT * FROM receivingreportform ORDER BY ReceivingReportFormID DESC");
    $receivingReports = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Receiving Reports: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Receiving Report Management</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
<?php include '../../general/sidebar.php'; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h2 class="mb-4">Receiving Report</h2>

                <!-- Success Message -->
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php foreach ($errors as $err): ?>
                            <p><i class="bi bi-exclamation-triangle"></i> <?php echo $err; ?></p>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Add/Edit Receiving Report Card -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <?php if ($editReceivingReport): ?>
                            <i class="bi bi-pencil-square"></i> Edit Receiving Report
                            <span class="badge bg-warning text-dark ms-2">Editing Mode</span>
                        <?php else: ?>
                            <i class="bi bi-plus-circle"></i> Add Receiving Report
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <?php if ($editReceivingReport): ?>
                                <input type="hidden" name="action" value="update">
                                <!-- Pass the record ID as a hidden field -->
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($editReceivingReport['ReceivingReportFormID']); ?>">
                            <?php else: ?>
                                <input type="hidden" name="action" value="add">
                            <?php endif; ?>

                            <!-- Removed the ReceivingReportFormID input field since it is auto-incremented -->

                            <div class="mb-3">
                                <label for="ReceivingReportNumber" class="form-label">
                                    <i class="bi bi-file-text"></i> Receiving Report Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="ReceivingReportNumber" name="ReceivingReportNumber" placeholder="Enter Receiving Report Number" required value="<?php echo $editReceivingReport ? htmlspecialchars($editReceivingReport['ReceivingReportNumber']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="AccountableIndividual" class="form-label">
                                    <i class="bi bi-person"></i> Accountable Individual <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="AccountableIndividual" name="AccountableIndividual" placeholder="Enter Accountable Individual" required value="<?php echo $editReceivingReport ? htmlspecialchars($editReceivingReport['AccountableIndividual']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="PurchaseOrderNumber" class="form-label">
                                    <i class="bi bi-file-text"></i> Purchase Order Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="PurchaseOrderNumber" name="PurchaseOrderNumber" placeholder="Enter Purchase Order Number" required value="<?php echo $editReceivingReport ? htmlspecialchars($editReceivingReport['PurchaseOrderNumber']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="AccountableIndividualLocation" class="form-label">
                                    <i class="bi bi-geo-alt"></i> Location <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="AccountableIndividualLocation" name="AccountableIndividualLocation" placeholder="Enter Location" required value="<?php echo $editReceivingReport ? htmlspecialchars($editReceivingReport['AccountableIndividualLocation']) : ''; ?>">
                            </div>
                            <div class="d-flex align-items-center">
                                <button type="submit" class="btn btn-success">
                                    <?php if ($editReceivingReport): ?>
                                        <i class="bi bi-check-circle"></i> Update Receiving Report
                                    <?php else: ?>
                                        <i class="bi bi-check-circle"></i> Add Receiving Report
                                    <?php endif; ?>
                                </button>
                                <?php if ($editReceivingReport): ?>
                                    <a href="receiving_report.php" class="btn btn-secondary ms-2">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- List of Receiving Reports Card -->
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
                        <span><i class="bi bi-list-ul"></i> List of Receiving Reports</span>
                        <div class="input-group w-auto">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" placeholder="Search..." id="rrSearch">
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($receivingReports)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle" id="rrTable">
                                    <thead class="table-dark">
                                    <tr>
                                        <th>Receiving Report ID</th>
                                        <th>RR Number</th>
                                        <th>Accountable Individual</th>
                                        <th>PO Number</th>
                                        <th>Location</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($receivingReports as $rr): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($rr['ReceivingReportFormID']); ?></td>
                                            <td><?php echo htmlspecialchars($rr['ReceivingReportNumber']); ?></td>
                                            <td><?php echo htmlspecialchars($rr['AccountableIndividual']); ?></td>
                                            <td><?php echo htmlspecialchars($rr['PurchaseOrderNumber']); ?></td>
                                            <td><?php echo htmlspecialchars($rr['AccountableIndividualLocation']); ?></td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a class="btn btn-sm btn-outline-primary" href="?action=edit&id=<?php echo htmlspecialchars($rr['ReceivingReportFormID']); ?>">
                                                        <i class="bi bi-pencil-square"></i> Edit
                                                    </a>
                                                    <a class="btn btn-sm btn-outline-danger" href="?action=delete&id=<?php echo htmlspecialchars($rr['ReceivingReportFormID']); ?>" onclick="return confirm('Are you sure you want to delete this Receiving Report?');">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="mb-0">No Receiving Reports found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<!-- JavaScript for Real-Time Table Filtering -->
<script>
    document.getElementById('rrSearch').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const rows = document.querySelectorAll('#rrTable tbody tr');
        rows.forEach(function(row) {
            const rowText = row.textContent.toLowerCase();
            row.style.display = rowText.indexOf(searchValue) > -1 ? '' : 'none';
        });
    });
</script>

<!-- Bootstrap 5 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
