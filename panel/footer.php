
<script>
function copyUrl(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('URL copied to clipboard');
    }).catch(function() {
        var input = document.createElement('input');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        alert('URL copied to clipboard');
    });
}
</script>
<footer>Mobile Server — Nginx &bull; PHP-FPM &bull; MariaDB</footer>
</div>
</body>
</html>
