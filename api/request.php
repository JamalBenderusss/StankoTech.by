<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
function respond(int $code, bool $ok, string $message): void {
    http_response_code($code);
    echo json_encode(['ok'=>$ok,'message'=>$message], JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST'); respond(405, false, 'Используйте форму на сайте.');
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 20000) respond(413, false, 'Слишком большой запрос.');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !in_array($origin, ['https://stankotech.by','https://www.stankotech.by'], true)) respond(403, false, 'Откройте форму на stankotech.by.');
function field(string $key, int $max): string {
    $raw=$_POST[$key] ?? '';
    if (!is_string($raw) || strlen($raw)>$max || !preg_match('//u', $raw)) respond(422,false,'Проверьте поля заявки.');
    return trim(str_replace(["\0","\r"], '', $raw));
}
if (field('website', 1000) !== '') respond(422, false, 'Проверьте поля заявки.');
$name=field('name',400); $contact=field('contact',480); $task=field('task',8000);
if ($name==='' || $task==='' || !(filter_var($contact,FILTER_VALIDATE_EMAIL) || preg_match('/^[+\d\s()\-]{7,25}$/D',$contact))) respond(422,false,'Заполните имя, телефон или email и описание задачи.');
$values=['Имя'=>$name,'Контакт'=>$contact,'Оборудование'=>field('equipment',960),'Задача'=>$task,'Материал'=>field('material',600),'Толщина / покрытие'=>field('thickness',600),'Объём работ'=>field('volume',600),'Страница'=>field('page',1000)];
// Fixed recipient and sender; visitor data never controls destination or subject.
$recipient='info@stankotech.by';
// Apply per-IP limiting where PHP permits a locked temporary file.
// Some shared-hosting setups restrict the system temp directory; that must not block a real request.
$ratePath=@sys_get_temp_dir().'/stankotech-form-'.hash('sha256',__DIR__.'|'.($_SERVER['REMOTE_ADDR'] ?? 'unknown')).'.json';
$handle=@fopen($ratePath,'c+');
if ($handle) {
    if (@flock($handle,LOCK_EX)) {
        @chmod($ratePath,0600);
        $times=json_decode(stream_get_contents($handle),true); $now=time();
        $times=array_values(array_filter(is_array($times)?$times:[],static fn($t)=>is_int($t) && $t>$now-600));
        if(count($times)>=5){flock($handle,LOCK_UN);fclose($handle);respond(429,false,'Повторите отправку через 10 минут.');}
        $times[]=$now;rewind($handle);ftruncate($handle,0);fwrite($handle,json_encode($times));fflush($handle);flock($handle,LOCK_UN);
    }
    fclose($handle);
}
$body="Заявка с stankotech.by\n\n";
foreach($values as $label=>$value) if($value!=='') $body.=$label.': '.$value."\n\n";
$headers=['From'=>'StankoTechnologii <info@stankotech.by>','MIME-Version'=>'1.0','Content-Type'=>'text/plain; charset=UTF-8','Content-Transfer-Encoding'=>'base64'];
if(filter_var($contact,FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n]/',$contact)) $headers['Reply-To']=$contact;
$subject='=?UTF-8?B?'.base64_encode('Заявка с сайта СтанкоТехнологии').'?=';
try {$sent=function_exists('mail') && @mail($recipient,$subject,chunk_split(base64_encode($body)),$headers,'-finfo@stankotech.by');}
catch(Throwable $e){$sent=false;}
if(!$sent) respond(503,false,'Почтовая служба не приняла заявку.');
respond(200,true,'Заявка принята для отправки.');
