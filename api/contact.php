<?php
declare(strict_types=1);
use PHPMailer\PHPMailer\PHPMailer;

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function respond(int $status, string $message, array $extra = []): never {
    http_response_code($status);
    echo json_encode(['ok' => $status >= 200 && $status < 300, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}
set_exception_handler(function (Throwable $e): void {
    error_log('StankoTech contact: unhandled exception ' . get_class($e));
    respond(500, 'Не удалось отправить заявку. Позвоните +375 29 290 58 63 или напишите на info@stankotech.by.');
});
$private = dirname(__DIR__, 2) . '/stankotech-private';
if (!is_file($private . '/smtp-config.php')) {
    respond(503, 'Отправка ещё не настроена. Свяжитесь с нами по телефону или email.');
}
$config = require $private . '/smtp-config.php';
if (!is_array($config)) { respond(503, 'Отправка временно недоступна.'); }
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST'); respond(405, 'Этот способ обращения не поддерживается.');
}
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !in_array($origin, $config['allowed_origins'] ?? [], true)) {
    respond(403, 'Отправьте заявку с сайта stankotech.by.');
}
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
    respond(403, 'Отправьте заявку с сайта stankotech.by.');
}
if ($method === 'POST' && $origin === '') {
    respond(403, 'Не удалось проверить источник запроса. Обновите страницу.');
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 12 * 1024 * 1024) {
    respond(413, 'Запрос слишком большой. Фото должно быть до 10 МБ.');
}
if (!is_dir($private . '/runtime') && !mkdir($private . '/runtime', 0700, true) && !is_dir($private . '/runtime')) {
    respond(503, 'Отправка временно недоступна.');
}
// Храним только хеш IP и время запросов; содержимое заявки сюда не записывается.
function limitRequests(string $dir, string $scope, int $max, int $window): void {
    $key = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ':' . $scope);
    $path = $dir . '/rate-' . $key . '.json';
    $fp = fopen($path, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) { respond(503, 'Отправка временно недоступна.'); }
    $raw = stream_get_contents($fp);
    $values = json_decode($raw ?: '[]', true);
    $values = is_array($values) ? $values : [];
    $now = time();
    $values = array_values(array_filter($values, fn($v) => is_int($v) && $v > $now - $window));
    if (count($values) >= $max) {
        flock($fp, LOCK_UN); fclose($fp);
        header('Retry-After: ' . $window);
        respond(429, 'Слишком много обращений. Попробуйте позже или позвоните нам.');
    }
    $values[] = $now;
    rewind($fp); ftruncate($fp, 0); fwrite($fp, json_encode($values)); fflush($fp);
    flock($fp, LOCK_UN); fclose($fp);
    if (random_int(1, 100) === 1) {
        foreach (glob($dir . '/rate-*.json') ?: [] as $file) {
            if ((filemtime($file) ?: $now) < $now - 86400) { @unlink($file); }
        }
    }
}
limitRequests($private . '/runtime', 'requests', 60, 900);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('stankotech_contact');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
if (!session_start()) { respond(503, 'Не удалось начать отправку. Обновите страницу.'); }
if (empty($_SESSION['csrf']) || (int)($_SESSION['csrf_time'] ?? 0) < time() - 3600) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32)); $_SESSION['csrf_time'] = time();
}
if ($method === 'GET') {
    respond(200, 'Форма готова к отправке.', ['token' => $_SESSION['csrf']]);
}
$token = $_POST['csrf'] ?? '';
if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
    respond(403, 'Не удалось проверить форму. Обновите страницу и повторите отправку.');
}
if (!empty($_POST['website'])) { respond(400, 'Не удалось отправить форму.'); }
function field(string $name, int $limit, bool $required = false): string {
    $v = $_POST[$name] ?? '';
    if (!is_string($v)) { respond(422, 'Некорректное поле формы.'); }
    $v = trim(str_replace(["\r\n", "\r"], "\n", $v));
    if (!preg_match('//u', $v) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $v)) { respond(422, 'Некорректный текст в форме.'); }
    $count = preg_match_all('/./us', $v);
    if ($count === false || $count > $limit || ($required && $v === '')) { respond(422, 'Проверьте обязательные поля и длину текста.'); }
    return $v;
}
$values = [
    'Имя' => field('name', 100, true),
    'Контакт' => field('contact', 160, true),
    'Задача' => field('task', 1500, true),
    'Оборудование' => field('equipment', 400),
    'Материал' => field('material', 150),
    'Толщина / покрытие' => field('thickness', 150),
    'Объём работ' => field('volume', 200),
];
$contact = $values['Контакт'];
$isEmail = filter_var($contact, FILTER_VALIDATE_EMAIL) !== false;
$isPhone = preg_match('/^\+?[\d\s()\-]{7,24}$/D', $contact) && strlen(preg_replace('/\D/', '', $contact)) >= 7;
if (!$isEmail && !$isPhone) { respond(422, 'Укажите корректный телефон или email.'); }
$attachment = null;
if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['photo'];
    if (!is_array($f) || !is_int($f['error'] ?? null) || $f['error'] !== UPLOAD_ERR_OK || !is_string($f['tmp_name'] ?? null) || !is_uploaded_file($f['tmp_name'])) {
        respond(422, 'Не удалось загрузить фото. Попробуйте отправить заявку без него.');
    }
    if ((int)$f['size'] > 10 * 1024 * 1024) { respond(413, 'Фото должно быть до 10 МБ.'); }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    $size = @getimagesize($f['tmp_name']);
    if (!$ext || !$size || $size[0] > 12000 || $size[1] > 12000) { respond(422, 'Прикрепите фото JPG, PNG или WebP размером не более 12000 × 12000 пикселей.'); }
    $attachment = ['path' => $f['tmp_name'], 'mime' => $mime, 'name' => 'sample.' . $ext];
}
$signature = hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE) . ($attachment ? hash_file('sha256', $attachment['path']) : ''));
if (($_SESSION['sent_hash'] ?? '') === $signature && (int)($_SESSION['sent_time'] ?? 0) > time() - 120) {
    respond(200, 'Эта заявка уже передана почтовому серверу. Повторное письмо не отправлено.');
}
limitRequests($private . '/runtime', 'submissions', 5, 900);
if (empty($config['password']) || $config['password'] === 'REPLACE_WITH_MAILBOX_PASSWORD') {
    respond(503, 'Отправка ещё не настроена. Позвоните +375 29 290 58 63 или напишите на info@stankotech.by.');
}
foreach (['Exception', 'PHPMailer', 'SMTP'] as $file) { require_once $private . '/vendor/PHPMailer/' . $file . '.php'; }
$body = "Заявка с сайта СтанкоТехнологии\n\n";
foreach ($values as $label => $value) { $body .= $label . ': ' . ($value !== '' ? $value : 'Не указано') . "\n"; }
$body .= "\nВремя UTC: " . gmdate('Y-m-d H:i:s') . "\n";
$mail = new PHPMailer(true);
try {
    $mail->isSMTP(); $mail->Host = $config['host']; $mail->Port = (int)$config['port'];
    $mail->SMTPAuth = true; $mail->Username = $config['username']; $mail->Password = $config['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Timeout = 20; $mail->Timelimit = 30; $mail->SMTPDebug = 0; $mail->CharSet = 'UTF-8';
    $mail->setFrom($config['from_email'], $config['from_name']); $mail->addAddress($config['to_email']);
    if ($isEmail) { $mail->addReplyTo($contact); }
    $mail->Subject = 'Заявка с сайта — СтанкоТехнологии'; $mail->Body = $body; $mail->isHTML(false);
    if ($attachment) { $mail->addAttachment($attachment['path'], $attachment['name'], PHPMailer::ENCODING_BASE64, $attachment['mime']); }
    if (!$mail->send()) { throw new RuntimeException('SMTP rejected message'); }
    $_SESSION['sent_hash'] = $signature; $_SESSION['sent_time'] = time();
    respond(200, 'Заявка передана почтовому серверу. Спасибо!');
} catch (Throwable $e) {
    // Не пишем SMTP-лог с адресами, содержимым заявки или паролем.
    error_log('StankoTech contact: SMTP submission failed; check mail configuration and server logs.');
    respond(502, 'Почтовый сервер не подтвердил отправку. Позвоните +375 29 290 58 63 или напишите на info@stankotech.by.');
}
