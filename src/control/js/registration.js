document.getElementById('first_name').addEventListener('input', function(event) {
    this.value = this.value
        .toLowerCase()
        .replace(/(^\w|\s\w)/g, match => match.toUpperCase());
});

document.getElementById('last_name').addEventListener('input', function(event) {
    this.value = this.value
        .toLowerCase()
        .replace(/(^\w|\s\w)/g, match => match.toUpperCase());
});

document.getElementById('email').addEventListener('input', function(event) {
    this.value = this.value.replace(/\s/g, '');
});