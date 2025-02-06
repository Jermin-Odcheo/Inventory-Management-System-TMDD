<!-- Delete Confirmation Modal (used for both soft and permanent deletion) -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="deleteForm" method="post" action="delete_user.php">
            <!-- Hidden inputs for the user ID and deletion type -->
            <input type="hidden" name="id" id="deleteUserId" value="">
            <input type="hidden" name="permanent" id="deletePermanent" value="0">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Enter your password below to confirm the deletion of this user.</p>
                    <div class="mb-3">
                        <input type="password" name="password" id="confirmPassword" class="form-control" placeholder="Enter your password" required>
                    </div>
                    <div id="deleteError" class="text-danger" style="display: none;">Please enter your password.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="confirmDeleteBtn" class="btn btn-danger" disabled>Delete</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Function to open the modal and set the user ID and deletion type
    // If permanentDelete is true, then permanent deletion is intended.
    function openDeleteModal(userId, permanentDelete = false) {
        // Set the hidden input value with the user ID
        document.getElementById('deleteUserId').value = userId;
        // Set the permanent flag: "1" for permanent deletion; "0" for soft deletion.
        document.getElementById('deletePermanent').value = permanentDelete ? "1" : "0";
        // Reset the password field and disable the confirm button
        document.getElementById('confirmPassword').value = '';
        document.getElementById('confirmDeleteBtn').disabled = true;
        document.getElementById('deleteError').style.display = 'none';
        // Show the modal using Bootstrap's modal method
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        deleteModal.show();
    }

    // Listen for input changes on the password field
    document.getElementById('confirmPassword').addEventListener('input', function() {
        var passwordInput = this.value.trim();
        if (passwordInput.length > 0) {
            document.getElementById('confirmDeleteBtn').disabled = false;
            document.getElementById('deleteError').style.display = 'none';
        } else {
            document.getElementById('confirmDeleteBtn').disabled = true;
            document.getElementById('deleteError').style.display = 'block';
        }
    });
</script>