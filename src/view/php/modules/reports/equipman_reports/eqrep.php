<?php
require_once __DIR__ . '/../../../../../../config/ims-tmdd.php';
require_once __DIR__ . '/../../../../../control/RBACService.php';
session_start();

// RBAC check
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
    body {
      background-color: #f5f5f5;
    }

    .main-content {
      margin-top: 75px;
      padding: 0 100px;
    }

    .form-section {
      background: #ffffff;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
    }

    .preview-box {
      border: 1px solid #ccc;
      height: 600px;
      width: 100%;
    }

    iframe {
      background-color: #fff;
    }

    .select2-container .select2-selection--single {
      height: 38px;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
      line-height: 38px;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 38px;
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
                <!-- Options will be populated dynamically via JavaScript -->
              </select>
            </div>
            <div class="col-md-6 mt-3">
              <label class="form-label">Laboratory/Office:</label>
              <select name="specific_area" class="form-select" id="specificAreaSelect" required>
                <option value="all">All Areas</option>
                <!-- Options will be populated dynamically via JavaScript -->
              </select>
            </div>
            <div class="col-md-6 mt-3">
              <label class="form-label">Date From:</label>
              <input type="date" name="date_from" class="form-control" required>
            </div>
            <div class="col-md-6 mt-2">
              <label class="form-label">Date To:</label>
              <input type="date" name="date_to" class="form-control" required>
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
              'building_location'       => 'Location',
              'specific_area'          => 'Laboratory/Office',
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
    document.addEventListener("DOMContentLoaded", function() {
      const BASE_URL = '/src/view/php/modules/reports/equipman_reports/get_locations.php';
      $('#buildingLocSelect, #specificAreaSelect').select2({
        width: '100%'
      });

      function populateLocations(selectedValue = 'all') {
        $.getJSON(`${BASE_URL}?action=get_locations`, function(data) {
          if (data.locations) {
            const $buildingLocSelect = $('#buildingLocSelect');
            const currentValue = $buildingLocSelect.val() || selectedValue;
            $buildingLocSelect.empty().append('<option value="all">All Locations</option>');
            $.each(data.locations, function(i, loc) {
              $buildingLocSelect.append(`<option value="${loc}">${loc}</option>`);
            });
            $buildingLocSelect.val(data.locations.includes(currentValue) ? currentValue : 'all').trigger('change');
            console.log('Populated locations, selected:', $buildingLocSelect.val());
          } else {
            console.error('Error fetching locations:', data.error);
            alert('Failed to load locations: ' + data.error);
          }
        }).fail(function(jqXHR, textStatus, errorThrown) {
          console.error('AJAX error:', textStatus, errorThrown);
        });
      }

      function populateSpecificAreas(building_loc = '', selectedValue = 'all') {
        const url = building_loc && building_loc !== 'all' ?
          `${BASE_URL}?action=get_specific_areas&building_loc=${encodeURIComponent(building_loc)}` :
          `${BASE_URL}?action=get_specific_areas`;
        $.getJSON(url, function(data) {
          if (data.specific_areas) {
            const $specificAreaSelect = $('#specificAreaSelect');
            const currentValue = $specificAreaSelect.val() || selectedValue;
            $specificAreaSelect.empty().append('<option value="all">All Areas</option>');
            $.each(data.specific_areas, function(i, area) {
              $specificAreaSelect.append(`<option value="${area}">${area}</option>`);
            });
            $specificAreaSelect.val(data.specific_areas.includes(currentValue) ? currentValue : 'all').trigger('change');
            console.log('Populated specific areas, selected:', $specificAreaSelect.val());
          } else {
            console.error('Error fetching specific areas:', data.error);
            alert('Failed to load specific areas: ' + data.error);
          }
        }).fail(function(jqXHR, textStatus, errorThrown) {
          console.error('AJAX error:', textStatus, errorThrown);
        });
      }

      populateLocations();
      populateSpecificAreas();

      $('#buildingLocSelect').on('change', function() {
        const building_loc = $(this).val();
        console.log('Building Loc Changed:', building_loc);
        populateSpecificAreas(building_loc);
      });

      $('#specificAreaSelect').on('change', function() {
        const specific_area = $(this).val();
        console.log('Specific Area Changed:', specific_area);
      });

      const form = document.getElementById("mainForm");
      const exportType = document.getElementById("exportTypeSelect");
      const docSpecs = document.getElementById("docSpecsSection");
      const checkboxes = [...document.querySelectorAll('input.report-checkbox')];
      const clearBtn = document.getElementById("clearCheckboxes");
      const docTypeSelect = document.getElementById("docTypeSelect");
      const previewBtn = document.getElementById("previewBtn");
      const downloadBtn = document.getElementById("downloadBtn");

      const detailedCols = [
        'asset_tag', 'asset_description', 'spec_brand_model', 'serial_number', 'date_acquired',
        'invoice_no', 'receiving_report', 'building_location', 'specific_area',
        'accountable_individual', 'remarks', 'date_created', 'last_date_modified',
        'equipment_status', 'action_taken', 'status_date_creation', 'status_remarks'
      ];

      const summarizedCols = [
        'asset_tag', 'asset_description', 'spec_brand_model', 'building_location',
        'specific_area', 'equipment_status', 'action_taken', 'status_date_creation',
        'status_remarks'
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

      form.addEventListener("submit", (e) => {
        const building_loc = $('#buildingLocSelect').val();
        const specific_area = $('#specificAreaSelect').val();
        console.log('Form Submit - building_loc:', building_loc, 'specific_area:', specific_area);
        if (!building_loc || !specific_area) {
          e.preventDefault();
          alert('Please select valid Location and Laboratory/Office values');
        }
      });

      previewBtn.addEventListener("click", () => {
        const building_loc = $('#buildingLocSelect').val();
        const specific_area = $('#specificAreaSelect').val();
        if (!building_loc || !specific_area) {
          alert('Please select valid Location and Laboratory/Office values');
          return;
        }
        form.action = 'generate_report_preview.php';
        form.target = 'previewFrame';
        form.submit();
        console.log('Preview Submitted - building_loc:', building_loc, 'specific_area:', specific_area);
      });

      downloadBtn.addEventListener("click", () => {
        const building_loc = $('#buildingLocSelect').val();
        const specific_area = $('#specificAreaSelect').val();
        if (!building_loc || !specific_area) {
          alert('Please select valid Location and Laboratory/Office values');
          return;
        }
        const type = exportType.value;
        form.action = `generate_report.php?export_type=${type}`;
        form.target = '';
        form.submit();
        console.log('Download Submitted - building_loc:', building_loc, 'specific_area:', specific_area);
      });

      exportType.addEventListener("change", () => {
        const show = ['pdf', 'word'].includes(exportType.value);
        docSpecs.style.display = show ? 'block' : 'none';
      });

      exportType.dispatchEvent(new Event('change'));
    });
  </script>
</body>

</html>