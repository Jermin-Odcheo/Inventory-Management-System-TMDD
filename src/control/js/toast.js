// Ultra-modern toast notification system with enhanced animations
function showToast(message, type = 'info', duration = 2800, title = null) {
    // Create or get container
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }

    // Create toast element with enhanced design
    const toast = document.createElement('div');
    toast.className = `custom-toast toast-${type}`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'polite');

    // Create header
    const header = document.createElement('div');
    header.className = 'toast-header';

    // Add icon with appropriate symbol
    const icon = document.createElement('div');
    icon.className = 'toast-icon';

    let iconContent = '';
    switch(type) {
        case 'success':
            iconContent = '✓';
            break;
        case 'error':
            iconContent = '×';
            break;
        case 'warning':
            iconContent = '!';
            break;
        case 'info':
        default:
            iconContent = 'i';
            break;
    }
    icon.textContent = iconContent;
    header.appendChild(icon);

    // Add title with appropriate text based on type or custom title
    const titleElement = document.createElement('h5');
    titleElement.className = 'toast-title';

    // Use custom title if provided, otherwise use default based on type
    if (title) {
        titleElement.textContent = title;
    } else {
        switch(type) {
            case 'success':
                titleElement.textContent = 'Success';
                break;
            case 'error':
                titleElement.textContent = 'Error';
                break;
            case 'warning':
                titleElement.textContent = 'Warning';
                break;
            case 'info':
            default:
                titleElement.textContent = 'Information';
                break;
        }
    }
    header.appendChild(titleElement);

    // Add modern close button
    const closeBtn = document.createElement('button');
    closeBtn.className = 'toast-close';
    closeBtn.innerHTML = '×';
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.onclick = function(event) {
        event.stopPropagation(); // Prevent triggering toast click
        dismissToast(toast);
    };
    header.appendChild(closeBtn);

    toast.appendChild(header);

    // Add body with support for HTML content
    const body = document.createElement('div');
    body.className = 'toast-body';

    // Check if we're dealing with HTML or plain text
    if (message.includes('<') && message.includes('>')) {
        body.innerHTML = message;
    } else {
        body.textContent = message;
    }

    toast.appendChild(body);

    // Add progress bar with gradient
    const progress = document.createElement('div');
    progress.className = 'toast-progress';

    // Only animate progress if duration is positive
    if (duration > 0) {
        progress.style.animation = `countdown ${duration}ms linear forwards`;
    }

    toast.appendChild(progress);

    // Add to DOM with a slight delay between multiple toasts
    const delay = Math.min(container.children.length * 100, 300);

    setTimeout(() => {
        container.appendChild(toast);

        // Force reflow before adding show class
        void toast.offsetWidth;

        // Trigger show animation
        toast.classList.add('show');
        toast.classList.add('new-toast');

        // Remove new-toast class after animation completes
        setTimeout(() => {
            toast.classList.remove('new-toast');
        }, 500);

        // Auto-dismiss
        if (duration > 0) {
            const dismissTimeout = setTimeout(() => {
                dismissToast(toast);
            }, duration);

            // Store the timeout ID so we can cancel it if manually closed
            toast._dismissTimeout = dismissTimeout;
        }

        // Make the entire toast clickable (optional) - can remove if not desired
        toast.addEventListener('click', function(event) {
            // Don't trigger if they clicked the close button
            if (event.target !== closeBtn && !closeBtn.contains(event.target)) {
                // Custom action when toast is clicked - modify as needed
            }
        });

    }, delay);

    return toast;
}

// Enhanced dismiss animation
function dismissToast(toast) {
    // Clear any existing dismiss timeout
    if (toast._dismissTimeout) {
        clearTimeout(toast._dismissTimeout);
    }

    // Add hide class to trigger exit animation
    toast.classList.add('hide');
    toast.classList.remove('show');

    // Remove from DOM after animation completes
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);

            // Remove container if empty
            const container = document.getElementById('toastContainer');
            if (container && container.children.length === 0) {
                document.body.removeChild(container);
            }
        }
    }, 600); // Match the CSS transition duration
}

// Create specialized toast functions for convenience
const Toast = {
    success: function(message, duration = 5000, title = null) {
        return showToast(message, 'success', duration, title);
    },

    error: function(message, duration = 5000, title = null) {
        return showToast(message, 'error', duration, title);
    },

    warning: function(message, duration = 5000, title = null) {
        return showToast(message, 'warning', duration, title);
    },

    info: function(message, duration = 5000, title = null) {
        return showToast(message, 'info', duration, title);
    }
};

// Optional: Utility to dismiss all visible toasts
function dismissAllToasts() {
    const container = document.getElementById('toastContainer');
    if (container) {
        const toasts = container.querySelectorAll('.custom-toast');
        toasts.forEach(toast => {
            dismissToast(toast);
        });
    }
}

// Usage examples:
// showToast('Your file has been uploaded successfully!', 'success', 5000);
// Toast.error('Failed to connect to the server. Please try again.');
// Toast.warning('Your session will expire in 5 minutes.', 10000, 'Session Expiring');
// Toast.info('<strong>Tip:</strong> You can also use HTML in your messages!');