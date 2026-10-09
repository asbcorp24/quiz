<?php
declare(strict_types=1);

session_start();

const APP_NAME = 'MicroFeedback';
const ADMIN_USER = 'admin';
// Смените пароль после первого запуска.
const ADMIN_PASSWORD = 'admin123';

$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if (str_ends_with($base, '/admin')) {
    $base = substr($base, 0, -6);
}
define('BASE_PATH', $base === '/' ? '' : $base);

$dataDir = __DIR__ . '/data';
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($dataDir)) mkdir($dataDir, 0775, true);
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

$pdo = new PDO('sqlite:' . $dataDir . '/app.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec("
CREATE TABLE IF NOT EXISTS surveys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    venue TEXT DEFAULT '',
    code TEXT NOT NULL UNIQUE,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS responses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    survey_id INTEGER NOT NULL,
    rating INTEGER NOT NULL,
    comment TEXT NOT NULL,
    visited_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_hash TEXT DEFAULT '',
    FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    response_id INTEGER NOT NULL,
    file_name TEXT NOT NULL,
    original_name TEXT DEFAULT '',
    FOREIGN KEY (response_id) REFERENCES responses(id) ON DELETE CASCADE
);
");

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function url(string $path = ''): string {
    return BASE_PATH . '/' . ltrim($path, '/');
}
function absolute_url(string $path = ''): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url($path);
}
function admin_required(): void {
    if (empty($_SESSION['admin'])) {
        header('Location: ' . url('admin/login.php'));
        exit;
    }
}
function flash(string $key, ?string $value = null): ?string {
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }
    $v = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $v;
}
function random_code(int $len = 10): string {
    return substr(bin2hex(random_bytes(16)), 0, $len);
}
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['_csrf'];
}

function resize_uploaded_image(string $src, string $dst, string $mime, int $maxW = 640, int $maxH = 480): bool {
    if (!extension_loaded('gd')) {
        return false;
    }

    [$w, $h] = getimagesize($src) ?: [0, 0];
    if ($w <= 0 || $h <= 0) return false;

    $scale = min($maxW / $w, $maxH / $h, 1);
    $newW = max(1, (int)round($w * $scale));
    $newH = max(1, (int)round($h * $scale));

    switch ($mime) {
        case 'image/jpeg':
            $source = imagecreatefromjpeg($src);
            break;
        case 'image/png':
            $source = imagecreatefrompng($src);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($src);
            break;
        default:
            return false;
    }

    if (!$source) return false;

    // Учитываем EXIF-ориентацию JPEG с телефона.
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($src);
        $orientation = (int)($exif['Orientation'] ?? 1);

        if ($orientation === 3) {
            $source = imagerotate($source, 180, 0);
        } elseif ($orientation === 6) {
            $source = imagerotate($source, -90, 0);
        } elseif ($orientation === 8) {
            $source = imagerotate($source, 90, 0);
        }

        if (in_array($orientation, [6, 8], true)) {
            $w = imagesx($source);
            $h = imagesy($source);
            $scale = min($maxW / $w, $maxH / $h, 1);
            $newW = max(1, (int)round($w * $scale));
            $newH = max(1, (int)round($h * $scale));
        }
    }

    $target = imagecreatetruecolor($newW, $newH);

    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $newW, $newH, $transparent);
    }

    imagecopyresampled(
        $target, $source,
        0, 0, 0, 0,
        $newW, $newH,
        imagesx($source), imagesy($source)
    );

    $ok = match ($mime) {
        'image/jpeg' => imagejpeg($target, $dst, 84),
        'image/png'  => imagepng($target, $dst, 7),
        'image/webp' => imagewebp($target, $dst, 82),
        default      => false,
    };

    imagedestroy($source);
    imagedestroy($target);

    return $ok;
}

function csrf_check(): void {
    $sent = $_POST['_csrf'] ?? '';
    if (!$sent || !hash_equals($_SESSION['_csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Сессия формы устарела. Обновите страницу.');
    }
}
