<?php
declare(strict_types=1);
ini_set('session.save_path', '/tmp');
session_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');

function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function money(int|string $value): string { return number_format((float)$value / 1_000_000_000, 2, ',', '.') . ' tỷ'; }
function url(string $page, array $params = []): string { return '/' . ($page === 'home' ? '' : $page) . ($params ? '?' . http_build_query($params) : ''); }
function text_len(string $value): int { return preg_match_all('/./us', $value, $matches) ?: 0; }

$pdo = null; $dbError = null; $errors = []; $notice = '';
try {
    $dsn = 'mysql:host=' . (getenv('DB_HOST') ?: 'db') . ';dbname=' . (getenv('DB_NAME') ?: 'batdongsan') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, getenv('DB_USER') ?: 'bds_app', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (Throwable $e) { http_response_code(503); $dbError = 'Chưa kết nối được cơ sở dữ liệu. Vui lòng thử lại sau.'; error_log('Database unavailable: ' . $e->getMessage()); }
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

$page = (string)($_GET['page'] ?? 'home');
if (!in_array($page, ['home', 'listings', 'property', 'contact', 'post'], true)) { http_response_code(404); $page = '404'; }
$types = ['Nhà bán', 'Căn hộ', 'Đất nền'];
$q = trim((string)($_GET['q'] ?? ''));
$type = (string)($_GET['type'] ?? '');
$maxPrice = filter_var($_GET['max_price'] ?? '', FILTER_VALIDATE_INT) ?: null;
$property = null; $properties = [];

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $errors[] = 'Phiên biểu mẫu hết hạn. Vui lòng tải lại trang.';
    elseif (($_POST['form_action'] ?? '') === 'contact') {
        $name = trim((string)($_POST['name'] ?? '')); $email = trim((string)($_POST['email'] ?? '')); $phone = trim((string)($_POST['phone'] ?? '')); $message = trim((string)($_POST['message'] ?? ''));
        $propertyId = filter_var($_POST['property_id'] ?? '', FILTER_VALIDATE_INT) ?: null;
        if ($name === '' || text_len($name) > 100) $errors[] = 'Vui lòng nhập họ tên tối đa 100 ký tự.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $errors[] = 'Email chưa đúng định dạng.';
        if ($phone === '' || text_len($phone) > 32) $errors[] = 'Vui lòng nhập số điện thoại tối đa 32 ký tự.';
        if ($message === '' || text_len($message) > 2000) $errors[] = 'Lời nhắn cần có từ 1 đến 2000 ký tự.';
        if ($propertyId) { $check=$pdo->prepare('SELECT id FROM properties WHERE id=:id'); $check->execute(['id'=>$propertyId]); if (!$check->fetch()) $errors[]='Tin bất động sản bạn chọn không còn tồn tại.'; }
        if (!$errors) { $stmt = $pdo->prepare('INSERT INTO contacts (property_id,name,email,phone,message) VALUES (:pid,:name,:email,:phone,:message)'); $stmt->execute(['pid'=>$propertyId,'name'=>$name,'email'=>$email,'phone'=>$phone,'message'=>$message]); $notice = 'Cảm ơn bạn! Yêu cầu liên hệ đã được lưu.'; $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    } elseif (($_POST['form_action'] ?? '') === 'post') {
        $title = trim((string)($_POST['title'] ?? '')); $description = trim((string)($_POST['description'] ?? '')); $address = trim((string)($_POST['address'] ?? '')); $kind = (string)($_POST['property_type'] ?? ''); $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_INT); $area = filter_var($_POST['area'] ?? '', FILTER_VALIDATE_FLOAT); $image = trim((string)($_POST['image_url'] ?? ''));
        if ($title === '' || text_len($title) > 180) $errors[] = 'Tiêu đề cần có từ 1 đến 180 ký tự.';
        if (!in_array($kind, $types, true)) $errors[] = 'Vui lòng chọn loại bất động sản.';
        if (!$price || $price < 1 || $price > 999999999999) $errors[] = 'Giá bán chưa hợp lệ (nhập bằng đồng).';
        if (!$area || $area <= 0 || $area > 999999) $errors[] = 'Diện tích chưa hợp lệ.';
        if ($address === '' || text_len($address) > 240) $errors[] = 'Địa chỉ cần có từ 1 đến 240 ký tự.';
        if (text_len($description) < 20 || text_len($description) > 5000) $errors[] = 'Mô tả cần có từ 20 đến 5000 ký tự.';
        if ($image !== '' && (!filter_var($image, FILTER_VALIDATE_URL) || !str_starts_with($image, 'https://') || text_len($image) > 500)) $errors[] = 'Ảnh cần là URL HTTPS hợp lệ.';
        if (!$errors) { $stmt = $pdo->prepare('INSERT INTO properties (title,description,property_type,price,area,address,image_url) VALUES (:title,:description,:type,:price,:area,:address,:image)'); $stmt->execute(['title'=>$title,'description'=>$description,'type'=>$kind,'price'=>$price,'area'=>$area,'address'=>$address,'image'=>$image]); header('Location: /property?id=' . $pdo->lastInsertId()); exit; }
    }
}
if ($pdo) {
    if ($page === 'property') { $stmt = $pdo->prepare('SELECT * FROM properties WHERE id=:id'); $stmt->execute(['id'=>filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT) ?: 0]); $property = $stmt->fetch() ?: null; if (!$property) { http_response_code(404); $page='404'; } }
    if (in_array($page, ['home','listings'], true)) {
        $sql = 'SELECT * FROM properties WHERE 1=1'; $params=[];
        if ($q !== '') { $sql .= ' AND (title LIKE :q OR address LIKE :q OR description LIKE :q)'; $params['q']='%'.$q.'%'; }
        if (in_array($type,$types,true)) { $sql .= ' AND property_type=:type'; $params['type']=$type; }
        if ($maxPrice) { $sql .= ' AND price<=:price'; $params['price']=$maxPrice; }
        $sql .= ' ORDER BY created_at DESC LIMIT ' . ($page === 'home' && $q === '' && !$type && !$maxPrice ? '6' : '60');
        $stmt=$pdo->prepare($sql); $stmt->execute($params); $properties=$stmt->fetchAll();
    }
}
$titles = ['home'=>'Nhà Tốt — Tìm tổ ấm của bạn','listings'=>'Khám phá bất động sản','property'=>'Chi tiết bất động sản','contact'=>'Liên hệ Nhà Tốt','post'=>'Đăng tin bất động sản','404'=>'Không tìm thấy trang'];
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Tìm kiếm nhà bán, căn hộ và đất nền; liên hệ trực tiếp với Nhà Tốt."><title><?=h($titles[$page])?></title><link rel="stylesheet" href="/style.css"></head><body>
<header class="topbar"><a class="brand" href="/"><span class="brand-mark">N</span><span>nhà<span class="brand-accent">tốt</span></span></a><nav><a href="/listings">Khám phá</a><a href="/contact">Liên hệ</a><a href="/admin">Quản trị</a></nav><a class="post-link" href="/post">Đăng tin miễn phí <span>↗</span></a></header><main>
<?php if ($dbError): ?><div class="notice error page-notice"><?=h($dbError)?></div><?php endif; ?>
<?php if ($page === 'home'): ?>
<section class="hero"><div class="hero-copy"><div class="eyebrow"><span class="pulse"></span> NỀN TẢNG BẤT ĐỘNG SẢN CỦA BẠN</div><h1>Tìm nơi gọi là<br><em>nhà.</em></h1><p>Từ căn hộ đầu tiên đến mảnh đất mơ ước. Hành trình của bạn bắt đầu ở đây.</p><a class="hero-cta" href="/listings">Khám phá bất động sản <span>↓</span></a><div class="hero-note"><span><b><?=count($properties)?>+</b> bất động sản đang được giới thiệu</span></div></div><div class="hero-art"><div class="hero-image"></div><div class="floating-card"><span class="card-icon">⌂</span><span><b>Ngôi nhà phù hợp đang chờ</b><small>Khám phá danh sách mới nhất</small></span></div></div></section>
<?php endif; ?>
<?php if (in_array($page,['home','listings'],true)): ?>
<section class="search-wrap"><form class="search-panel" method="get" action="/listings"><label class="search-field"><span>⌕</span><span class="field-copy"><small>BẠN ĐANG TÌM GÌ?</small><input name="q" value="<?=h($q)?>" placeholder="Khu vực, tên dự án..."></span></label><label class="search-field select-field"><span>⌂</span><span class="field-copy"><small>LOẠI BẤT ĐỘNG SẢN</small><select name="type"><option value="">Tất cả loại hình</option><?php foreach($types as $t): ?><option value="<?=h($t)?>" <?=$type===$t?'selected':''?>><?=h($t)?></option><?php endforeach; ?></select></span></label><label class="search-field price-field"><span>◷</span><span class="field-copy"><small>NGÂN SÁCH TỐI ĐA</small><select name="max_price"><option value="">Không giới hạn</option><?php foreach([2000000000=>'2 tỷ',3000000000=>'3 tỷ',5000000000=>'5 tỷ',10000000000=>'10 tỷ'] as $v=>$label): ?><option value="<?=$v?>" <?=$maxPrice===$v?'selected':''?>><?=$label?></option><?php endforeach; ?></select></span></label><button class="search-button" type="submit">Tìm kiếm <span>↗</span></button></form></section>
<section class="listings section" id="tin-moi"><div class="section-heading"><div><div class="eyebrow"><?=$page==='home'?'ĐƯỢC CHỌN RIÊNG CHO BẠN':'TÌM KIẾM BẤT ĐỘNG SẢN'?></div><h2><?=$page==='home'?'Những nơi đáng ':'Danh sách ' ?><em><?=$page==='home'?'để về.':'bất động sản.'?></em></h2></div><?php if($page==='home'): ?><a class="text-link" href="/listings">Xem tất cả <span>↗</span></a><?php endif; ?></div>
<?php if($notice): ?><div class="notice success"><?=h($notice)?></div><?php endif; ?><?php foreach($errors as $error): ?><div class="notice error"><?=h($error)?></div><?php endforeach; ?>
<div class="property-grid"><?php foreach($properties as $p): ?><article class="property-card"><a class="property-image" href="/property?id=<?=h($p['id'])?>"><img src="<?=h($p['image_url'] ?: '/no-image.svg')?>" alt="<?=h($p['title'])?>" loading="lazy"><span class="property-tag"><?=h($p['property_type'])?></span></a><div class="property-body"><div class="property-price"><?=h(money($p['price']))?><small> · <?=h($p['area'])?> m²</small></div><h3><a href="/property?id=<?=h($p['id'])?>"><?=h($p['title'])?></a></h3><p class="property-address">⌖ <?=h($p['address'])?></p><div class="property-footer"><span><?=h($p['area'])?> m² · <?=h($p['property_type'])?></span><a href="/property?id=<?=h($p['id'])?>" aria-label="Xem <?=h($p['title'])?>">↗</a></div></div></article><?php endforeach; ?><?php if(!$properties): ?><div class="empty-state">Chưa tìm thấy tin phù hợp. Hãy thử thay đổi điều kiện tìm kiếm.</div><?php endif; ?></div></section>
<?php endif; ?>
<?php if ($page === 'property' && $property): ?><section class="detail section"><a class="text-link" href="/listings">← Quay lại danh sách</a><div class="detail-layout"><div><img class="detail-image" src="<?=h($property['image_url'] ?: '/no-image.svg')?>" alt="<?=h($property['title'])?>"></div><div class="detail-info"><span class="property-tag static-tag"><?=h($property['property_type'])?></span><h1><?=h($property['title'])?></h1><p class="property-address">⌖ <?=h($property['address'])?></p><div class="property-price"><?=h(money($property['price']))?></div><p>Diện tích: <b><?=h($property['area'])?> m²</b></p><h2>Mô tả</h2><p><?=nl2br(h($property['description']))?></p><a class="hero-cta" href="/contact?property_id=<?=h($property['id'])?>">Liên hệ về tin này ↗</a></div></div></section><?php endif; ?>
<?php if ($page === 'contact' || ($page === 'property' && $property)): ?><section class="contact-band" id="lien-he"><div class="contact-intro"><div class="eyebrow">CHÚNG TÔI SẴN SÀNG HỖ TRỢ</div><h2>Hãy để chúng tôi<br><em>giúp bạn.</em></h2><p>Gửi thông tin, đội ngũ Nhà Tốt sẽ liên hệ để hỗ trợ bạn về bất động sản.</p><div class="contact-detail"><span>✳</span><div><b>Liên hệ tư vấn</b><small>Thông tin được lưu an toàn trong hệ thống.</small></div></div></div><form class="contact-form" method="post" action="/contact"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="form_action" value="contact"><input type="hidden" name="property_id" value="<?=h($_GET['property_id'] ?? ($property['id'] ?? ''))?>"><?php if($notice): ?><div class="notice success"><?=h($notice)?></div><?php endif; ?><?php foreach($errors as $error): ?><div class="notice error"><?=h($error)?></div><?php endforeach; ?><label>HỌ VÀ TÊN<input name="name" required maxlength="100" placeholder="Tên của bạn"></label><div class="form-row"><label>EMAIL<input name="email" type="email" required maxlength="190" placeholder="ban@email.com"></label><label>SỐ ĐIỆN THOẠI<input name="phone" required maxlength="32" placeholder="09xx xxx xxx"></label></div><label>LỜI NHẮN<textarea name="message" required maxlength="2000" rows="4" placeholder="Tôi đang quan tâm đến..."></textarea></label><button type="submit" class="search-button">Gửi lời nhắn <span>↗</span></button></form></section><?php endif; ?>
<?php if ($page === 'post'): ?><section class="contact-band post-band"><div class="contact-intro"><div class="eyebrow">ĐĂNG TIN MIỄN PHÍ</div><h2>Giới thiệu<br><em>bất động sản.</em></h2><p>Điền thông tin căn nhà, căn hộ hoặc đất nền. Tin đăng được lưu vào cơ sở dữ liệu của website.</p></div><form class="contact-form" method="post" action="/post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="form_action" value="post"><?php foreach($errors as $error): ?><div class="notice error"><?=h($error)?></div><?php endforeach; ?><label>TIÊU ĐỀ<input name="title" required maxlength="180"></label><label>LOẠI BẤT ĐỘNG SẢN<select name="property_type" required><?php foreach($types as $t): ?><option value="<?=h($t)?>"><?=h($t)?></option><?php endforeach; ?></select></label><div class="form-row"><label>GIÁ (VNĐ)<input name="price" type="number" min="1" required></label><label>DIỆN TÍCH (M²)<input name="area" type="number" min="1" step="0.01" required></label></div><label>ĐỊA CHỈ<input name="address" maxlength="240" required></label><label>MÔ TẢ<textarea name="description" maxlength="5000" rows="4" required></textarea></label><label>URL ẢNH HTTPS (KHÔNG BẮT BUỘC)<input name="image_url" type="url" maxlength="500" placeholder="https://..."></label><button class="search-button" type="submit">Đăng tin</button></form></section><?php endif; ?>
<?php if ($page === '404'): ?><section class="section"><h1>Không tìm thấy trang</h1><p>Đường dẫn có thể không tồn tại hoặc tin đăng đã bị gỡ.</p><a class="hero-cta" href="/">Về trang chủ</a></section><?php endif; ?>
<section class="about-strip"><span>NHÀ TỐT © 2026</span><span>Đề tài thực hành · Đề 25 · Website bất động sản</span><a href="/contact">LIÊN HỆ ↗</a></section></main><footer><a class="brand" href="/"><span class="brand-mark">N</span><span>nhà<span class="brand-accent">tốt</span></span></a><span>Website bất động sản · Nhà bán, căn hộ, đất nền</span><a href="#">Lên đầu trang ↑</a></footer><script src="/app.js" defer></script></body></html>
