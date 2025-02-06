<!-- Restore Confirmation Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="restoreForm" method="post" action="restore_user.php">
            <!-- Hidden input for the user ID -->
            <input type="hidden" name="id" id="restoreUserId" value="">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="restoreModalLabel">Confirm Restoration</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Type <strong>RESTORE</strong> in the box below to confirm the restoration of this user.</p>
                    <div class="mb-3">
                        <input type="text" id="restoreConfirmText" class="form-control" placeholder="Type RESTORE to confirm" required>
                    </div>
                    <div id="restoreError" class="text-danger" style="display: none;">You must type RESTORE exactly.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="confirmRestoreBtn" class="btn btn-primary" disabled>Restore</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Function to open the restore modal and set the user ID
    function openRestoreModal(userId) {
        // Set the hidden input value with the user ID
        document.getElementById('restoreUserId').value = userId;
        // Reset the input and disable the confirm button
        document.getElementById('restoreConfirmText').value = '';
        document.getElementById('confirmRestoreBtn').disabled = true;
        document.getElementById('restoreError').style.display = 'none';
        // Show the modal using Bootstrap's modal method
        var restoreModal = new bootstrap.Modal(document.getElementById('restoreModal'));
        restoreModal.show();
    }

    // Listen for input changes on the confirmation text box for restore
    document.getElementById('restoreConfirmText').addEventListener('input', function() {
        var confirmInput = this.value.trim();
        if (confirmInput === 'RESTORE') {
            document.getElementById('confirmRestoreBtn').disabled = false;
            document.getElementById('restoreError').style.display = 'none';
        } else {
            document.getElementById('confirmRestoreBtn').disabled = true;
            if (confirmInput.length > 0) {
                document.getElementById('restoreError').style.display = 'block';
            } else {
                document.getElementById('restoreError').style.display = 'none';
            }
        }
    });
</script>