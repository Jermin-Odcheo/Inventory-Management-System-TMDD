<?php
require_once(__DIR__ . '/../../../../config/config.php');

//If not logged in redirect to the LOGIN PAGE
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "public/index.php"); // Redirect to login page
    exit();
}
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
?>
<!-- Sidebar -->
<div class="sidebar">
    <!-- Font Awesome CSS (Ideally load this in your <head> for performance) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>src/view/styles/css/sidebar.css">
    <div></div>
    <h3>Menu</h3>
    <nav>

        <ul>
            <li>
                <a href="<?php echo BASE_URL; ?>src/view/php/clients/admins/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <!-- Example: Only show User Management for Super Admins & Admins -->
            <?php if ($role === 'Super Admin' || $role === 'Admin'): ?>
                <!-- Add admin-specific links here -->
            <?php endif; ?>

            <li class="dropdown-item">
                <button class="dropdown-toggle" aria-expanded="false">
                    <i class="fas fa-history"></i> Logs
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>

                <ul class="dropdown">
                    <li>
                        <a href="<?php echo BASE_URL; ?>src/view/php/clients/admins/audit_log.php">
                            <i class="fas fa-history"></i> Audit Logs
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>src/view/php/clients/admins/archive.php">
                            <i class="fas fa-archive"></i> Archives
                        </a>
                    </li>
                </ul>
            </li>

            <li class="dropdown-item">
                <button class="dropdown-toggle">
                    <i class="fa-solid fa-user"></i> User Management
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown">
                    <li>
                        <a href="<?php echo BASE_URL; ?>src/view/php/modules/user_manager/user_management.php">
                            Manage Accounts
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>src/view/php/modules/role_manager/manage_roles.php">
                            Manage Roles
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>src/view/php/modules/role_manager/manage_privilege.php">
                            Manage Privileges
                        </a>
                    </li>
                </ul>
            </li>

            <li class="dropdown-item">
                <button class="dropdown-toggle">
                    <i class="fa-solid fa-wrench"></i> Equipment Management
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown">
                    <li>
                        <a href="../../modules/equipment_manager/purchase_order.php">
                            Purchase Order
                        </a>
                    </li>
                    <li>
                        <a href="../../modules/equipment_manager/charge_invoice.php">
                            Charge Invoice
                        </a>
                    </li>
                    <li>
                        <a href="../../modules/equipment_manager/receiving_report.php">
                            Receiving Report
                        </a>
                    </li>
                    <li>
                        <a href="../../modules/equipment_manager/equipment_details.php">
                            Equipment Details
                        </a>
                    </li>
                    <li>
                        <a href="../../modules/equipment_manager/equipment_location.php">
                            Equipment Location
                        </a>
                    </li>
                    <li>
                        <a href="../../modules/equipment_manager/equipment_status.php">
                            Equipment Status
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Departments with nested dropdowns -->
            <li class="dropdown-item">
                <button class="dropdown-toggle">
                    <i class="fas fa-building"></i>
                    Departments
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </button>
                <ul class="dropdown">
                    <li>
                        <a href="#"><span>Office of the President</span></a>
                    </li>
                    <li>
                        <a href="#"><span>Office of the Executive Assistant to the President</span></a>
                    </li>
                    <li>
                        <a href="#"><span>Office of the Internal Auditor</span></a>
                    </li>
                    <li class="dropdown-item">
                        <button class="dropdown-toggle" aria-expanded="false">
                            <span>Mission and Identity Cluster</span>
                            <i class="fas fa-chevron-down dropdown-icon"></i>
                        </button>
                        <ul class="dropdown">
                            <li>
                                <a href="#"><span>Office of the Vice President for Mission and Identity</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Center for Campus Ministry</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Community Extension and Outreach Programs Office</span></a>
                            </li>
                            <li>
                                <a href="#"><span>St. Aloysius Gonzaga Parish Office</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Sunflower Child and Youth Wellness Center</span></a>
                            </li>
                        </ul>
                    </li>
                    <li class="dropdown-item">
                        <button class="dropdown-toggle" aria-expanded="false">
                            <span>Academic Cluster</span>
                            <i class="fas fa-chevron-down dropdown-icon"></i>
                        </button>
                        <ul class="dropdown">
                            <li>
                                <a href="#"><span>Office of the Vice President for Academic Affairs</span></a>
                            </li>
                            <li>
                                <a href="#"><span>School of Accountancy, Management, Computing and Information Studies (SAMCIS)</span></a>
                            </li>
                            <li>
                                <a href="#"><span>School of Advanced Studies (SAS)</span></a>
                            </li>
                            <li>
                                <a href="#"><span>School of Engineering and Architecture (SEA)</span></a>
                            </li>
                            <li>
                                <a href="#"><span>School of Law (SOL)</span></a>
                            </li>
                            <li>
                                <a href="#"><span>School of Medicine (SOM)</span></a>
                            </li>
                            <li>
                                <a href="#"><span>School of Nursing, Allied Health, and Biological Sciences Natural Sciences (SONAHBS)</span></a>
                            </li>
                            <li>
                                <a href="#"><span>School of Teacher Education and Liberal Arts (STELA)</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Basic Education School (SLU BEdS)</span></a>
                            </li>
                        </ul>
                    </li>
                    <li class="dropdown-item">
                        <button class="dropdown-toggle" aria-expanded="false">
                            <span>External Relations, Media and Communications and Alumni Affairs</span>
                            <i class="fas fa-chevron-down dropdown-icon"></i>
                        </button>
                        <ul class="dropdown">
                            <li>
                                <a href="#"><span>Office of Institutional Development and Quality Assurance</span></a>
                            </li>
                            <li>
                                <a href="#"><span>University Libraries</span></a>
                            </li>
                            <li>
                                <a href="#"><span>University Registrar’s Office</span></a>
                            </li>
                            <li>
                                <a href="#"><span>University Research and Innovation Center</span></a>
                            </li>
                        </ul>
                    </li>
                    <li class="dropdown-item">
                        <button class="dropdown-toggle" aria-expanded="false">
                            <span>Finance Cluster</span>
                            <i class="fas fa-chevron-down dropdown-icon"></i>
                        </button>
                        <ul class="dropdown">
                            <li>
                                <a href="#"><span>Office of the Vice President for Finance</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Asset Management and Inventory Control Office</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Finance Office</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Printing Operations Office</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Technology Management and Development Department (TMDD)</span></a>
                            </li>
                        </ul>
                    </li>
                    <li class="dropdown-item">
                        <button class="dropdown-toggle" aria-expanded="false">
                            <span>Administration Cluster</span>
                            <i class="fas fa-chevron-down dropdown-icon"></i>
                        </button>
                        <ul class="dropdown">
                            <li>
                                <a href="#"><span>Office of the Vice President for Administration</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Athletics and Fitness Center</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Campus Planning, Maintenance, and Security Department (CPMSD)</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Center for Culture and the Arts (CCA)</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Dental Clinic</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Guidance Center</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Human Resource Department (HRD)</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Students’ Residence Hall</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Medical Clinic</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Office for Legal Affairs (OLA)</span></a>
                            </li>
                            <li>
                                <a href="#"><span>Office of Student Affairs (OSA)</span></a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </li>

    </nav>
</div>
<script src="<?php echo BASE_URL; ?>src/control/js/sidebar.js"></script>