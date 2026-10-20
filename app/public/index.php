<?php
declare(strict_types=1);
ini_set('session.save_path', '/tmp');
session_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');

$pdo = null;
$dbError = null;
try {
    $dsn = 'mysql:host=' . (getenv('DB_HOST') ?: 'db') . ';dbname=' . (getenv('DB_NAME') ?: 'batdongsan') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, getenv('DB_USER') ?: 'bds_app', getenv('DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(503);
    $dbError = 'Chưa kết nối được cơ sở dữ liệu. Hãy kiểm tra dịch vụ MySQL.';
    error_log('Database unavailable: ' . $e->getMessage());
}

function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money(int|string $value): string { return number_format((float)$value / 1_000_000_000, 2, ',', '.') . ' tỷ'; }

$errors = [];
$sent = false;
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        $errors[] = 'Phiên gửi biểu mẫu không hợp lệ. Hãy tải lại trang.';
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        $propertyId = filter_var($_POST['property_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
        if ($name === '' || strlen($name) > 400) $errors[] = 'Vui lòng nhập họ tên hợp lệ.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email chưa đúng định dạng.';
        if ($phone === '' || strlen($phone) > 128) $errors[] = 'Vui lòng nhập số điện thoại.';
        if ($message === '' || strlen($message) > 8000) $errors[] = 'Lời nhắn phải có từ 1 đến 2000 ký tự.';
        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO contacts (property_id, name, email, phone, message) VALUES (:pid, :name, :email, :phone, :message)');
            $stmt->execute(['pid' => $propertyId, 'name' => $name, 'email' => $email, 'phone' => $phone, 'message' => $message]);
            $sent = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
    }
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

$types = ['Nhà bán', 'Căn hộ', 'Đất nền'];
$properties = [];
if ($pdo) {
    $sql = 'SELECT * FROM properties WHERE 1=1';
    $params = [];
    $q = trim((string)($_GET['q'] ?? ''));
    $type = (string)($_GET['type'] ?? '');
    $maxPrice = filter_var($_GET['max_price'] ?? '', FILTER_VALIDATE_INT);
    if ($q !== '') { $sql .= ' AND (title LIKE :q OR address LIKE :q)'; $params['q'] = '%' . $q . '%'; }
    if (in_array($type, $types, true)) { $sql .= ' AND property_type = :type'; $params['type'] = $type; }
    if ($maxPrice) { $sql .= ' AND price <= :price'; $params['price'] = $maxPrice; }
    $sql .= ' ORDER BY created_at DESC LIMIT 60';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $properties = $stmt->fetchAll();
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Tìm nhà, căn hộ và đất nền phù hợp tại Nhà Tốt."><title>Nhà Tốt — Tìm tổ ấm của bạn</title><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="/style.css"></head><body>
<header class="topbar"><a class="brand" href="/"><span class="brand-mark">N</span><span>nhà<span class="brand-accent">tốt</span></span></a><nav><a href="#tin-moi">Khám phá</a><a href="#gioi-thieu">Về chúng tôi</a></nav><a class="post-link" href="#lien-he">Đăng tin miễn phí <span>↗</span></a></header>
<main><section class="hero"><div class="hero-copy"><div class="eyebrow"><span class="pulse"></span> NỀN TẢNG BẤT ĐỘNG SẢN CỦA BẠN</div><h1>Tìm nơi gọi là<br><em>nhà.</em></h1><p>Từ căn hộ đầu tiên đến mảnh đất mơ ước. Hành trình của bạn bắt đầu ở đây.</p><a class="hero-cta" href="#tin-moi">Khám phá bất động sản <span>↓</span></a><div class="hero-note"><div class="avatar-stack"><i>A</i><i>M</i><i>L</i></div><span><b>2.400+</b> khách hàng đã tìm thấy tổ ấm</span></div></div><div class="hero-art"><div class="hero-image"></div><div class="hero-stamp"><span>✳</span><small>KHÔNG GIAN<br>CHO BẠN</small></div><div class="floating-card"><span class="card-icon">⌂</span><span><b>Nhà phù hợp đang chờ</b><small>Khám phá danh sách mới nhất</small></span><span class="card-arrow">↗</span></div><div class="image-caption">01 / TÌM KIẾM THEO CÁCH CỦA BẠN</div></div></section>
<section class="search-wrap"><form class="search-panel" method="get" action="/"><label class="search-field"><span>⌕</span><span class="field-copy"><small>BẠN ĐANG TÌM GÌ?</small><input name="q" value="<?=h($_GET['q'] ?? '')?>" placeholder="Khu vực, tên dự án..."></span></label><label class="search-field select-field"><span>⌂</span><span class="field-copy"><small>LOẠI BẤT ĐỘNG SẢN</small><select name="type"><option value="">Tất cả loại hình</option><?php foreach($types as $t):?><option value="<?=h($t)?>" <?=($_GET['type'] ?? '')===$t?'selected':''?>><?=h($t)?></option><?php endforeach;?></select></span></label><label class="search-field price-field"><span>◷</span><span class="field-copy"><small>NGÂN SÁCH TỐI ĐA</small><select name="max_price"><option value="">Không giới hạn</option><?php foreach([2000000000=>'2 tỷ',3000000000=>'3 tỷ',5000000000=>'5 tỷ',10000000000=>'10 tỷ'] as $v=>$l):?><option value="<?=$v?>" <?=($_GET['max_price'] ?? '')==$v?'selected':''?>><?=$l?></option><?php endforeach;?></select></span></label><button class="search-button" type="submit">Tìm kiếm <span>↗</span></button></form></section>
<section class="listings section" id="tin-moi"><div class="section-heading"><div><div class="eyebrow">ĐƯỢC CHỌN RIÊNG CHO BẠN</div><h2>Những nơi đáng <em>để về.</em></h2></div><a class="text-link" href="/?">Xem tất cả <span>↗</span></a></div><?php if($dbError):?><div class="notice error"><?=h($dbError)?></div><?php endif;?><?php if($sent):?><div class="notice success">Cảm ơn bạn! Nhà Tốt đã lưu yêu cầu liên hệ.</div><?php endif;?><?php foreach($errors as $error):?><div class="notice error"><?=h($error)?></div><?php endforeach;?>
<div class="property-grid"><?php foreach($properties as $i=>$p):?><article class="property-card"><a class="property-image" href="#lien-he"><img src="<?=h($p['image_url'])?>" alt="<?=h($p['title'])?>" loading="lazy"><span class="property-tag"><?=h($p['property_type'])?></span><span class="favorite">♡</span></a><div class="property-body"><div class="property-price"><?=h(money($p['price']))?><small> · <?=number_format((float)$p['area'],0)?> m²</small></div><h3><?=h($p['title'])?></h3><p class="property-address"><span>⌖</span> <?=h($p['address'])?></p><div class="property-footer"><span><?=number_format((float)$p['area'],0)?> m² <i>·</i> <?=h($p['property_type'])?></span><a href="#lien-he" aria-label="Liên hệ về <?=h($p['title'])?>">↗</a></div></div></article><?php endforeach;?><?php if(!$dbError && !$properties):?><div class="empty-state">Chưa tìm thấy tin phù hợp. Hãy thử thay đổi điều kiện tìm kiếm.</div><?php endif;?></div></section>
<section class="contact-band" id="lien-he"><div class="contact-intro"><div class="eyebrow">BẠN ĐANG TÌM TỔ ẤM?</div><h2>Hãy để chúng tôi<br><em>giúp bạn.</em></h2><p>Để lại lời nhắn, đội ngũ Nhà Tốt sẽ kết nối bạn với bất động sản phù hợp.</p><div class="contact-detail"><span>✳</span><div><b>Luôn sẵn sàng lắng nghe</b><small>Phản hồi trong giờ hành chính</small></div></div></div><form class="contact-form" method="post" action="#lien-he"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><label>HỌ VÀ TÊN<input name="name" required maxlength="100" placeholder="Tên của bạn"></label><div class="form-row"><label>EMAIL<input name="email" type="email" required maxlength="190" placeholder="ban@email.com"></label><label>SỐ ĐIỆN THOẠI<input name="phone" required maxlength="32" placeholder="09xx xxx xxx"></label></div><label>LỜI NHẮN<textarea name="message" required maxlength="2000" rows="3" placeholder="Tôi đang quan tâm đến..."></textarea></label><label class="visually-hidden">Mã tin<input name="property_id" inputmode="numeric" tabindex="-1" autocomplete="off"></label><button type="submit" class="search-button">Gửi lời nhắn <span>↗</span></button><small class="privacy-note">Thông tin của bạn được bảo mật an toàn.</small></form></section>
<section class="about-strip" id="gioi-thieu"><span>NHÀ TỐT © 2026</span><span>Một hành trình, nhiều điểm đến</span><a href="mailto:hello@nhatot.example">HELLO@NHATOT.EXAMPLE ↗</a></section></main><footer><a class="brand" href="/"><span class="brand-mark">N</span><span>nhà<span class="brand-accent">tốt</span></span></a><span>Đề tài thực hành · Đề 25 · Website bất động sản</span><a href="#">Lên đầu trang ↑</a></footer></body></html>
