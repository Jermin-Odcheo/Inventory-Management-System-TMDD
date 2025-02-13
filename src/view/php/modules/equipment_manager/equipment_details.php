<?php
// purchase_order.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

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
// RETRIEVE ALL EQUIPMENT DETAILS
// ------------------------
try {
    $stmt = $pdo->query("SELECT *
                         FROM equipmentdetails 
                         ORDER BY DateAcquired DESC");
    $equipmentDetails = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Equipment Details: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Equipment Details</title>
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
                    <h2 class="mb-4">Equipment Details</h2>

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
                            <i class="bi bi-plus-circle"></i> Add Equipment
                        </div>
                        <div class="card-body">
                            <form method="post" action="process_purchase_order.php">
                                <input type="hidden" name="action" value="add">
                                
                                <div class="mb-3">
                                    <label for="equipmentDetailsID" class="form-label">
                                        <i class="bi bi-file-text"></i> Equipment Details ID <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" class="form-control" id="equipmentDetailsID" name="equipmentDetailsID" placeholder="Enter Equipment Details ID" required>
                                </div>

                                <div class="mb-3">
                                    <label for="assetTag" class="form-label">Asset Tag <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="assetTag" name="assetTag" placeholder="Enter Asset Tag" required>
                                </div>

                                <div class="mb-3">
                                    <label for="assetDescription1" class="form-label">Asset Description 1</label>
                                    <input type="text" class="form-control" id="assetDescription1" name="assetDescription1" placeholder="Enter Asset Description 1">
                                </div>

                                <div class="mb-3">
                                    <label for="assetDescription2" class="form-label">Asset Description 2</label>
                                    <input type="text" class="form-control" id="assetDescription2" name="assetDescription2" placeholder="Enter Asset Description 2">
                                </div>

                                <div class="mb-3">
                                    <label for="specification" class="form-label">Specification</label>
                                    <input type="text" class="form-control" id="specification" name="specification" placeholder="Enter Specification">
                                </div>

                                <div class="mb-3">
                                    <label for="brand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" id="brand" name="brand" placeholder="Enter Brand">
                                </div>

                                <div class="mb-3">
                                    <label for="model" class="form-label">Model</label>
                                    <input type="text" class="form-control" id="model" name="model" placeholder="Enter Model">
                                </div>

                                <div class="mb-3">
                                    <label for="serialNumber" class="form-label">Serial Number</label>
                                    <input type="text" class="form-control" id="serialNumber" name="serialNumber" placeholder="Enter Serial Number">
                                </div>

                                <div class="mb-3">
                                    <label for="dateAcquired" class="form-label">Date Acquired <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="dateAcquired" name="dateAcquired" required>
                                </div>

                                <div class="mb-3">
                                    <label for="receivingReportFormNumber" class="form-label">Receiving Report Form Number</label>
                                    <input type="text" class="form-control" id="receivingReportFormNumber" name="receivingReportFormNumber" placeholder="Enter Report Form Number">
                                </div>

                                <div class="mb-3">
                                    <label for="accountableIndividualLocation" class="form-label">Accountable Individual Location</label>
                                    <input type="text" class="form-control" id="accountableIndividualLocation" name="accountableIndividualLocation" placeholder="Enter Location">
                                </div>

                                <div class="mb-3">
                                    <label for="accountableIndividual" class="form-label">Accountable Individual</label>
                                    <input type="text" class="form-control" id="accountableIndividual" name="accountableIndividual" placeholder="Enter Individual's Name">
                                </div>

                                <div class="mb-3">
                                    <label for="remarks" class="form-label">Remarks</label>
                                    <textarea class="form-control" id="remarks" name="remarks" rows="3" placeholder="Enter Remarks"></textarea>
                                </div>

                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-circle"></i> Add Equipment Details
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- List of Equipment Details Card -->
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
                        <span><i class="bi bi-list-ul"></i> List of Equipment Details</span>
                        <!-- Real-Time Search Input -->
                        <div class="input-group w-auto">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" placeholder="Search..." id="equipmentSearch">
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($equipmentDetails)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle" id="equipmentTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Asset Tag</th>
                                            <th>Description 1</th>
                                            <th>Description 2</th>
                                            <th>Specification</th>
                                            <th>Brand</th>
                                            <th>Model</th>
                                            <th>Serial Number</th>
                                            <th>Date Acquired</th>
                                            <th>Accountable Individual</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($equipmentDetails as $equipment): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($equipment['EquipmentDetailsID']); ?></td>
                                                <td><?php echo htmlspecialchars($equipment['AssetTag']); ?></td>
                                                <td><?php echo htmlspecialchars($equipment['AssetDescription1']); ?></td>
                                                <td><?php echo htmlspecialchars($equipment['AssetDescription2']); ?></td>
                                                <td><?php echo htmlspecialchars($equipment['Specification']); ?></td>
                                                <td><?php echo htmlspecialchars($equipment['Brand']); ?></td>
                                                <td><?php echo htmlspecialchars($equipment['Model']); ?></td>
                                                <td><?php echo htmlspecialchars($equipment['SerialNumber']); ?></td>
                                                <td><?php echo htmlspecialchars($equipment['DateAcquired']); ?></td>
                                                <td><?php echo htmlspecialchars($equipment['AccountableIndividual']); ?></td>
                                                <td class="text-center">
                                                    <!-- Inline Button Group for Actions -->
                                                    <div class="btn-group" role="group">
                                                        <a class="btn btn-sm btn-outline-primary" href="<?php echo $_SERVER['PHP_SELF']; ?>?action=edit&id=<?php echo htmlspecialchars($equipment['EquipmentDetailsID']); ?>">
                                                            <i class="bi bi-pencil-square"></i> Edit
                                                        </a>
                                                        <a class="btn btn-sm btn-outline-danger" href="<?php echo $_SERVER['PHP_SELF']; ?>?action=delete&id=<?php echo htmlspecialchars($equipment['EquipmentDetailsID']); ?>" onclick="return confirm('Are you sure you want to delete this equipment record?');">
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
                            <p class="mb-0">No equipment details found.</p>
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