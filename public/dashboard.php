<?php
session_start();
require_once '../config/ims-tmdd.php'; // Keeping your original config

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// ---------------------------------------------------------------------
// 1) FETCH STATS FROM DATABASE
// ---------------------------------------------------------------------

// Total Assets
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM assets");
$totalAssets = (int) $stmt->fetchColumn();

// Total Purchase Orders
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM purchase_orders");
$totalPOs = (int) $stmt->fetchColumn();

// Total Charge Invoices
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM charge_invoices");
$totalInvoices = (int) $stmt->fetchColumn();

// Total Users
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM users");
$totalUsers = (int) $stmt->fetchColumn();

// ---------------------------------------------------------------------
// 2) ASSETS BY BRAND (Existing Chart)
// ---------------------------------------------------------------------
$sql = "SELECT brand, COUNT(*) AS count 
        FROM assets 
        GROUP BY brand 
        ORDER BY count DESC";
$stmt = $pdo->query($sql);
$brandData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$brands = [];
$brandCounts = [];
foreach ($brandData as $row) {
    // If brand is NULL or empty, label it 'Unknown'
    $brands[] = $row['brand'] ?: 'Unknown';
    $brandCounts[] = (int)$row['count'];
}
$brandsJson = json_encode($brands);
$brandCountsJson = json_encode($brandCounts);

// ---------------------------------------------------------------------
// 3) ASSETS BY LOCATION (New Donut Chart)
//     - We assume you have a 'locations' table and 'assets.location_id' 
//       referencing 'locations.id'.
// ---------------------------------------------------------------------
$sqlLoc = "SELECT 
             IFNULL(l.building, 'No Location') AS building, 
             COUNT(a.id) AS total
           FROM assets a
           LEFT JOIN locations l ON a.location_id = l.id
           GROUP BY l.building
           ORDER BY total DESC";
$stmt = $pdo->query($sqlLoc);
$locationData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$locationLabels = [];
$locationCounts = [];
foreach ($locationData as $ld) {
    $buildingName = $ld['building'] ?: 'No Location';
    $locationLabels[] = $buildingName;
    $locationCounts[] = (int) $ld['total'];
}
$locationLabelsJson = json_encode($locationLabels);
$locationCountsJson = json_encode($locationCounts);

// ---------------------------------------------------------------------
// 4) ASSETS ACQUIRED MONTHLY (New Line Chart)
//     - We'll look at the last 12 months for example
// ---------------------------------------------------------------------
$sqlMonthly = "
    SELECT DATE_FORMAT(date_acquired, '%Y-%m') AS month, COUNT(*) AS count
    FROM assets
    WHERE date_acquired >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
      AND date_acquired IS NOT NULL
    GROUP BY DATE_FORMAT(date_acquired, '%Y-%m')
    ORDER BY month
";
$stmt = $pdo->query($sqlMonthly);
$monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$months = [];
$monthCounts = [];
foreach ($monthlyData as $md) {
    $months[] = $md['month'];       // e.g. "2023-08"
    $monthCounts[] = (int)$md['count'];
}
$monthsJson = json_encode($months);
$monthCountsJson = json_encode($monthCounts);

// ---------------------------------------------------------------------
// 5) RECENTLY ADDED ASSETS (Table of last 5 assets)
// ---------------------------------------------------------------------
$sqlRecent = "
    SELECT id, asset_tag, brand, serial_number, date_acquired
    FROM assets
    ORDER BY id DESC
    LIMIT 5
";
$stmt = $pdo->query($sqlRecent);
$recentAssets = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-bg: #f8f9fa;
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        body {
            background-color: var(--primary-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Sidebar */
        #sidebar {
            width: 250px;
            height: 100vh;
            background-color: var(--sidebar-bg);
            position: fixed;
            left: 0;
            top: 0;
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        #sidebar .nav-link {
            color: #fff;
            padding: 15px 20px;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        #sidebar .nav-link:hover {
            background-color: var(--sidebar-hover);
        }

        #sidebar .nav-link i {
            width: 20px;
        }

        /* Main content */
        #main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            margin-top: 20px;
        }

        .navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .flex-container {
            display: flex;
            flex-direction: column;
            /* Arrange children in a column (vertical stack) */
            gap: 20px;
            /* Space between each row */
            padding: 20px;
            /* Optional padding around the container */
            /* Optional: Center the container content */
            justify-content: flex-start;
            align-items: stretch;
        }

        .chart-container {
            background-color: #ffffff;
            /* Background color for each chart container */
            padding: 20px;
            /* Inner padding */
            border: 1px solid #e0e0e0;
            /* Border around each container */
            border-radius: 8px;
            /* Rounded corners */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            /* Subtle shadow */
            /* Optional: Ensure full width */
            width: 100%;
            box-sizing: border-box;
        }

        /* Responsive Adjustments (Optional) */
        @media (max-width: 768px) {
            .flex-container {
                padding: 10px;
                gap: 15px;
            }

            .chart-container {
                padding: 15px;
            }

            #sidebar {
                transform: translateX(-100%);
            }

            #sidebar.active {
                transform: translateX(0);
            }

            #main-content {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div id="sidebar">
        <div class="p-4 text-white">
            <h3 class="mb-0">Asset Management</h3>
        </div>
        <div class="nav flex-column">
            <a href="index.php" class="nav-link">
                <i class="fas fa-laptop"></i>
                <span>Manage Assets</span>
            </a>
            <a href="purchase_order_index.php" class="nav-link">
                <i class="fas fa-shopping-cart"></i>
                <span>Purchase Orders</span>
            </a>
            <a href="invoices_index.php" class="nav-link">
                <i class="fas fa-file-invoice"></i>
                <span>Invoices</span>
            </a>
            <a href="user_index.php" class="nav-link">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </a>
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div id="main-content">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <button class="btn btn-link" id="menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="navbar-brand mb-0">Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h1>
                <div class="ms-auto">
                    <span class="text-muted"><?php echo date('F j, Y'); ?></span>
                </div>
            </div>
        </nav>

        <!-- Stats Grid -->
        <div class="row g-4">
            <!-- Total Assets -->
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-laptop"></i>
                    </div>
                    <h3 class="fs-1 fw-bold mb-1"><?php echo number_format($totalAssets); ?></h3>
                    <p class="text-muted mb-0">Total Assets</p>
                </div>
            </div>

            <!-- Total POs -->
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h3 class="fs-1 fw-bold mb-1"><?php echo number_format($totalPOs); ?></h3>
                    <p class="text-muted mb-0">Total POs</p>
                </div>
            </div>

            <!-- Total Invoices -->
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h3 class="fs-1 fw-bold mb-1"><?php echo number_format($totalInvoices); ?></h3>
                    <p class="text-muted mb-0">Total Invoices</p>
                </div>
            </div>

            <!-- Total Users -->
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="fs-1 fw-bold mb-1"><?php echo number_format($totalUsers); ?></h3>
                    <p class="text-muted mb-0">Total Users</p>
                </div>
            </div>
        </div>
        <!-- Replace the existing .flex-container with a Bootstrap row -->
        <div class="row g-4 mt-4">
            <!-- Chart: Assets by Brand -->
            <div class="col-lg-3 col-md-6">
                <div class="chart-container">
                    <h2 class="h4 mb-4">Assets by Brand</h2>
                    <canvas id="brandChart"></canvas>
                </div>
            </div>

            <!-- Chart: Assets by Location (Donut) -->
            <div class="col-lg-3 col-md-6">
                <div class="chart-container">
                    <h2 class="h4 mb-4">Assets by Location</h2>
                    <canvas id="locationChart"></canvas>
                </div>
            </div>

            <!-- Chart: Assets Acquired Monthly (Line) -->
            <div class="col-lg-3 col-md-6">
                <div class="chart-container">
                    <h2 class="h4 mb-4">Assets Acquired (Last 12 Months)</h2>
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            <!-- Recent Assets -->
            <div class="col-lg-3 col-md-6">
                <div class="chart-container">
                    <h2 class="h4 mb-4">Recently Added Assets</h2>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Asset Tag</th>
                                    <th>Brand</th>
                                    <th>Serial Number</th>
                                    <th>Date Acquired</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentAssets as $ra): ?>
                                    <tr>
                                        <td><?php echo $ra['id']; ?></td>
                                        <td><?php echo htmlspecialchars($ra['asset_tag']); ?></td>
                                        <td><?php echo htmlspecialchars($ra['brand']); ?></td>
                                        <td><?php echo htmlspecialchars($ra['serial_number']); ?></td>
                                        <td><?php echo htmlspecialchars($ra['date_acquired']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>



    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Sidebar Toggle
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // ===== BRAND CHART (Bar) =====
        const brandLabels = <?php echo $brandsJson; ?>; // e.g. ["Dell", "HP", "Unknown", ...]
        const brandCounts = <?php echo $brandCountsJson; ?>; // e.g. [10, 7, 5, ...]

        const ctxBrand = document.getElementById('brandChart').getContext('2d');
        new Chart(ctxBrand, {
            type: 'bar',
            data: {
                labels: brandLabels,
                datasets: [{
                    label: 'Number of Assets',
                    data: brandCounts,
                    backgroundColor: 'rgba(54,162,235,0.6)',
                    borderColor: 'rgba(54,162,235,1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Asset Count'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Brand'
                        }
                    }
                }
            }
        });

        // ===== LOCATION CHART (Donut) =====
        const locationLabels = <?php echo $locationLabelsJson; ?>; // e.g. ["Main HQ", "Warehouse", "No Location"]
        const locationCounts = <?php echo $locationCountsJson; ?>; // e.g. [12, 5, 3]

        const ctxLocation = document.getElementById('locationChart').getContext('2d');
        new Chart(ctxLocation, {
            type: 'doughnut',
            data: {
                labels: locationLabels,
                datasets: [{
                    label: 'Assets by Location',
                    data: locationCounts,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(255, 205, 86, 0.6)',
                        'rgba(201, 203, 207, 0.6)',
                        'rgba(54, 162, 235, 0.6)'
                    ]
                }]
            },
            options: {
                responsive: true
            }
        });

        // ===== MONTHLY CHART (Line) =====
        const months = <?php echo $monthsJson; ?>; // e.g. ["2023-01", "2023-02", ...]
        const monthCounts = <?php echo $monthCountsJson; ?>; // e.g. [2, 5, 3, 10, ...]

        const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctxMonthly, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Assets Acquired',
                    data: monthCounts,
                    fill: false,
                    borderColor: 'rgba(255,99,132,1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Asset Count'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month (YYYY-MM)'
                        }
                    }
                }
            }
        });
    </script>
</body>

</html>