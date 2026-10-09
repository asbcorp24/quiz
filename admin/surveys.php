<?php
require dirname(__DIR__) . '/config.php';
admin_required();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $title = trim($_POST['title'] ?? '');
    $venue = trim($_POST['venue'] ?? '');
    if ($title !== '') {
        do { $code = random_code(); $q=$pdo->prepare("SELECT COUNT(*) FROM surveys WHERE code=?"); $q->execute([$code]); } while($q->fetchColumn());
        $st=$pdo->prepare("INSERT INTO surveys(title,venue,code) VALUES(?,?,?)");
        $st->execute([$title,$venue,$code]);
        flash('ok','Опрос создан. QR-код готов.');
        header('Location: '.url('admin/surveys.php')); exit;
    }
}
if (isset($_GET['toggle'])) {
    $id=(int)$_GET['toggle'];
    $pdo->prepare("UPDATE surveys SET is_active=CASE WHEN is_active=1 THEN 0 ELSE 1 END WHERE id=?")->execute([$id]);
    header('Location: '.url('admin/surveys.php')); exit;
}
$rows=$pdo->query("SELECT s.*,COUNT(r.id) responses,ROUND(AVG(r.rating),2) avg_rating FROM surveys s LEFT JOIN responses r ON r.survey_id=s.id GROUP BY s.id ORDER BY s.id DESC")->fetchAll();
?>
<!doctype html><html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>QR и опросы</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= h(url('assets/style.css')) ?>" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
</head><body>
<nav class="navbar px-3 py-3"><div class="container-fluid">
<a class="navbar-brand" href="<?= h(url('admin/index.php')) ?>">◫ MICRO//FEEDBACK</a>
<a href="<?= h(url('admin/index.php')) ?>" class="btn btn-sm btn-outline-info">← Аналитика</a>
</div></nav>
<main class="container py-4">
<div class="row g-4">
<div class="col-lg-4">
<form method="post" class="glass rounded-4 p-4 sticky-lg-top" style="top:20px">
<input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
<div class="smallcaps mb-2">Новый канал</div><h1 class="h3 hero-title mb-4">Создать QR-опрос</h1>
<div class="mb-3"><label class="form-label">Название</label><input class="form-control" name="title" placeholder="Например: Столовая №1" required></div>
<div class="mb-4"><label class="form-label">Место / объект</label><input class="form-control" name="venue" placeholder="Корпус, кафе, мероприятие"></div>
<button class="btn btn-primary w-100 py-3">Сгенерировать →</button>
</form>
</div>
<div class="col-lg-8">
<?php if($m=flash('ok')): ?><div class="alert alert-success"><?= h($m) ?></div><?php endif; ?>
<h2 class="h4 mb-3">Опросы и QR-коды</h2>
<?php foreach($rows as $r):
 $link=absolute_url('index.php?s='.urlencode($r['code']));
?>
<div class="card rounded-4 p-4 mb-3">
 <div class="row align-items-center g-4">
  <div class="col-md">
   <div class="d-flex align-items-center gap-2 mb-2">
    <h3 class="h5 mb-0"><?= h($r['title']) ?></h3>
    <span class="badge <?= $r['is_active']?'badge-soft':'text-bg-secondary' ?>"><?= $r['is_active']?'активен':'выключен' ?></span>
   </div>
   <div class="text-muted2 mb-3"><?= h($r['venue']) ?></div>
   <div class="small text-break mb-3"><?= h($link) ?></div>
   <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-sm btn-primary" onclick='showQr(<?= json_encode($link) ?>,<?= json_encode($r['title']) ?>)'>Показать QR</button>
    <button class="btn btn-sm btn-outline-info" onclick='navigator.clipboard.writeText(<?= json_encode($link) ?>)'>Копировать ссылку</button>
    <a class="btn btn-sm btn-outline-secondary" href="?toggle=<?= (int)$r['id'] ?>"><?= $r['is_active']?'Отключить':'Включить' ?></a>
   </div>
  </div>
  <div class="col-md-auto text-md-end">
   <div class="metric"><div class="smallcaps">Ответов</div><div class="value mt-2"><?= (int)$r['responses'] ?></div>
   <div class="text-muted2 small mt-2">Средняя: <?= $r['avg_rating'] ? h((string)$r['avg_rating']) : '—' ?></div></div>
  </div>
 </div>
</div>
<?php endforeach; ?>
<?php if(!$rows): ?><div class="glass rounded-4 p-5 text-center text-muted2">Создайте первый опрос — система сформирует уникальную ссылку и QR-код.</div><?php endif; ?>
</div></div>
</main>

<div class="modal fade" id="qrModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content card">
<div class="modal-header border-secondary"><h5 class="modal-title" id="qrTitle">QR-код</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body text-center p-4"><div id="qrcode" class="qr-wrap mb-3"></div><div id="qrLink" class="small text-break text-muted2"></div></div>
<div class="modal-footer border-secondary"><button class="btn btn-primary" onclick="downloadQr()">Скачать PNG</button></div>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script>
let modal = new bootstrap.Modal(document.getElementById('qrModal'));
function showQr(link,title){
 const box=document.getElementById('qrcode'); box.innerHTML='';
 document.getElementById('qrTitle').textContent=title;
 document.getElementById('qrLink').textContent=link;
 new QRCode(box,{text:link,width:280,height:280,correctLevel:QRCode.CorrectLevel.H});
 modal.show();
}
function downloadQr(){
 const box=document.getElementById('qrcode');
 const canvas=box.querySelector('canvas');
 const img=box.querySelector('img');
 let src=canvas ? canvas.toDataURL('image/png') : (img ? img.src : null);
 if(!src) return;
 const a=document.createElement('a');a.href=src;a.download='feedback-qr.png';a.click();
}
</script></body></html>
