<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';
require_once __DIR__ . '/../../../../../control/RBACService.php';
session_start();

// ✅ RBAC check
$rbac = new RBACService($pdo, $_SESSION['user_id']);
$rbac->requirePrivilege('Reports', 'View');

$today = date('Y-m-d');
$todayDisplay = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Generate Equipment Report</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <style>
    body { background-color: #f5f5f5; }
    .main-content {
      margin-top: 75px;
      padding: 0 100px;
    }
    .form-section {
      background: #ffffff;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 0 10px rgba(0,0,0,0.05);
    }
    .preview-box {
      border: 1px solid #ccc;
      height: 600px;
      width: 100%;
    }
    iframe { background-color: #fff; }
  </style>
</head>
<body>
<?php include_once __DIR__ . '/../../../general/header.php'; ?>
<?php include_once __DIR__ . '/../../../general/sidebar.php'; ?>

<div class="main-content">
  <div class="container-fluid">
    <h2 class="mb-4">Generate Equipment Report</h2>

    <form id="mainForm" target="previewFrame" method="POST" action="generate_report_preview.php">
      <div class="form-section">
        <h5>Document Specifications</h5>
        <div class="row">
          <div class="col-md-6">
            <label class="form-label">Document Type:</label>
            <select class="form-select" id="docTypeSelect" name="docTypeSelect" required>
              <option value="summarized">Summarized Report</option>
              <option value="detailed">Detailed Report</option>
              <option value="custom" selected>Custom Report</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Location:</label>
            <select name="building_loc" class="form-select" id="buildingLocSelect" required>
            <option value="all">All Locations</option>
              <?php
              $stmt = $pdo->query("SELECT DISTINCT building_loc FROM equipment_location ORDER BY building_loc");
              while ($row = $stmt->fetch()) {
                echo "<option value='" . htmlspecialchars($row['building_loc']) . "'>" . htmlspecialchars($row['building_loc']) . "</option>";
              }
              ?>
            </select>
          </div>
          <div class="col-md-6 mt-3">
            <label class="form-label">Laboratory/Office:</label>
            <select name="specific_area" class="form-select" id="specificAreaSelect" required>
            <option value="all">All Locations</option>
              <?php
              $stmt = $pdo->query("SELECT DISTINCT specific_area FROM equipment_location ORDER BY specific_area");
              while ($row = $stmt->fetch()) {
                echo "<option value='" . htmlspecialchars($row['specific_area']) . "'>" . htmlspecialchars($row['specific_area']) . "</option>";
              }
              ?>
            </select>
          </div>
          <div class="col-md-6 mt-3">
            <label class="form-label">Date From:</label>
            <input type="date" name="date_from" class="form-control" required value="<?= $today ?>">
          </div>
          <div class="col-md-6 mt-2">
            <label class="form-label">Date To:</label>
            <input type="date" name="date_to" class="form-control" required value="<?= $today ?>">
          </div>
        </div>
      </div>

      <div class="form-section">
        <h5>Generated Table Columns</h5>
        <h6>*Select the columns you want to include*</h6>
        <div class="mb-2">
          <button type="button" class="btn btn-outline-secondary btn-sm" id="clearCheckboxes">Clear All</button>
        </div>
        <div class="row" id="checkboxContainer">
          <?php
          $columns = [
            'asset_tag'              => 'Asset Tag',
            'asset_description'      => 'Asset Description 1 & 2',
            'spec_brand_model'       => 'Specifications, Brand and Model',
            'serial_number'          => 'Serial Number',
            'date_acquired'          => 'Date Acquired',
            'invoice_no'             => 'Invoice No',
            'receiving_report'       => 'Receiving Report',
            'accountable_individual' => 'Accountable Individual',
            'remarks'                => 'Remarks',
            'date_created'           => 'Date Created',
            'last_date_modified'     => 'Last Date Modified',
            'equipment_status'       => 'Equipment Status',
            'action_taken'           => 'Action Taken',
            'status_date_creation'   => 'Status Date Creation',
            'status_remarks'         => 'Status Remarks'
          ];
          foreach ($columns as $val => $label) {
            echo '<div class="col-md-4"><div class="form-check">';
            echo "<input class='form-check-input report-checkbox' type='checkbox' name='columns[]' value='$val' id='$val'>";
            echo "<label class='form-check-label' for='$val'>$label</label>";
            echo '</div></div>';
          }
          ?>
        </div>
      </div>

      <div class="form-section">
        <h5>Preparation</h5>
        <div class="row">
          <div class="col-md-6">
            <label class="form-label">Prepared By:</label>
            <input type="text" name="prepared_by" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Role Title / Department:</label>
            <input type="text" name="role_department" class="form-control" required>
          </div>
          <div class="col-md-6 mt-2">
            <label class="form-label">Date:</label>
            <input type="text" name="prepared_date" class="form-control" value="<?= $todayDisplay ?>" readonly>
          </div>
        </div>
      </div>

      <div class="form-section">
        <h5>Export Options</h5>
        <div class="row align-items-end">
          <div class="col-md-4">
            <label class="form-label">Download Type:</label>
            <select name="export_type" class="form-select" id="exportTypeSelect" required>
              <option value="pdf">PDF</option>
              <option value="excel">Excel</option>
              <option value="word">Word</option>
            </select>
          </div>
          <div class="col-md-8 d-flex justify-content-end gap-2 mt-3">
            <button type="button" class="btn btn-dark" id="previewBtn">Preview Report</button>
            <button type="submit" class="btn btn-primary" id="downloadBtn">Download Report</button>
          </div>
        </div>
        <div id="docSpecsSection" class="mt-3">
          <h6>Document Specifications (Visible for PDF/Word)</h6>
          <label class="form-label">Paper Size:</label>
          <select name="paper_size" class="form-select" id="paperSizeSelect">
            <option value="letter">Letter (8x11")</option>
            <option value="legal">Legal (8x14")</option>
          </select>
        </div>
        <input type="hidden" name="orientation" value="landscape">
      </div>
    </form>

    <div class="form-section mt-4">
      <h4>Document Preview</h4>
      <iframe name="previewFrame" class="preview-box"></iframe>
    </div>
  </div>
</div>

<!-- JS includes -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("mainForm");
    document.getElementById("previewBtn").addEventListener("click", function () {
  const form = document.getElementById("mainForm");
  form.action = "generate_report_preview.php";
  form.target = "previewFrame"; // 👈 stays in iframe
  form.submit();
});

document.getElementById("downloadBtn").addEventListener("click", function () {
  const form = document.getElementById("mainForm");
  const exportType = document.getElementById("exportTypeSelect").value;
  form.action = "generate_report.php?export_type=" + exportType;
  form.target = ""; // 👈 new tab or same page
  form.submit();
});

    const exportType = document.getElementById("exportTypeSelect");
    const docSpecs = document.getElementById("docSpecsSection");
    const checkboxes = [...document.querySelectorAll('input.report-checkbox')];
    const clearBtn = document.getElementById("clearCheckboxes");
    const docTypeSelect = document.getElementById("docTypeSelect");
    const mainForm = document.getElementById("mainForm");

  const detailedCols = [
    'asset_tag', 'asset_description', 'spec_brand_model', 'serial_number', 'date_acquired',
    'invoice_no', 'receiving_report', 'accountable_individual',
    'remarks', 'date_created', 'last_date_modified', 'equipment_status',
    'action_taken', 'status_date_creation', 'status_remarks'
  ];

  const summarizedCols = [
    'asset_tag', 'asset_description', 'spec_brand_model',
    'equipment_status', 'action_taken', 'status_date_creation', 'status_remarks'
  ];

  const applyDocTypeSelection = () => {
    const docType = docTypeSelect.value;
    if (docType === 'summarized') {
      checkboxes.forEach(cb => cb.checked = summarizedCols.includes(cb.value));
    } else if (docType === 'detailed') {
      checkboxes.forEach(cb => cb.checked = detailedCols.includes(cb.value));
    } else {
      checkboxes.forEach(cb => cb.checked = false);
    }
    updateDocTypeByCheckboxes();
  };

  const getCheckedColumns = () => checkboxes.filter(cb => cb.checked).map(cb => cb.value).sort();

  const arraysEqual = (a, b) => {
    if (a.length !== b.length) return false;
    return a.every((val, i) => val === b[i]);
  };

  const updateDocTypeByCheckboxes = () => {
    const selectedCols = getCheckedColumns();
    if (arraysEqual(selectedCols, detailedCols.slice().sort())) {
      docTypeSelect.value = 'detailed';
    } else if (arraysEqual(selectedCols, summarizedCols.slice().sort())) {
      docTypeSelect.value = 'summarized';
    } else {
      docTypeSelect.value = 'custom';
    }
  };

  clearBtn?.addEventListener("click", () => {
    checkboxes.forEach(cb => cb.checked = false);
    docTypeSelect.value = 'custom';
  });

  docTypeSelect?.addEventListener("change", applyDocTypeSelection);
  checkboxes.forEach(cb => cb.addEventListener('change', updateDocTypeByCheckboxes));
  applyDocTypeSelection();

    previewBtn.addEventListener("click", () => {
      form.action = 'generate_report_preview.php';
      form.target = 'previewFrame';
      form.submit();
    });

    downloadBtn.addEventListener("click", () => {
      const type = exportType.value;
      form.action = `generate_report.php?export_type=${type}`;
      form.target = '';
    });

    document.getElementById("clearCheckboxes").addEventListener("click", () => {
      checkboxes.forEach(cb => cb.checked = false);
    });

    exportType.addEventListener("change", () => {
      const show = ['pdf', 'word'].includes(exportType.value);
      docSpecs.style.display = show ? 'block' : 'none';
    });

    exportType.dispatchEvent(new Event('change'));

    $('#buildingLocSelect, #specificAreaSelect').select2({ width: '100%' });
  });
</script>
</body>
</html>
