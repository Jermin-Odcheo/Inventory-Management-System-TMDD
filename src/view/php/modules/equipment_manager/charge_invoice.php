<?php
// charge_invoice.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// -----------------------------------------------------------------
// Optionally check for admin privileges (uncomment if needed)
// if (!isset($_SESSION['user_id'])) {
//     header("Location: add_user.php");
//     exit();
// }    
// -----------------------------------------------------------------

// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    // Use the logged-in user's ID.
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    $pdo->exec("SET @current_user_id = NULL");
}

// Set IP address; adjust as needed if you use a proxy.
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
// DELETE CHARGE INVOICE
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM chargeinvoice WHERE ChargeInvoiceID = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Charge Invoice deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['errors'] = ["Error deleting Charge Invoice: " . $e->getMessage()];
    }
    header("Location: charge_invoice.php");
    exit;
}

// ------------------------
// PROCESS FORM SUBMISSIONS (Add / Update)
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form input
    $ChargeInvoiceNo    = trim($_POST['ChargeInvoiceNo'] ?? '');
    $DateOfChargeInvoice = trim($_POST['DateOfChargeInvoice'] ?? '');
    $PurchaseOrderNumber = trim($_POST['PurchaseOrderNumber'] ?? '');

    // Validate required fields
    if (empty($ChargeInvoiceNo) || empty($DateOfChargeInvoice) || empty($PurchaseOrderNumber)) {
        $_SESSION['errors'] = ["Please fill in all required fields."];
        header("Location: charge_invoice.php");
        exit;
    }

    // Check if the form is for "Add" or "Update"
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        try {
            $stmt = $pdo->prepare("INSERT INTO chargeinvoice (ChargeInvoiceNo, DateOfChargeInvoice, PurchaseOrderNumber)
                                   VALUES (?, ?, ?)");
            $stmt->execute([$ChargeInvoiceNo, $DateOfChargeInvoice, $PurchaseOrderNumber]);
            $_SESSION['success'] = "Charge Invoice has been added successfully.";
        } catch (PDOException $e) {
            $_SESSION['errors'] = ["Error adding Charge Invoice: " . $e->getMessage()];
        }
        header("Location: charge_invoice.php");
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("UPDATE chargeinvoice 
                                   SET ChargeInvoiceNo = ?, DateOfChargeInvoice = ?, PurchaseOrderNumber = ?
                                   WHERE ChargeInvoiceID = ?");
            $stmt->execute([$ChargeInvoiceNo, $DateOfChargeInvoice, $PurchaseOrderNumber, $id]);
            $_SESSION['success'] = "Charge Invoice has been updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['errors'] = ["Error updating Charge Invoice: " . $e->getMessage()];
        }
        header("Location: charge_invoice.php");
        exit;
    }
}

// ------------------------
// LOAD CHARGE INVOICE DATA FOR EDITING (if applicable)
// ------------------------
$editChargeInvoice = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM chargeinvoice WHERE ChargeInvoiceID = ?");
        $stmt->execute([$id]);
        $editChargeInvoice = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$editChargeInvoice) {
            $_SESSION['errors'] = ["Charge Invoice not found for editing."];
            header("Location: charge_invoice.php");
            exit;
        }
    } catch (PDOException $e) {
        $errors[] = "Error loading Charge Invoice for editing: " . $e->getMessage();
    }
}

// ------------------------
// RETRIEVE ALL CHARGE INVOICES
// ------------------------
try {
    $stmt = $pdo->query("SELECT * FROM chargeinvoice ORDER BY ChargeInvoiceID DESC");
    $chargeInvoices = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Charge Invoices: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Charge Invoice Management</title>
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
            margin-left: 300px;
            padding: 20px;
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
            <main class="col-md-12 px-md-4 py-4">
                <h2 class="mb-4">Charge Invoice</h2>

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

                <!-- Add/Edit Charge Invoice Card -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <?php if ($editChargeInvoice): ?>
                            <i class="bi bi-pencil-square"></i> Edit Charge Invoice
                            <span class="badge bg-warning text-dark ms-2">Editing Mode</span>
                        <?php else: ?>
                            <i class="bi bi-plus-circle"></i> Add Charge Invoice
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <?php if ($editChargeInvoice): ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($editChargeInvoice['ChargeInvoiceID']); ?>">
                            <?php else: ?>
                                <input type="hidden" name="action" value="add">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="ChargeInvoiceNo" class="form-label">
                                    <i class="bi bi-file-text"></i> Charge Invoice Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="ChargeInvoiceNo" name="ChargeInvoiceNo" placeholder="Enter Charge Invoice Number" required value="<?php echo $editChargeInvoice ? htmlspecialchars($editChargeInvoice['ChargeInvoiceNo']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="DateOfChargeInvoice" class="form-label">
                                    <i class="bi bi-calendar-event"></i> Date of Charge Invoice <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="DateOfChargeInvoice" name="DateOfChargeInvoice" required value="<?php echo $editChargeInvoice ? htmlspecialchars($editChargeInvoice['DateOfChargeInvoice']) : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="PurchaseOrderNumber" class="form-label">
                                    <i class="bi bi-hdd-stack"></i> Purchase Order Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="PurchaseOrderNumber" name="PurchaseOrderNumber" placeholder="Enter Purchase Order Number" required value="<?php echo $editChargeInvoice ? htmlspecialchars($editChargeInvoice['PurchaseOrderNumber']) : ''; ?>">
                            </div>
                            <div class="d-flex align-items-center">
                                <button type="submit" class="btn btn-success">
                                    <?php if ($editChargeInvoice): ?>
                                        <i class="bi bi-check-circle"></i> Update Charge Invoice
                                    <?php else: ?>
                                        <i class="bi bi-check-circle"></i> Add Charge Invoice
                                    <?php endif; ?>
                                </button>
                                <?php if ($editChargeInvoice): ?>
                                    <a href="charge_invoice.php" class="btn btn-secondary ms-2">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- List of Charge Invoices Card -->
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
                        <span><i class="bi bi-list-ul"></i> List of Charge Invoices</span>
                        <div class="input-group w-auto">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" placeholder="Search..." id="ciSearch">
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($chargeInvoices)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle" id="ciTable">
                                    <thead class="table-dark">
                                    <tr>
                                        <th>Charge Invoice ID</th>
                                        <th>Invoice Number</th>
                                        <th>Date of Charge Invoice</th>
                                        <th>Purchase Order Number</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($chargeInvoices as $invoice): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($invoice['ChargeInvoiceID']); ?></td>
                                            <td><?php echo htmlspecialchars($invoice['ChargeInvoiceNo']); ?></td>
                                            <td><?php echo htmlspecialchars($invoice['DateOfChargeInvoice']); ?></td>
                                            <td><?php echo htmlspecialchars($invoice['PurchaseOrderNumber']); ?></td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a class="btn btn-sm btn-outline-primary" href="?action=edit&id=<?php echo htmlspecialchars($invoice['ChargeInvoiceID']); ?>">
                                                        <i class="bi bi-pencil-square"></i> Edit
                                                    </a>
                                                    <a class="btn btn-sm btn-outline-danger" href="?action=delete&id=<?php echo htmlspecialchars($invoice['ChargeInvoiceID']); ?>" onclick="return confirm('Are you sure you want to delete this Charge Invoice?');">
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
                            <p class="mb-0">No Charge Invoices found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<!-- JavaScript for Real-Time Table Filtering -->
<script>
    document.getElementById('ciSearch').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const rows = document.querySelectorAll('#ciTable tbody tr');
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
