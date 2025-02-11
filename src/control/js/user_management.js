// assets/js/user_management.js
$(document).ready(function () {
  // Enable/disable action buttons based on checkbox selections.
  $(".select-row, .select-row-deleted").change(function () {
    let activeCount = $(".select-row:checked").length;
    let deletedCount = $(".select-row-deleted:checked").length;
    $("#delete-selected").prop("disabled", activeCount === 0);
    $("#restore-selected").prop("disabled", deletedCount === 0);
  });

  // "Select All" for active users.
  $("#select-all").change(function () {
    let isChecked = $(this).prop("checked");
    $(".select-row").prop("checked", isChecked).trigger("change");
  });

  // "Select All" for deleted users.
  $("#select-all-deleted").change(function () {
    let isChecked = $(this).prop("checked");
    $(".select-row-deleted").prop("checked", isChecked).trigger("change");
  });

  // Handler for "Delete Selected" button (active users).
  $("#delete-selected").click(function () {
    let selected = [];
    $(".select-row:checked").each(function () {
      selected.push($(this).val());
    });
    if (
      selected.length > 0 &&
      confirm(
        "Are you sure you want to delete the selected users? They will be moved to deleted users."
      )
    ) {
      $.ajax({
        type: "POST",
        url: "/src/view/php/modules/user_manager/delete_user.php",
        data: { user_ids: selected, action: "soft_delete" },
        success: function (response) {
          location.reload();
        },
        error: function () {
          alert("Failed to delete selected users. Please try again.");
        },
      });
    }
  });

  // Handler for "Restore Selected" button (deleted users).
  $("#restore-selected").click(function () {
    let selected = [];
    $(".select-row-deleted:checked").each(function () {
      selected.push($(this).val());
    });
    if (
      selected.length > 0 &&
      confirm("Are you sure you want to restore the selected users?")
    ) {
      $.ajax({
        type: "POST",
        url: "/src/view/php/modules/user_manager/restore_user.php",
        data: { user_ids: selected, action: "restore" },
        success: function (response) {
          location.reload();
        },
        error: function () {
          alert("Failed to restore selected users. Please try again.");
        },
      });
    } else {
      alert("No users selected for restoration.");
    }
  });

  // Handler for individual deletion.
  $(".btn-danger[data-id]").click(function () {
    let userId = $(this).data("id");
    if (
      confirm(
        "Are you sure you want to delete this user? This action will move the user to deleted users."
      )
    ) {
      $.ajax({
        type: "POST",
        url: "/src/view/php/modules/user_manager/delete_user.php",
        data: { user_id: userId, action: "soft_delete" },
        success: function (response) {
          location.reload();
        },
        error: function () {
          alert("Failed to delete user. Please try again.");
        },
      });
    }
  });

  // Handler for individual restore.
  $(".restore-individual").click(function () {
    let userId = $(this).data("id");
    if (confirm("Are you sure you want to restore this user?")) {
      $.ajax({
        type: "POST",
        url: "/src/view/php/modules/user_manager/restore_user.php",
        data: { id: userId, action: "restore" },
        success: function (response) {
          location.reload();
        },
        error: function () {
          alert("Failed to restore user. Please try again.");
        },
      });
    }
  });

  // Open the edit user modal with the user data.
  $(".btn-edit").click(function () {
    let userEmail = $(this).data("email");
    let userFirstName = $(this).data("first-name");
    let userLastName = $(this).data("last-name");
    let userDepartment = $(this).data("department");
    let userStatus = $(this).data("status");
    let userPassword = $(this).data("password"); // if needed

    $("#editUserForm").find("#editEmail").val(userEmail);
    $("#editUserForm").find("#editFirstName").val(userFirstName);
    $("#editUserForm").find("#editLastName").val(userLastName);
    $("#editUserForm").find("#editDepartment").val(userDepartment);
    $("#editUserForm").find("#editStatus").val(userStatus);
    $("#editUserForm").find("#editPassword").val(userPassword);
  });
});
