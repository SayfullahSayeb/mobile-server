<div class="sec">
  <div class="st">🔷 One-Click WordPress Install</div>
  <div class="st3">Creates a site directory, downloads WordPress, sets up MariaDB, and prepares wp-config.</div>
  <form method="post">
    <?= csrf() ?>
    <input type="hidden" name="action" value="wp_install">
    <input type="text" name="wp_site" class="inp" placeholder="Site name (e.g. myblog)" required pattern="[a-z0-9_-]+">
    <input type="text" name="wp_title" class="inp" placeholder="Site title (e.g. My Blog)">
    <div class="fr2">
      <input type="text" name="wp_user" class="inp" placeholder="Admin username" value="admin" required>
      <input type="password" name="wp_pass" class="inp" placeholder="Admin password" required>
    </div>
    <input type="email" name="wp_email" class="inp" placeholder="Admin email" value="admin@localhost.local">
    <button type="submit" class="btn btn-p btn-l">📥 Install WordPress</button>
  </form>
</div>
