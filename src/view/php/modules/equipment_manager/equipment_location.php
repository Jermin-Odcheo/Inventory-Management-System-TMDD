<?php
// equipment_location.php
session_start();
require_once('../../../../../config/ims-tmdd.php'); // Adjust the path as needed

// -----------------------------------------------------------------
// Optionally check for admin privileges (uncomment if needed)
// if (!isset($_SESSION['user_id'])) {
//     header("Location: login.php");
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
// DELETE EQUIPMENT LOCATION
// ------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM equipmentlocation WHERE EquipmentLocationID = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Equipment Location deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['errors'] = ["Error deleting Equipment Location: " . $e->getMessage()];
    }
    header("Location: equipment_location.php");
    exit;
}

// ------------------------
// PROCESS FORM SUBMISSIONS (Add / Update)
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize form input
    $AssetTag          = trim($_POST['AssetTag'] ?? '');
    $BuildingLocation  = trim($_POST['BuildingLocation'] ?? '');
    $FloorNumber       = trim($_POST['FloorNumber'] ?? '');
    $SpecificArea      = trim($_POST['SpecificArea'] ?? '');
    $PersonResponsible = trim($_POST['PersonResponsible'] ?? '');
    $Remarks           = trim($_POST['Remarks'] ?? '');

    // Validate required fields (adjust which fields are required as needed)
    if (empty($AssetTag) || empty($BuildingLocation) || empty($FloorNumber) || empty($SpecificArea) || empty($PersonResponsible)) {
        $_SESSION['errors'] = ["Please fill in all required fields."];
        header("Location: equipment_location.php");
        exit;
    }

    // Check if the form is for "Add" or "Update"
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        try {
            $stmt = $pdo->prepare("INSERT INTO equipmentlocation 
                (AssetTag, BuildingLocation, FloorNumber, SpecificArea, PersonResponsible, Remarks)
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$AssetTag, $BuildingLocation, $FloorNumber, $SpecificArea, $PersonResponsible, $Remarks]);
            $_SESSION['success'] = "Equipment Location has been added successfully.";
        } catch (PDOException $e) {
            $_SESSION['errors'] = ["Error adding Equipment Location: " . $e->getMessage()];
        }
        header("Location: equipment_location.php");
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = $_POST['id'];
        try {
            $stmt = $pdo->prepare("UPDATE equipmentlocation 
                SET AssetTag = ?, BuildingLocation = ?, FloorNumber = ?, SpecificArea = ?, PersonResponsible = ?, Remarks = ?
                WHERE EquipmentLocationID = ?");
            $stmt->execute([$AssetTag, $BuildingLocation, $FloorNumber, $SpecificArea, $PersonResponsible, $Remarks, $id]);
            $_SESSION['success'] = "Equipment Location has been updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['errors'] = ["Error updating Equipment Location: " . $e->getMessage()];
        }
        header("Location: equipment_location.php");
        exit;
    }
}

// ------------------------
// LOAD EQUIPMENT LOCATION DATA FOR EDITING (if applicable)
// ------------------------
$editEquipmentLocation = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM equipmentlocation WHERE EquipmentLocationID = ?");
        $stmt->execute([$id]);
        $editEquipmentLocation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$editEquipmentLocation) {
            $_SESSION['errors'] = ["Equipment Location not found for editing."];
            header("Location: equipment_location.php");
            exit;
        }
    } catch (PDOException $e) {
        $errors[] = "Error loading Equipment Location for editing: " . $e->getMessage();
    }
}

// ------------------------
// RETRIEVE ALL EQUIPMENT LOCATIONS
// ------------------------
try {
    $stmt = $pdo->query("SELECT * FROM equipmentlocation ORDER BY EquipmentLocationID DESC");
    $equipmentLocations = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error retrieving Equipment Locations: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Equipment Location Management</title>
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
                <h2 class="mb-4">Equipment Location</h2>

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

                <!-- Add/Edit Equipment Location Card -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <?php if ($editEquipmentLocation): ?>
                            <i class="bi bi-pencil-square"></i> Edit Equipment Location
                            <span class="badge bg-warning text-dark ms-2">Editing Mode</span>
                        <?php else: ?>
                            <i class="bi bi-plus-circle"></i> Add Equipment Location
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <?php if ($editEquipmentLocation): ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($editEquipmentLocation['EquipmentLocationID']); ?>">
                            <?php else: ?>
                                <input type="hidden" name="action" value="add">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="AssetTag" class="form-label">
                                    <i class="bi bi-tag"></i> Asset Tag <span class="text-danger">*</span>
                                </label>
                                <select name="AssetTag">
                                    <option disabled hidden>Select an Asset Tag</option>
                                    <?php
                                    $stmt = $pdo->prepare("SELECT AssetTag FROM equipmentdetails");
                                    $stmt->execute();
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $selected = ($editEquipmentLocation && $editEquipmentLocation['AssetTag'] == $row['AssetTag']) ? 'selected' : '';
                                        echo '<option value="' . htmlspecialchars($row['AssetTag']) . '" ' . $selected . '>' . htmlspecialchars($row['AssetTag']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="BuildingLocation" class="form-label">
                                    <i class="bi bi-building"></i> Building Location <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="BuildingLocation" name="BuildingLocation" placeholder="Enter Building Location" required value="<?php echo $editEquipmentLocation ? htmlspecialchars($editEquipmentLocation['BuildingLocation']) : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="FloorNumber" class="form-label">
                                    <i class="bi bi-layers"></i> Floor Number <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="FloorNumber" name="FloorNumber" placeholder="Enter Floor Number" required value="<?php echo $editEquipmentLocation ? htmlspecialchars($editEquipmentLocation['FloorNumber']) : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="SpecificArea" class="form-label">
                                    <i class="bi bi-pin-map"></i> Specific Area <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="SpecificArea" name="SpecificArea" placeholder="Enter Specific Area" required value="<?php echo $editEquipmentLocation ? htmlspecialchars($editEquipmentLocation['SpecificArea']) : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="PersonResponsible" class="form-label">
                                    <i class="bi bi-person"></i> Person Responsible <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="PersonResponsible" name="PersonResponsible" placeholder="Enter Person Responsible" required value="<?php echo $editEquipmentLocation ? htmlspecialchars($editEquipmentLocation['PersonResponsible']) : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="Remarks" class="form-label">
                                    <i class="bi bi-chat-left-text"></i> Remarks
                                </label>
                                <textarea class="form-control" id="Remarks" name="Remarks" placeholder="Enter any remarks" rows="3"><?php echo $editEquipmentLocation ? htmlspecialchars($editEquipmentLocation['Remarks']) : ''; ?></textarea>
                            </div>

                            <div class="d-flex align-items-center">
                                <button type="submit" class="btn btn-success">
                                    <?php if ($editEquipmentLocation): ?>
                                        <i class="bi bi-check-circle"></i> Update Equipment Location
                                    <?php else: ?>
                                        <i class="bi bi-check-circle"></i> Add Equipment Location
                                    <?php endif; ?>
                                </button>
                                <?php if ($editEquipmentLocation): ?>
                                    <a href="equipment_location.php" class="btn btn-secondary ms-2">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- List of Equipment Locations Card -->
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center bg-dark text-white">
                        <span><i class="bi bi-list-ul"></i> List of Equipment Locations</span>
                        <div class="input-group w-auto">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" placeholder="Search..." id="eqSearch">
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($equipmentLocations)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle" id="eqTable">
                                    <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Asset Tag</th>
                                        <th>Building Location</th>
                                        <th>Floor Number</th>
                                        <th>Specific Area</th>
                                        <th>Person Responsible</th>
                                        <th>Remarks</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($equipmentLocations as $location): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($location['EquipmentLocationID']); ?></td>
                                            <td><?php echo htmlspecialchars($location['AssetTag']); ?></td>
                                            <td><?php echo htmlspecialchars($location['BuildingLocation']); ?></td>
                                            <td><?php echo htmlspecialchars($location['FloorNumber']); ?></td>
                                            <td><?php echo htmlspecialchars($location['SpecificArea']); ?></td>
                                            <td><?php echo htmlspecialchars($location['PersonResponsible']); ?></td>
                                            <td><?php echo htmlspecialchars($location['Remarks']); ?></td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a class="btn btn-sm btn-outline-primary" href="?action=edit&id=<?php echo htmlspecialchars($location['EquipmentLocationID']); ?>">
                                                        <i class="bi bi-pencil-square"></i> Edit
                                                    </a>
                                                    <a class="btn btn-sm btn-outline-danger" href="?action=delete&id=<?php echo htmlspecialchars($location['EquipmentLocationID']); ?>" onclick="return confirm('Are you sure you want to delete this Equipment Location?');">
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
                            <p class="mb-0">No Equipment Locations found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<!-- JavaScript for Real-Time Table Filtering -->
<script>
    document.getElementById('eqSearch').addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const rows = document.querySelectorAll('#eqTable tbody tr');
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
