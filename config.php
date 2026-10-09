<?php
declare(strict_types=1);

session_start();

const APP_NAME = 'MicroFeedback';
const ADMIN_USER = 'admin';
const ADMIN_PASSWORD = 'admin123';

$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (str_ends_with($base, '/admin')) $base = substr($base, 0, -6);
define('BASE_PATH', $base === '/' ? '' : $base);

$dataDir = __DIR__ . '/data';
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($dataDir)) mkdir($dataDir, 0775, true);
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

$pdo = new PDO('sqlite:' . $dataDir . '/app.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->exec("
CREATE TABLE IF NOT EXISTS quiz_groups (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 title TEXT NOT NULL,
 description TEXT DEFAULT '',
 code TEXT NOT NULL UNIQUE,
 is_active INTEGER NOT NULL DEFAULT 1,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS surveys (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 title TEXT NOT NULL,
 venue TEXT DEFAULT '',
 code TEXT NOT NULL UNIQUE,
 is_active INTEGER NOT NULL DEFAULT 1,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 group_id INTEGER DEFAULT NULL,
 daily_limit INTEGER NOT NULL DEFAULT 0,
 collect_gps INTEGER NOT NULL DEFAULT 0,
 intro TEXT DEFAULT '',
 FOREIGN KEY(group_id) REFERENCES quiz_groups(id) ON DELETE SET NULL
);
CREATE TABLE IF NOT EXISTS responses (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 survey_id INTEGER NOT NULL,
 rating INTEGER NOT NULL DEFAULT 0,
 comment TEXT NOT NULL DEFAULT '',
 visited_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 ip_hash TEXT DEFAULT '',
 latitude REAL DEFAULT NULL,
 longitude REAL DEFAULT NULL,
 gps_accuracy REAL DEFAULT NULL,
 FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS questions (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 survey_id INTEGER NOT NULL,
 label TEXT NOT NULL,
 type TEXT NOT NULL,
 is_required INTEGER NOT NULL DEFAULT 0,
 sort_order INTEGER NOT NULL DEFAULT 0,
 settings_json TEXT DEFAULT '{}',
 FOREIGN KEY(survey_id) REFERENCES surveys(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS answers (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 response_id INTEGER NOT NULL,
 question_id INTEGER NOT NULL,
 value_text TEXT DEFAULT '',
 FOREIGN KEY(response_id) REFERENCES responses(id) ON DELETE CASCADE,
 FOREIGN KEY(question_id) REFERENCES questions(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS photos (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 response_id INTEGER NOT NULL,
 question_id INTEGER DEFAULT NULL,
 file_name TEXT NOT NULL,
 original_name TEXT DEFAULT '',
 FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE CASCADE,
 FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE SET NULL
);
");

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void {
    $cols = $pdo->query("PRAGMA table_info(" . $table . ")")->fetchAll();
    foreach ($cols as $c) if ($c['name'] === $column) return;
    $pdo->exec("ALTER TABLE " . $table . " ADD COLUMN " . $column . " " . $definition);
}
ensure_column($pdo,'surveys','group_id','INTEGER DEFAULT NULL');
ensure_column($pdo,'surveys','daily_limit','INTEGER NOT NULL DEFAULT 0');
ensure_column($pdo,'surveys','collect_gps','INTEGER NOT NULL DEFAULT 0');
ensure_column($pdo,'surveys','intro',"TEXT DEFAULT ''");
ensure_column($pdo,'responses','latitude','REAL DEFAULT NULL');
ensure_column($pdo,'responses','longitude','REAL DEFAULT NULL');
ensure_column($pdo,'responses','gps_accuracy','REAL DEFAULT NULL');
ensure_column($pdo,'photos','question_id','INTEGER DEFAULT NULL');

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function url(string $path=''): string { return BASE_PATH . '/' . ltrim($path,'/'); }
function absolute_url(string $path=''): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return ($https?'https':'http').'://'.($_SERVER['HTTP_HOST'] ?? 'localhost').url($path);
}
function admin_required(): void {
    if (empty($_SESSION['admin'])) { header('Location: '.url('admin/login.php')); exit; }
}
function flash(string $key, ?string $value=null): ?string {
    if ($value !== null) { $_SESSION['_flash'][$key]=$value; return null; }
    $v=$_SESSION['_flash'][$key] ?? null; unset($_SESSION['_flash'][$key]); return $v;
}
function random_code(int $len=10): string { return substr(bin2hex(random_bytes(16)),0,$len); }
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(24));
    return $_SESSION['_csrf'];
}
function csrf_check(): void {
    $sent=$_POST['_csrf'] ?? '';
    if (!$sent || !hash_equals($_SESSION['_csrf'] ?? '', $sent)) {
        http_response_code(419); exit('Сессия формы устарела. Обновите страницу.');
    }
}
function client_ip_hash(): string {
    $ip=$_SERVER['REMOTE_ADDR'] ?? '';
    return hash('sha256',$ip.'|'.date('Y-m-d'));
}
function question_types(): array {
    return [
      'rating'=>'Ваша оценка',
      'textarea'=>'Многострочный текст',
      'datetime'=>'Дата и время',
      'date'=>'Дата',
      'phone'=>'Телефон',
      'photo'=>'Фото'
    ];
}
function resize_uploaded_image(string $src,string $dst,string $mime,int $maxW=640,int $maxH=480): bool {
    if (!extension_loaded('gd')) return false;
    [$w,$h]=getimagesize($src) ?: [0,0]; if($w<=0||$h<=0) return false;
    $source=match($mime){
      'image/jpeg'=>imagecreatefromjpeg($src),
      'image/png'=>imagecreatefrompng($src),
      'image/webp'=>imagecreatefromwebp($src),
      default=>false
    };
    if(!$source) return false;
    if($mime==='image/jpeg' && function_exists('exif_read_data')){
      $exif=@exif_read_data($src); $o=(int)($exif['Orientation'] ?? 1);
      if($o===3)$source=imagerotate($source,180,0);
      elseif($o===6)$source=imagerotate($source,-90,0);
      elseif($o===8)$source=imagerotate($source,90,0);
    }
    $w=imagesx($source);$h=imagesy($source);
    $scale=min($maxW/$w,$maxH/$h,1);$nw=max(1,(int)round($w*$scale));$nh=max(1,(int)round($h*$scale));
    $target=imagecreatetruecolor($nw,$nh);
    if($mime==='image/png'||$mime==='image/webp'){
      imagealphablending($target,false);imagesavealpha($target,true);
      $tr=imagecolorallocatealpha($target,0,0,0,127);imagefilledrectangle($target,0,0,$nw,$nh,$tr);
    }
    imagecopyresampled($target,$source,0,0,0,0,$nw,$nh,$w,$h);
    $ok=match($mime){
      'image/jpeg'=>imagejpeg($target,$dst,84),
      'image/png'=>imagepng($target,$dst,7),
      'image/webp'=>imagewebp($target,$dst,82),
      default=>false
    };
    imagedestroy($source);imagedestroy($target);return $ok;
}

// Старые опросы автоматически получают прежние 4 поля в новом конструкторе.
$legacy=$pdo->query("SELECT s.id FROM surveys s WHERE NOT EXISTS(SELECT 1 FROM questions q WHERE q.survey_id=s.id)")->fetchAll();
if($legacy){
  $ins=$pdo->prepare("INSERT INTO questions(survey_id,label,type,is_required,sort_order,settings_json) VALUES(?,?,?,?,?,?)");
  foreach($legacy as $s){
    $sid=(int)$s['id'];
    $ins->execute([$sid,'Ваша оценка','rating',1,10,'{}']);
    $ins->execute([$sid,'Коротко опишите ситуацию','textarea',1,20,'{"max_length":300}']);
    $ins->execute([$sid,'Дата и время посещения','datetime',1,30,'{}']);
    $ins->execute([$sid,'Фото — при необходимости','photo',0,40,'{"max_files":3}']);
  }
}
