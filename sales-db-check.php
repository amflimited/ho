<?php
declare(strict_types=1);require __DIR__.'/admin-core.php';require __DIR__.'/../database.php';$result=null;try{$pdo=ho_db();$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$result=['ok'=>true,'tables'=>$tables];}catch(Throwable $e){$result=['ok'=>false,'message'=>$e->getMessage()];}ho_admin_render_start(
    'tools',
    'System Check',
    'Tool',
    'System <em>Check</em>',
    'Use only when something feels wrong or after installing an update.'
);?>

<section class="admin-tool-return admin-card">
  <p class="admin-kicker">Tool Surface</p>
  <h2>Diagnostic Tool</h2>
  <p class="admin-muted">Use this when the database, import flow, or admin pages feel wrong. Return to Work Queue when the check is complete.</p>
  <div class="admin-action-row">
    <a class="admin-btn admin-btn-primary" href="/sales-portal-dashboard.php">Return To Work Queue</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php#dashboard-import">Go To Intake</a>
  </div>
</section>



<section class="admin-operator-banner">
  <div>
    <strong>Database check</strong>
    <span>This page supports the operator workflow. Use Prospects as the main working surface unless this page is needed for reference or maintenance.</span>
  </div>
  <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Prospects</a>
</section>

<section class="admin-card"><h2><?=$result['ok']?'Connected':'Connection Failed'?></h2><?php if($result['ok']):?><p>Database connection works. Tables found:</p><?=ho_admin_doc_list($result['tables'])?><?php else:?><p><?=ho_h($result['message'])?></p><?php endif;?></section><?php ho_admin_render_end();?>
