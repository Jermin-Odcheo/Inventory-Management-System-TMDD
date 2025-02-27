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
    $stmt = $pdo->query("SELECT PurchaseOrderID, PurchaseOrderNumber, NumberOfUnits, DateOfPurchaseOrder, CreatedDate, ModifiedDate, ItemsSpecification 
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
    <link href="../../../styles/css/equipment-manager.css" rel="stylesheet">

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
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addPOModal">
                            <i class="bi bi-plus-circle"></i> Add Purchase Order
                        </button>
                        <select class="form-select form-select-sm" id="filterSpecification" style="width: auto;">
                            <option value="">Filter Specification</option>
                            <?php
                            $specifications = array_unique(array_column($purchaseOrders, 'ItemsSpecification'));
                            foreach($specifications as $spec) {
                                if(!empty($spec)) {
                                    echo "<option value='" . htmlspecialchars($spec) . "'>" . htmlspecialchars($spec) . "</option>";
                                }
                            }
                            ?>
                        </select>
                        <div class="d-flex gap-2 align-items-center">
                            <select class="form-select form-select-sm" id="dateFilter" style="width: auto;">
                                <option value="">Filter by Date</option>
                                <option value="desc">Newest to Oldest</option>
                                <option value="asc">Oldest to Newest</option>
                                <option value="month">Specific Month</option>
                                <option value="range">Custom Date Range</option>
                            </select>
                            <!-- Date inputs container -->
                            <div id="dateInputsContainer" style="display: none;">
                                <!-- Month Picker -->
                                <div class="d-flex gap-2" id="monthPickerContainer" style="display: none;">
                                    <select class="form-select form-select-sm" id="monthSelect" style="min-width: 130px;">
                                        <option value="">Select Month</option>
                                        <?php
                                        $months = [
                                            'January', 'February', 'March', 'April', 'May', 'June',
                                            'July', 'August', 'September', 'October', 'November', 'December'
                                        ];
                                        foreach ($months as $index => $month) {
                                            echo "<option value='" . ($index + 1) . "'>" . $month . "</option>";
                                        }
                                        ?>
                                    </select>
                                    <select class="form-select form-select-sm" id="yearSelect" style="min-width: 110px;">
                                        <option value="">Select Year</option>
                                        <?php
                                        $currentYear = date('Y');
                                        for ($year = $currentYear; $year >= $currentYear - 10; $year--) {
                                            echo "<option value='" . $year . "'>" . $year . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <!-- Date Range Pickers -->
                                <div class="d-flex gap-2" id="dateRangePickers" style="display: none;">
                                    <input type="date" class="form-control form-control-sm" id="dateFrom" placeholder="From">
                                    <input type="date" class="form-control form-control-sm" id="dateTo" placeholder="To">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-sm mb-0" id="table">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>PO Number</th>
                                <th>Units</th>
                                <th>Date of PO</th>
                                <th>Created Date</th>
                                <th>Modified Date</th>
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
                                    <td><?php echo date('Y-m-d H:i', strtotime($po['CreatedDate'])); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($po['ModifiedDate'])); ?></td>
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
                <div class="container-fluid">
                    <div class="row align-items-center g-3">
                        <!-- Pagination Info -->
                        <div class="col-12 col-sm-auto">
                            <div class="text-muted">
                                Showing <span id="currentPage">1</span> to <span id="rowsPerPage">10</span> of <span
                                        id="totalRows">0</span> entries
                            </div>
                        </div>

                        <!-- Pagination Controls -->
                        <div class="col-12 col-sm-auto ms-sm-auto">
                            <div class="d-flex align-items-center gap-2">
                                <button id="prevPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                    <i class="bi bi-chevron-left"></i>
                                    Previous
                                </button>

                                <select id="rowsPerPageSelect" class="form-select" style="width: auto;">
                                    <option value="10">10</option>
                                    <option value="20" selected>20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>

                                <button id="nextPage" class="btn btn-outline-primary d-flex align-items-center gap-1">
                                    Next
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
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
    <script type="text/javascript" src="<?php echo BASE_URL; ?>src/control/js/pagination.js" defer></script>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Search functionality
            $('#searchPO').on('input', function() {
                filterTable();
            });

            // Specification filter
            $('#filterSpecification').on('change', function() {
                filterTable();
            });

            // Date filter change handler
            $('#dateFilter').on('change', function() {
                const value = $(this).val();
                
                // Hide all date inputs container first
                $('#dateInputsContainer').hide();
                $('#monthPickerContainer, #dateRangePickers').hide();
                $('#dateFrom, #dateTo').hide();  // Explicitly hide date inputs
                
                switch(value) {
                    case 'month':
                        $('#dateInputsContainer').show();
                        $('#monthPickerContainer').show();
                        $('#dateRangePickers').hide();  // Ensure date range pickers are hidden
                        break;
                    case 'range':
                        $('#dateInputsContainer').show();
                        $('#dateRangePickers').show();
                        $('#monthPickerContainer').hide();  // Ensure month picker is hidden
                        $('#dateFrom, #dateTo').show();  // Show date inputs
                        break;
                    default:
                        filterTable();
                        break;
                }
            });

            // Month and Year select change handler
            $('#monthSelect, #yearSelect').on('change', function() {
                if ($('#monthSelect').val() && $('#yearSelect').val()) {
                    filterTable();
                }
            });

            function filterTable() {
                const searchText = $('#searchPO').val().toLowerCase();
                const filterSpec = $('#filterSpecification').val().toLowerCase();
                const filterType = $('#dateFilter').val();
                const selectedMonth = $('#monthSelect').val();
                const selectedYear = $('#yearSelect').val();
                const dateFrom = $('#dateFrom').val();
                const dateTo = $('#dateTo').val();

                $(".table tbody tr").each(function() {
                    const row = $(this);
                    const rowText = row.text().toLowerCase();
                    const specCell = row.find('td:nth-child(5)').text().toLowerCase();
                    const dateCell = row.find('td:nth-child(4)').text();
                    const date = new Date(dateCell);

                    const searchMatch = rowText.indexOf(searchText) > -1;
                    const specMatch = !filterSpec || specCell === filterSpec;
                    let dateMatch = true;

                    switch(filterType) {
                        case 'asc':
                            // Sort rows ascending
                            const tbody = $('.table tbody');
                            const rows = tbody.find('tr').toArray();
                            rows.sort((a, b) => {
                                const dateA = new Date($(a).find('td:nth-child(4)').text());
                                const dateB = new Date($(b).find('td:nth-child(4)').text());
                                return dateA - dateB;
                            });
                            tbody.append(rows);
                            return;
                            
                        case 'desc':
                            // Sort rows descending
                            const tbody2 = $('.table tbody');
                            const rows2 = tbody2.find('tr').toArray();
                            rows2.sort((a, b) => {
                                const dateA = new Date($(a).find('td:nth-child(4)').text());
                                const dateB = new Date($(b).find('td:nth-child(4)').text());
                                return dateB - dateA;
                            });
                            tbody2.append(rows2);
                            return;
                            
                        case 'month':
                            if (selectedMonth && selectedYear) {
                                dateMatch = date.getMonth() + 1 === parseInt(selectedMonth) && 
                                           date.getFullYear() === parseInt(selectedYear);
                            }
                            break;
                            
                        case 'range':
                            if (dateFrom && dateTo) {
                                const from = new Date(dateFrom);
                                const to = new Date(dateTo);
                                to.setHours(23, 59, 59);
                                dateMatch = date >= from && date <= to;
                            }
                            break;
                    }

                    row.toggle(searchMatch && specMatch && dateMatch);
                });
            }

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