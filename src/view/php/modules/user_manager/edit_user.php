<!-- Bootstrap Edit Modal Template -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">Edit Record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editForm">
            <input type="hidden" id="editUserID" name="id">
            <div id="dynamicFields"></div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" class="btn btn-primary" id="saveChanges">Save changes</button>
      </div>
    </div>
  </div>
</div>


<script>
  document.addEventListener("DOMContentLoaded", function () {
    function getCurrentPage() {
      const path = window.location.pathname;
      if (path.includes("purchase_order")) return "PurchaseOrder";
      if (path.includes("charge_invoice")) return "ChargeInvoice";
      if (path.includes("receive_report")) return "ReceiveReport";
      if (path.includes("equipment_details")) return "EquipmentDetails";
      if (path.includes("equipment_location")) return "EquipmentLocation";
      if (path.includes("equipment_status")) return "EquipmentStatus";
      if (path.includes("user")) return "User";
      if (path.includes("roles")) return "Roles";
      return "";
    }

    function loadFields(module, data) {
      let fields = "";
      switch (module) {
        case "PurchaseOrder":
          fields = `
            <label>PO No:</label><input type='text' class='form-control' name='POno' value='${data.POno}' required>
            <label>Date of Order:</label><input type='date' class='form-control' name='DateOfOrder' value='${data.DateOfOrder}' required>
            <label>No. of Units:</label><input type='number' class='form-control' name='NoOfUnits' value='${data.NoOfUnits}' required>
            <label>Item Specifications:</label><textarea class='form-control' name='ItemSpecifications'>${data.ItemSpecifications}</textarea>
          `;
          break;
        case "ChargeInvoice":
          fields = `
            <label>Invoice No:</label><input type='text' class='form-control' name='InvoiceNo' value='${data.InvoiceNo}' required>
            <label>Date of Purchase:</label><input type='date' class='form-control' name='DateOfPurchase' value='${data.DateOfPurchase}' required>
            <label>PO No:</label><input type='text' class='form-control' name='PONo' value='${data.PONo}' required>
          `;
          break;
        case "Roles":
          if (!isAdminOrSuperAdmin()) return alert("Access Denied");
          fields = `
            <label>Role Name:</label><input type='text' class='form-control' name='RoleName' value='${data.RoleName}' required>
            <label>Privileges:</label><textarea class='form-control' name='Privileges'>${data.Privileges}</textarea>
            <label>Modules:</label><input type='text' class='form-control' name='Modules' value='${data.Modules}' required>
          `;
          break;
      }
      document.getElementById("dynamicFields").innerHTML = fields;
    }

    function isAdminOrSuperAdmin() {
      let userRole = "user"; // Fetch the role from session or API
      return userRole === "admin" || userRole === "superadmin";
    }

    document.getElementById("saveChanges").addEventListener("click", function () {
      const formData = new FormData(document.getElementById("editForm"));
      console.log("Saving:", Object.fromEntries(formData));
      // Implement the AJAX call here to save the data.
    });

    window.showEditModal = function (data) {
      const module = getCurrentPage();
      document.getElementById("editID").value = data.ID;
      loadFields(module, data);
      new bootstrap.Modal(document.getElementById("editModal")).show();
    };
  });
  document.addEventListener("DOMContentLoaded", function () {
    const modalElement = document.getElementById("editModal");
    
    modalElement.addEventListener("show.bs.modal", function () {
        document.querySelector(".header").style.zIndex = "1000"; // Lower header while modal is open
    });

    modalElement.addEventListener("hidden.bs.modal", function () {
        document.querySelector(".header").style.zIndex = "1100"; // Restore header after closing modal
    });
});

</script>
