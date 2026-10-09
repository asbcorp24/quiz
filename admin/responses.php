<?php
require dirname(__DIR__) . '/config.php';
admin_required();

$rating = (int)($_GET['rating'] ?? 0);
$surveyId = (int)($_GET['survey_id'] ?? 0);
$where=[];$args=[];
if($rating>=1 && $rating<=5){$where[]='r.rating=?';$args[]=$rating;}
if($surveyId>0){$where[]='r.survey_id=?';$args[]=$surveyId;}
$sql="SELECT r.*,s.title survey_title FROM responses r JOIN surveys s ON s.id=r.survey_id";
if($where)$sql.=" WHERE ".implode(' AND ',$where);
$sql.=" ORDER BY r.id DESC LIMIT 500";
$st=$pdo->prepare($sql);$st->execute($args);$rows=$st->fetchAll();
$surveys=$pdo->query("SELECT id,title FROM surveys ORDER BY title")->fetchAll();
?>
<!doctype html><html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Отзывы</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= h(url('assets/style.css')) ?>" rel="stylesheet">
</head><body>
<nav class="navbar px-3 py-3"><div class="container-fluid">
<a class="navbar-brand" href="<?= h(url('admin/index.php')) ?>">◫ MICRO//FEEDBACK</a>
<a href="<?= h(url('admin/index.php')) ?>" class="btn btn-sm btn-outline-info">← Аналитика</a>
</div></nav>
<main class="container-fluid px-3 px-lg-4 py-4">
<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
<div><div class="smallcaps">Журнал входящих сигналов</div><h1 class="hero-title mb-0">Отзывы</h1></div>
<form class="d-flex gap-2 flex-wrap">
<select class="form-select" name="survey_id" style="width:auto"><option value="0">Все опросы</option><?php foreach($surveys as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $surveyId==(int)$s['id']?'selected':'' ?>><?= h($s['title']) ?></option><?php endforeach; ?></select>
<select class="form-select" name="rating" style="width:auto"><option value="0">Все оценки</option><?php for($i=5;$i>=1;$i--): ?><option value="<?= $i ?>" <?= $rating===$i?'selected':'' ?>><?= $i ?> ★</option><?php endfor; ?></select>
<button class="btn btn-primary">Фильтр</button>
</form></div>
<div class="card rounded-4 p-3">
<div class="table-responsive"><table class="table align-middle">
<thead><tr><th>#</th><th>Посещение</th><th>Опрос</th><th>Оценка</th><th>Описание</th><th>Фото</th></tr></thead>
<tbody>
<?php foreach($rows as $r):
 $ps=$pdo->prepare("SELECT * FROM photos WHERE response_id=?");$ps->execute([$r['id']]);$photos=$ps->fetchAll();
?>
<tr>
<td><?= (int)$r['id'] ?></td><td class="text-nowrap"><?= h(date('d.m.Y H:i',strtotime($r['visited_at']))) ?></td>
<td><?= h($r['survey_title']) ?></td><td><span class="badge badge-soft"><?= (int)$r['rating'] ?> ★</span></td>
<td style="min-width:320px"><?= nl2br(h($r['comment'])) ?></td>
<td><div class="d-flex gap-2">
<?php foreach($photos as $p): ?><a target="_blank" href="<?= h(url('uploads/'.$p['file_name'])) ?>"><img class="photo-thumb" src="<?= h(url('uploads/'.$p['file_name'])) ?>" alt=""></a><?php endforeach; ?>
<?php if(!$photos): ?><span class="text-muted2">—</span><?php endif; ?>
</div></td></tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="6" class="text-center text-muted2 py-5">Нет отзывов по выбранному фильтру.</td></tr><?php endif; ?>
</tbody></table></div></div>
</main></body></html>
