<?php
require __DIR__ . '/config.php';

$code=trim($_GET['s']??'');
$st=$pdo->prepare("SELECT * FROM surveys WHERE code=? AND is_active=1");$st->execute([$code]);$survey=$st->fetch();
if(!$survey){http_response_code(404);$pageTitle='Опрос не найден';$questions=[];}
else{$pageTitle=$survey['title'];$q=$pdo->prepare("SELECT * FROM questions WHERE survey_id=? ORDER BY sort_order,id");$q->execute([$survey['id']]);$questions=$q->fetchAll();}

$errors=[];
if($_SERVER['REQUEST_METHOD']==='POST' && $survey){
 csrf_check();
 if((int)$survey['daily_limit']>0){
   $qh=$pdo->prepare("SELECT COUNT(*) FROM responses WHERE survey_id=? AND ip_hash=? AND date(created_at)=date('now','localtime')");
   $qh->execute([$survey['id'],client_ip_hash()]);
   if((int)$qh->fetchColumn()>=(int)$survey['daily_limit'])$errors[]='Лимит ответов с этого адреса на сегодня исчерпан.';
 }
 $answers=[];$ratingLegacy=0;$commentLegacy='';$visitedLegacy=date('Y-m-d H:i:s');$photoJobs=[];
 foreach($questions as $x){
   $qid=(int)$x['id'];$type=$x['type'];$name='q_'.$qid;$value=trim((string)($_POST[$name]??''));$set=json_decode($x['settings_json']?:'{}',true)?:[];
   if($type==='photo'){
     $files=$_FILES[$name]??null;$count=0;
     if($files && is_array($files['name'])){
       $max=max(1,min(3,(int)($set['max_files']??1)));
       for($i=0;$i<count($files['name']) && $count<$max;$i++){
         if($files['error'][$i]===UPLOAD_ERR_OK){$photoJobs[]=[$x,$files,$i];$count++;}
       }
     }
     if($x['is_required'] && $count===0)$errors[]='Загрузите фото: '.$x['label'];
     continue;
   }
   if($x['is_required'] && $value==='')$errors[]='Заполните: '.$x['label'];
   if($value!==''){
     if($type==='rating'){
       $n=(int)$value;if($n<1||$n>5)$errors[]='Некорректная оценка: '.$x['label']; else $ratingLegacy=$ratingLegacy?:$n;
     }
     if($type==='textarea'){
       $max=(int)($set['max_length']??300);if(mb_strlen($value)>$max)$errors[]='Слишком длинный ответ: '.$x['label'];
       if($commentLegacy==='')$commentLegacy=$value;
     }
     if($type==='phone' && !preg_match('/^[0-9+()\-\s]{5,30}$/u',$value))$errors[]='Проверьте телефон: '.$x['label'];
     if($type==='datetime'){
       $dt=DateTime::createFromFormat('Y-m-d\TH:i',$value);if(!$dt)$errors[]='Проверьте дату и время: '.$x['label']; else $visitedLegacy=$dt->format('Y-m-d H:i:s');
     }
     if($type==='date' && !DateTime::createFromFormat('Y-m-d',$value))$errors[]='Проверьте дату: '.$x['label'];
   }
   $answers[$qid]=$value;
 }
 foreach($photoJobs as [$x,$files,$i]){
   if($files['size'][$i]>6*1024*1024){$errors[]='Фото должно быть не больше 6 МБ.';continue;}
   $mime=(new finfo(FILEINFO_MIME_TYPE))->file($files['tmp_name'][$i]);if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))$errors[]='Допустимы JPG, PNG и WEBP.';
 }
 if(!$errors){
   $lat=null;$lng=null;$acc=null;
   if($survey['collect_gps']){
     $lat=is_numeric($_POST['gps_lat']??null)?(float)$_POST['gps_lat']:null;
     $lng=is_numeric($_POST['gps_lng']??null)?(float)$_POST['gps_lng']:null;
     $acc=is_numeric($_POST['gps_acc']??null)?(float)$_POST['gps_acc']:null;
   }
   $pdo->beginTransaction();
   try{
     $pdo->prepare("INSERT INTO responses(survey_id,rating,comment,visited_at,ip_hash,latitude,longitude,gps_accuracy) VALUES(?,?,?,?,?,?,?,?)")
       ->execute([$survey['id'],$ratingLegacy,$commentLegacy,$visitedLegacy,client_ip_hash(),$lat,$lng,$acc]);
     $rid=(int)$pdo->lastInsertId();
     $ai=$pdo->prepare("INSERT INTO answers(response_id,question_id,value_text) VALUES(?,?,?)");
     foreach($answers as $qid=>$value)$ai->execute([$rid,$qid,$value]);
     foreach($photoJobs as [$x,$files,$i]){
       $tmp=$files['tmp_name'][$i];$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;if(!$ext)continue;
       $name=bin2hex(random_bytes(14)).'.'.$ext;$dst=__DIR__.'/uploads/'.$name;
       if(!resize_uploaded_image($tmp,$dst,$mime,640,480))throw new RuntimeException('photo');
       $pdo->prepare("INSERT INTO photos(response_id,question_id,file_name,original_name) VALUES(?,?,?,?)")->execute([$rid,$x['id'],$name,$files['name'][$i]]);
     }
     $pdo->commit();header('Location: '.url('thanks.php'));exit;
   }catch(Throwable $e){$pdo->rollBack();$errors[]='Не удалось сохранить ответ. Попробуйте ещё раз.';}
 }
}
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=h($pageTitle)?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"><link href="<?=h(url('assets/style.css'))?>" rel="stylesheet"></head><body>
<nav class="navbar px-3 py-3"><div class="container"><a class="navbar-brand" href="#">◫ MICRO//FEEDBACK</a><span class="chip">● SYSTEM ONLINE</span></div></nav>
<main class="container py-5" style="max-width:860px">
<?php if(!$survey):?><div class="glass rounded-4 p-5 text-center"><div class="display-5">404</div><h1>Опрос не найден</h1></div>
<?php else:?>
<div class="mb-4"><div class="smallcaps"><?=h($survey['venue'])?></div><h1 class="hero-title display-5"><?=h($survey['title'])?></h1><?php if($survey['intro']):?><p class="text-muted2 fs-5"><?=nl2br(h($survey['intro']))?></p><?php endif;?></div>
<?php if($errors):?><div class="alert alert-danger"><?php foreach($errors as $e):?><div><?=h($e)?></div><?php endforeach;?></div><?php endif;?>
<form method="post" enctype="multipart/form-data" class="glass rounded-4 p-4 p-md-5"><input type="hidden" name="_csrf" value="<?=h(csrf_token())?>">
<?php if($survey['collect_gps']):?><input type="hidden" name="gps_lat" id="gps_lat"><input type="hidden" name="gps_lng" id="gps_lng"><input type="hidden" name="gps_acc" id="gps_acc"><div class="alert alert-info py-2 small">Этот квиз запрашивает геопозицию. Браузер отдельно попросит разрешение.</div><?php endif;?>
<?php foreach($questions as $i=>$x):$n='q_'.$x['id'];$set=json_decode($x['settings_json']?:'{}',true)?:[];?>
<div class="mb-4"><label class="form-label fw-semibold"><?=($i+1)?>. <?=h($x['label'])?> <?=$x['is_required']?'<span class="text-danger">*</span>':''?></label>
<?php if($x['type']==='rating'):?><div class="rating"><?php for($v=1;$v<=5;$v++):?><input type="radio" name="<?=$n?>" id="<?=$n.'_'.$v?>" value="<?=$v?>" <?=($_POST[$n]??'')==$v?'checked':''?>><label for="<?=$n.'_'.$v?>"><?=$v?>★</label><?php endfor;?></div>
<?php elseif($x['type']==='textarea'):?><textarea class="form-control" name="<?=$n?>" rows="5" maxlength="<?=(int)($set['max_length']??300)?>" <?=$x['is_required']?'required':''?>><?=h($_POST[$n]??'')?></textarea>
<?php elseif($x['type']==='datetime'):?><input class="form-control" type="datetime-local" name="<?=$n?>" value="<?=h($_POST[$n]??'')?>" <?=$x['is_required']?'required':''?>>
<?php elseif($x['type']==='date'):?><input class="form-control" type="date" name="<?=$n?>" value="<?=h($_POST[$n]??'')?>" <?=$x['is_required']?'required':''?>>
<?php elseif($x['type']==='phone'):?><input class="form-control" type="tel" name="<?=$n?>" value="<?=h($_POST[$n]??'')?>" placeholder="+7..." <?=$x['is_required']?'required':''?>>
<?php elseif($x['type']==='photo'):?><input class="form-control" type="file" name="<?=$n?>[]" accept="image/jpeg,image/png,image/webp" <?=((int)($set['max_files']??1)>1)?'multiple':''?> <?=$x['is_required']?'required':''?>><div class="form-text text-muted2">До <?=(int)($set['max_files']??1)?> фото, каждое до 6 МБ.</div>
<?php endif;?></div><div class="circuit-line my-4"></div><?php endforeach;?>
<button class="btn btn-primary btn-lg w-100 py-3">Отправить →</button></form><?php endif;?></main>
<?php if($survey && $survey['collect_gps']):?><script>if(navigator.geolocation)navigator.geolocation.getCurrentPosition(p=>{gps_lat.value=p.coords.latitude;gps_lng.value=p.coords.longitude;gps_acc.value=p.coords.accuracy},()=>{}, {enableHighAccuracy:true,timeout:8000});</script><?php endif;?>
</body></html>