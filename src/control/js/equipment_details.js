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

    function filterTable() {
        const searchText = $('#searchEquipment').val().toLowerCase();
        const filterType = $('#filterEquipment').val().toLowerCase();
        const dateFilterType = $('#dateFilter').val();
        const selectedMonth = $('#monthSelect').val();
        const selectedYear = $('#yearSelect').val();
        const dateFrom = $('#dateFrom').val();
        const dateTo = $('#dateTo').val();

        $(".table tbody tr").each(function() {
            const row = $(this);
            const rowText = row.text().toLowerCase();
            const typeCell = row.find('td:eq(2)').text().toLowerCase();
            const dateCell = row.find('td:eq(8)').text(); // Adjust index based on date column
            const date = new Date(dateCell);

            const searchMatch = rowText.indexOf(searchText) > -1;
            const typeMatch = !filterType || typeCell === filterType;
            let dateMatch = true;

            switch(dateFilterType) {
                case 'asc':
                    const tbody = $('.table tbody');
                    const rows = tbody.find('tr').toArray();
                    rows.sort((a, b) => {
                        const dateA = new Date($(a).find('td:eq(8)').text());
                        const dateB = new Date($(b).find('td:eq(8)').text());
                        return dateA - dateB;
                    });
                    tbody.append(rows);
                    return;

                case 'desc':
                    const tbody2 = $('.table tbody');
                    const rows2 = tbody2.find('tr').toArray();
                    rows2.sort((a, b) => {
                        const dateA = new Date($(a).find('td:eq(8)').text());
                        const dateB = new Date($(b).find('td:eq(8)').text());
                        return dateB - dateA;
                    });
                    tbody2.append(rows2);
                    return;

                case 'month':
                    if (selectedMonth && selectedYear) {
                        dateMatch = date.getMonth() + 1 === parseInt(selectedMonth) &&
                            date.getFullYear() === parseInt(selectedYear);
                    }
                    break;

                case 'range':
                    if (dateFrom && dateTo) {
                        const from = new Date(dateFrom);
                        const to = new Date(dateTo);
                        to.setHours(23, 59, 59);
                        dateMatch = date >= from && date <= to;
                    }
                    break;
            }

            row.toggle(searchMatch && typeMatch && dateMatch);
        });
    }
});

$(document).ready(function() {
    // Add Equipment
    $('#addEquipmentForm').on('submit', function(e) {
        e.preventDefault();
        console.log('Form submitted', $(this).serialize()); // Debug line

        $.ajax({
            url: '../../modules/equipment_manager/equipment_details.php',
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                console.log('Response:', response); // Debug line
                try {
                    const result = JSON.parse(response);
                    if (result.status === 'success') {54
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
                alert('Error submitting the form');
            }
        });
    });

    // Edit Equipment
    $('.edit-equipment').click(function() {
        var id = $(this).data('id');
        var asset = $(this).data('asset');
        var desc1 = $(this).data('desc1');
        var desc2 = $(this).data('desc2');
        var spec = $(this).data('spec');
        var brand = $(this).data('brand');
        var model = $(this).data('model');
        var serial = $(this).data('serial');
        var date = $(this).data('date');
        var accountable = $(this).data('accountable');
        var remarks = $(this).data('remarks');

        $('#edit_equipment_id').val(id);
        $('#edit_asset_tag').val(asset);
        $('#edit_asset_description1').val(desc1);
        $('#edit_asset_description2').val(desc2);
        $('#edit_specification').val(spec);
        $('#edit_brand').val(brand);
        $('#edit_model').val(model);
        $('#edit_serial_number').val(serial);
        $('#edit_date_acquired').val(date);
        $('#edit_accountable_individual').val(accountable);
        $('#edit_remarks').val(remarks);

        $('#editEquipmentModal').modal('show');
    });


});