<div class="df jb ac fw g2 mb4">
  <div class="st" style="margin-bottom:0">Service Status</div>
  <div class="df ac g2 fw">
    <form method="post" style="display:inline">
      <?= csrf() ?>
      <input type="hidden" name="action" value="restart_all">
      <button type="submit" class="btn btn-w btn-s" onclick="return confirm('Restart all services?')">↻ Restart All</button>
    </form>
  </div>
</div>
<div class="sr">
<?php $icons = ['Nginx'=>'nx','PHP-FPM'=>'pp','MariaDB'=>'my']; ?>
<?php foreach ($services as $name => $s): $is_running = $status[$name]; ?>
<div class="sc">
  <div class="sc-h">
    <div class="sc-ic <?= $icons[$name]??'nx' ?>"><?= $name==='Nginx'?'<i class="fas fa-bolt"></i>':($name==='PHP-FPM'?'<i class="fas fa-server"></i>':'<i class="fas fa-database"></i>') ?></div>
    <span class="bdg <?= $is_running ? 'on' : 'off' ?>"><span class="dt"></span><?= $is_running ? 'Running' : 'Stopped' ?></span>
  </div>
  <div class="sc-n"><?= htmlspecialchars($name) ?></div>
  <div class="sc-ac">
    <?php if (!$is_running): ?>
    <form method="post"><?= csrf() ?><input type="hidden" name="action" value="start"><input type="hidden" name="service" value="<?= $name ?>"><button type="submit" class="btn btn-st btn-s">▶ Start</button></form>
    <?php else: ?>
    <form method="post"><?= csrf() ?><input type="hidden" name="action" value="stop"><input type="hidden" name="service" value="<?= $name ?>"><button type="submit" class="btn btn-sp btn-s" onclick="return confirm('Stop <?= $name ?>?')">■ Stop</button></form>
    <?php endif; ?>
    <form method="post"><?= csrf() ?><input type="hidden" name="action" value="restart"><input type="hidden" name="service" value="<?= $name ?>"><button type="submit" class="btn btn-rs btn-s" onclick="return confirm('Restart <?= $name ?>?')">↻ Restart</button></form>
  </div>
</div>
<?php endforeach; ?>
</div>

