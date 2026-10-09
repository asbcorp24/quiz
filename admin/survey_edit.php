<?php
require dirname(__DIR__) . '/config.php';
admin_required();
$id=(int)($_GET['id']??0);
$st=$pdo->prepare("SELECT * FROM surveys WHERE id=?");$st->execute([$id]);$survey=$st->fetch();
if(!$survey){http_response_code(404);exit('Квиз не найден');}

if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();$action=$_POST['action']??'';
 if($action==='settings'){
   $title=trim($_POST['title']??'');$venue=trim($_POST['venue']??'');$intro=trim($_POST['intro']??'');
   $group=(int)($_POST['group_id']??0);$limit=max(0,(int)($_POST['daily_limit']??0));$gps=!empty($_POST['collect_gps'])?1:0;
   $pdo->prepare("UPDATE surveys SET title=?,venue=?,intro=?,group_id=?,daily_limit=?,collect_gps=? WHERE id=?")
     ->execute([$title,$venue,$intro,$group?:null,$limit,$gps,$id]);
 } elseif($action==='add'){
   $label=trim($_POST['label']??'');$type=$_POST['type']??'';$required=!empty($_POST['is_required'])?1:0;
   if($label!=='' && isset(question_types()[$type])){
     $max=(int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM questions WHERE survey_id=".$id)->fetchColumn()+10;
     $settings='{}';
     if($type==='photo')$settings=json_encode(['max_files'=>max(1,min(3,(int)($_POST['max_files']??1)))]);
     if($type==='textarea')$settings=json_encode(['max_length'=>max(10,min(5000,(int)($_POST['max_length']??300)))]);
     $pdo->prepare("INSERT INTO questions(survey_id,label,type,is_required,sort_order,settings_json) VALUES(?,?,?,?,?,?)")->execute([$id,$label,$type,$required,$max,$settings]);
   }
 } elseif($action==='delete'){
   $qid=(int)($_POST['qid']??0);$pdo->prepare("DELETE FROM questions WHERE id=? AND survey_id=?")->execute([$qid,$id]);
 } elseif($action==='move'){
   $qid=(int)($_POST['qid']??0);$dir=$_POST['dir']??'up';
   $qs=$pdo->prepare("SELECT id,sort_order FROM questions WHERE survey_id=? ORDER BY sort_order,id");$qs->execute([$id]);$arr=$qs->fetchAll();
   $idx=array_search($qid,array_column($arr,'id'));
   if($idx!==false){$j=$dir==='up'?$idx-1:$idx+1;if(isset($arr[$j])){
     $pdo->beginTransaction();
     $pdo->prepare("UPDATE questions SET sort_order=? WHERE id=?")->execute([$arr[$j]['sort_order'],$qid]);
     $pdo->prepare("UPDATE questions SET sort_order=? WHERE id=?")->execute([$arr[$idx]['sort_order'],$arr[$j]['id']]);
     $pdo->commit();
   }}
 }
 header('Location: '.url('admin/survey_edit.php?id='.$id));exit;
}
$groups=$pdo->query("SELECT id,title FROM quiz_groups WHERE is_active=1 ORDER BY title")->fetchAll();
$q=$pdo->prepare("SELECT * FROM questions WHERE survey_id=? ORDER BY sort_order,id");$q->execute([$id]);$questions=$q->fetchAll();
$types=question_types();
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Конструктор — <?=h($survey['title'])?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"><link href="<?=h(url('assets/style.css'))?>" rel="stylesheet"></head><body>
<nav class="navbar px-3 py-3"><div class="container-fluid"><a class="navbar-brand" href="<?=h(url('admin/index.php'))?>">◫ MICRO//FEEDBACK</a><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-info" href="<?=h(url('index.php?s='.$survey['code']))?>" target="_blank">Предпросмотр</a><a class="btn btn-sm btn-outline-secondary" href="<?=h(url('admin/surveys.php'))?>">← Квизы</a></div></div></nav>
<main class="container py-4">
<div class="mb-4"><div class="smallcaps">Конструктор квиза</div><h1 class="hero-title"><?=h($survey['title'])?></h1></div>
<div class="row g-4"><div class="col-lg-5">
<div class="glass rounded-4 p-4 mb-4"><h2 class="h5">Настройки квиза</h2><form method="post"><input type="hidden" name="_csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="settings">
<div class="mb-3"><label class="form-label">Название</label><input class="form-control" name="title" value="<?=h($survey['title'])?>" required></div>
<div class="mb-3"><label class="form-label">Место / объект</label><input class="form-control" name="venue" value="<?=h($survey['venue'])?>"></div>
<div class="mb-3"><label class="form-label">Вводный текст</label><textarea class="form-control" name="intro"><?=h($survey['intro'])?></textarea></div>
<div class="mb-3"><label class="form-label">Группа вопросов / квизов</label><select class="form-select" name="group_id"><option value="0">Без группы</option><?php foreach($groups as $g):?><option value="<?=$g['id']?>" <?=$survey['group_id']==$g['id']?'selected':''?>><?=h($g['title'])?></option><?php endforeach;?></select></div>
<div class="mb-3"><label class="form-label">Голосований в день с одного IP</label><input class="form-control" type="number" min="0" name="daily_limit" value="<?=(int)$survey['daily_limit']?>"><div class="form-text text-muted2">0 — без ограничений.</div></div>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="collect_gps" id="gps" <?=$survey['collect_gps']?'checked':''?>><label class="form-check-label" for="gps">Собирать GPS (с разрешения браузера)</label></div>
<button class="btn btn-primary w-100">Сохранить настройки</button></form></div>

<div class="glass rounded-4 p-4"><h2 class="h5">Добавить вопрос</h2><form method="post"><input type="hidden" name="_csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="add">
<div class="mb-3"><label class="form-label">Текст вопроса</label><input class="form-control" name="label" required></div>
<div class="mb-3"><label class="form-label">Тип</label><select class="form-select" name="type" id="qtype"><?php foreach($types as $k=>$v):?><option value="<?=h($k)?>"><?=h($v)?></option><?php endforeach;?></select></div>
<div class="mb-3" id="photoOpt" style="display:none"><label class="form-label">Максимум фото</label><input class="form-control" type="number" min="1" max="3" name="max_files" value="1"></div>
<div class="mb-3" id="textOpt" style="display:none"><label class="form-label">Максимум символов</label><input class="form-control" type="number" min="10" max="5000" name="max_length" value="300"></div>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_required" id="req"><label class="form-check-label" for="req">Обязательный вопрос</label></div>
<button class="btn btn-outline-info w-100">+ Добавить</button></form></div>
</div>
<div class="col-lg-7"><h2 class="h4">Вопросы</h2>
<?php foreach($questions as $i=>$x):$set=json_decode($x['settings_json']?:'{}',true)?:[];?>
<div class="card rounded-4 p-3 mb-3"><div class="d-flex justify-content-between gap-3"><div><div class="smallcaps">№<?=$i+1?> · <?=h($types[$x['type']]??$x['type'])?></div><div class="fs-5 fw-semibold"><?=h($x['label'])?></div><div class="text-muted2 small"><?=$x['is_required']?'обязательный':'необязательный'?><?php if($x['type']==='photo'):?> · до <?=(int)($set['max_files']??1)?> фото<?php endif;?><?php if($x['type']==='textarea'):?> · до <?=(int)($set['max_length']??300)?> символов<?php endif;?></div></div>
<div class="d-flex gap-1 align-items-start"><form method="post"><input type="hidden" name="_csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="move"><input type="hidden" name="qid" value="<?=$x['id']?>"><button class="btn btn-sm btn-outline-secondary" name="dir" value="up">↑</button><button class="btn btn-sm btn-outline-secondary" name="dir" value="down">↓</button></form>
<form method="post" onsubmit="return confirm('Удалить вопрос?')"><input type="hidden" name="_csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="qid" value="<?=$x['id']?>"><button class="btn btn-sm btn-outline-danger">×</button></form></div></div></div>
<?php endforeach;?><?php if(!$questions):?><div class="glass rounded-4 p-5 text-center text-muted2">Добавьте первый вопрос слева.</div><?php endif;?>
</div></div></main>
<script>const s=document.getElementById('qtype'),p=document.getElementById('photoOpt'),t=document.getElementById('textOpt');function sync(){p.style.display=s.value==='photo'?'block':'none';t.style.display=s.value==='textarea'?'block':'none'}s.addEventListener('change',sync);sync();</script></body></html>