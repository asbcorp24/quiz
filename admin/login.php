<?php
require dirname(__DIR__) . '/config.php';
if (!empty($_SESSION['admin'])) { header('Location: ' . url('admin/index.php')); exit; }
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (hash_equals(ADMIN_USER, (string)($_POST['login'] ?? '')) &&
        hash_equals(ADMIN_PASSWORD, (string)($_POST['password'] ?? ''))) {
        $_SESSION['admin'] = true;
        header('Location: ' . url('admin/index.php')); exit;
    }
    $error = 'Неверный логин или пароль.';
}
?><!doctype html><html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Вход — <?= h(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= h(url('assets/style.css')) ?>" rel="stylesheet">
</head><body class="d-flex align-items-center">
<div class="container" style="max-width:470px">
<form method="post" class="glass rounded-4 p-4 p-md-5">
<input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
<div class="smallcaps mb-2">Служебный интерфейс</div>
<h1 class="h2 hero-title mb-4">Вход в админ-панель</h1>
<?php if($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
<div class="mb-3"><label class="form-label">Логин</label><input class="form-control" name="login" required></div>
<div class="mb-4"><label class="form-label">Пароль</label><input class="form-control" type="password" name="password" required></div>
<button class="btn btn-primary w-100 py-3">Войти →</button>
</form>
</div></body></html>
