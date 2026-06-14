<?php
error_reporting(E_ALL);
ini_set('display_errors', isset($_GET['debug']) ? '1' : '0');
session_start();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function db(){
  static $p=null; if($p) return $p;
  foreach(array(__DIR__.'/../../database.php', __DIR__.'/../database.php') as $f){ if(is_file($f)){ require_once $f; break; } }
  if(function_exists('ho_db')) $p=ho_db();
  elseif(isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) $p=$GLOBALS['pdo'];
  else throw new Exception('No DB connection found.');
  $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  return $p;
}
function setting($p,$k){ try{$s=$p->prepare('SELECT v FROM wmt_settings WHERE k=?');$s->execute(array($k));$v=$s->fetchColumn();return $v===false?null:$v;}catch(Exception $e){return null;} }
function auth($p){
  $hash=setting($p,'admin_hash');
  if(!$hash){ header('Location:index.php'); exit; }
  if(($_POST['form']??'')==='login' && password_verify($_POST['password']??'', $hash)){ $_SESSION['wmt']=1; header('Location:export.php'); exit; }
  if(empty($_SESSION['wmt'])){ echo '<!doctype html><title>Login</title><link rel="stylesheet" href="walmart-theme.css"><div class="card" style="max-width:420px;margin:12vh auto"><h1>AP Services Login</h1><form method="post"><input type="hidden" name="form" value="login"><input type="password" name="password" placeholder="Password"><button>Continue</button></form></div>'; exit; }
}
function tables(){ return array(
  'wmt_settings','wmt_associates','wmt_shifts','wmt_tasks','wmt_turnins','wmt_exceptions','wmt_html_imports','wmt_task_assignments','wmt_assignment_items','wmt_auto_assignments'
); }
function table_exists($p,$table){ try{$s=$p->prepare('SHOW TABLES LIKE ?');$s->execute(array($table));return (bool)$s->fetchColumn();}catch(Exception $e){return false;} }
function rows($p,$table){ if(!table_exists($p,$table)) return array(); return $p->query('SELECT * FROM `'.$table.'`')->fetchAll(); }
function counts($p){ $out=array(); foreach(tables() as $t){ if(table_exists($p,$t)){ $out[$t]=(int)$p->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn(); } else $out[$t]=null; } return $out; }
function filename($base,$ext){ return $base.'_'.date('Ymd_His').'.'.$ext; }
function export_json($p){ $data=array('exported_at'=>date('c'),'source'=>'hoosieronline.com/wmt','tables'=>array()); foreach(tables() as $t){ $data['tables'][$t]=rows($p,$t); } header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="'.filename('wmt_all_data','json').'"'); echo json_encode($data, JSON_PRETTY_PRINT); exit; }
function csv_line($fh,$row){ fputcsv($fh,$row); }
function export_csv($p,$table){ if(!in_array($table,tables(),true)) throw new Exception('Table not allowed.'); $data=rows($p,$table); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="'.filename($table,'csv').'"'); $fh=fopen('php://output','w'); if($data){ csv_line($fh,array_keys($data[0])); foreach($data as $r) csv_line($fh,$r); } else { csv_line($fh,array('empty_or_missing_table')); } fclose($fh); exit; }
function export_sql($p){ header('Content-Type: text/plain; charset=utf-8'); header('Content-Disposition: attachment; filename="'.filename('wmt_backup','sql').'"'); echo "-- WMT AP Services export\n-- Exported: ".date('c')."\n\n"; foreach(tables() as $t){ if(!table_exists($p,$t)){ echo "-- Missing table: `$t`\n\n"; continue; } echo "-- Table: `$t`\n"; $rows=rows($p,$t); if(!$rows){ echo "-- No rows\n\n"; continue; } foreach($rows as $r){ $cols=array(); $vals=array(); foreach($r as $k=>$v){ $cols[]='`'.str_replace('`','``',$k).'`'; $vals[]=$v===null?'NULL':$p->quote((string)$v); } echo 'INSERT INTO `'.$t.'` ('.implode(',',$cols).') VALUES ('.implode(',',$vals).');' . "\n"; } echo "\n"; } exit; }
function export_manifest($p){ header('Content-Type: application/json; charset=utf-8'); echo json_encode(array('exported_at'=>date('c'),'counts'=>counts($p),'tables'=>tables()),JSON_PRETTY_PRINT); exit; }
function head($title){ echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).'</title><link rel="stylesheet" href="walmart-theme.css"><div class="top no-print"><div class="wrap"><b>AP Services Tool</b><div class="nav"><a href="index.php">Dashboard</a><a href="assignments.php">Assignments</a><a href="tasks.php">Door Tasks</a><a href="import_html.php">HTML Import</a><a href="export.php">Export</a></div></div></div><main class="wrap">'; }
function foot(){ echo '</main>'; }
try{
  $p=db(); auth($p);
  $format=$_GET['format']??'';
  if($format==='json') export_json($p);
  if($format==='csv') export_csv($p,$_GET['table']??'');
  if($format==='sql') export_sql($p);
  if($format==='manifest') export_manifest($p);
  $counts=counts($p);
  head('Export WMT Data');
  echo '<div class="card"><h1 class="print-title">Export All WMT Data</h1><p class="muted">Use this page to back up or move the AP Services system data. Code lives in GitHub; schedule records, assignments, turn-ins, tasks, exceptions, and history live in MySQL.</p></div>';
  echo '<div class="card"><h2>Full exports</h2><p><a class="btn" href="?format=json">Download all data as JSON</a> <a class="btn" href="?format=sql">Download SQL insert backup</a> <a class="btn" href="?format=manifest">View manifest JSON</a></p></div>';
  echo '<div class="card"><h2>Table exports</h2><table><tr><th>Table</th><th>Rows</th><th>Download</th></tr>';
  foreach(tables() as $t){ $c=$counts[$t]; echo '<tr><td><b>'.h($t).'</b></td><td>'.($c===null?'<span class="bad">Missing</span>':h($c)).'</td><td>'.($c===null?'-':'<a class="btn" href="?format=csv&amp;table='.h($t).'">CSV</a>').'</td></tr>'; }
  echo '</table></div>';
  echo '<div class="card"><h2>Recommended backup rhythm</h2><p>Before rebuilding logic, download <b>all data as JSON</b>. After a good week of usage, download JSON again so assignment history and completion records are preserved outside MySQL.</p></div>';
  foot();
}catch(Throwable $e){ http_response_code(500); head('Export error'); echo '<div class="card"><h1>Export error</h1><pre>'.h($e->getMessage()).'</pre></div>'; foot(); }
