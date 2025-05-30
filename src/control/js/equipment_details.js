$(document).ready(function() {
    // Search functionality
    $('#searchEquipment').on('input', function() {
        filterTable();
    });

    // Filter functionality
    $('#filterEquipment').on('change', function() {
        filterTable();
    });

    // Date filter change handler
    $('#dateFilter').on('change', function() {
        const value = $(this).val();

        // Hide all date inputs container first
        $('#dateInputsContainer').hide();
        $('#monthPickerContainer, #dateRangePickers').hide();
        $('#dateFrom, #dateTo').hide();

        switch(value) {
            case 'month':
                $('#dateInputsContainer').show();
                $('#monthPickerContainer').show();
                $('#dateRangePickers').hide();
                break;
            case 'range':
                $('#dateInputsContainer').show();
                $('#dateRangePickers').show();
                $('#monthPickerContainer').hide();
                $('#dateFrom, #dateTo').show();
                break;
            default:
                filterTable();
                break;
        }
    });

    // Month and Year select change handler
    $('#monthSelect, #yearSelect').on('change', function() {
        if ($('#monthSelect').val() && $('#yearSelect').val()) {
            filterTable();
        }
    });

    // Date range change handler
    $('#dateFrom, #dateTo').on('change', function() {
        if ($('#dateFrom').val() && $('#dateTo').val()) {
            filterTable();
        }
    });

    // Reset filters button handler
    $(document).on('click', '#resetFilters', function() {
        $('#searchEquipment').val('');
        $('#filterEquipment').val('all');
        if ($('#filterEquipment').data('select2')) {
            $('#filterEquipment').trigger('change.select2');
        }
        $('#dateFilter').val('');
        $('#monthSelect').val('');
        $('#yearSelect').val('');
        $('#dateFrom').val('');
        $('#dateTo').val('');
        $('#dateInputsContainer').hide();
        filterTable();
    });

    // Main filterTable implementation (single source of truth)
    function filterTable() {
        // Get filter values
        const searchText = $('#searchEquipment').val() || '';
        const filterEquipment = $('#filterEquipment').val() || '';
        const dateFilterType = $('#dateFilter').val() || '';
        const selectedMonth = $('#monthSelect').val() || '';
        const selectedYear = $('#yearSelect').val() || '';
        const dateFrom = $('#dateFrom').val() || '';
        const dateTo = $('#dateTo').val() || '';

        // Make sure we have allRows populated
        if (!window.allRows || window.allRows.length === 0) {
            window.allRows = Array.from(document.querySelectorAll('#equipmentTable tr:not(#noResultsMessage)'));
        }

        // Reset filteredRows array
        window.filteredRows = [];

        // Filter each row
        window.allRows.forEach(row => {
            // Get text content for filtering
            const rowText = row.textContent || '';

            // Get equipment type column (3rd column, index 2)
            const equipmentTypeCell = row.cells && row.cells.length > 2 ? row.cells[2] : null;
            const equipmentTypeText = equipmentTypeCell ? equipmentTypeCell.textContent.trim() || '' : '';

            // Get date column (10th column, index 9 - created date)
            const dateCell = row.cells && row.cells.length > 9 ? row.cells[9] : null;
            const dateText = dateCell ? dateCell.textContent.trim() || '' : '';
            const date = dateText ? new Date(dateText) : null;

            // Apply search filter (case insensitive)
            const searchMatch = !searchText || rowText.toLowerCase().includes(searchText.toLowerCase());

            // Apply equipment type filter (case insensitive, exact match)
            let equipmentMatch = true;
            if (filterEquipment && filterEquipment !== 'all' && filterEquipment.toLowerCase() !== 'filter equipment type') {
                equipmentMatch = equipmentTypeText.toLowerCase() === filterEquipment.trim().toLowerCase();
            }

            // Apply date filter
            let dateMatch = true;
            if (dateFilterType && date) {
                if (dateFilterType === 'month' && selectedMonth && selectedYear) {
                    dateMatch = (date.getMonth() + 1 === parseInt(selectedMonth)) &&
                        (date.getFullYear() === parseInt(selectedYear));
                } else if (dateFilterType === 'range') {
                    if (dateFrom && dateTo) {
                        const from = new Date(dateFrom);
                        const to = new Date(dateTo);
                        to.setHours(23, 59, 59); // End of day
                        dateMatch = date >= from && date <= to;
                    } else {
                        // If range is selected but dates are not, don't filter by date
                        dateMatch = true;
                    }
                }
            }

            // Show or hide row based on filter match
            const shouldShow = searchMatch && equipmentMatch && dateMatch;
            if (shouldShow) {
                window.filteredRows.push(row);
            }
        });

        // Sort if needed
        if (dateFilterType === 'asc' || dateFilterType === 'desc') {
            window.filteredRows.sort((a, b) => {
                const dateA = a.cells && a.cells[9] ? new Date(a.cells[9].textContent) : new Date(0);
                const dateB = b.cells && b.cells[9] ? new Date(b.cells[9].textContent) : new Date(0);
                return dateFilterType === 'asc' ? dateA - dateB : dateB - dateA;
            });
        }

        // Reset to page 1 and update pagination
        if (typeof paginationConfig !== 'undefined') {
            paginationConfig.currentPage = 1;
        }

        // Update pagination to show/hide rows based on current page
        if (typeof updatePagination === 'function') {
            updatePagination();
        }

        // Show a message if no results found
        const noResultsMessage = document.getElementById('noResultsMessage');
        const tbody = document.getElementById('equipmentTable');

        if (window.filteredRows.length === 0) {
            if (!noResultsMessage) {
                // Create and insert a "no results" message if it doesn't exist
                if (tbody) {
                    const noResultsRow = document.createElement('tr');
                    noResultsRow.id = 'noResultsMessage';
                    noResultsRow.innerHTML = `
                        <td colspan="16" class="text-center py-4">
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-circle me-2"></i> No results found for the current filter criteria.
                            </div>
                        </td>
                    `;
                    tbody.appendChild(noResultsRow);
                }
            } else {
                noResultsMessage.style.display = 'table-row';
            }
        } else if (noResultsMessage) {
            noResultsMessage.style.display = 'none';
        }
    }
    // Expose filterTable globally for other scripts (e.g., pagination.js)
    window.filterTable = filterTable;

    // Initialize allRows and filteredRows on page load
    window.allRows = Array.from(document.querySelectorAll('#equipmentTable tr:not(#noResultsMessage)'));
    window.filteredRows = [...window.allRows];

    // Initial filter
    filterTable();
});

$(document).ready(function() {
    // Add Equipment
    $('#addEquipmentForm').on('submit', function(e) {
        e.preventDefault();
    

        $.ajax({
            url: '../../modules/equipment_manager/equipment_details.php',
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                try {
                    const result = JSON.parse(response);
                    if (result.status === 'success') {
                        $('#addEquipmentModal').modal('hide');
                        location.reload();
                    } else {
                        alert(result.message);
                    }
                } catch (e) {
                    console.error('Parse error:', e); // Debug line
                    alert('Error processing the request');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error); // Debug line

    // Edit Equipment (delegated)
    $(document).on('click', '.edit-equipment', function() {
        var id = $(this).data('id');
        var asset = $(this).data('asset');
        var desc1 = $(this).data('desc1');
        var desc2 = $(this).data('desc2');
        var spec = $(this).data('spec');
        var brand = $(this).data('brand');
        var model = $(this).data('model');
        var serial = $(this).data('serial');
        var dateAcquired = $(this).data('date-acquired');
        var location = $(this).data('location');
        var accountable = $(this).data('accountable');
        var rr = $(this).data('rr');
        var remarks = $(this).data('remarks');

        $('#edit_equipment_id').val(id);
        $('#edit_equipment_asset_tag').val(asset).trigger('change');
        $('#edit_asset_description_1').val(desc1);
        $('#edit_asset_description_2').val(desc2);
        $('#edit_specifications').val(spec);
        $('#edit_brand').val(brand);
        $('#edit_model').val(model);
        $('#edit_serial_number').val(serial);
        $('#edit_date_acquired').val(dateAcquired);
        $('#edit_location').val(location);
        $('#edit_accountable_individual').val(accountable);
        $('#edit_rr_no').val(rr).trigger('change');
        $('#edit_remarks').val(remarks);

        $('#editEquipmentModal').modal('show');
    });

    // Delete Equipment (delegated)
    let deleteEquipmentId = null;
    $(document).on('click', '.remove-equipment', function() {
        deleteEquipmentId = $(this).data('id');
        $('#deleteEDModal').modal('show');
    });

    $('#confirmDeleteBtn').on('click', function() {
        if (!deleteEquipmentId) return;
        $.ajax({
            url: '../../modules/equipment_manager/delete_equipment.php',
            method: 'POST',
            data: { id: deleteEquipmentId, permanent: 1, module: 'Equipment Details' },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $('#deleteEDModal').modal('hide');
                    location.reload();
                } else {
                    alert(response.message || 'Failed to delete equipment.');
                }
            },
            error: function(xhr, status, error) {
                alert('Error deleting equipment.');
            }
        });
    });