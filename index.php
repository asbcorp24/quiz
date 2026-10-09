<?php
require __DIR__ . '/config.php';

$code = trim($_GET['s'] ?? '');
$stmt = $pdo->prepare("SELECT * FROM surveys WHERE code = ? AND is_active = 1");
$stmt->execute([$code]);
$survey = $stmt->fetch();

if (!$survey) {
    http_response_code(404);
    $pageTitle = 'Опрос не найден';
} else {
    $pageTitle = $survey['title'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $survey) {
    csrf_check();
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $visitedAt = trim($_POST['visited_at'] ?? '');

    $errors = [];
    if ($rating < 1 || $rating > 5) $errors[] = 'Выберите оценку от 1 до 5.';
    $len = mb_strlen($comment);
    if ($len < 3 || $len > 300) $errors[] = 'Описание должно содержать от 3 до 300 символов.';
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $visitedAt);
    if (!$dt) $errors[] = 'Проверьте дату и время посещения.';

    $validFiles = [];
    if (!empty($_FILES['photos']['name'][0])) {
        $count = count($_FILES['photos']['name']);
        if ($count > 3) $errors[] = 'Можно загрузить не более 3 фотографий.';
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        for ($i=0; $i<$count && $i<3; $i++) {
            if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if ($_FILES['photos']['size'][$i] > 6 * 1024 * 1024) {
                $errors[] = 'Каждое фото должно быть не больше 6 МБ.';
                continue;
            }
            $tmp = $_FILES['photos']['tmp_name'][$i];
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
            if (!isset($allowed[$mime])) {
                $errors[] = 'Допустимы JPG, PNG и WEBP.';
                continue;
            }
            $validFiles[] = [$tmp, $allowed[$mime], $_FILES['photos']['name'][$i]];
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO responses (survey_id, rating, comment, visited_at, ip_hash) VALUES (?, ?, ?, ?, ?)");
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $stmt->execute([
                $survey['id'], $rating, $comment, $dt->format('Y-m-d H:i:s'),
                hash('sha256', $ip . '|' . date('Y-m'))
            ]);
            $responseId = (int)$pdo->lastInsertId();

            foreach ($validFiles as [$tmp,$ext,$original]) {
                $name = bin2hex(random_bytes(14)) . '.' . $ext;
                $destination = __DIR__ . '/uploads/' . $name;

                $mime = match ($ext) {
                    'jpg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    default => '',
                };

                // Фото автоматически уменьшается до границ 640×480,
                // пропорции сохраняются, маленькие фото не увеличиваются.
                if (!resize_uploaded_image($tmp, $destination, $mime, 640, 480)) {
                    throw new RuntimeException('Не удалось уменьшить фотографию. Проверьте расширение PHP GD.');
                }

                $p = $pdo->prepare("INSERT INTO photos(response_id,file_name,original_name) VALUES(?,?,?)");
                $p->execute([$responseId,$name,$original]);
            }
            $pdo->commit();
            header('Location: ' . url('thanks.php'));
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Не удалось сохранить отзыв. Попробуйте ещё раз.';
        }
    }
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= h(url('assets/style.css')) ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand px-3 py-3"><div class="container">
  <a class="navbar-brand" href="#">◫ MICRO//FEEDBACK</a>
  <span class="chip">● SYSTEM ONLINE</span>
</div></nav>

<main class="container py-5" style="max-width:860px">
<?php if (!$survey): ?>
  <div class="glass rounded-4 p-5 text-center">
    <div class="display-5 mb-3">404</div>
    <h1 class="h3">Опрос не найден</h1>
    <p class="text-muted2 mb-0">QR-код устарел или опрос отключён администратором.</p>
  </div>
<?php else: ?>
  <div class="mb-4">
    <div class="smallcaps mb-2">Канал обратной связи / <?= h($survey['venue']) ?></div>
    <h1 class="hero-title display-5 mb-3"><?= h($survey['title']) ?></h1>
    <p class="text-muted2 fs-5">Оцените посещение. Ответ займёт меньше минуты.</p>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= h($e) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="glass rounded-4 p-4 p-md-5">
    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
    <div class="mb-4">
      <label class="form-label fw-semibold">1. Ваша оценка</label>
      <div class="text-muted2 small mb-3">1 — плохо, 5 — отлично</div>
      <div class="rating">
        <?php for($i=1;$i<=5;$i++): ?>
          <input type="radio" name="rating" value="<?= $i ?>" id="r<?= $i ?>" <?= (($_POST['rating'] ?? '') == $i) ? 'checked' : '' ?>>
          <label for="r<?= $i ?>" title="<?= $i ?> из 5"><?= $i ?>★</label>
        <?php endfor; ?>
      </div>
    </div>

    <div class="circuit-line my-4"></div>

    <div class="mb-4">
      <div class="d-flex justify-content-between gap-3">
        <label for="comment" class="form-label fw-semibold">2. Коротко опишите ситуацию</label>
        <span id="counter" class="text-muted2 small"></span>
      </div>
      <textarea class="form-control" id="comment" name="comment" rows="5" minlength="3" maxlength="300"
        placeholder="Что понравилось или что стоит улучшить?" required><?= h($_POST['comment'] ?? '') ?></textarea>
      <div class="form-text text-muted2">До 300 символов.</div>
    </div>

    <div class="mb-4">
      <label for="visited_at" class="form-label fw-semibold">3. Дата и время посещения</label>
      <input class="form-control" type="datetime-local" id="visited_at" name="visited_at"
        value="<?= h($_POST['visited_at'] ?? '') ?>" data-autonow required>
    </div>

    <div class="mb-4">
      <label class="form-label fw-semibold">4. Фото — при необходимости</label>
      <input class="form-control" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
      <div class="form-text text-muted2">До 3 фото. После загрузки каждое автоматически уменьшается до 640×480 с сохранением пропорций.</div>
    </div>

    <button class="btn btn-primary btn-lg w-100 py-3" type="submit">Отправить обратную связь →</button>
  </form>
<?php endif; ?>
</main>
<script src="<?= h(url('assets/app.js')) ?>"></script>
</body></html>
