<div class="section">
<h2>One-Click WordPress Install</h2>
<p style="color:#64748b;font-size:13px;margin-bottom:16px">Creates a site directory, downloads WordPress, sets up MariaDB, and prepares wp-config.</p>
<form method="post">
<input type="hidden" name="action" value="wp_install">
<input type="text" name="wp_site" placeholder="Site name (e.g. myblog)" required pattern="[a-z0-9_-]+">
<input type="text" name="wp_title" placeholder="Site title (e.g. My Blog)">
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
<input type="text" name="wp_user" placeholder="Admin username" value="admin" required>
<input type="password" name="wp_pass" placeholder="Admin password" required>
</div>
<input type="email" name="wp_email" placeholder="Admin email" value="admin@localhost.local">
<button type="submit" class="btn-form">Install WordPress</button>
</form>
</div>
