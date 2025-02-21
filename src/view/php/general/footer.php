
<footer class="footer">
    <p class="footer-text">&copy; 2025 TMDD Interns</p>
</footer>

<style>
    /* Base styles for the footer */
    .footer {
        width: 100%;
        text-align: center;
        padding: 10px;
    }
    .footer-text {
        margin: 0;           /* Remove default margin */
        font-size: 14px;     /* Set a modest font size */
        /* Default color; will be overridden by JavaScript */
        color: #000000;
    }
    /* Sticky footer class (added via JavaScript) */
    .footer.sticky {
        position: fixed;
        bottom: 0;
        left: 0;
    }
</style>

<script>
    /**
     * Parses an rgb/rgba string (e.g. "rgb(255, 255, 255)") and returns its brightness.
     * Uses the formula: brightness = (299*R + 587*G + 114*B) / 1000.
     */
    function getBrightness(rgb) {
        // Expecting format: "rgb(r, g, b)" or "rgba(r, g, b, a)"
        var result = rgb.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (result) {
            var r = parseInt(result[1], 10);
            var g = parseInt(result[2], 10);
            var b = parseInt(result[3], 10);
            return (r * 299 + g * 587 + b * 114) / 1000;
        }
        return 255; // Default to bright if parsing fails
    }

    // Adjust the footer position if content is short
    function adjustFooter() {
        var footer = document.querySelector('.footer');
        if (document.body.scrollHeight <= window.innerHeight) {
            footer.classList.add('sticky');
        } else {
            footer.classList.remove('sticky');
        }
    }

    // Adjust the footer text color based on the page background brightness.
    function adjustFooterTextColor() {
        var footerText = document.querySelector('.footer-text');
        // Get the computed background color of the body.
        var bodyBg = window.getComputedStyle(document.body).backgroundColor;
        var brightness = getBrightness(bodyBg);
        // You can adjust the threshold (here 128) as needed.
        if (brightness > 128) {
            // Light background: use dark text with a subtle dark glow.
            footerText.style.color = '#000000';
            footerText.style.textShadow = '0 0 8px rgba(0, 0, 0, 0.7)';
        } else {
            // Dark background: use white text with a subtle light glow.
            footerText.style.color = '#ffffff';
            footerText.style.textShadow = '0 0 8px rgba(255, 255, 255, 0.7)';
        }
    }

    // Run our adjustments on load and on window resize.
    window.addEventListener('load', function() {
        adjustFooter();
        adjustFooterTextColor();
    });
    window.addEventListener('resize', function() {
        adjustFooter();
        adjustFooterTextColor();
    });
</script>