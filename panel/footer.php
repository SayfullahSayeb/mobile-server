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
function toggleSidebar() {
    var sb = document.getElementById('sidebar');
    var ov = document.querySelector('.sb-overlay');
    sb.classList.toggle('show');
    if (sb.classList.contains('show')) {
        ov.classList.add('show');
    } else {
        ov.classList.remove('show');
    }
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('show');
    document.querySelector('.sb-overlay').classList.remove('show');
}
var navLinks = document.querySelectorAll('.nav-i');
for (var i = 0; i < navLinks.length; i++) {
    navLinks[i].addEventListener('click', closeSidebar);
}
</script>
</div>
</body>
</html>
