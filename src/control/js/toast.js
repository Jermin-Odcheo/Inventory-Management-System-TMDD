// toast.js
function showToast(message, type = 'success') {
    const toastId = 'toast-' + Date.now();
    const toastClass = type === 'success' ? 'toast-success' : 'toast-error';
    const iconClass = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
    const duration = 3000; // Duration in milliseconds

    const toastHTML = `
        <div id="${toastId}" class="custom-toast ${toastClass}">
            <div class="toast-header">
                <i class="bi ${iconClass} me-2"></i>
                <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
                <button type="button" class="toast-close" onclick="closeToast('${toastId}')">&times;</button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
            <div id="progress-${toastId}" class="toast-progress"></div>
        </div>
    `;
    document.getElementById('toastContainer').insertAdjacentHTML('beforeend', toastHTML);

    // Show the toast with a slight delay
    setTimeout(() => {
        const toast = document.getElementById(toastId);
        toast.style.display = 'block';
        toast.offsetHeight; // Force reflow
        toast.classList.add('show');

        // Start the progress bar animation
        const progressBar = document.getElementById(`progress-${toastId}`);
        progressBar.style.animation = `countdown ${duration / 1000}s linear forwards`;
    }, 100);

    // Auto-hide the toast after the duration
    setTimeout(() => {
        closeToast(toastId);
    }, duration);
}

function closeToast(toastId) {
    const toast = document.getElementById(toastId);
    if (toast) {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300); // Match transition duration
    }
}
