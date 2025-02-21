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
            $_SESSION['errors'] = [
                "A Purchase Order with this PO number already exists."
            ];
        } else {
            try {
                // Simplified INSERT query - removed any reference to 'User' column
                $stmt = $pdo->prepare("INSERT INTO purchaseorder 
                    (PurchaseOrderNumber, NumberOfUnits, DateOfPurchaseOrder, ItemsSpecification) 
                    VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $purchaseOrderNumber,
                    $numberOfUnits,
                    $dateOfPurchaseOrder,
                    $itemsSpecification
                ]);
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

    <div class="container-fluid" style="margin-left: 320px; padding: 20px; width: calc(100vw - 340px);">
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

        <!-- Title moved outside the card -->
        <h2 class="mb-4">Purchase Orders Management</h2>

        <div class="card shadow">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul"></i> List of Purchase Orders</span>
                <div class="input-group w-auto">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchPO" class="form-control" placeholder="Search purchase orders...">
                </div>
            </div>
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addPOModal">
                        <i class="bi bi-plus-circle"></i> Add Purchase Order
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-sm mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>PO Number</th>
                                <th>Units</th>
                                <th>Date of PO</th>
                                <th>Specification</th>
                                <th>Actions</th>
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
                                        <div class="btn-group" role="group">
                                            <a class="btn btn-sm btn-outline-primary edit-po" 
                                               data-id="<?php echo htmlspecialchars($po['PurchaseOrderID']); ?>"
                                               data-po-number="<?php echo htmlspecialchars($po['PurchaseOrderNumber']); ?>"
                                               data-units="<?php echo htmlspecialchars($po['NumberOfUnits']); ?>"
                                               data-date="<?php echo htmlspecialchars($po['DateOfPurchaseOrder']); ?>"
                                               data-spec="<?php echo htmlspecialchars($po['ItemsSpecification']); ?>">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </a>
                                            <a class="btn btn-sm btn-outline-danger delete-po" 
                                               data-id="<?php echo htmlspecialchars($po['PurchaseOrderID']); ?>"
                                               href="#">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Purchase Order Modal -->
    <div class="modal fade" id="addPOModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Purchase Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addPOForm" method="post">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="purchaseOrderNumber" class="form-label">Purchase Order Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="purchaseOrderNumber" name="purchaseOrderNumber" required>
                        </div>
                        <div class="mb-3">
                            <label for="numberOfUnits" class="form-label">Number of Units <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="numberOfUnits" name="numberOfUnits" required>
                        </div>
                        <div class="mb-3">
                            <label for="dateOfPurchaseOrder" class="form-label">Date of Purchase Order <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="dateOfPurchaseOrder" name="dateOfPurchaseOrder" required>
                        </div>
                        <div class="mb-3">
                            <label for="itemsSpecification" class="form-label">Items Specification</label>
                            <textarea class="form-control" id="itemsSpecification" name="itemsSpecification" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Add Purchase Order</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Purchase Order Modal -->
    <div class="modal fade" id="editPOModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Purchase Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editPOForm" method="post">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_po_id">
                        <div class="mb-3">
                            <label for="edit_purchaseOrderNumber" class="form-label">Purchase Order Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_purchaseOrderNumber" name="purchaseOrderNumber" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_numberOfUnits" class="form-label">Number of Units <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit_numberOfUnits" name="numberOfUnits" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_dateOfPurchaseOrder" class="form-label">Date of Purchase Order <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="edit_dateOfPurchaseOrder" name="dateOfPurchaseOrder" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_itemsSpecification" class="form-label">Items Specification</label>
                            <textarea class="form-control" id="edit_itemsSpecification" name="itemsSpecification" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Search functionality
            $('#searchPO').on('input', function() {
                var searchText = $(this).val().toLowerCase();
                $(".table tbody tr").each(function() {
                    var rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.indexOf(searchText) > -1);
                });
            });

            // Edit Purchase Order
            $('.edit-po').click(function() {
                var id = $(this).data('id');
                var poNumber = $(this).data('po-number');
                var units = $(this).data('units');
                var date = $(this).data('date');
                var spec = $(this).data('spec');
                
                $('#edit_po_id').val(id);
                $('#edit_purchaseOrderNumber').val(poNumber);
                $('#edit_numberOfUnits').val(units);
                $('#edit_dateOfPurchaseOrder').val(date);
                $('#edit_itemsSpecification').val(spec);
                
                $('#editPOModal').modal('show');
            });

            // Delete Purchase Order
            $('.delete-po').click(function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to delete this Purchase Order?')) {
                    window.location.href = 'purchase_order.php?action=delete&id=' + $(this).data('id');
                }
            });
        });
    </script>
</body>

</html>