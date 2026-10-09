<?php
require dirname(__DIR__) . '/config.php';
admin_required();

$surveyId=(int)($_GET['survey_id']??0);
$where=[];$args=[];
if($surveyId>0){$where[]='r.survey_id=?';$args[]=$surveyId;}
$sql="SELECT r.*,s.title survey_title,s.collect_gps FROM responses r JOIN surveys s ON s.id=r.survey_id";
if($where)$sql.=" WHERE ".implode(' AND ',$where);
$sql.=" ORDER BY r.id DESC LIMIT 500";
$st=$pdo->prepare($sql);$st->execute($args);$rows=$st->fetchAll();
$surveys=$pdo->query("SELECT id,title FROM surveys ORDER BY title")->fetchAll();

$answerStmt=$pdo->prepare("SELECT a.value_text,q.label,q.type FROM answers a JOIN questions q ON q.id=a.question_id WHERE a.response_id=? ORDER BY q.sort_order,q.id");
$photoStmt=$pdo->prepare("SELECT p.*,q.label FROM photos p LEFT JOIN questions q ON q.id=p.question_id WHERE p.response_id=? ORDER BY p.id");
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ответы</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"><link href="<?=h(url('assets/style.css'))?>" rel="stylesheet"></head><body>
<nav class="navbar px-3 py-3"><div class="container-fluid"><a class="navbar-brand" href="<?=h(url('admin/index.php'))?>">◫ MICRO//FEEDBACK</a><a href="<?=h(url('admin/index.php'))?>" class="btn btn-sm btn-outline-info">← Аналитика</a></div></nav>
<main class="container-fluid px-3 px-lg-4 py-4">
<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4"><div><div class="smallcaps">Журнал ответов</div><h1 class="hero-title mb-0">Ответы квизов</h1></div>
<form class="d-flex gap-2"><select class="form-select" name="survey_id" style="width:auto"><option value="0">Все квизы</option><?php foreach($surveys as $s):?><option value="<?=$s['id']?>" <?=$surveyId==$s['id']?'selected':''?>><?=h($s['title'])?></option><?php endforeach;?></select><button class="btn btn-primary">Фильтр</button></form></div>

<?php foreach($rows as $r):
$answerStmt->execute([$r['id']]);$answers=$answerStmt->fetchAll();
$photoStmt->execute([$r['id']]);$photos=$photoStmt->fetchAll();
?>
<div class="card rounded-4 p-4 mb-3">
<div class="d-flex justify-content-between flex-wrap gap-3 mb-3"><div><div class="smallcaps">Ответ #<?=(int)$r['id']?></div><h2 class="h5 mb-1"><?=h($r['survey_title'])?></h2><div class="text-muted2 small"><?=h(date('d.m.Y H:i',strtotime($r['created_at'])))?></div></div>
<?php if($r['latitude']!==null && $r['longitude']!==null):?><div class="text-end"><div class="smallcaps">GPS</div><div><?=h((string)$r['latitude'])?>, <?=h((string)$r['longitude'])?></div><div class="text-muted2 small">точность: <?=h((string)$r['gps_accuracy'])?> м</div></div><?php endif;?></div>

<div class="row g-3">
<?php foreach($answers as $a):?><div class="col-md-6"><div class="metric h-100"><div class="smallcaps"><?=h($a['label'])?></div><div class="mt-2"><?php if($a['type']==='rating'):?><span class="badge badge-soft fs-6"><?=h($a['value_text'])?> ★</span><?php else:?><?=nl2br(h($a['value_text']!==''?$a['value_text']:'—'))?><?php endif;?></div></div></div><?php endforeach;?>
<?php if(!$answers):?><div class="col-12"><div class="text-muted2">Нет сохранённых текстовых ответов.</div></div><?php endif;?>
</div>

<?php if($photos):?><div class="mt-4"><div class="smallcaps mb-2">Фото</div><div class="d-flex gap-3 flex-wrap"><?php foreach($photos as $p):?><div><a target="_blank" href="<?=h(url('uploads/'.$p['file_name']))?>"><img class="photo-thumb" style="width:120px;height:90px" src="<?=h(url('uploads/'.$p['file_name']))?>" alt=""></a><?php if($p['label']):?><div class="text-muted2 small mt-1"><?=h($p['label'])?></div><?php endif;?></div><?php endforeach;?></div></div><?php endif;?>
</div>
<?php endforeach;?>
<?php if(!$rows):?><div class="glass rounded-4 p-5 text-center text-muted2">Ответов пока нет.</div><?php endif;?>
</main></body></html>