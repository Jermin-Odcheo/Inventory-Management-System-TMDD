document.addEventListener('DOMContentLoaded', function () {
    var dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(function (toggle) {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var parentLi = this.parentElement;
            parentLi.classList.toggle('open');

            // Rotate the dropdown arrow
            var icon = this.querySelector('.dropdown-icon');
            if (parentLi.classList.contains('open')) {
                icon.style.transform = 'rotate(180deg)';
            } else {
                icon.style.transform = 'rotate(0deg)';
            }
        });
    });

    // Close dropdowns when clicking outside the sidebar
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.sidebar')) {
            document.querySelectorAll('.dropdown-item.open').forEach(function (item) {
                item.classList.remove('open');
                var icon = item.querySelector('.dropdown-icon');
                if (icon) icon.style.transform = 'rotate(0deg)';
            });
        }
    });
});
