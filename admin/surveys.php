<?php
require dirname(__DIR__) . '/config.php';
admin_required();

if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();
 $action=$_POST['action']??'';
 if($action==='create_group'){
   $title=trim($_POST['title']??'');$description=trim($_POST['description']??'');
   if($title!==''){
     do{$code=random_code();$q=$pdo->prepare("SELECT COUNT(*) FROM quiz_groups WHERE code=?");$q->execute([$code]);}while($q->fetchColumn());
     $pdo->prepare("INSERT INTO quiz_groups(title,description,code) VALUES(?,?,?)")->execute([$title,$description,$code]);
     flash('ok','Группа создана.');
   }
 } elseif($action==='create_survey'){
   $title=trim($_POST['title']??'');$venue=trim($_POST['venue']??'');
   $group=(int)($_POST['group_id']??0);$limit=max(0,(int)($_POST['daily_limit']??0));
   $gps=!empty($_POST['collect_gps'])?1:0;$intro=trim($_POST['intro']??'');
   if($title!==''){
     do{$code=random_code();$q=$pdo->prepare("SELECT COUNT(*) FROM surveys WHERE code=?");$q->execute([$code]);}while($q->fetchColumn());
     $pdo->prepare("INSERT INTO surveys(title,venue,code,group_id,daily_limit,collect_gps,intro) VALUES(?,?,?,?,?,?,?)")
       ->execute([$title,$venue,$code,$group?:null,$limit,$gps,$intro]);
     $id=(int)$pdo->lastInsertId();
     header('Location: '.url('admin/survey_edit.php?id='.$id));exit;
   }
 } elseif($action==='toggle_survey'){
   $id=(int)($_POST['id']??0);
   $pdo->prepare("UPDATE surveys SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?")->execute([$id]);
 } elseif($action==='toggle_group'){
   $id=(int)($_POST['id']??0);
   $pdo->prepare("UPDATE quiz_groups SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?")->execute([$id]);
 }
 header('Location: '.url('admin/surveys.php'));exit;
}
$groups=$pdo->query("SELECT g.*,(SELECT COUNT(*) FROM surveys s WHERE s.group_id=g.id) quiz_count FROM quiz_groups g ORDER BY g.id DESC")->fetchAll();
$rows=$pdo->query("SELECT s.*,g.title group_title,COUNT(r.id) responses,ROUND(AVG(CASE WHEN r.rating>0 THEN r.rating END),2) avg_rating FROM surveys s LEFT JOIN quiz_groups g ON g.id=s.group_id LEFT JOIN responses r ON r.survey_id=s.id GROUP BY s.id ORDER BY s.id DESC")->fetchAll();
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Конструктор квизов</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=h(url('assets/style.css'))?>" rel="stylesheet"><script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script></head><body>
<nav class="navbar px-3 py-3"><div class="container-fluid"><a class="navbar-brand" href="<?=h(url('admin/index.php'))?>">◫ MICRO//FEEDBACK</a><a href="<?=h(url('admin/index.php'))?>" class="btn btn-sm btn-outline-info">← Аналитика</a></div></nav>
<main class="container py-4">
<?php if($m=flash('ok')):?><div class="alert alert-success"><?=h($m)?></div><?php endif;?>
<div class="row g-4">
<div class="col-lg-5">
<div class="glass rounded-4 p-4 mb-4"><div class="smallcaps mb-2">Новая группа</div><h2 class="h4">Группа квизов</h2>
<form method="post"><input type="hidden" name="_csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="create_group">
<div class="mb-3"><label class="form-label">Название</label><input class="form-control" name="title" required></div>
<div class="mb-3"><label class="form-label">Описание</label><textarea class="form-control" name="description"></textarea></div>
<button class="btn btn-outline-info w-100">Создать группу</button></form></div>

<div class="glass rounded-4 p-4"><div class="smallcaps mb-2">Новый квиз</div><h1 class="h3 hero-title">Создать квиз</h1>
<form method="post"><input type="hidden" name="_csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="create_survey">
<div class="mb-3"><label class="form-label">Название</label><input class="form-control" name="title" required></div>
<div class="mb-3"><label class="form-label">Место / объект</label><input class="form-control" name="venue"></div>
<div class="mb-3"><label class="form-label">Вводный текст</label><textarea class="form-control" name="intro"></textarea></div>
<div class="mb-3"><label class="form-label">Группа</label><select class="form-select" name="group_id"><option value="0">Без группы</option><?php foreach($groups as $g):?><option value="<?=$g['id']?>"><?=h($g['title'])?></option><?php endforeach;?></select></div>
<div class="mb-3"><label class="form-label">Сколько раз в день с одного IP</label><input class="form-control" type="number" min="0" name="daily_limit" value="1"><div class="form-text text-muted2">0 — без ограничения.</div></div>
<div class="form-check mb-4"><input class="form-check-input" type="checkbox" name="collect_gps" id="gps"><label class="form-check-label" for="gps">Запрашивать GPS координаты</label></div>
<button class="btn btn-primary w-100 py-3">Создать и открыть конструктор →</button></form></div>
</div>

<div class="col-lg-7">
<h2 class="h4 mb-3">Группы</h2>
<?php foreach($groups as $g):$link=absolute_url('group.php?g='.urlencode($g['code']));?>
<div class="card rounded-4 p-3 mb-3"><div class="d-flex justify-content-between gap-3 flex-wrap"><div><strong><?=h($g['title'])?></strong><div class="text-muted2 small"><?=h($g['description'])?></div><div class="small mt-2"><?=h($link)?></div></div><div class="d-flex gap-2 align-items-start">
<button class="btn btn-sm btn-primary" onclick='showQr(<?=json_encode($link)?>,<?=json_encode("Группа: ".$g["title"])?>)'>QR группы</button>
<form method="post"><input type="hidden" name="_csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="toggle_group"><input type="hidden" name="id" value="<?=$g['id']?>"><button class="btn btn-sm btn-outline-secondary"><?=$g['is_active']?'Отключить':'Включить'?></button></form></div></div></div>
<?php endforeach;?>

<h2 class="h4 mt-4 mb-3">Квизы</h2>
<?php foreach($rows as $r):$link=absolute_url('index.php?s='.urlencode($r['code']));?>
<div class="card rounded-4 p-4 mb-3"><div class="d-flex justify-content-between gap-3 flex-wrap"><div>
<div class="d-flex gap-2 align-items-center"><h3 class="h5 mb-0"><?=h($r['title'])?></h3><span class="badge <?=$r['is_active']?'badge-soft':'text-bg-secondary'?>"><?=$r['is_active']?'активен':'выключен'?></span></div>
<div class="text-muted2 mt-1"><?=h($r['group_title']??'Без группы')?> · ответов: <?=(int)$r['responses']?> · лимит/IP: <?=$r['daily_limit']?:'∞'?> · GPS: <?=$r['collect_gps']?'да':'нет'?></div>
<div class="small mt-2 text-break"><?=h($link)?></div></div><div class="d-flex gap-2 align-items-start flex-wrap">
<a class="btn btn-sm btn-outline-info" href="<?=h(url('admin/survey_edit.php?id='.$r['id']))?>">Конструктор</a>
<button class="btn btn-sm btn-primary" onclick='showQr(<?=json_encode($link)?>,<?=json_encode($r["title"])?>)'>QR квиза</button>
<form method="post"><input type="hidden" name="_csrf" value="<?=h(csrf_token())?>"><input type="hidden" name="action" value="toggle_survey"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn btn-sm btn-outline-secondary"><?=$r['is_active']?'Отключить':'Включить'?></button></form>
</div></div></div><?php endforeach;?>
</div></div></main>

<div class="modal fade" id="qrModal"><div class="modal-dialog modal-dialog-centered"><div class="modal-content card"><div class="modal-header border-secondary"><h5 id="qrTitle"></h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body text-center"><div id="qrcode" class="qr-wrap"></div><div id="qrLink" class="small text-break mt-3"></div></div><div class="modal-footer border-secondary"><button class="btn btn-primary" onclick="downloadQr()">Скачать PNG</button></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script><script>
let modal=new bootstrap.Modal(document.getElementById('qrModal'));
function showQr(link,title){qrcode.innerHTML='';qrTitle.textContent=title;qrLink.textContent=link;new QRCode(qrcode,{text:link,width:280,height:280,correctLevel:QRCode.CorrectLevel.H});modal.show()}
function downloadQr(){let c=qrcode.querySelector('canvas'),i=qrcode.querySelector('img'),src=c?c.toDataURL('image/png'):(i?i.src:null);if(!src)return;let a=document.createElement('a');a.href=src;a.download='quiz-qr.png';a.click()}
</script></body></html>