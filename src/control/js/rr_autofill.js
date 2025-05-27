/**
 * RR Autofill Functionality
 * Fetches charge invoice date based on RR# selection and updates the equipment details form
 */

/**
 * Fetches information related to an RR# and autofills the acquired date field
 * @param {string} rrNo - The RR number to fetch information for
 * @param {string} formType - Either 'add' or 'edit' to determine which form to update
 * @param {boolean} showNotification - Whether to show a notification when data is autofilled
 */
function fetchRRInfo(rrNo, formType, showNotification = true) {
    if (!rrNo) return;
    
    console.log(`Fetching data for RR#: ${rrNo} (form: ${formType})`);
    
    // Determine which form element to update based on formType
    const dateAcquiredField = formType === 'add' ? 
        document.querySelector('input[name="date_acquired"]') : 
        document.getElementById('edit_date_acquired');
    
    // Make AJAX request to get RR information
    $.ajax({
        url: window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1) + 'rr_info.php',
        method: 'POST',
        data: {
            action: 'get_rr_info',
            rr_no: rrNo
        },
        dataType: 'json',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(response) {
            if (response.status === 'success' && response.data) {
                // Autofill date_acquired if available and field exists
                if (response.data.date_acquired && dateAcquiredField) {
                    dateAcquiredField.value = response.data.date_acquired;
                    dateAcquiredField.setAttribute('data-autofill', 'true');
                    
                    // Make field read-only and grey it out
                    dateAcquiredField.readOnly = true;
                    dateAcquiredField.classList.add('bg-light');
                    
                    // Show notification if requested and data was found
                    if (showNotification) {
                        showToast('Acquired date has been automatically set from the Charge Invoice', 'info');
                    }
                }
            } else if (response.status === 'partial' && showNotification) {
                // PO found but no CI data
                showToast('PO found but no Charge Invoice information available', 'warning');
                
                // Reset field to be editable if no data was found
                if (dateAcquiredField) {
                    dateAcquiredField.readOnly = false;
                    dateAcquiredField.classList.remove('bg-light');
                    dateAcquiredField.setAttribute('data-autofill', 'false');
                }
            } else if (showNotification) {
                console.log('No RR info found');
                // Optional: Show notification that no data was found
                showToast('No Charge Invoice information found for this RR#', 'warning');
                
                // Reset field to be editable if no data was found
                if (dateAcquiredField) {
                    dateAcquiredField.readOnly = false;
                    dateAcquiredField.classList.remove('bg-light');
                    dateAcquiredField.setAttribute('data-autofill', 'false');
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching RR info:', error);
            if (showNotification) {
                showToast('Error fetching RR information', 'error');
            }
            
            // Reset field to be editable on error
            if (dateAcquiredField) {
                dateAcquiredField.readOnly = false;
                dateAcquiredField.classList.remove('bg-light');
                dateAcquiredField.setAttribute('data-autofill', 'false');
            }
        }
    });
}

// Add event handlers when document is ready
$(document).ready(function() {
    // Check if we're on the equipment details page
    if (window.location.href.indexOf('equipment_details.php') > -1) {
        
        // Add event listener for RR# selection in add form
        $('#add_rr_no').on('select2:select', function(e) {
            const rrNo = e.params.data.id;
            if (rrNo) {
                fetchRRInfo(rrNo, 'add', true);
            }
        });
        
        // Add event listener for RR# selection in edit form
        $('#edit_rr_no').on('select2:select', function(e) {
            const rrNo = e.params.data.id;
            if (rrNo) {
                fetchRRInfo(rrNo, 'edit', true);
            }
        });
        
        // Handle clearing of RR# in add form
        $('#add_rr_no').on('select2:clear', function() {
            const dateAcquiredField = document.querySelector('input[name="date_acquired"]');
            if (dateAcquiredField) {
                dateAcquiredField.value = '';
                dateAcquiredField.readOnly = false;
                dateAcquiredField.classList.remove('bg-light');
                dateAcquiredField.setAttribute('data-autofill', 'false');
            }
        });
        
        // Handle clearing of RR# in edit form
        $('#edit_rr_no').on('select2:clear', function() {
            const dateAcquiredField = document.getElementById('edit_date_acquired');
            if (dateAcquiredField) {
                dateAcquiredField.value = '';
                dateAcquiredField.readOnly = false;
                dateAcquiredField.classList.remove('bg-light');
                dateAcquiredField.setAttribute('data-autofill', 'false');
            }
        });
        
        // Check if RR# is already selected when add modal opens
        $('#addEquipmentModal').on('shown.bs.modal', function() {
            const rrNoValue = $('#add_rr_no').val();
            if (rrNoValue) {
                console.log('Add modal opened with RR# already selected:', rrNoValue);
                fetchRRInfo(rrNoValue, 'add', false);
            }
        });
        
        // Check if RR# is already selected when edit modal opens
        $('#editEquipmentModal').on('shown.bs.modal', function() {
            const rrNoValue = $('#edit_rr_no').val();
            if (rrNoValue) {
                console.log('Edit modal opened with RR# already selected:', rrNoValue);
                fetchRRInfo(rrNoValue, 'edit', false);
            }
        });
        
        // Reset date field when add modal is closed
        $('#addEquipmentModal').on('hidden.bs.modal', function() {
            const dateAcquiredField = document.querySelector('input[name="date_acquired"]');
            if (dateAcquiredField) {
                dateAcquiredField.readOnly = false;
                dateAcquiredField.classList.remove('bg-light');
                dateAcquiredField.setAttribute('data-autofill', 'false');
            }
        });
        
        // Reset date field when edit modal is closed
        $('#editEquipmentModal').on('hidden.bs.modal', function() {
            const dateAcquiredField = document.getElementById('edit_date_acquired');
            if (dateAcquiredField) {
                dateAcquiredField.readOnly = false;
                dateAcquiredField.classList.remove('bg-light');
                dateAcquiredField.setAttribute('data-autofill', 'false');
            }
        });
    }
}); 