<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Generate Equipment Report</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f5f5f5;
    }

    .main-content {
      margin-top: 75px; /* Adjust this if your header is taller */
      padding-left: 100px; /* Adjust if your sidebar is wider */
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
    <form method="POST" action="generate_report_preview.php" target="previewFrame">
      <!-- Section 1: Document Setup -->
      <div class="row form-section">
        <div class="col-md-6">
          <label class="form-label">Document Type:</label>
          <select name="doc_type" class="form-select" required>
            <option value="summarized">Summarized Report</option>
            <option value="detailed">Detailed Report</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Paper Size:</label>
          <select name="paper_size" class="form-select" required>
            <option value="letter">Letter (8x11")</option>
            <option value="legal">Legal (8x14")</option>
          </select>
        </div>
      </div>

      <!-- Section 2: Location and Timeline -->
      <div class="row form-section">
        <div class="col-md-6">
          <label class="form-label">Laboratory/Office (Specific_area):</label>
          <select name="specific_area" class="form-select" required>
            <?php
            $stmt = $pdo->query("SELECT DISTINCT specific_area FROM equipment_location ORDER BY specific_area");
            while ($row = $stmt->fetch()) {
              echo "<option value='" . htmlspecialchars($row['specific_area']) . "'>" . htmlspecialchars($row['specific_area']) . "</option>";
            }
            ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Location (building_loc):</label>
          <select name="building_loc" class="form-select" required>
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
          <input type="date" name="date_from" class="form-control" required>
        </div>
        <div class="col-md-6 mt-2">
          <label class="form-label">Date To:</label>
          <input type="date" name="date_to" class="form-control" required>
        </div>
      </div>

      <!-- Section 3: Columns -->
      <div class="form-section">
        <label class="form-label">Select Columns to Include:</label>
        <div class="row">
          <?php
          $columns = [
            'last_modified_date' => 'Last Modified date / Date Created',
            'asset_tag' => 'Asset Tag',
            'asset_description' => 'Asset Description 1 & 2',
            'spec_brand_model' => 'Specifications, Brand and Model',
            'serial_number' => 'Serial Number',
            'date_acquired' => 'Date Acquired',
            'invoice_no' => 'Invoice No',
            'receiving_report' => 'Receiving Report',
            'building_location' => 'Building Location',
            'accountable_individual' => 'Accountable Individual',
            'remarks' => 'Remarks',
            'date_created' => 'Date Created',
            'last_date_modified' => 'Last Date Modified',
            'equipment_status' => 'Equipment Status',
            'action_taken' => 'Action Taken',
            'status_date_creation' => 'Status Date Creation',
            'status_remarks' => 'Status Remarks'
          ];
          foreach ($columns as $val => $label) {
            echo '<div class="col-md-4"><div class="form-check">';
            echo "<input class='form-check-input' type='checkbox' name='columns[]' value='$val' id='$val'>";
            echo "<label class='form-check-label' for='$val'>$label</label>";
            echo '</div></div>';
          }
          ?>
        </div>
      </div>

      <!-- Section 4: Prepared By -->
      <div class="row form-section">
        <div class="col-md-6">
          <label class="form-label">Prepared By:</label>
          <input type="text" name="prepared_by" class="form-control" required placeholder="Full Name">
        </div>
        <div class="col-md-6">
          <label class="form-label">Role Title / Department:</label>
          <input type="text" name="role_department" class="form-control" required>
        </div>
      </div>

      <!-- Buttons -->
      <div class="form-section text-end">
        <button type="submit" class="btn btn-primary me-2">Preview Report</button>
        <button type="submit" formaction="generate_report.php" class="btn btn-success">Download PDF</button>
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
</body>
</html>
