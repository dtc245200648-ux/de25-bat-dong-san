<?php
declare(strict_types=1);
ini_set('session.save_path', '/tmp');
session_set_cookie_params(['httponly' => true, 'secure' => true, 'samesite' => 'Lax']);
session_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');

function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function text_len(string $value): int { return preg_match_all('/./us', $value, $matches) ?: 0; }
function money(int|string $value): string { return number_format((float)$value / 1_000_000_000, 2, ',', '.') . ' tỷ'; }

$adminUser = getenv('ADMIN_USER') ?: '';
$adminPassword = getenv('ADMIN_PASSWORD') ?: '';
$errors = [];
$notice = '';
$pdo = null;
try {
    $dsn = 'mysql:host=' . (getenv('DB_HOST') ?: 'db') . ';dbname=' . (getenv('DB_NAME') ?: 'batdongsan') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, getenv('DB_USER') ?: 'bds_app', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (Throwable $e) { http_response_code(503); error_log('Admin database unavailable: ' . $e->getMessage()); $errors[] = 'Không kết nối được cơ sở dữ liệu.'; }
if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
$loggedIn = ($_SESSION['is_admin'] ?? false) === true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['admin_csrf'], (string)($_POST['csrf'] ?? ''))) $errors[] = 'Phiên làm việc hết hạn. Hãy tải lại trang.';
    else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'login') {
            $lockedUntil = (int)($_SESSION['admin_locked_until'] ?? 0);
            if ($lockedUntil > time()) $errors[] = 'Đăng nhập tạm khóa. Vui lòng thử lại sau một phút.';
            elseif ($adminUser !== '' && $adminPassword !== '' && hash_equals($adminUser, (string)($_POST['username'] ?? '')) && hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
                session_regenerate_id(true); $_SESSION['is_admin'] = true; $_SESSION['admin_failures'] = 0; $_SESSION['admin_csrf'] = bin2hex(random_bytes(32)); header('Location: /admin'); exit;
            } else {
                $_SESSION['admin_failures'] = (int)($_SESSION['admin_failures'] ?? 0) + 1;
                if ($_SESSION['admin_failures'] >= 5) { $_SESSION['admin_failures'] = 0; $_SESSION['admin_locked_until'] = time() + 60; }
                $errors[] = 'Tên đăng nhập hoặc mật khẩu không đúng.';
            }
        } elseif ($action === 'logout') { $_SESSION = []; session_regenerate_id(true); header('Location: /admin'); exit;
        } elseif (!$loggedIn) { http_response_code(403); $errors[] = 'Vui lòng đăng nhập quản trị.';
        } elseif ($pdo && $action === 'delete_property') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            if ($id) { $stmt = $pdo->prepare('DELETE FROM properties WHERE id=:id'); $stmt->execute(['id'=>$id]); $notice = $stmt->rowCount() ? 'Đã xóa tin đăng.' : 'Không tìm thấy tin đăng.'; }
        } elseif ($pdo && $action === 'update_property') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $title=trim((string)($_POST['title']??'')); $description=trim((string)($_POST['description']??'')); $type=(string)($_POST['property_type']??''); $price=filter_var($_POST['price']??'',FILTER_VALIDATE_INT); $area=filter_var($_POST['area']??'',FILTER_VALIDATE_FLOAT); $address=trim((string)($_POST['address']??'')); $image=trim((string)($_POST['image_url']??''));
            if (!$id) $errors[]='Mã tin không hợp lệ.';
            if ($title==='' || text_len($title)>180) $errors[]='Tiêu đề cần có từ 1 đến 180 ký tự.';
            if (!in_array($type,['Nhà bán','Căn hộ','Đất nền'],true)) $errors[]='Loại bất động sản không hợp lệ.';
            if (!$price || $price<1) $errors[]='Giá phải lớn hơn 0.';
            if (!$area || $area<=0) $errors[]='Diện tích phải lớn hơn 0.';
            if ($address==='' || text_len($address)>240) $errors[]='Địa chỉ chưa hợp lệ.';
            if ($description==='' || text_len($description)>5000) $errors[]='Mô tả cần có từ 1 đến 5000 ký tự.';
            if ($image!=='' && (!filter_var($image,FILTER_VALIDATE_URL) || !str_starts_with($image,'https://') || text_len($image)>500)) $errors[]='Ảnh cần là URL HTTPS hợp lệ.';
            if (!$errors) { $stmt=$pdo->prepare('UPDATE properties SET title=:title,description=:description,property_type=:type,price=:price,area=:area,address=:address,image_url=:image WHERE id=:id'); $stmt->execute(['title'=>$title,'description'=>$description,'type'=>$type,'price'=>$price,'area'=>$area,'address'=>$address,'image'=>$image,'id'=>$id]); $notice='Đã cập nhật tin đăng.'; }
        }
    }
}
$loggedIn = ($_SESSION['is_admin'] ?? false) === true;
$editProperty = null; $properties = []; $contacts = [];
if ($pdo && $loggedIn) {
    $editId=filter_var($_GET['edit']??'',FILTER_VALIDATE_INT);
    if ($editId) { $stmt=$pdo->prepare('SELECT * FROM properties WHERE id=:id'); $stmt->execute(['id'=>$editId]); $editProperty=$stmt->fetch()?:null; }
    $properties=$pdo->query('SELECT id,title,property_type,price,address,created_at FROM properties ORDER BY created_at DESC LIMIT 100')->fetchAll();
    $contacts=$pdo->query('SELECT c.id,c.name,c.email,c.phone,c.message,c.created_at,p.id AS property_id,p.title AS property_title FROM contacts c LEFT JOIN properties p ON p.id=c.property_id ORDER BY c.created_at DESC LIMIT 100')->fetchAll();
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Quản trị Nhà Tốt</title><link rel="stylesheet" href="/style.css"></head><body>
<header class="topbar"><a class="brand" href="/"><span class="brand-mark">N</span><span>nhà<span class="brand-accent">tốt</span></span></a><nav><a href="/">Xem website</a><a href="/listings">Danh sách tin</a></nav><?php if($loggedIn): ?><form method="post" action="/admin"><input type="hidden" name="csrf" value="<?=h($_SESSION['admin_csrf'])?>"><input type="hidden" name="action" value="logout"><button class="post-link admin-logout" type="submit">Đăng xuất</button></form><?php endif; ?></header>
<?php if(!$loggedIn): ?><main class="admin-login"><div class="eyebrow">KHU VỰC QUẢN TRỊ</div><h1>Đăng nhập quản trị</h1><p>Quản lý tin bất động sản và các yêu cầu liên hệ.</p><?php if(!$adminPassword): ?><div class="notice error">Chưa cấu hình ADMIN_PASSWORD trong file .env. Hãy đặt mật khẩu quản trị mạnh rồi khởi động lại dịch vụ web.</div><?php endif; ?><?php foreach($errors as $error): ?><div class="notice error"><?=h($error)?></div><?php endforeach; ?><form class="contact-form" method="post" action="/admin"><input type="hidden" name="csrf" value="<?=h($_SESSION['admin_csrf'])?>"><input type="hidden" name="action" value="login"><label>TÊN ĐĂNG NHẬP<input name="username" autocomplete="username" required maxlength="100"></label><label>MẬT KHẨU<input type="password" name="password" autocomplete="current-password" required></label><button class="search-button" type="submit">Đăng nhập</button></form></main>
<?php else: ?><main class="admin-page"><div class="section-heading"><div><div class="eyebrow">NHÀ TỐT · QUẢN TRỊ</div><h1>Quản lý nội dung</h1></div><a class="hero-cta" href="/post">+ Đăng tin mới</a></div><?php if($notice): ?><div class="notice success"><?=h($notice)?></div><?php endif; ?><?php foreach($errors as $error): ?><div class="notice error"><?=h($error)?></div><?php endforeach; ?>
<?php if($editProperty): ?><section class="admin-edit"><h2>Sửa tin #<?=h($editProperty['id'])?></h2><form class="contact-form" method="post" action="/admin"><input type="hidden" name="csrf" value="<?=h($_SESSION['admin_csrf'])?>"><input type="hidden" name="action" value="update_property"><input type="hidden" name="id" value="<?=h($editProperty['id'])?>"><label>TIÊU ĐỀ<input name="title" required maxlength="180" value="<?=h($editProperty['title'])?>"></label><label>LOẠI BẤT ĐỘNG SẢN<select name="property_type"><?php foreach(['Nhà bán','Căn hộ','Đất nền'] as $kind): ?><option value="<?=h($kind)?>" <?=$editProperty['property_type']===$kind?'selected':''?>><?=h($kind)?></option><?php endforeach; ?></select></label><div class="form-row"><label>GIÁ (VNĐ)<input type="number" name="price" min="1" required value="<?=h($editProperty['price'])?>"></label><label>DIỆN TÍCH (M²)<input type="number" name="area" min="0.01" step="0.01" required value="<?=h($editProperty['area'])?>"></label></div><label>ĐỊA CHỈ<input name="address" maxlength="240" required value="<?=h($editProperty['address'])?>"></label><label>MÔ TẢ<textarea name="description" maxlength="5000" rows="4" required><?=h($editProperty['description'])?></textarea></label><label>URL ẢNH HTTPS<input name="image_url" type="url" maxlength="500" value="<?=h($editProperty['image_url'])?>"></label><div class="admin-actions"><button class="search-button" type="submit">Lưu thay đổi</button><a class="text-link" href="/admin">Hủy</a></div></form></section><?php endif; ?>
<section class="admin-section"><h2>Tin bất động sản <span><?=count($properties)?></span></h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Mã</th><th>Tiêu đề</th><th>Loại</th><th>Giá</th><th>Địa chỉ</th><th>Thao tác</th></tr></thead><tbody><?php foreach($properties as $p): ?><tr><td><?=h($p['id'])?></td><td><?=h($p['title'])?></td><td><?=h($p['property_type'])?></td><td><?=h(money($p['price']))?></td><td><?=h($p['address'])?></td><td class="admin-actions"><a href="/admin?edit=<?=h($p['id'])?>">Sửa</a><a href="/property?id=<?=h($p['id'])?>">Xem</a><form method="post" action="/admin"><input type="hidden" name="csrf" value="<?=h($_SESSION['admin_csrf'])?>"><input type="hidden" name="action" value="delete_property"><input type="hidden" name="id" value="<?=h($p['id'])?>"><button type="submit" class="danger-button">Xóa</button></form></td></tr><?php endforeach; ?><?php if(!$properties): ?><tr><td colspan="6">Chưa có tin đăng.</td></tr><?php endif; ?></tbody></table></div></section>
<section class="admin-section"><h2>Yêu cầu liên hệ <span><?=count($contacts)?></span></h2><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Thời gian</th><th>Người gửi</th><th>Thông tin</th><th>Tin quan tâm</th><th>Lời nhắn</th></tr></thead><tbody><?php foreach($contacts as $c): ?><tr><td><?=h($c['created_at'])?></td><td><?=h($c['name'])?></td><td><a href="mailto:<?=h($c['email'])?>"><?=h($c['email'])?></a><br><?=h($c['phone'])?></td><td><?=$c['property_id']?'<a href="/property?id='.h($c['property_id']).'">#'.h($c['property_id']).' · '.h($c['property_title']).'</a>':'Liên hệ chung'?></td><td class="message-cell"><?=nl2br(h($c['message']))?></td></tr><?php endforeach; ?><?php if(!$contacts): ?><tr><td colspan="5">Chưa có yêu cầu liên hệ.</td></tr><?php endif; ?></tbody></table></div></section></main><?php endif; ?>
<footer><a class="brand" href="/"><span class="brand-mark">N</span><span>nhà<span class="brand-accent">tốt</span></span></a><span>Khu vực quản trị Nhà Tốt</span><a href="#">Lên đầu trang ↑</a></footer></body></html>
