<?php
error_reporting(E_ALL);
ini_set('display_errors', isset($_GET['debug']) ? '1' : '0');
ini_set('log_errors', '1');
session_start();

function wmt_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function wmt_contains($s,$n){ return strpos((string)$s,(string)$n) !== false; }
function wmt_mins($t){ if(!preg_match('/^(\d{1,2}):(\d{2})/',(string)$t,$m)) return 0; return ((int)$m[1]*60)+(int)$m[2]; }
function wmt_time($m){ $m=(($m%1440)+1440)%1440; return sprintf('%02d:%02d', floor($m/60), $m%60); }
function wmt_nice($m){ $m=(($m%1440)+1440)%1440; $h=(int)floor($m/60); $ap=$h>=12?'PM':'AM'; $hh=$h%12; if(!$hh)$hh=12; return $hh.':'.sprintf('%02d',$m%60).$ap; }
function wmt_team($r){ $x=strtolower(($r['team']??'').' '.($r['role_type']??'')); if(wmt_contains($x,'invest')) return 'Investigator'; if(wmt_contains($x,'lead')||wmt_contains($x,'tl')) return 'Team Lead'; if(wmt_contains($x,'ops')||wmt_contains($x,'operation')) return 'Ops'; return 'Services'; }
function wmt_priority_num($p){ $p=strtolower((string)$p); if($p==='critical')return 1; if($p==='high')return 2; if($p==='medium')return 3; return 4; }

function wmt_db(){
  static $pdo=null; if($pdo) return $pdo;
  foreach(array(__DIR__.'/../../database.php', __DIR__.'/../database.php') as $f){ if(is_file($f)){ require_once $f; break; } }
  if(function_exists('ho_db')) $pdo=ho_db();
  elseif(isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) $pdo=$GLOBALS['pdo'];
  else throw new Exception('No database connection found. Expected database.php with ho_db().');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  return $pdo;
}
function wmt_schema($pdo){
  $pdo->exec('CREATE TABLE IF NOT EXISTS wmt_settings (k VARCHAR(80) PRIMARY KEY, v LONGTEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  $pdo->exec('CREATE TABLE IF NOT EXISTS wmt_associates (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL UNIQUE, team VARCHAR(40) NOT NULL DEFAULT \'Services\', role_type VARCHAR(80) NOT NULL DEFAULT \'AP Service TA\', can_cover TINYINT(1) NOT NULL DEFAULT 1, preferred_door VARCHAR(20) NOT NULL DEFAULT \'Either\', active TINYINT(1) NOT NULL DEFAULT 1, reliability DECIMAL(5,2) NOT NULL DEFAULT 3.00, notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  $pdo->exec('CREATE TABLE IF NOT EXISTS wmt_shifts (id INT AUTO_INCREMENT PRIMARY KEY, work_date DATE NOT NULL, associate_id INT NULL, associate_name VARCHAR(120) NOT NULL, team VARCHAR(40) NOT NULL DEFAULT \'Services\', role_type VARCHAR(80) NOT NULL DEFAULT \'AP Service TA\', start_time TIME NOT NULL, end_time TIME NOT NULL, notes TEXT, source_file VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY work_date (work_date), KEY associate_id (associate_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  try { $pdo->exec('ALTER TABLE wmt_shifts ADD COLUMN source_file VARCHAR(255) NULL'); } catch(Exception $e) {}
  $pdo->exec('CREATE TABLE IF NOT EXISTS wmt_tasks (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(180) NOT NULL UNIQUE, category VARCHAR(60) NOT NULL DEFAULT \'Entrance Readiness\', priority VARCHAR(20) NOT NULL DEFAULT \'Medium\', weight INT NOT NULL DEFAULT 2, frequency VARCHAR(40) NOT NULL DEFAULT \'Daily\', active TINYINT(1) NOT NULL DEFAULT 1, notes TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  $pdo->exec('CREATE TABLE IF NOT EXISTS wmt_turnins (id INT AUTO_INCREMENT PRIMARY KEY, work_date DATE NOT NULL, task_name VARCHAR(180) NOT NULL, status VARCHAR(30) NOT NULL DEFAULT \'Not Started\', completed_by VARCHAR(120), notes TEXT, manager_signoff VARCHAR(120), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY work_date (work_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  $pdo->exec('CREATE TABLE IF NOT EXISTS wmt_exceptions (id INT AUTO_INCREMENT PRIMARY KEY, work_date DATE NOT NULL, type VARCHAR(80) NOT NULL, post VARCHAR(20), start_time TIME, end_time TIME, associate_name VARCHAR(120), severity VARCHAR(20) NOT NULL DEFAULT \'Medium\', notes TEXT, reported_to VARCHAR(120), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY work_date (work_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  $pdo->exec('CREATE TABLE IF NOT EXISTS wmt_html_imports (id INT AUTO_INCREMENT PRIMARY KEY, filename VARCHAR(255), imported_rows INT NOT NULL DEFAULT 0, week_start DATE NULL, date_min DATE NULL, date_max DATE NULL, message TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  wmt_seed($pdo);
}
function wmt_get($pdo,$k,$d=null){ $s=$pdo->prepare('SELECT v FROM wmt_settings WHERE k=?'); $s->execute(array($k)); $v=$s->fetchColumn(); return $v===false?$d:$v; }
function wmt_set($pdo,$k,$v){ $pdo->prepare('INSERT INTO wmt_settings(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')->execute(array($k,(string)$v)); }
function wmt_seed($pdo){
  $settings=array(
    'triage_start'=>'08:00','triage_end'=>'17:00','grocery_gold_start'=>'06:00','grocery_gold_end'=>'23:00','gm_gold_start'=>'06:00','gm_gold_end'=>'21:00','slot_minutes'=>'15',
    'ops_blackout_start'=>'10:00','ops_blackout_end'=>'12:00','ops_flex_per_person'=>'30','tl_flex_per_day'=>'15','investigator_flex'=>'never',
    'break_stagger_mode'=>'coverage_first','max_services_breaks_same_slot'=>'1','break_candidate_spread_minutes'=>'150'
  );
  foreach($settings as $k=>$v){ if(wmt_get($pdo,$k)===null) wmt_set($pdo,$k,$v); }
  $tasks=array(
    array('Dust EAS pedestals','Entrance Readiness','Medium',2,'Daily'), array('Cart wipes dispenser cleaned','Entrance Readiness','High',3,'Daily'), array('Benches cleaned','Entrance Readiness','Medium',2,'Daily'), array('Windows cleaned','Entrance Readiness','High',3,'Daily'), array('Doors cleaned','Entrance Readiness','High',3,'Daily'), array('Door track swept','Entrance Readiness','Medium',2,'Daily'), array('Closets NCO','Organization','Low',1,'Daily/Weekly'), array('Bottom of Mart Carts wiped','Entrance Readiness','High',4,'Daily'), array('Vestibules clean and clear of spills/trash','Safety','Critical',5,'Continuous'), array('Inclement weather response','Safety','Critical',5,'Triggered'), array('Front entrance safety/security observation','Coverage','Critical',5,'Continuous'), array('Greet, help, and thank customers','Service','High',3,'Continuous'), array('Visual theft deterrence','Coverage','High',4,'Continuous'), array('Merchandise protection device support','Merchandise Protection','Medium',3,'As Needed'), array('Suspicious activity report/escalation','Reporting','High',2,'As Needed')
  );
  $q=$pdo->prepare('INSERT IGNORE INTO wmt_tasks(name,category,priority,weight,frequency) VALUES(?,?,?,?,?)'); foreach($tasks as $t){ $q->execute($t); }
}

function wmt_login($title,$form){ echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.wmt_h($title).'</title><style>body{font-family:system-ui;background:#f4f1ea}.box{max-width:420px;margin:12vh auto;background:white;padding:28px;border-radius:18px;border:1px solid #ddd}input,button{width:100%;padding:12px;border-radius:10px;margin-top:10px;box-sizing:border-box}button{background:#223b2a;color:white;font-weight:800}</style><div class=box><h1>'.wmt_h($title).'</h1><form method=post><input type=hidden name=form value="'.wmt_h($form).'"><input type=password name=password placeholder="Password" required><button>Continue</button></form></div>'; exit; }
function wmt_auth($pdo){ $hash=wmt_get($pdo,'admin_hash'); if(!$hash){ if(($_POST['form']??'')==='setpass' && strlen($_POST['password']??'')>=8){ wmt_set($pdo,'admin_hash',password_hash($_POST['password'],PASSWORD_DEFAULT)); $_SESSION['wmt']=1; header('Location:index.php'); exit; } wmt_login('Create WMT Admin Password','setpass'); } if(isset($_GET['logout'])){ unset($_SESSION['wmt']); header('Location:index.php'); exit; } if(($_POST['form']??'')==='login' && password_verify($_POST['password']??'',$hash)){ $_SESSION['wmt']=1; header('Location:index.php'); exit; } if(empty($_SESSION['wmt'])) wmt_login('AP Services Login','login'); }

function wmt_up_assoc($pdo,$r){
  $n=trim($r['name']??$r['associate_name']??''); if($n==='') return 0;
  $team=$r['team']??'Services'; $role=$r['role_type']??($r['role']??'AP Service TA'); $pref=$r['preferred_door']??'Either'; if(!in_array($pref,array('Grocery','GM','Either'))) $pref='Either';
  $can=(wmt_team(array('team'=>$team,'role_type'=>$role))==='Investigator')?0:1;
  $pdo->prepare('INSERT INTO wmt_associates(name,team,role_type,can_cover,preferred_door,notes) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE team=VALUES(team),role_type=VALUES(role_type),can_cover=VALUES(can_cover),preferred_door=VALUES(preferred_door),notes=VALUES(notes)')->execute(array($n,$team,$role,$can,$pref,$r['notes']??null));
  $id=(int)$pdo->lastInsertId(); if(!$id){ $q=$pdo->prepare('SELECT id FROM wmt_associates WHERE name=?'); $q->execute(array($n)); $id=(int)$q->fetchColumn(); } return $id;
}
function wmt_import_payload($pdo,$j){
  if(!is_array($j)) return 'Bad JSON'; $count=0;
  foreach($j['settings']??array() as $k=>$v){ if(is_scalar($v)) wmt_set($pdo,$k,$v); }
  foreach($j['replace_schedule_dates']??array() as $d){ $pdo->prepare('DELETE FROM wmt_shifts WHERE work_date=?')->execute(array($d)); }
  foreach($j['associates']??array() as $a){ wmt_up_assoc($pdo,$a); }
  foreach($j['tasks']??array() as $t){ if(empty($t['name'])) continue; $pdo->prepare('INSERT INTO wmt_tasks(name,category,priority,weight,frequency,notes) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE category=VALUES(category),priority=VALUES(priority),weight=VALUES(weight),frequency=VALUES(frequency),notes=VALUES(notes),active=1')->execute(array($t['name'],$t['category']??'Entrance Readiness',$t['priority']??'Medium',(int)($t['weight']??2),$t['frequency']??'Daily',$t['notes']??null)); }
  foreach($j['schedule']??array() as $s){
    $d=$s['date']??$s['work_date']??''; $n=$s['name']??$s['associate_name']??''; $start=$s['start']??$s['start_time']??''; $end=$s['end']??$s['end_time']??'';
    if(!$d||!$n||!$start||!$end) continue;
    $id=wmt_up_assoc($pdo,array('name'=>$n,'team'=>$s['team']??'Services','role_type'=>$s['role_type']??'AP Service TA','preferred_door'=>$s['preferred_door']??'Either','notes'=>$s['notes']??null));
    $pdo->prepare('INSERT INTO wmt_shifts(work_date,associate_id,associate_name,team,role_type,start_time,end_time,notes,source_file) VALUES(?,?,?,?,?,?,?,?,?)')->execute(array($d,$id,$n,$s['team']??'Services',$s['role_type']??'AP Service TA',$start,$end,$s['notes']??null,$s['source_file']??null));
    $count++;
  }
  return 'Imported '.$count.' schedule rows';
}
function wmt_import_current_week($pdo){ $file=__DIR__.'/current-week-import.json'; if(!is_file($file)) return 'Current-week import file not found.'; return wmt_import_payload($pdo,json_decode(file_get_contents($file),true)); }
function wmt_auto_seed($pdo){ if(wmt_get($pdo,'current_week_auto_seeded')==='1') return; $cnt=(int)$pdo->query('SELECT COUNT(*) FROM wmt_shifts')->fetchColumn(); if($cnt===0 && is_file(__DIR__.'/current-week-import.json')){ wmt_import_current_week($pdo); wmt_set($pdo,'current_week_auto_seeded','1'); } }
function wmt_post($pdo){
  if($_SERVER['REQUEST_METHOD']!=='POST') return '';
  $f=$_POST['form']??'';
  if($f==='current_week') return wmt_import_current_week($pdo);
  if($f==='json') return wmt_import_payload($pdo,json_decode($_POST['payload']??'',true));
  if($f==='csv'){
    $lines=preg_split('/\r\n|\n|\r/',trim($_POST['csv']??'')); if(!$lines || count($lines)<2) return 'No CSV rows found.';
    $h=array_map('strtolower',array_map('trim',str_getcsv(array_shift($lines)))); $c=0;
    foreach($lines as $line){ if(trim($line)==='') continue; $v=str_getcsv($line); $r=array(); foreach($h as $i=>$k){ $r[$k]=$v[$i]??''; } if(empty($r['date'])||empty($r['name'])||empty($r['start'])||empty($r['end'])) continue; $id=wmt_up_assoc($pdo,$r); $pdo->prepare('INSERT INTO wmt_shifts(work_date,associate_id,associate_name,team,role_type,start_time,end_time,notes) VALUES(?,?,?,?,?,?,?,?)')->execute(array($r['date'],$id,$r['name'],$r['team']??'Services',$r['role_type']??'AP Service TA',$r['start'],$r['end'],$r['notes']??null)); $c++; }
    return 'Imported '.$c.' CSV rows';
  }
  if($f==='turnin'){
    foreach($_POST['task']??array() as $t){ $pdo->prepare('INSERT INTO wmt_turnins(work_date,task_name,status,completed_by,notes,manager_signoff) VALUES(?,?,?,?,?,?)')->execute(array($_POST['date'],$t['name'],$t['status'],$t['by'],$t['notes'],$_POST['mgr']??null)); }
    if(trim($_POST['exceptions']??'')!=='') $pdo->prepare('INSERT INTO wmt_exceptions(work_date,type,severity,notes,reported_to) VALUES(?,?,?,?,?)')->execute(array($_POST['date'],'Daily note','Medium',$_POST['exceptions'],$_POST['mgr']??null));
    return 'Turn-in saved';
  }
  return '';
}

function wmt_shift_span($s){ $st=wmt_mins(substr($s['start_time'],0,5)); $en=wmt_mins(substr($s['end_time'],0,5)); if($en<=$st)$en+=1440; return array($st,$en,$en-$st); }
function wmt_shift_covers($s,$a,$b){ list($st,$en,$dur)=wmt_shift_span($s); return $a>=$st && $b<=$en; }
function wmt_raw_breaks($s,$slot=15){
  list($st,$en,$dur)=wmt_shift_span($s); $out=array();
  if($dur>=480){ $q=$dur/4; $out[]=array('Break 1',(int)(round(($st+$q-8)/$slot)*$slot),15); $out[]=array('Lunch',(int)(round(($st+2*$q-30)/$slot)*$slot),60); $out[]=array('Break 2',(int)(round(($st+3*$q-8)/$slot)*$slot),15); }
  elseif($dur>=360){ $out[]=array('Break',(int)(round(($st+$dur/3-8)/$slot)*$slot),15); $out[]=array('Lunch',(int)(round(($st+$dur*2/3-15)/$slot)*$slot),30); }
  elseif($dur>=240){ $out[]=array('Break',(int)(round(($st+$dur/2-8)/$slot)*$slot),15); }
  return $out;
}
function wmt_breaks($s){ return wmt_raw_breaks($s,15); }
function wmt_break_needs($s,$slot){
  list($st,$en,$dur)=wmt_shift_span($s); $n=$s['associate_name']; $needs=array();
  if($dur>=480){ $q=$dur/4; $needs[]=array('associate'=>$n,'type'=>'Break 1','duration'=>15,'ideal'=>(int)(round(($st+$q-8)/$slot)*$slot),'earliest'=>$st+75,'latest'=>min($st+240,$en-240),'order'=>1); $needs[]=array('associate'=>$n,'type'=>'Lunch','duration'=>60,'ideal'=>(int)(round(($st+2*$q-30)/$slot)*$slot),'earliest'=>$st+210,'latest'=>$en-180,'order'=>2); $needs[]=array('associate'=>$n,'type'=>'Break 2','duration'=>15,'ideal'=>(int)(round(($st+3*$q-8)/$slot)*$slot),'earliest'=>max($st+300,$en-240),'latest'=>$en-45,'order'=>3); }
  elseif($dur>=360){ $needs[]=array('associate'=>$n,'type'=>'Break','duration'=>15,'ideal'=>(int)(round(($st+$dur/3-8)/$slot)*$slot),'earliest'=>$st+90,'latest'=>$en-210,'order'=>1); $needs[]=array('associate'=>$n,'type'=>'Lunch','duration'=>30,'ideal'=>(int)(round(($st+$dur*2/3-15)/$slot)*$slot),'earliest'=>$st+180,'latest'=>$en-90,'order'=>2); }
  elseif($dur>=240){ $needs[]=array('associate'=>$n,'type'=>'Break','duration'=>15,'ideal'=>(int)(round(($st+$dur/2-8)/$slot)*$slot),'earliest'=>$st+90,'latest'=>$en-45,'order'=>1); }
  foreach($needs as &$b){ if($b['latest']<$b['earliest']){ $b['earliest']=$st+30; $b['latest']=$en-$b['duration']; } $b['earliest']=(int)(ceil($b['earliest']/$slot)*$slot); $b['latest']=(int)(floor($b['latest']/$slot)*$slot); }
  return $needs;
}
function wmt_service_available_count($sh,$a,$b,$breakSlots,$exclude=''){
  $n=0;
  foreach($sh as $s){ if(wmt_team($s)!=='Services' || !(int)($s['can_cover']??1)) continue; if(!wmt_shift_covers($s,$a,$b)) continue; $name=$s['associate_name']; if($name===$exclude) continue; if(isset($breakSlots[$a]) && in_array($name,$breakSlots[$a],true)) continue; $n++; }
  return $n;
}
function wmt_event_score($event,$candidate,$sh,$breakSlots,$placed,$slot){
  $score=abs($candidate-$event['ideal']); $impact=0; $minAfter=99;
  for($t=$candidate;$t<$candidate+$event['duration'];$t+=$slot){
    $already=count($breakSlots[$t]??array()); if($already>0) $score += 5000*$already;
    $after=wmt_service_available_count($sh,$t,$t+$slot,$breakSlots,$event['associate']); $minAfter=min($minAfter,$after);
    if($after<=0){ $score += 100000; $impact+=2; }
    elseif($after==1){ $score += 900; $impact+=1; }
    else { $score -= 200; }
  }
  foreach($placed as $p){ if($p['associate']!==$event['associate']) continue; if($event['order']>$p['order'] && $candidate < $p['end']+60) $score += 50000; if($event['order']<$p['order'] && $candidate+$event['duration']+60 > $p['start']) $score += 50000; }
  return array($score,$impact,$minAfter);
}\nfunction wmt_build_break_plan($sh,$slot){
  $needs=array(); foreach($sh as $s){ if(wmt_team($s)==='Services' && (int)($s['can_cover']??1)){ foreach(wmt_break_needs($s,$slot) as $b) $needs[]=$b; } }
  usort($needs,function($a,$b){ if($a['duration']===$b['duration']) return $a['ideal']-$b['ideal']; return $b['duration']-$a['duration']; });
  $breakSlots=array(); $events=array(); $warnings=array();
  foreach($needs as $b){
    $spread=150; $lo=max($b['earliest'],$b['ideal']-$spread); $hi=min($b['latest'],$b['ideal']+$spread); if($lo>$hi){ $lo=$b['earliest']; $hi=$b['latest']; }
    $best=null; $bestScore=null; $meta=array(0,0,0);
    for($cand=$lo;$cand<=$hi;$cand+=$slot){ if($cand+$b['duration']>$b['latest']+$b['duration']) continue; $s=wmt_event_score($b,$cand,$sh,$breakSlots,$events,$slot); if($best===null || $s[0]<$bestScore){ $best=$cand; $bestScore=$s[0]; $meta=$s; } }
    if($best===null){ $best=$b['ideal']; $meta=array(999999,99,0); }
    $b['start']=$best; $b['end']=$best+$b['duration']; $b['impact']=$meta[1]; $b['min_after']=$meta[2];
    if($b['impact']>0) $warnings[]=$b['associate'].' '.$b['type'].' has coverage impact; no cleaner stagger window existed.';
    $events[]=$b;
    for($t=$b['start'];$t<$b['end'];$t+=$slot){ if(!isset($breakSlots[$t])) $breakSlots[$t]=array(); $breakSlots[$t][]=$b['associate']; }
  }
  usort($events,function($a,$b){ if($a['start']===$b['start']) return strcmp($a['associate'],$b['associate']); return $a['start']-$b['start']; });
  $labels=array(); foreach($events as $e){ for($t=$e['start'];$t<$e['end'];$t+=$slot) $labels[$e['associate']][$t]=$e['type']; }
  return array('events'=>$events,'labels'=>$labels,'warnings'=>$warnings);
}
function wmt_off_label($breakPlan,$s,$slot){
  $name=$s['associate_name']; if(wmt_team($s)==='Services') return $breakPlan['labels'][$name][$slot]??'';
  foreach(wmt_raw_breaks($s,15) as $b){ if($slot>=$b[1] && $slot<$b[1]+$b[2]) return $b[0]; }
  return '';
}

function wmt_shifts($pdo,$d){ $q=$pdo->prepare('SELECT s.*,a.preferred_door,a.can_cover,a.reliability FROM wmt_shifts s LEFT JOIN wmt_associates a ON a.id=s.associate_id WHERE work_date=? ORDER BY start_time,associate_name'); $q->execute(array($d)); return $q->fetchAll(); }
function wmt_pick_service($c,$door,$used,$counts){
  $best=null; $score=999999;
  foreach($c as $r){ $n=$r['associate_name']; if(isset($used[$n])) continue; $s=($counts[$n]??0)*10 - ((float)($r['reliability']??3))*2; $pref=$r['preferred_door']??'Either'; if($pref===$door)$s-=20; elseif($pref==='Either')$s-=5; if($s<$score){$score=$s;$best=$r;} }
  return $best;
}
function wmt_plan($pdo,$d){
  $sh=wmt_shifts($pdo,$d); $rows=array(); $counts=array(); $opsUsed=array(); $tlUsed=0; $gap=0; $cov=0; $req=0; $float=0;
  $slot=(int)wmt_get($pdo,'slot_minutes',15); if($slot<5)$slot=15; $start=wmt_mins(wmt_get($pdo,'triage_start','08:00')); $end=wmt_mins(wmt_get($pdo,'triage_end','17:00')); $opsB=wmt_mins(wmt_get($pdo,'ops_blackout_start','10:00')); $opsE=wmt_mins(wmt_get($pdo,'ops_blackout_end','12:00')); $opsMax=(int)wmt_get($pdo,'ops_flex_per_person',30); $tlMax=(int)wmt_get($pdo,'tl_flex_per_day',15); $breakPlan=wmt_build_break_plan($sh,$slot);
  for($t=$start;$t<$end;$t+=$slot){
    $u=array(); $off=array();
    foreach($sh as $s){ if(!wmt_shift_covers($s,$t,$t+$slot)) continue; $o=wmt_off_label($breakPlan,$s,$t); if($o) $off[]=$s['associate_name'].' '.$o; else $u[]=$s; }
    $svc=array(); $ops=array(); $tls=array();
    foreach($u as $x){ $tm=wmt_team($x); if($tm==='Services' && (int)($x['can_cover']??1)) $svc[]=$x; elseif($tm==='Ops') $ops[]=$x; elseif($tm==='Team Lead') $tls[]=$x; }
    $used=array(); $g=wmt_pick_service($svc,'Grocery',$used,$counts); if($g){$used[$g['associate_name']]=1; $counts[$g['associate_name']]=($counts[$g['associate_name']]??0)+1;}
    $gm=wmt_pick_service($svc,'GM',$used,$counts); if($gm){$used[$gm['associate_name']]=1; $counts[$gm['associate_name']]=($counts[$gm['associate_name']]??0)+1;}
    $flex=array();
    foreach(array('Grocery','GM') as $door){
      $covered=($door==='Grocery')?$g:$gm; if($covered) continue; $assigned=null;
      if(count($ops)>=2 && !($t<$opsE && $t+$slot>$opsB)){
        foreach($ops as $o){ $n=$o['associate_name']; if(!isset($used[$n]) && (($opsUsed[$n]??0)+$slot)<=$opsMax){ $assigned=$o; $used[$n]=1; $opsUsed[$n]=($opsUsed[$n]??0)+$slot; $flex[]=$n.' Ops flex'; break; } }
      }
      if(!$assigned && $tlUsed+$slot<=$tlMax && $tls){ foreach($tls as $tl){ $n=$tl['associate_name']; if(!isset($used[$n])){ $assigned=$tl; $used[$n]=1; $tlUsed+=$slot; $flex[]=$n.' TL flex'; break; } } }
      if($assigned){ if($door==='Grocery')$g=$assigned; else $gm=$assigned; }
    }
    $fl=''; foreach($svc as $s){ if(!isset($used[$s['associate_name']])){ $fl=$s['associate_name']; $float+=$slot; break; } }
    $note=array(); $req += 2*$slot; if($g)$cov+=$slot; else{$gap+=$slot; $note[]='Grocery gap';} if($gm)$cov+=$slot; else{$gap+=$slot; $note[]='GM gap';}
    $rows[]=array('start'=>$t,'end'=>$t+$slot,'grocery'=>$g?$g['associate_name']:'','gm'=>$gm?$gm['associate_name']:'','floater'=>$fl,'off'=>implode(', ',$off),'flex'=>implode(', ',$flex),'watch'=>implode('; ',$note));
  }
  return array('date'=>$d,'shifts'=>$sh,'rows'=>$rows,'gap'=>$gap,'cov'=>$cov,'req'=>$req,'pct'=>$req?round($cov/$req*100,1):0,'float'=>$float,'opsUsed'=>$opsUsed,'tlUsed'=>$tlUsed,'break_plan'=>$breakPlan);
}
function wmt_same_block($a,$b,$keys){ foreach($keys as $k){ if(($a[$k]??'')!==($b[$k]??'')) return false; } return true; }
function wmt_compress_rows($rows,$keys){ $blocks=array(); foreach($rows as $r){ if($blocks && $blocks[count($blocks)-1]['end']===$r['start'] && wmt_same_block($blocks[count($blocks)-1],$r,$keys)){ $blocks[count($blocks)-1]['end']=$r['end']; } else $blocks[]=$r; } return $blocks; }
function wmt_person_blocks($pl,$name){
  $items=array(); foreach($pl['rows'] as $r){ $pos=''; $handoff=''; if($r['grocery']===$name){$pos='Grocery Door';$handoff='Wait for assigned relief / leadership direction.';} elseif($r['gm']===$name){$pos='GM Door';$handoff='Wait for assigned relief / leadership direction.';} elseif($r['floater']===$name){$pos='Task/Floater';$handoff='Stay available for door relief first; tasks second.';} elseif(wmt_contains($r['off'],$name)){$pos='Break/Lunch';$handoff='Return to assigned post at end of window.';} if($pos) $items[]=array('start'=>$r['start'],'end'=>$r['end'],'pos'=>$pos,'handoff'=>$handoff,'notes'=>$r['watch']); }
  return wmt_compress_rows($items,array('pos','handoff','notes'));
}

function wmt_head($t){ echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.wmt_h($t).'</title><style>
body{margin:0;background:#f4f1ea;color:#172019;font-family:system-ui,-apple-system,Segoe UI,sans-serif}.top{background:#223b2a;color:#fff}.wrap{max-width:1220px;margin:auto;padding:18px}.nav a{color:#fff;margin-right:14px;font-weight:800;text-decoration:none}.card{background:#fff;border:1px solid #ddd2bf;border-radius:16px;padding:16px;margin:14px 0}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.kpi{background:#fff8ea;border:1px solid #ddd2bf;border-radius:12px;padding:12px}.kpi b{font-size:28px;display:block}.bad{color:#a32016;font-weight:900}.ok{color:#18632d;font-weight:900}.muted{color:#666}.warn{background:#fff8ea;border-left:5px solid #c78900;padding:10px;margin:8px 0}table{width:100%;border-collapse:collapse;background:white}td,th{border:1px solid #ddd;padding:7px;text-align:left;vertical-align:top}th{background:#f1eadc;font-size:12px;text-transform:uppercase}textarea{width:100%;min-height:160px;box-sizing:border-box}input,select,button,.btn{padding:9px;border-radius:9px;border:1px solid #bbb}button,.btn{background:#223b2a;color:white;font-weight:800;text-decoration:none;display:inline-block}.print-title{font-size:28px}.page{break-before:page}.small{font-size:12px}.time{white-space:nowrap;font-weight:800}@media(max-width:800px){.grid{grid-template-columns:1fr}}@media print{.top,.no-print{display:none}.wrap{max-width:none;padding:0}.card{border:0;margin:0 0 10px;padding:0;break-inside:avoid}body{background:white;font-size:12px}td,th{padding:5px}.page{break-before:page}.print-title{font-size:22px}}
</style><div class="top no-print"><div class="wrap"><b>AP Services Tool</b><div class="nav"><a href="index.php">Dashboard</a><a href="?v=week">Week</a><a href="?v=import">Import</a><a href="import_html.php">HTML Import</a><a href="?v=turnin">Turn-In</a><a href="?export=json">Export</a><a href="?logout=1">Logout</a></div></div></div><main class="wrap">'; }
function wmt_foot(){ echo '</main>'; }
function wmt_shift_table($pl){ echo '<div class="card"><h2>Who is here</h2><table><tr><th>Name</th><th>Team</th><th>Role</th><th>Shift</th><th>Optimized Break/Lunch Plan</th><th>Preferred Door</th></tr>'; $by=array(); foreach($pl['break_plan']['events'] as $e){ $by[$e['associate']][]=$e['type'].' '.wmt_nice($e['start']).'-'.wmt_nice($e['end']); } foreach($pl['shifts'] as $s){ $n=$s['associate_name']; echo '<tr><td><b>'.wmt_h($n).'</b></td><td>'.wmt_h($s['team']).'</td><td>'.wmt_h($s['role_type']).'</td><td>'.wmt_h(substr($s['start_time'],0,5)).'-'.wmt_h(substr($s['end_time'],0,5)).'</td><td>'.wmt_h(implode('; ',$by[$n]??array())?:'None planned').'</td><td>'.wmt_h($s['preferred_door']??'Either').'</td></tr>'; } echo '</table></div>'; }
function wmt_plan_table($pl,$compressed=true){ $rows=$compressed?wmt_compress_rows($pl['rows'],array('grocery','gm','floater','off','flex','watch')):$pl['rows']; echo '<div class="card"><h2>8AM-5PM Coverage Plan '.($compressed?'<span class="muted small">compressed by change</span>':'<span class="muted small">15-minute grid</span>').'</h2><table><tr><th>Time</th><th>Grocery</th><th>GM</th><th>Break/Lunch</th><th>Floater / Tasks</th><th>Flex</th><th>Watch</th></tr>'; foreach($rows as $r){ echo '<tr><td class="time">'.wmt_nice($r['start']).'-'.wmt_nice($r['end']).'</td><td>'.($r['grocery']?wmt_h($r['grocery']):'<span class="bad">Uncovered</span>').'</td><td>'.($r['gm']?wmt_h($r['gm']):'<span class="bad">Uncovered</span>').'</td><td>'.wmt_h($r['off']?:'-').'</td><td>'.wmt_h($r['floater']?:'-').'</td><td>'.wmt_h($r['flex']?:'-').'</td><td>'.($r['watch']?'<span class="bad">'.wmt_h($r['watch']).'</span>':'<span class="ok">Covered</span>').'</td></tr>'; } echo '</table></div>'; }
function wmt_break_warnings($pl){ if(empty($pl['break_plan']['warnings'])) return; echo '<div class="card"><h2>Break/Lunch Stagger Notes</h2>'; foreach(array_unique($pl['break_plan']['warnings']) as $w) echo '<div class="warn">'.wmt_h($w).'</div>'; echo '</div>'; }
function wmt_dashboard($pdo,$msg){ $d=$_GET['date']??date('Y-m-d'); $pl=wmt_plan($pdo,$d); wmt_head('Dashboard'); if($msg)echo '<div class="card">'.wmt_h($msg).'</div>'; echo '<div class="card no-print"><form><input type="date" name="date" value="'.wmt_h($d).'"> <button>Load</button> <a class="btn" href="?v=mgmt&date='.wmt_h($d).'">Print Mgmt</a> <a class="btn" href="?v=ta&date='.wmt_h($d).'">Print TA</a> <a class="btn" href="?v=grid&date='.wmt_h($d).'">15-min Grid</a> <a class="btn" href="?v=turnin&date='.wmt_h($d).'">Turn-In</a></form></div><div class="card"><h1 class="print-title">Daily Coverage Review - '.wmt_h($d).'</h1><p class="muted">Engine plans in 15-minute slots. Display is compressed into blocks that change only when assignment, break, flex, or gap status changes.</p><div class="grid"><div class="kpi">Coverage<b>'.wmt_h($pl['pct']).'%</b></div><div class="kpi">Gap minutes<b class="'.($pl['gap']?'bad':'ok').'">'.wmt_h($pl['gap']).'</b></div><div class="kpi">Floater minutes<b>'.wmt_h($pl['float']).'</b></div><div class="kpi">Scheduled<b>'.count($pl['shifts']).'</b></div></div></div>'; wmt_shift_table($pl); wmt_break_warnings($pl); wmt_plan_table($pl,true); wmt_foot(); }
function wmt_week($pdo){ $d=new DateTime($_GET['date']??date('Y-m-d')); $d->modify('monday this week'); wmt_head('Week'); echo '<div class="card"><h1>Weekly Summary</h1><table><tr><th>Date</th><th>Coverage</th><th>Gap</th><th>People</th><th>Print</th></tr>'; for($i=0;$i<7;$i++){ $day=(clone $d)->modify('+'.$i.' day')->format('Y-m-d'); $pl=wmt_plan($pdo,$day); echo '<tr><td><b>'.wmt_h($day).'</b></td><td>'.wmt_h($pl['pct']).'%</td><td class="'.($pl['gap']?'bad':'ok').'">'.wmt_h($pl['gap']).'</td><td>'.count($pl['shifts']).'</td><td><a href="?date='.$day.'">View</a> · <a href="?v=mgmt&date='.$day.'">Mgmt</a> · <a href="?v=ta&date='.$day.'">TA</a> · <a href="?v=grid&date='.$day.'">Grid</a> · <a href="?v=turnin&date='.$day.'">Turn-in</a></td></tr>'; } echo '</table></div>'; wmt_foot(); }
function wmt_import_page($msg){ wmt_head('Import'); if($msg)echo '<div class="card">'.wmt_h($msg).'</div>'; echo '<div class="card"><h1>Import data</h1><p>Use the HTML importer for saved Workforce Planning Portal files, or paste CSV/JSON.</p><p><a class="btn" href="import_html.php">Upload Scheduler HTML</a></p><form method="post"><input type="hidden" name="form" value="current_week"><button>Import current screenshot week</button></form></div><div class="card"><h2>CSV</h2><p class="muted">Header: date,name,team,role_type,start,end,preferred_door,notes</p><form method="post"><input type="hidden" name="form" value="csv"><textarea name="csv">date,name,team,role_type,start,end,preferred_door,notes\n2026-06-15,Example TA,Services,AP Service TA,08:00,17:00,Grocery,</textarea><button>Import CSV</button></form></div><div class="card"><h2>JSON</h2><form method="post"><input type="hidden" name="form" value="json"><textarea name="payload">{\n  "replace_schedule_dates":["2026-06-15"],\n  "schedule":[{"date":"2026-06-15","name":"Example TA","team":"Services","role_type":"AP Service TA","start":"08:00","end":"17:00","preferred_door":"Grocery"}]\n}</textarea><button>Import JSON</button></form></div>'; wmt_foot(); }
function wmt_tasks($pdo){ $rows=$pdo->query('SELECT * FROM wmt_tasks WHERE active=1')->fetchAll(); usort($rows,function($a,$b){ $pa=wmt_priority_num($a['priority']); $pb=wmt_priority_num($b['priority']); if($pa===$pb) return (int)$b['weight'] - (int)$a['weight']; return $pa-$pb; }); return $rows; }
function wmt_turnin($pdo,$msg){ $d=$_GET['date']??date('Y-m-d'); $tasks=wmt_tasks($pdo); wmt_head('Turn-In'); if($msg)echo '<div class="card">'.wmt_h($msg).'</div>'; echo '<div class="card"><h1 class="print-title">Daily Turn-In / Sign-Off - '.wmt_h($d).'</h1><form method="post"><input type="hidden" name="form" value="turnin"><input type="hidden" name="date" value="'.wmt_h($d).'"><table><tr><th>Task</th><th>Status</th><th>Completed By</th><th>Notes</th></tr>'; foreach($tasks as $i=>$t){ echo '<tr><td><b>'.wmt_h($t['name']).'</b><br><span class="muted small">'.wmt_h($t['category'].' · '.$t['priority'].' · weight '.$t['weight']).'</span><input type="hidden" name="task['.$i.'][name]" value="'.wmt_h($t['name']).'"></td><td><select name="task['.$i.'][status]"><option>Not Started</option><option>Completed</option><option>Missed</option><option>Deferred</option><option>N/A</option></select></td><td><input name="task['.$i.'][by]"></td><td><input name="task['.$i.'][notes]"></td></tr>'; } echo '</table><p>Exceptions / weather / door gaps / suspicious activity / equipment issues<br><textarea name="exceptions"></textarea></p><p>Manager sign-off <input name="mgr"></p><button class="no-print">Save</button></form></div>'; wmt_foot(); }
function wmt_mgmt($pdo){ $d=$_GET['date']??date('Y-m-d'); $pl=wmt_plan($pdo,$d); wmt_head('Management Print'); echo '<div class="card"><h1 class="print-title">Management Daily Coverage Review - '.wmt_h($d).'</h1><p><b>Rules:</b> Services own doors. Breaks/lunches are staggered coverage-first. Ops flex only when two Ops are available and not 10AM-12PM. TL max 15 minutes/day. Investigator never flexes.</p><p><b>Coverage:</b> '.wmt_h($pl['pct']).'% · <b>Gap minutes:</b> '.wmt_h($pl['gap']).' · <b>Floater minutes:</b> '.wmt_h($pl['float']).'</p></div>'; wmt_shift_table($pl); wmt_break_warnings($pl); wmt_plan_table($pl,true); wmt_foot(); }
function wmt_grid($pdo){ $d=$_GET['date']??date('Y-m-d'); $pl=wmt_plan($pdo,$d); wmt_head('15-min Grid'); echo '<div class="card"><h1>15-minute Planning Grid - '.wmt_h($d).'</h1><p class="muted">This is the raw planning layer. Print views use compressed blocks.</p></div>'; wmt_plan_table($pl,false); wmt_foot(); }
function wmt_ta($pdo){ $d=$_GET['date']??date('Y-m-d'); $pl=wmt_plan($pdo,$d); wmt_head('TA Print'); $names=array(); foreach($pl['shifts'] as $s){ if(wmt_team($s)==='Services') $names[$s['associate_name']]=$s; } if(!$names) echo '<div class="card"><h1>No AP Services TAs scheduled for '.wmt_h($d).'</h1></div>'; foreach($names as $name=>$s){ echo '<div class="card page"><h1 class="print-title">Associate Position Sheet - '.wmt_h($name).'</h1><p><b>Date:</b> '.wmt_h($d).' <b>Shift:</b> '.wmt_h(substr($s['start_time'],0,5)).'-'.wmt_h(substr($s['end_time'],0,5)).'</p><p><b>Do not leave your post until relieved or directed.</b> If relief does not arrive, contact leadership/AP.</p><table><tr><th>Time</th><th>Your Position</th><th>Handoff</th><th>Notes</th></tr>'; foreach(wmt_person_blocks($pl,$name) as $r){ echo '<tr><td class="time">'.wmt_nice($r['start']).'-'.wmt_nice($r['end']).'</td><td><b>'.wmt_h($r['pos']).'</b></td><td>'.wmt_h($r['handoff']).'</td><td>'.wmt_h($r['notes']).'</td></tr>'; } echo '</table></div>'; } wmt_foot(); }

try{
  $pdo=wmt_db(); wmt_schema($pdo); wmt_auth($pdo); wmt_auto_seed($pdo);
  if(isset($_GET['export'])){ header('Content-Type: application/json'); echo json_encode(array('settings'=>$pdo->query('SELECT * FROM wmt_settings')->fetchAll(),'associates'=>$pdo->query('SELECT * FROM wmt_associates')->fetchAll(),'schedule'=>$pdo->query('SELECT * FROM wmt_shifts ORDER BY work_date,start_time')->fetchAll(),'tasks'=>$pdo->query('SELECT * FROM wmt_tasks')->fetchAll()), JSON_PRETTY_PRINT); exit; }
  $msg=wmt_post($pdo); $v=$_GET['v']??'dashboard';
  if($v==='week') wmt_week($pdo); elseif($v==='import') wmt_import_page($msg); elseif($v==='turnin') wmt_turnin($pdo,$msg); elseif($v==='mgmt') wmt_mgmt($pdo); elseif($v==='ta') wmt_ta($pdo); elseif($v==='grid') wmt_grid($pdo); else wmt_dashboard($pdo,$msg);
}catch(Throwable $e){ http_response_code(500); echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui;background:#f4f1ea"><div style="max-width:900px;margin:40px auto;background:white;padding:24px;border-radius:16px"><h1>WMT setup error</h1><p>The PHP file loaded, but setup failed.</p><pre>'.wmt_h($e->getMessage()).'</pre><p>Try adding <code>?debug=1</code> to the URL and send the exact message.</p></div>'; }
