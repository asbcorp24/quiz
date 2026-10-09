<?php
require __DIR__ . '/config.php';
$code=trim($_GET['g']??'');
$st=$pdo->prepare("SELECT * FROM quiz_groups WHERE code=? AND is_active=1");$st->execute([$code]);$group=$st->fetch();
if(!$group){http_response_code(404);$pageTitle='Группа не найдена';$quizzes=[];}
else{
 $pageTitle=$group['title'];
 $q=$pdo->prepare("SELECT s.*,(SELECT COUNT(*) FROM responses r WHERE r.survey_id=s.id) response_count FROM surveys s WHERE s.group_id=? AND s.is_active=1 ORDER BY s.title");
 $q->execute([$group['id']]);$quizzes=$q->fetchAll();
}
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($pageTitle)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"><link href="<?=h(url('assets/style.css'))?>" rel="stylesheet"></head><body>
<nav class="navbar px-3 py-3"><div class="container"><a class="navbar-brand" href="#">◫ MICRO//FEEDBACK</a><span class="chip">● QUIZ GROUP</span></div></nav>
<main class="container py-5" style="max-width:920px">
<?php if(!$group):?><div class="glass rounded-4 p-5 text-center"><div class="display-5">404</div><h1>Группа не найдена</h1></div>
<?php else:?><div class="mb-4"><div class="smallcaps">Группа квизов</div><h1 class="hero-title display-5"><?=h($group['title'])?></h1><?php if($group['description']):?><p class="text-muted2 fs-5"><?=nl2br(h($group['description']))?></p><?php endif;?></div>
<div class="row g-3"><?php foreach($quizzes as $q):?><div class="col-md-6"><a class="text-decoration-none" href="<?=h(url('index.php?s='.$q['code']))?>"><div class="card rounded-4 p-4 h-100"><div class="smallcaps mb-2"><?=h($q['venue'])?></div><h2 class="h4 text-light"><?=h($q['title'])?></h2><?php if($q['intro']):?><p class="text-muted2"><?=h(mb_strimwidth($q['intro'],0,130,'…'))?></p><?php endif;?><div class="mt-auto pt-2">Открыть квиз →</div></div></a></div><?php endforeach;?></div>
<?php if(!$quizzes):?><div class="glass rounded-4 p-5 text-center text-muted2">В этой группе пока нет активных квизов.</div><?php endif;?>
<?php endif;?></main></body></html>