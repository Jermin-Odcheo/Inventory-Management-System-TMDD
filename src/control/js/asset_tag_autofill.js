/**
 * Asset Tag Autofill Functionality
 * Fetches location and accountable individual data based on asset tag selection
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
        url: window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1) + 'asset_tag_info.php',  // Use current directory
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