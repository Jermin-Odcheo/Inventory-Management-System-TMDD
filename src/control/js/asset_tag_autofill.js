/**
 * Asset Tag Autofill Functionality
 * Fetches location and accountable individual data based on asset tag selection
 * Also provides bidirectional synchronization between equipment_details and equipment_location
 */

/**
 * Fetches information related to an asset tag and autofills form fields
 * @param {string} assetTag - The asset tag to fetch information for
 * @param {string} formType - Either 'add' or 'edit' to determine which form to update
 * @param {boolean} showNotification - Whether to show a notification when data is autofilled
 */
function fetchAssetTagInfo(assetTag, formType, showNotification = true) {
    if (!assetTag) return;
    
    console.log(`Fetching data for asset tag: ${assetTag} (form: ${formType})`);
    
    // Determine which form elements to update based on formType
    const locationField = formType === 'add' ? 
        document.querySelector('input[name="location"]') : 
        document.getElementById('edit_location');
        
    const accountableField = formType === 'add' ? 
        document.querySelector('input[name="accountable_individual"]') : 
        document.getElementById('edit_accountable_individual');
    
    // Check if fields exist before proceeding
    if (!locationField || !accountableField) {
        console.error('Location or accountable field not found in the DOM');
        return;
    }
    
    // Make AJAX request to get asset tag information
    $.ajax({
        url: window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1) + 'asset_tag_info.php',
        method: 'POST',
        data: {
            action: 'get_asset_info',
            asset_tag: assetTag
        },
        dataType: 'json',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(response) {
            if (response.status === 'success' && response.data) {
                // Autofill location if available
                if (response.data.location) {
                    locationField.value = response.data.location;
                    locationField.setAttribute('data-autofill', 'true');
                }
                
                // Autofill accountable individual if available
                if (response.data.accountable_individual) {
                    accountableField.value = response.data.accountable_individual;
                    accountableField.setAttribute('data-autofill', 'true');
                }
                
                // Show notification if requested and data was found
                if (showNotification && (response.data.location || response.data.accountable_individual)) {
                    showToast('Location and accountable individual information autofilled from existing records', 'info');
                }
            } else if (showNotification) {
                console.log('No asset tag info found');
                // Optional: Show notification that no data was found
                // showToast('No location or accountable individual information found for this asset tag', 'info');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error fetching asset tag info:', error);
            if (showNotification) {
                showToast('Error fetching asset tag information', 'error');
            }
        }
    });
}

/**
 * Updates equipment_location when changes are made to equipment_details
 * This function should be called after successful submission of equipment details forms
 * @param {string} assetTag - The asset tag that was updated
 * @param {string} location - The location value (will be parsed to extract building and area)
 * @param {string} accountableIndividual - The accountable individual
 */
function updateEquipmentLocationFromDetails(assetTag, location, accountableIndividual) {
    if (!assetTag) return;
    
    // Parse location to extract building and specific area
    // Format is typically "Building, Area"
    let buildingLoc = '';
    let specificArea = '';
    
    if (location) {
        const locationParts = location.split(',');
        if (locationParts.length > 1) {
            buildingLoc = locationParts[0].trim();
            specificArea = locationParts[1].trim();
        } else {
            // If no comma, assume it's all building location
            buildingLoc = location.trim();
        }
    }
    
    // Make AJAX request to update equipment location
    $.ajax({
        url: window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) + '/equipment_location_update.php',
        method: 'POST',
        data: {
            action: 'update_from_details',
            asset_tag: assetTag,
            building_loc: buildingLoc,
            specific_area: specificArea,
            person_responsible: accountableIndividual
        },
        dataType: 'json',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(response) {
            if (response.status === 'success') {
                console.log('Equipment location updated successfully from details change');
            } else {
                console.error('Failed to update equipment location:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error updating equipment location:', error);
        }
    });
}

/**
 * Updates equipment details when changes are made to equipment location
 * This function should be called after successful submission of equipment location forms
 * @param {string} assetTag - The asset tag that was updated
 * @param {string} buildingLoc - The building location
 * @param {string} specificArea - The specific area
 * @param {string} personResponsible - The person responsible (accountable individual)
 */
function updateEquipmentDetailsFromLocation(assetTag, buildingLoc, specificArea, personResponsible) {
    if (!assetTag) {
        console.error('Cannot update equipment details: Asset tag is missing');
        return;
    }
    
    console.log('Updating equipment details from location:', {
        assetTag,
        buildingLoc,
        specificArea,
        personResponsible
    });
    
    // Format location as "Building, Area" if both are available
    let location = '';
    if (buildingLoc && specificArea) {
        location = buildingLoc + ', ' + specificArea;
    } else if (buildingLoc) {
        location = buildingLoc;
    } else if (specificArea) {
        location = specificArea;
    }
    
    console.log('Formatted location value:', location);
    
    // Make AJAX request to update equipment details
    $.ajax({
        url: window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/')) + '/equipment_details_update.php',
        method: 'POST',
        data: {
            action: 'update_from_location',
            asset_tag: assetTag,
            location: location,
            accountable_individual: personResponsible
        },
        dataType: 'json',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(response) {
            console.log('Equipment details update response:', response);
            if (response.status === 'success') {
                console.log('Equipment details updated successfully from location change');
                
                // If we're on the equipment_details.php page, handle the update
                if (window.location.href.indexOf('equipment_details.php') > -1) {
                    if (response.refresh_needed) {
                        // Force a page reload to show the updated data
                        window.location.reload();
                    } else {
                        // Use the existing refresh function if available
                        if (typeof refreshEquipmentList === 'function') {
                            refreshEquipmentList();
                        } else {
                            // Fallback: reload the table via AJAX
                            $.get(window.location.href, function(html) {
                                const newTbody = $(html).find('#equipmentTable').html();
                                $('#equipmentTable').html(newTbody);
                                // Re-initialize pagination if needed
                                if (typeof initPagination === 'function') {
                                    initPagination({
                                        tableId: 'equipmentTable',
                                        currentPage: 1
                                    });
                                }
                            });
                        }
                    }
                }
            } else {
                console.error('Failed to update equipment details:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error updating equipment details:', error);
            console.error('XHR status:', status);
            console.error('XHR object:', xhr);
        }
    });
}

// Add a helper function for initializing Select2 dropdowns with proper event handling
function initFilterSelect2(selector, placeholder) {
    // Destroy any existing Select2 instance
    if ($(selector).data('select2')) {
        $(selector).select2('destroy');
    }
    
    // Initialize Select2 with proper settings
    $(selector).select2({
        placeholder: placeholder || 'Select an option',
        allowClear: true,
        width: '100%',
        dropdownAutoWidth: true,
        minimumResultsForSearch: 0
    }).on('select2:select', function() {
        // Explicitly trigger the change event after selection
        $(this).trigger('change');
    }).on('select2:clear', function() {
        // Set to default value and trigger change
        $(this).val('all').trigger('change');
    });
}

// Function to safely handle filter changes
function safeFilterChange(selector, value) {
    // Set the value directly first
    $(selector).val(value);
    
    // If using Select2, update the UI properly
    if ($(selector).data('select2')) {
        $(selector).trigger('change.select2');
    }
    
    // Always trigger the standard change event
    $(selector).trigger('change');
}

// Add event listeners based on which page we're on
$(document).ready(function() {
    // Check if we're on the equipment location page
    if (window.location.href.indexOf('equipment_location.php') > -1) {
        
        // Add event listener for the add location form submission
        $('#addLocationForm').on('submit', function() {
            try {
                // Get values directly from form fields at submission time
                const assetTag = $('#add_location_asset_tag').val();
                // Use more specific selectors to get the correct form fields
                const buildingLoc = $('#addLocationForm input[name="building_loc"]').val();
                const specificArea = $('#addLocationForm input[name="specific_area"]').val();
                const personResponsible = $('#addLocationForm input[name="person_responsible"]').val();
                
                console.log('Form submission - captured values:', {
                    assetTag,
                    buildingLoc,
                    specificArea,
                    personResponsible
                });
                
                // Store the values to be used after successful form submission
                sessionStorage.setItem('updated_asset_tag', assetTag);
                sessionStorage.setItem('updated_building_loc', buildingLoc);
                sessionStorage.setItem('updated_specific_area', specificArea);
                sessionStorage.setItem('updated_person_responsible', personResponsible);
            } catch (error) {
                console.error('Error capturing form data:', error);
            }
        });
        
        // Add event listener for the edit location form submission
        $('#editLocationForm').on('submit', function() {
            try {
                // Get values directly from form fields at submission time
                const assetTag = $('#edit_location_asset_tag').val();
                // Use more specific selectors to get the correct form fields
                const buildingLoc = $('#editLocationForm #edit_building_loc').val();
                const specificArea = $('#editLocationForm #edit_specific_area').val();
                const personResponsible = $('#editLocationForm #edit_person_responsible').val();
                
                console.log('Edit form submission - captured values:', {
                    assetTag,
                    buildingLoc,
                    specificArea,
                    personResponsible
                });
                
                // Store the values to be used after successful form submission
                sessionStorage.setItem('updated_asset_tag', assetTag);
                sessionStorage.setItem('updated_building_loc', buildingLoc);
                sessionStorage.setItem('updated_specific_area', specificArea);
                sessionStorage.setItem('updated_person_responsible', personResponsible);
            } catch (error) {
                console.error('Error capturing form data:', error);
            }
        });
        
        // Listen for AJAX success responses to detect when forms are successfully submitted
        $(document).ajaxSuccess(function(event, xhr, settings) {
            // Check if this is a response to our form submissions
            if (settings.url === window.location.href && 
                (settings.data.indexOf('action=add') > -1 || settings.data.indexOf('action=update') > -1)) {
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        // Get the stored values
                        const assetTag = sessionStorage.getItem('updated_asset_tag');
                        const buildingLoc = sessionStorage.getItem('updated_building_loc');
                        const specificArea = sessionStorage.getItem('updated_specific_area');
                        const personResponsible = sessionStorage.getItem('updated_person_responsible');
                        
                        console.log('AJAX success - using stored values:', {
                            assetTag,
                            buildingLoc,
                            specificArea,
                            personResponsible
                        });
                        
                        // Validate that we have the necessary data
                        if (!assetTag) {
                            console.error('Missing asset tag in stored data, cannot update equipment details');
                            return;
                        }
                        
                        // Update equipment details
                        updateEquipmentDetailsFromLocation(assetTag, buildingLoc, specificArea, personResponsible);
                        
                        // Clear the stored values
                        sessionStorage.removeItem('updated_asset_tag');
                        sessionStorage.removeItem('updated_building_loc');
                        sessionStorage.removeItem('updated_specific_area');
                        sessionStorage.removeItem('updated_person_responsible');
                    }
                } catch (error) {
                    console.error('Error parsing AJAX response:', error);
                    console.error('Raw response text:', xhr.responseText);
                }
            }
        });
    }
    
    // Check if we're on the equipment details page
    if (window.location.href.indexOf('equipment_details.php') > -1) {
        
        // Add event listener for the add equipment form submission
        $('#addEquipmentForm').on('submit', function() {
            const assetTag = $('#add_equipment_asset_tag').val();
            const location = $('input[name="location"]').val();
            const accountableIndividual = $('input[name="accountable_individual"]').val();
            
            // Store the values to be used after successful form submission
            sessionStorage.setItem('details_asset_tag', assetTag);
            sessionStorage.setItem('details_location', location);
            sessionStorage.setItem('details_accountable', accountableIndividual);
        });
        
        // Add event listener for the edit equipment form submission
        $('#editEquipmentForm').on('submit', function() {
            const assetTag = $('#edit_equipment_asset_tag').val();
            const location = $('#edit_location').val();
            const accountableIndividual = $('#edit_accountable_individual').val();
            
            // Store the values to be used after successful form submission
            sessionStorage.setItem('details_asset_tag', assetTag);
            sessionStorage.setItem('details_location', location);
            sessionStorage.setItem('details_accountable', accountableIndividual);
        });
        
        // Listen for AJAX success responses to detect when forms are successfully submitted
        $(document).ajaxSuccess(function(event, xhr, settings) {
            // Check if this is a response to our form submissions
            if ((settings.data.indexOf('action=create') > -1 || settings.data.indexOf('action=update') > -1)) {
                
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.status === 'success') {
                        // Get the stored values
                        const assetTag = sessionStorage.getItem('details_asset_tag');
                        const location = sessionStorage.getItem('details_location');
                        const accountableIndividual = sessionStorage.getItem('details_accountable');
                        
                        // Update equipment location
                        updateEquipmentLocationFromDetails(assetTag, location, accountableIndividual);
                        
                        // Clear the stored values
                        sessionStorage.removeItem('details_asset_tag');
                        sessionStorage.removeItem('details_location');
                        sessionStorage.removeItem('details_accountable');
                    }
                } catch (error) {
                    console.error('Error parsing AJAX response:', error);
                }
            }
        });
    }
}); 