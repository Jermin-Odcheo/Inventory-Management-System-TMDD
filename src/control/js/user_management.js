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
                data: {user_ids: selected, action: "soft_delete"},
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
                data: {user_ids: selected, action: "restore"},
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
                data: {user_id: userId, action: "soft_delete"},
                success: function (response) {
                    location.reload();
                },
                error: function () {
                    alert("Failed to delete user. Please try again.");
                },
            });
        }
    });

    // Open the edit user modal with the user data.
    $(".btn-edit").click(function () {
        const userId = $(this).data("id");
        const email = $(this).data("email");
        const firstName = $(this).data("first-name");
        const lastName = $(this).data("last-name");
        const department = $(this).data("department");

        $("#editUserID").val(userId);
        $("#editEmail").val(email);
        $("#editFirstName").val(firstName);
        $("#editLastName").val(lastName);
        $("#editDepartment").val(department);
    });

    $("#editUserForm").on("submit", function(e) {
        e.preventDefault();
        $.ajax({
            type: "POST",
            url: $(this).attr("action"),
            data: $(this).serialize(),
            success: function(response) {
                console.log("Response from server:", response);
                $("#editUserModal").modal("hide");
                $("#alertMessage").html(
                    '<div class="alert alert-success alert-dismissible fade show" role="alert">' + response +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>'
                );
                setTimeout(function() {
                    $(".alert").alert('close');
                }, 3000);
                // Remove or comment out the following line to prevent reloading the page:
                // location.reload();
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                $("#alertMessage").html(
                    '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error updating user: ' + error +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>'
                );
            }
        });
    });


    /*
    FOR ARCHIVE (TO BE IMPLEMENTED)
    RESTORE
    PERMANENT DELETE
     */
    // Handler for individual restore.
    $(".restore-individual").click(function () {
        let userId = $(this).data("id");
        if (confirm("Are you sure you want to restore this user?")) {
            $.ajax({
                type: "POST",
                url: "/src/view/php/modules/user_manager/restore_user.php",
                data: {id: userId, action: "restore"},
                success: function (response) {
                    location.reload();
                },
                error: function () {
                    alert("Failed to restore user. Please try again.");
                },
            });
        }
    });


});
$(".permanent-delete-btn").on("click", function () {
    const userId = $(this).data("id");
    if (confirm("Are you sure you want to permanently delete this user?")) {
        $.post('delete_user.php',
            {user_id: userId, permanent: 1},
            function (response) {
                alert(response);
                location.reload();
            }
        );
    }
});

function openDeleteModal(userId) {
    if (confirm("Are you sure you want to permanently delete this user?")) {
        $.post('/src/view/php/modules/user_manager/delete_user.php',
            {user_id: userId, permanent: 1},
            function (response) {
                alert(response);
                location.reload();
            }
        );
    }
}


