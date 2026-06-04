<?php
declare(strict_types=1);require __DIR__.'/admin-core.php';require __DIR__.'/database.php';$result=null;try{$pdo=ho_db();$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$result=['ok'=>true,'tables'=>$tables];}catch(Throwable $e){$result=['ok'=>false,'message'=>$e->getMessage()];}ho_admin_render_start('portal','Sales Portal DB Check','Sales portal','DB <em>Check</em>','Checks whether database.php can connect and see the imported tables.');?>

<section class="admin-operator-banner">
  <div>
    <strong>Database check</strong>
    <span>This page supports the operator workflow. Use Prospects as the main working surface unless this page is needed for reference or maintenance.</span>
  </div>
  <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Prospects</a>
</section>

<section class="admin-card"><h2><?=$result['ok']?'Connected':'Connection Failed'?></h2><?php if($result['ok']):?><p>Database connection works. Tables found:</p><?=ho_admin_doc_list($result['tables'])?><?php else:?><p><?=ho_h($result['message'])?></p><?php endif;?></section><?php ho_admin_render_end();?>
