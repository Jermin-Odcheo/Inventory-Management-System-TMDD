<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';
session_start();
$today        = date('Y-m-d');
$todayDisplay = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Generate Equipment Report</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <style>
    body {
      background-color: #f5f5f5;
    }
    .main-content {
      margin-top: 75px;
      padding-left: 100px;
      padding-right: 100px;
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
    iframe {
      background-color: #fff;
    }
  </style>
</head>
<body>
<?php include_once __DIR__ . '/../../../general/header.php'; ?>
<?php include_once __DIR__ . '/../../../general/sidebar.php'; ?>

<div class="main-content">
  <div class="container-fluid">
    <h2 class="mb-4">Generate Equipment Report</h2>

    <form id="mainForm" target="previewFrame" method="POST" action="generate_report_preview.php">
      <!-- Document Setup -->
      <div class="row form-section">
        <h5>Document Specifications</h5>
        <div class="col-md-6">
          <label class="form-label">Document Type:</label>
          <select class="form-select" id="docTypeSelect" name="docTypeSelect" required>
  <option value="summarized">Summarized Report</option>
  <option value="detailed">Detailed Report</option>
  <option value="custom" selected>Custom Report</option> <!-- make this selected -->
</select>

        </div>
        <div class="col-md-6">
          <label class="form-label">Paper Size:</label>
          <select name="paper_size" class="form-select" id="paperSizeSelect" required>
            <option value="letter">Letter (8x11")</option>
            <option value="legal">Legal (8x14")</option>
          </select>
        </div>
        <input type="hidden" name="orientation" value="landscape" id="orientationInput">
      </div>

      <!-- Location and Timeline -->
      <div class="row form-section">
        <h5>Data Range</h5>
        <h6>*Select the filtering details you want to display*</h6>
        <div class="col-md-6">
          <label class="form-label">Laboratory/Office:</label>
          <select name="specific_area" class="form-select" id="specificAreaSelect" required>
            <?php
            $stmt = $pdo->query("SELECT DISTINCT specific_area FROM equipment_location ORDER BY specific_area");
            while ($row = $stmt->fetch()) {
              echo "<option value='" . htmlspecialchars($row['specific_area']) . "'>" . htmlspecialchars($row['specific_area']) . "</option>";
            }
            ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Location:</label>
          <select name="building_loc" class="form-select" id="buildingLocSelect" required>
            <?php
            $stmt = $pdo->query("SELECT DISTINCT building_loc FROM equipment_location ORDER BY building_loc");
            while ($row = $stmt->fetch()) {
              echo "<option value='" . htmlspecialchars($row['building_loc']) . "'>" . htmlspecialchars($row['building_loc']) . "</option>";
            }
            ?>
          </select>
        </div>
        <div class="col-md-6 mt-2">
          <label class="form-label">Date From:</label>
          <input type="date" name="date_from" id="dateFromInput" class="form-control" required value="<?= $today ?>">
        </div>
        <div class="col-md-6 mt-2">
          <label class="form-label">Date To:</label>
          <input type="date" name="date_to" id="dateToInput" class="form-control" required value="<?= $today ?>">
        </div>
      </div>

      <!-- Columns -->
      <div class="form-section">
        <h5>Generated Table Columns</h5>
        <h6>*Select the columns you want to display on the Table*</h6>
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
            'building_location'      => 'Building Location',
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

      <!-- Prepared By -->
      <div class="row form-section">
        <h5>Preparation by Box:</h5>
        <div class="col-md-6">
          <label class="form-label">Prepared By:</label>
          <input type="text" name="prepared_by" id="preparedByInput" class="form-control" required placeholder="Full Name">
        </div>
        <div class="col-md-6">
          <label class="form-label">Role Title / Department:</label>
          <input type="text" name="role_department" id="roleDepartmentInput" class="form-control" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Date:</label>
          <input type="text" name="prepared_date" id="preparedDateInput" class="form-control" value="<?= $todayDisplay ?>" readonly>
        </div>
      </div>

      <!-- Buttons: Preview and Export -->
      <div class="form-section">
        <div class="row align-items-end">
          <div class="col-md-4">
            <label class="form-label">Download and/or File Type:</label>
            <select name="export_type" class="form-select" id="exportTypeSelect" required>
              <option value="pdf">PDF</option>
              <option value="excel">Excel</option>
              <option value="docs">Word</option>
            </select>
          </div>
          <div class="col-md-8 d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-dark" id="previewBtn">Preview Report</button>
            <button type="submit" class="btn btn-primary" id="downloadBtn">Download Report</button>
          </div>
        </div>
      </div>
    </form>

    <!-- Preview -->
    <div class="form-section mt-4">
      <h4>Document Preview</h4>
      <iframe name="previewFrame" class="preview-box"></iframe>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("mainForm");
  const previewBtn = document.getElementById("previewBtn");
  const downloadBtn = document.getElementById("downloadBtn");
  const previewFrame = document.querySelector("iframe");
  const exportTypeSelect = document.getElementById("exportTypeSelect");
  const clearCheckboxes = document.getElementById("clearCheckboxes");
  
  const defaultColumns = {
    summarized: ['asset_tag',
'asset_description',
    'spec_brand_model',
    'equipment_status',
    'action_taken',
    'status_date_creation',
    'status_remarks'
],
    detailed: ['asset_tag',
    'asset_description',
    'spec_brand_model',
    'serial_number',
    'date_acquired',
    'invoice_no',
    'receiving_report',
    'building_location',
    'accountable_individual',
    'remarks',
    'date_created',
    'last_date_modified',
    'equipment_status',
    'action_taken',
    'status_date_creation',
    'status_remarks'],
    custom: []
  };

  const docTypeSelect = document.getElementById('docTypeSelect');
  const checkboxes = document.querySelectorAll('.report-checkbox');

  function arraysEqual(arr1, arr2) {
    if (arr1.length !== arr2.length) return false;
    return arr1.every(item => arr2.includes(item)) && arr2.every(item => arr1.includes(item));
  }

  function syncCheckboxes(docType) {
    if (docType === 'summarized' || docType === 'detailed') {
      checkboxes.forEach(cb => cb.checked = defaultColumns[docType].includes(cb.value));
    } else {
      checkboxes.forEach(cb => cb.checked = false);
    }
  }

  function syncDropdown() {
    const checkedValues = Array.from(checkboxes)
      .filter(cb => cb.checked)
      .map(cb => cb.value);

    if (arraysEqual(checkedValues, defaultColumns.summarized)) {
      docTypeSelect.value = 'summarized';
    } else if (arraysEqual(checkedValues, defaultColumns.detailed)) {
      docTypeSelect.value = 'detailed';
    } else {
      docTypeSelect.value = 'custom';
    }
  }

  docTypeSelect.addEventListener('change', () => {
    syncCheckboxes(docTypeSelect.value);
  });

  checkboxes.forEach(cb => {
    cb.addEventListener('change', () => {
      syncDropdown();
    });
  });

  // Initialize state on page load
  syncCheckboxes(docTypeSelect.value);

  // Preview button: submit form to iframe
  previewBtn.addEventListener('click', () => {
    form.action = 'generate_report_preview.php';
    form.target = 'previewFrame';
    form.submit();
  });

  // Download button: submit form to export script
  downloadBtn.addEventListener('click', () => {
    const exportType = document.getElementById('exportTypeSelect').value;
    form.action = 'generate_report.php?export_type=' + encodeURIComponent(exportType);
    form.target = '';
    form.submit();
  });

  // Clear all checkboxes button
  document.getElementById('clearCheckboxes').addEventListener('click', () => {
    checkboxes.forEach(cb => cb.checked = false);
    docTypeSelect.value = 'custom';
  });
});

$(document).ready(function () {
    $('#specificAreaSelect').select2({
      placeholder: "Select Laboratory/Office",
      width: '100%'
    });

    $('#buildingLocSelect').select2({
      placeholder: "Select Building Location",
      width: '100%'
    });
  });
</script>
</body>
</html>
