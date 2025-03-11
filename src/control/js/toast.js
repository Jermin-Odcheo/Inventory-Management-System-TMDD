// Ultra-modern badass toast notification system
function showToast(message, type = 'info', duration = 5000) {
    // Create or get container
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }

    // Create toast element with badass design
    const toast = document.createElement('div');
    toast.className = `custom-toast toast-${type}`;

    // Create header
    const header = document.createElement('div');
    header.className = 'toast-header';

    // Add slick icon with appropriate symbol
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

    // Add title with appropriate text based on type
    const title = document.createElement('h5');
    title.className = 'toast-title';

    switch(type) {
        case 'success':
            title.textContent = 'Success';
            break;
        case 'error':
            title.textContent = 'Error';
            break;
        case 'warning':
            title.textContent = 'Warning';
            break;
        case 'info':
        default:
            title.textContent = 'Information';
            break;
    }
    header.appendChild(title);

    // Add modern close button
    const closeBtn = document.createElement('button');
    closeBtn.className = 'toast-close';
    closeBtn.innerHTML = '×';
    closeBtn.setAttribute('aria-label', 'Close');
    closeBtn.onclick = function() {
        dismissToast(toast);
    };
    header.appendChild(closeBtn);

    toast.appendChild(header);

    // Add sleek body
    const body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = message;
    toast.appendChild(body);

    // Add progress bar with gradient
    const progress = document.createElement('div');
    progress.className = 'toast-progress';
    progress.style.animation = `countdown ${duration}ms linear forwards`;
    toast.appendChild(progress);

    // Add to DOM with a slight delay between multiple toasts
    setTimeout(() => {
        container.appendChild(toast);

        // Trigger show animation
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        // Add sound effect for extra badassery (optional)
        // playToastSound(type);

        // Auto-dismiss
        if (duration > 0) {
            setTimeout(() => {
                dismissToast(toast);
            }, duration);
        }
    }, container.children.length * 100);

    return toast;
}

// Smooth dismiss animation
function dismissToast(toast) {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(20px) scale(0.98)';

    // Remove from DOM after animation completes
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);

            // Remove container if empty
            const container = document.getElementById('toastContainer');
            if (container && container.children.length === 0) {
                container.parentNode.removeChild(container);
            }
        }
    }, 400); // Match the CSS transition time
}

// Optional: Play sound based on toast type
function playToastSound(type) {
    // Only implement if you want sound effects
    const audio = new Audio();

    switch(type) {
        case 'success':
            audio.src = 'path/to/success-sound.mp3';
            break;
        case 'error':
            audio.src = 'path/to/error-sound.mp3';
            break;
        case 'warning':
            audio.src = 'path/to/warning-sound.mp3';
            break;
        case 'info':
            audio.src = 'path/to/info-sound.mp3';
            break;
    }

    audio.volume = 0.5;
    audio.play().catch(e => {
        // Browser might block autoplay, just ignore
    });
}