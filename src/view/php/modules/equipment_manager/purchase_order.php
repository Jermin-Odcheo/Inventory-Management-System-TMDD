<?php
// purchase_order.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed
// Check for admin privileges (you should implement your privilege check).
if (!isset($_SESSION['user_id'])) {
    header("Location: add_user.php");
    exit();
}
// Set the audit log session variables for MySQL triggers.
if (isset($_SESSION['user_id'])) {
    // Use the logged-in user's ID.
    $pdo->exec("SET @current_user_id = " . (int)$_SESSION['user_id']);
} else {
    // For anonymous actions, you might set a default.
    $pdo->exec("SET @current_user_id = NULL");
}

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
// DELETE PURCHASE ORDER
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM purchaseorder WHERE PurchaseOrderID = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Purchase Order deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['errors'] = ["Error deleting Purchase Order: " . $e->getMessage()];
    }
    header("Location: purchase_order.php");
    exit;
}

// ------------------------
// PROCESS FORM SUBMISSIONS (Add / Update)
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form input
    $purchaseOrderNumber = trim($_POST['purchaseOrderNumber'] ?? '');
    $numberOfUnits       = trim($_POST['numberOfUnits'] ?? '');
    $dateOfPurchaseOrder = trim($_POST['dateOfPurchaseOrder'] ?? '');
    $itemsSpecification  = trim($_POST['itemsSpecification'] ?? '');

    // Validate required fields
    if (empty($purchaseOrderNumber) || empty($numberOfUnits) || empty($dateOfPurchaseOrder)) {
        $_SESSION['errors'] = ["Please fill in all required fields (PO Number, Number of Units, and Date of Purchase Order)."];
        header("Location: purchase_order.php");
        exit;
    }

    // Check if the form is for "Add" or "Update"
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        // Check for an existing PO number
        $stmt = $pdo->prepare("SELECT PurchaseOrderID FROM purchaseorder WHERE PurchaseOrderNumber = ?");
        $stmt->execute([$purchaseOrderNumber]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Instead of adding a duplicate, show a warning with a link to edit
            $_SESSION['errors'] = [
                "A Purchase Order with this PO number already exists. <a href=\"" . $_SERVER['PHP_SELF'] . "?action=edit&id={$existing['PurchaseOrderID']}\">Click here to edit it.</a>"
            ];
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO purchaseorder (PurchaseOrderNumber, NumberOfUnits, DateOfPurchaseOrder, ItemsSpecification)
                                       VALUES (?, ?, ?, ?)");
                $stmt->execute([$purchaseOrderNumber, $numberOfUnits, $dateOfPurchaseOrder, $itemsSpecification]);
                $_SESSION['success'] = "Purchase Order added successfully.";
            } catch (PDOException $e) {
                $_SESSION['errors'] = ["Error adding Purchase Order: " . $e->getMessage()];
            }
        }
        header("Location: purchase_order.php");
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("UPDATE purchaseorder 
                                   SET PurchaseOrderNumber = ?, NumberOfUnits = ?, DateOfPurchaseOrder = ?, ItemsSpecification = ? 
                                   WHERE PurchaseOrderID = ?");
            $stmt->execute([$purchaseOrderNumber, $numberOfUnits, $dateOfPurchaseOrder, $itemsSpecification, $id]);
            $_SESSION['success'] = "Purchase Order updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['errors'] = ["Error updating Purchase Order: " . $e->getMessage()];
        }
        header("Location: purchase_order.php");
        exit;
    }
}

// ------------------------
// LOAD PURCHASE ORDER DATA FOR EDITING (if applicable)
// ------------------------
$editPurchaseOrder = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int) $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM purchaseorder WHERE PurchaseOrderID = ?");
        $stmt->execute([$id]);
        $editPurchaseOrder = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$editPurchaseOrder) {
            $_SESSION['errors'] = ["Purchase Order not found for editing."];
            header("Location: purchase_order.php");
            exit;
        }
    } catch (PDOException $e) {
        $errors[] = "Error loading Purchase Order for editing: " . $e->getMessage();
    }
}

// ------------------------
// RETRIEVE ALL PURCHASE ORDERS
// ------------------------
try {
    $stmt = $pdo->query("SELECT PurchaseOrderID, PurchaseOrderNumber, NumberOfUnits, DateOfPurchaseOrder, ItemsSpecification 
                         FROM purchaseorder 
                         ORDER BY DateOfPurchaseOrder DESC");
    $purchaseOrders = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Purchase Orders: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Purchase Orders Management</title>
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
                    <h2 class="mb-4">Purchase Orders</h2>

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
                                <!-- Note: The duplicate warning message contains HTML, so we output it raw. -->
                                <p><i class="bi bi-exclamation-triangle"></i> <?php echo $err; ?></p>
                            <?php endforeach; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Add/Edit Purchase Order Card -->
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header bg-dark text-white">
                            <?php if ($editPurchaseOrder): ?>
                                <i class="bi bi-pencil-square"></i> Edit Purchase Order
                                <span class="badge bg-warning text-dark ms-2">Editing Mode</span>
                            <?php else: ?>
                                <i class="bi bi-plus-circle"></i> Add Purchase Order
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <?php if ($editPurchaseOrder): ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPurchaseOrder['PurchaseOrderID']); ?>">
                                <?php else: ?>
                                    <input type="hidden" name="action" value="add">
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label for="purchaseOrderNumber" class="form-label">
                                        <i class="bi bi-file-text"></i> Purchase Order Number <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="purchaseOrderNumber" name="purchaseOrderNumber" placeholder="Enter PO Number" required value="<?php echo $editPurchaseOrder ? htmlspecialchars($editPurchaseOrder['PurchaseOrderNumber']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="numberOfUnits" class="form-label">
                                        <i class="bi bi-hdd-stack"></i> Number of Units <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control" id="numberOfUnits" name="numberOfUnits" placeholder="Enter number of units" required value="<?php echo $editPurchaseOrder ? htmlspecialchars($editPurchaseOrder['NumberOfUnits']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="dateOfPurchaseOrder" class="form-label">
                                        <i class="bi bi-calendar-event"></i> Date of Purchase Order <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="dateOfPurchaseOrder" name="dateOfPurchaseOrder" required value="<?php echo $editPurchaseOrder ? htmlspecialchars($editPurchaseOrder['DateOfPurchaseOrder']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="itemsSpecification" class="form-label">
                                        <i class="bi bi-card-text"></i> Items Specification
                                    </label>
                                    <textarea class="form-control" id="itemsSpecification" name="itemsSpecification" rows="3" placeholder="Enter items specification"><?php echo $editPurchaseOrder ? htmlspecialchars($editPurchaseOrder['ItemsSpecification']) : ''; ?></textarea>
                                </div>
                                <div class="d-flex align-items-center">
                                    <button type="submit" class="btn btn-success">
                                        <?php if ($editPurchaseOrder): ?>
                                            <i class="bi bi-check-circle"></i> Update Purchase Order
                                        <?php else: ?>
                                            <i class="bi bi-check-circle"></i> Add Purchase Order
                                        <?php endif; ?>
                                    </button>
                                    <!-- Cancel button appears only in editing mode -->
                                    <?php if ($editPurchaseOrder): ?>
                                        <a href="purchase_order.php" class="btn btn-secondary ms-2">
                                            <i class="bi bi-x-circle"></i> Cancel
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- List of Purchase Orders Card -->
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
                            <span><i class="bi bi-list-ul"></i> List of Purchase Orders</span>
                            <!-- Real-Time Search Input -->
                            <div class="input-group w-auto">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" placeholder="Search..." id="poSearch">
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($purchaseOrders)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover align-middle" id="poTable">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>ID</th>
                                                <th>PO Number</th>
                                                <th>Units</th>
                                                <th>Date of PO</th>
                                                <th>Specification</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($purchaseOrders as $po): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($po['PurchaseOrderID']); ?></td>
                                                    <td><?php echo htmlspecialchars($po['PurchaseOrderNumber']); ?></td>
                                                    <td><?php echo htmlspecialchars($po['NumberOfUnits']); ?></td>
                                                    <td><?php echo htmlspecialchars($po['DateOfPurchaseOrder']); ?></td>
                                                    <td><?php echo htmlspecialchars($po['ItemsSpecification']); ?></td>
                                                    <td class="text-center">
                                                        <!-- Inline Button Group for Actions -->
                                                        <div class="btn-group" role="group">
                                                            <a class="btn btn-sm btn-outline-primary" href="<?php echo $_SERVER['PHP_SELF']; ?>?action=edit&id=<?php echo htmlspecialchars($po['PurchaseOrderID']); ?>">
                                                                <i class="bi bi-pencil-square"></i> Edit
                                                            </a>
                                                            <a class="btn btn-sm btn-outline-danger" href="<?php echo $_SERVER['PHP_SELF']; ?>?action=delete&id=<?php echo htmlspecialchars($po['PurchaseOrderID']); ?>" onclick="return confirm('Are you sure you want to delete this Purchase Order?');">
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
                                <p class="mb-0">No purchase orders found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <!-- JavaScript for Real-Time Table Filtering -->
    <script>
        document.getElementById('poSearch').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#poTable tbody tr');
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