<?php
require dirname(__DIR__) . '/config.php';
admin_required();

$total = (int)$pdo->query("SELECT COUNT(*) FROM responses")->fetchColumn();
$avg = (float)$pdo->query("SELECT COALESCE(AVG(rating),0) FROM responses")->fetchColumn();
$today = (int)$pdo->query("SELECT COUNT(*) FROM responses WHERE date(created_at)=date('now','localtime')")->fetchColumn();
$negative = (int)$pdo->query("SELECT COUNT(*) FROM responses WHERE rating <= 2")->fetchColumn();

$ratingRows = $pdo->query("SELECT rating,COUNT(*) c FROM responses GROUP BY rating ORDER BY rating")->fetchAll();
$ratingData = [1=>0,2=>0,3=>0,4=>0,5=>0];
foreach ($ratingRows as $r) $ratingData[(int)$r['rating']] = (int)$r['c'];

$dailyRows = $pdo->query("
SELECT date(created_at) d, COUNT(*) c, ROUND(AVG(rating),2) a
FROM responses
WHERE created_at >= datetime('now','-13 days')
GROUP BY date(created_at)
ORDER BY d
")->fetchAll();

$recent = $pdo->query("
SELECT r.*, s.title survey_title,
       (SELECT COUNT(*) FROM photos p WHERE p.response_id=r.id) photo_count
FROM responses r JOIN surveys s ON s.id=r.survey_id
ORDER BY r.id DESC LIMIT 10
")->fetchAll();
?>
<!doctype html><html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Панель — <?= h(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= h(url('assets/style.css')) ?>" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js"></script>
</head><body>
<nav class="navbar navbar-expand-lg px-3 py-3"><div class="container-fluid">
<a class="navbar-brand" href="<?= h(url('admin/index.php')) ?>">◫ MICRO//FEEDBACK</a>
<div class="d-flex gap-2">
<a class="btn btn-sm btn-outline-info" href="<?= h(url('admin/surveys.php')) ?>">QR / Опросы</a>
<a class="btn btn-sm btn-outline-info" href="<?= h(url('admin/responses.php')) ?>">Отзывы</a>
<a class="btn btn-sm btn-outline-secondary" href="<?= h(url('admin/logout.php')) ?>">Выход</a>
</div>
</div></nav>

<main class="container-fluid px-3 px-lg-4 py-4">
<div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-4">
 <div><div class="smallcaps">Центр аналитики</div><h1 class="hero-title mb-0">Панель обратной связи</h1></div>
 <span class="chip">● LIVE DATA</span>
</div>

<div class="row g-3 mb-4">
 <div class="col-6 col-xl-3"><div class="metric"><div class="smallcaps">Всего ответов</div><div class="value mt-2"><?= $total ?></div></div></div>
 <div class="col-6 col-xl-3"><div class="metric"><div class="smallcaps">Средняя оценка</div><div class="value mt-2"><?= number_format($avg,2,',',' ') ?> <small class="fs-6">/ 5</small></div></div></div>
 <div class="col-6 col-xl-3"><div class="metric"><div class="smallcaps">Сегодня</div><div class="value mt-2"><?= $today ?></div></div></div>
 <div class="col-6 col-xl-3"><div class="metric"><div class="smallcaps">Оценки 1–2</div><div class="value mt-2"><?= $negative ?></div></div></div>
</div>

<div class="row g-3 mb-4">
 <div class="col-xl-5"><div class="card rounded-4 p-4 h-100"><h2 class="h5 mb-3">Распределение оценок</h2><canvas id="ratings" height="210"></canvas></div></div>
 <div class="col-xl-7"><div class="card rounded-4 p-4 h-100"><h2 class="h5 mb-3">Активность за 14 дней</h2><canvas id="daily" height="210"></canvas></div></div>
</div>

<div class="card rounded-4 p-3 p-lg-4">
<div class="d-flex justify-content-between mb-3"><h2 class="h5 mb-0">Последние отзывы</h2><a href="<?= h(url('admin/responses.php')) ?>">Все отзывы →</a></div>
<div class="table-responsive"><table class="table align-middle">
<thead><tr><th>Дата</th><th>Опрос</th><th>Оценка</th><th>Комментарий</th><th>Фото</th></tr></thead>
<tbody>
<?php foreach($recent as $r): ?><tr>
<td class="text-nowrap"><?= h(date('d.m.Y H:i', strtotime($r['visited_at']))) ?></td>
<td><?= h($r['survey_title']) ?></td>
<td><span class="badge badge-soft"><?= (int)$r['rating'] ?> ★</span></td>
<td><?= h(mb_strimwidth($r['comment'],0,100,'…')) ?></td>
<td><?= (int)$r['photo_count'] ?></td>
</tr><?php endforeach; ?>
<?php if(!$recent): ?><tr><td colspan="5" class="text-center text-muted2 py-5">Пока нет ответов.</td></tr><?php endif; ?>
</tbody></table></div>
</div>
</main>
<script>
Chart.defaults.color='#9bbbc2'; Chart.defaults.borderColor='rgba(120,210,215,.12)';
new Chart(document.getElementById('ratings'),{
 type:'bar',
 data:{labels:['1','2','3','4','5'],datasets:[{label:'Ответов',data:<?= json_encode(array_values($ratingData)) ?>,backgroundColor:'rgba(64,245,220,.55)',borderColor:'#40f5dc',borderWidth:1,borderRadius:8}]},
 options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}},x:{grid:{display:false}}}}
});
const daily=<?= json_encode($dailyRows, JSON_UNESCAPED_UNICODE) ?>;
new Chart(document.getElementById('daily'),{
 type:'line',
 data:{labels:daily.map(x=>x.d),datasets:[
   {label:'Ответы',data:daily.map(x=>x.c),borderColor:'#40f5dc',backgroundColor:'rgba(64,245,220,.10)',fill:true,tension:.35,yAxisID:'y'},
   {label:'Средняя оценка',data:daily.map(x=>x.a),borderColor:'#34baff',tension:.35,yAxisID:'y1'}
 ]},
 options:{interaction:{mode:'index',intersect:false},scales:{
   y:{beginAtZero:true,ticks:{precision:0}},
   y1:{position:'right',min:0,max:5,grid:{drawOnChartArea:false}}
 }}
});
</script></body></html>
