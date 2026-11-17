<?php
declare(strict_types=1);

$configPath = __DIR__ . '/../config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error'=>'server_not_configured','message'=>'فایل تنظیمات PHP هنوز ساخته نشده است.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$config = require $configPath;
date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');
session_name($config['session_name'] ?? 'avid_admin');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']),'samesite'=>'Lax','path'=>'/']);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function respond(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function db(array $config): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $d = $config['db'];
    $dsn = 'mysql:host='.$d['host'].';dbname='.$d['name'].';charset='.($d['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $d['user'], $d['password'], [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    return $pdo;
}
function inputJson(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}
function adminOnly(): void {
    if (empty($_SESSION['avid_admin'])) respond(401,['error'=>'unauthorized']);
}
function validDate(string $date): bool {
    $d = DateTime::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

try {
    $pdo = db($config);

    if ($method === 'GET' && $action === 'services') {
        respond(200,['services'=>$pdo->query('SELECT id,name,price_irr,duration_minutes FROM services WHERE active=1 ORDER BY sort_order,id')->fetchAll()]);
    }
    if ($method === 'GET' && $action === 'settings') {
        $s = $pdo->query('SELECT opening_time,closing_time,default_duration_minutes,deposit_percent,bank_account_holder,bank_card_number,bank_iban FROM business_settings WHERE id=1')->fetch();
        respond(200,['settings'=>$s]);
    }
    if ($method === 'GET' && $action === 'availability') {
        $date = (string)($_GET['date'] ?? '');
        if (!validDate($date)) respond(422,['error'=>'invalid_date']);
        $q = $pdo->prepare("SELECT start_time,end_time FROM bookings WHERE appointment_date=? AND (status='confirmed' OR (status='pending_transfer_review' AND (hold_expires_at IS NULL OR hold_expires_at>NOW())))");
        $q->execute([$date]);
        respond(200,['busy'=>$q->fetchAll()]);
    }
    if ($method === 'POST' && $action === 'booking') {
        $name = trim((string)($_POST['name'] ?? ''));
        $phone = preg_replace('/\D+/', '', (string)($_POST['phone'] ?? ''));
        $gender = trim((string)($_POST['gender'] ?? ''));
        $date = (string)($_POST['date'] ?? '');
        $time = (string)($_POST['time'] ?? '');
        $ids = json_decode((string)($_POST['service_ids'] ?? '[]'), true);
        if ($name==='' || !preg_match('/^09\d{9}$/',$phone) || !in_array($gender,['خانم','آقا'],true) || !validDate($date) || !preg_match('/^(1[2-9]|20|21):00$/',$time) || !is_array($ids) || !$ids) respond(422,['error'=>'invalid_booking_data']);
        if (empty($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) respond(422,['error'=>'receipt_required']);
        $file = $_FILES['receipt'];
        if ($file['size'] > 5*1024*1024) respond(422,['error'=>'receipt_too_large']);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($extensions[$mime])) respond(422,['error'=>'invalid_receipt_type']);
        $ids = array_values(array_unique(array_filter(array_map('intval',$ids),fn($v)=>$v>0)));
        if (!$ids) respond(422,['error'=>'no_services']);
        $placeholders = implode(',',array_fill(0,count($ids),'?'));
        $sq = $pdo->prepare("SELECT id,name,price_irr,duration_minutes FROM services WHERE active=1 AND id IN ($placeholders)");
        $sq->execute($ids);
        $services = $sq->fetchAll();
        if (count($services)!==count($ids)) respond(422,['error'=>'invalid_services']);
        $settings = $pdo->query('SELECT * FROM business_settings WHERE id=1')->fetch();
        $total = array_sum(array_map(fn($s)=>(int)$s['price_irr'],$services));
        $duration = max((int)$settings['default_duration_minutes'], ...array_map(fn($s)=>(int)$s['duration_minutes'],$services));
        $deposit = (int)round($total*(int)$settings['deposit_percent']/100);
        $start = DateTime::createFromFormat('!Y-m-d H:i',"$date $time");
        $end = clone $start; $end->modify("+$duration minutes");
        $open = new DateTime("$date ".$settings['opening_time']);
        $close = new DateTime("$date ".$settings['closing_time']);
        if ($start < $open || $end > $close) respond(422,['error'=>'outside_business_hours']);
        if ($date < date('Y-m-d')) respond(422,['error'=>'date_in_past']);
        $receiptDir = rtrim((string)$config['receipt_dir'],'/');
        if (!$receiptDir || !is_dir($receiptDir) || !is_writable($receiptDir)) respond(503,['error'=>'receipt_storage_not_ready']);
        $lockName = 'avid_day_'.str_replace('-','',$date);
        $lock = $pdo->prepare('SELECT GET_LOCK(?,10)'); $lock->execute([$lockName]);
        if ((int)$lock->fetchColumn() !== 1) respond(409,['error'=>'booking_busy_try_again']);
        $stored = null;
        try {
            $pdo->beginTransaction();
            $expire = $pdo->prepare("UPDATE bookings SET status='expired' WHERE appointment_date=? AND status='pending_transfer_review' AND hold_expires_at<NOW()");
            $expire->execute([$date]);
            $check = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE appointment_date=? AND (status='confirmed' OR (status='pending_transfer_review' AND (hold_expires_at IS NULL OR hold_expires_at>NOW()))) AND start_time < ? AND end_time > ?");
            $check->execute([$date,$end->format('H:i:s'),$start->format('H:i:s')]);
            if ((int)$check->fetchColumn()>0) { $pdo->rollBack(); respond(409,['error'=>'slot_unavailable']); }
            $code = 'AV-'.strtoupper(bin2hex(random_bytes(5)));
            $hold = (int)$settings['hold_minutes'];
            $ins = $pdo->prepare("INSERT INTO bookings(tracking_code,customer_name,customer_phone,gender,appointment_date,start_time,end_time,duration_minutes,total_irr,deposit_irr,status,hold_expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,'pending_transfer_review',DATE_ADD(NOW(),INTERVAL ? MINUTE))");
            $ins->execute([$code,$name,$phone,$gender,$date,$start->format('H:i:s'),$end->format('H:i:s'),$duration,$total,$deposit,$hold]);
            $bookingId = (int)$pdo->lastInsertId();
            $bi = $pdo->prepare('INSERT INTO booking_services(booking_id,service_id,service_name,price_irr) VALUES(?,?,?,?)');
            foreach($services as $s) $bi->execute([$bookingId,$s['id'],$s['name'],$s['price_irr']]);
            $filename = bin2hex(random_bytes(20)).'.'.$extensions[$mime];
            $stored = $receiptDir.'/'.$filename;
            if (!move_uploaded_file($file['tmp_name'],$stored)) throw new RuntimeException('receipt_move_failed');
            $pi = $pdo->prepare("INSERT INTO payments(booking_id,amount_irr,receipt_path) VALUES(?,?,?)");
            $pi->execute([$bookingId,$deposit,$filename]);
            $pdo->commit();
            respond(201,['ok'=>true,'tracking_code'=>$code,'status'=>'pending_transfer_review','total_irr'=>$total,'deposit_irr'=>$deposit,'message'=>'درخواست رزرو و رسید شما برای بررسی مدیر ارسال شد.']);
        } catch(Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($stored && is_file($stored)) @unlink($stored);
            throw $e;
        } finally {
            $release=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lockName]);
        }
    }
    if ($method === 'POST' && $action === 'admin-login') {
        $data=inputJson(); $hash=(string)($config['admin_password_hash'] ?? '');
        if (!$hash || $hash==='REPLACE_WITH_PASSWORD_HASH' || !password_verify((string)($data['password'] ?? ''),$hash)) respond(401,['error'=>'invalid_credentials']);
        session_regenerate_id(true); $_SESSION['avid_admin']=true;
        respond(200,['ok'=>true]);
    }
    if ($method === 'POST' && $action === 'admin-logout') {
        adminOnly(); $_SESSION=[]; session_destroy(); respond(200,['ok'=>true]);
    }
    if ($method === 'GET' && $action === 'admin-bookings') {
        adminOnly();
        $rows=$pdo->query("SELECT b.id,b.tracking_code,b.customer_name,b.customer_phone,b.gender,b.appointment_date,b.start_time,b.end_time,b.total_irr,b.deposit_irr,b.status,b.created_at,p.id payment_id,p.receipt_path,p.status payment_status FROM bookings b LEFT JOIN payments p ON p.booking_id=b.id ORDER BY b.created_at DESC LIMIT 200")->fetchAll();
        foreach($rows as &$row) { $row['receipt_url']='?action=admin-receipt&id='.(int)$row['payment_id']; }
        respond(200,['bookings'=>$rows]);
    }
    if ($method === 'GET' && $action === 'admin-receipt') {
        adminOnly();
        $id=(int)($_GET['id'] ?? 0);
        $q=$pdo->prepare('SELECT receipt_path FROM payments WHERE id=?'); $q->execute([$id]); $file=$q->fetchColumn();
        if (!$file) respond(404,['error'=>'not_found']);
        $path=rtrim($config['receipt_dir'],'/').'/'.basename((string)$file);
        if (!is_file($path)) respond(404,['error'=>'receipt_missing']);
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
        header('Content-Type: '.$mime); header('Content-Disposition: inline; filename="receipt"'); header('Content-Length: '.filesize($path)); readfile($path); exit;
    }
    if ($method === 'POST' && $action === 'admin-review') {
        adminOnly(); $data=inputJson(); $id=(int)($data['booking_id'] ?? 0); $approve=($data['decision'] ?? '')==='approve';
        if (!$id || !in_array($data['decision'] ?? '',['approve','reject'],true)) respond(422,['error'=>'invalid_review']);
        $pdo->beginTransaction();
        $q=$pdo->prepare('SELECT status FROM bookings WHERE id=? FOR UPDATE'); $q->execute([$id]); $status=$q->fetchColumn();
        if (!$status || $status!=='pending_transfer_review') { $pdo->rollBack(); respond(409,['error'=>'booking_not_reviewable']); }
        $new=$approve?'confirmed':'cancelled'; $payment=$approve?'approved':'rejected';
        $pdo->prepare('UPDATE bookings SET status=?,hold_expires_at=NULL WHERE id=?')->execute([$new,$id]);
        $pdo->prepare('UPDATE payments SET status=?,verified_at=NOW() WHERE booking_id=?')->execute([$payment,$id]);
        $pdo->commit(); respond(200,['ok'=>true,'status'=>$new]);
    }
    respond(404,['error'=>'route_not_found']);
} catch(Throwable $e) {
    error_log('[avid-api] '.$e->getMessage());
    respond(500,['error'=>'server_error','message'=>'خطای داخلی سرور. لاگ هاست را بررسی کنید.']);
}
