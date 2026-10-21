
<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
function q($k, $d='') { return trim($_GET[$k] ?? $d); }
function qi($k, $d=1) { $v=(int)($_GET[$k]??$d); return $v<1?$d:$v; }
function pg($total,$page,$per){ $pages=max(1,(int)ceil($total/$per)); $page=max(1,min($page,$pages)); return [$pages,$page,($page-1)*$per]; }

$col = mysqli_query($con, "SHOW COLUMNS FROM orders LIKE 'order_status'");
if ($col && mysqli_num_rows($col) === 0) {
    mysqli_query($con, "ALTER TABLE orders ADD COLUMN order_status VARCHAR(20) NOT NULL DEFAULT 'Pending'");
}

$has_contact_table = mysqli_num_rows(mysqli_query($con, "SHOW TABLES LIKE 'contact_messages'")) > 0;
$has_user_cart_table = mysqli_num_rows(mysqli_query($con, "SHOW TABLES LIKE 'user_cart'")) > 0;
$has_user_wishlist_table = mysqli_num_rows(mysqli_query($con, "SHOW TABLES LIKE 'user_wishlist'")) > 0;

$flash = $_SESSION['admin_flash'] ?? '';
$flash_type = $_SESSION['admin_flash_type'] ?? 'success';
unset($_SESSION['admin_flash'], $_SESSION['admin_flash_type']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tab = $_POST['tab'] ?? 'dashboard';

    if ($action === 'update_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? 'user';
        $status = $_POST['status'] ?? 'Active';
        if ($id > 0 && in_array($role,['admin','user'],true) && in_array($status,['Active','Inactive'],true)) {
            if (mysqli_query($con, "UPDATE register SET role='$role', status='$status' WHERE id=$id")) $_SESSION['admin_flash']='User updated successfully.';
            else { $_SESSION['admin_flash']='Failed to update user.'; $_SESSION['admin_flash_type']='danger'; }
        }
    }

    if ($action === 'delete_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id > 0 && $id !== (int)$_SESSION['user_id']) {
            if (mysqli_query($con, "DELETE FROM register WHERE id=$id")) $_SESSION['admin_flash']='User deleted successfully.';
            else { $_SESSION['admin_flash']='Failed to delete user.'; $_SESSION['admin_flash_type']='danger'; }
        } else {
            $_SESSION['admin_flash']='You cannot delete your own admin account.';
            $_SESSION['admin_flash_type']='danger';
        }
    }

    if ($action === 'add_product' || $action === 'update_product') {
        $name = mysqli_real_escape_string($con, trim($_POST['name'] ?? ''));
        $brand = mysqli_real_escape_string($con, trim($_POST['brand'] ?? ''));
        $category = mysqli_real_escape_string($con, trim($_POST['category'] ?? ''));
        $description = mysqli_real_escape_string($con, trim($_POST['description'] ?? ''));
        $price = (float)($_POST['price'] ?? 0);
        $old = (float)($_POST['old_price'] ?? 0);
        $image = mysqli_real_escape_string($con, $_POST['existing_image'] ?? '');

        if (!empty($_FILES['image']['name'])) {
            $new = time().'_'.basename($_FILES['image']['name']);
            if (move_uploaded_file($_FILES['image']['tmp_name'], __DIR__.'/img/'.$new)) $image = mysqli_real_escape_string($con, $new);
        }

        if ($name==='' || $brand==='' || $category==='' || $price<=0) {
            $_SESSION['admin_flash']='Please fill required product fields.'; $_SESSION['admin_flash_type']='danger';
        } elseif ($action === 'add_product') {
            $sql="INSERT INTO products (name,brand,category,price,old_price,image,description) VALUES ('$name','$brand','$category',$price,$old,'$image','$description')";
            if (mysqli_query($con,$sql)) $_SESSION['admin_flash']='Product added successfully.';
            else { $_SESSION['admin_flash']='Failed to add product.'; $_SESSION['admin_flash_type']='danger'; }
        } else {
            $pid=(int)($_POST['product_id']??0);
            $sql="UPDATE products SET name='$name',brand='$brand',category='$category',price=$price,old_price=$old,image='$image',description='$description' WHERE id=$pid";
            if (mysqli_query($con,$sql)) $_SESSION['admin_flash']='Product updated successfully.';
            else { $_SESSION['admin_flash']='Failed to update product.'; $_SESSION['admin_flash_type']='danger'; }
        }
    }
    if ($action === 'delete_product') {
        $id = (int)($_POST['product_id'] ?? 0);
        if ($id > 0) {
            if (mysqli_query($con, "DELETE FROM products WHERE id=$id")) $_SESSION['admin_flash']='Product deleted successfully.';
            else { $_SESSION['admin_flash']='Failed to delete product.'; $_SESSION['admin_flash_type']='danger'; }
        }
    }

    if ($action === 'update_order_status') {
        $id = (int)($_POST['order_id'] ?? 0);
        $st = $_POST['order_status'] ?? 'Pending';
        if ($id > 0 && in_array($st, ['Pending','Shipped','Delivered'], true)) {
            $st = mysqli_real_escape_string($con, $st);
            if (mysqli_query($con, "UPDATE orders SET order_status='$st' WHERE id=$id")) $_SESSION['admin_flash']='Order status updated.';
            else { $_SESSION['admin_flash']='Failed to update order status.'; $_SESSION['admin_flash_type']='danger'; }
        }
    }

    if ($action === 'delete_order') {
        $id = (int)($_POST['order_id'] ?? 0);
        if ($id > 0) {
            if (mysqli_query($con, "DELETE FROM orders WHERE id=$id")) $_SESSION['admin_flash']='Order deleted successfully.';
            else { $_SESSION['admin_flash']='Failed to delete order.'; $_SESSION['admin_flash_type']='danger'; }
        }
    }

    if ($action === 'delete_contact' && $has_contact_table) {
        $id = (int)($_POST['contact_id'] ?? 0);
        if ($id > 0) {
            if (mysqli_query($con, "DELETE FROM contact_messages WHERE id=$id")) $_SESSION['admin_flash']='Contact message deleted successfully.';
            else { $_SESSION['admin_flash']='Failed to delete contact message.'; $_SESSION['admin_flash_type']='danger'; }
        }
    }

    if ($action === 'delete_cart_item' && $has_user_cart_table) {
        $id = (int)($_POST['cart_id'] ?? 0);
        if ($id > 0) {
            if (mysqli_query($con, "DELETE FROM user_cart WHERE id=$id")) $_SESSION['admin_flash']='Cart item deleted successfully.';
            else { $_SESSION['admin_flash']='Failed to delete cart item.'; $_SESSION['admin_flash_type']='danger'; }
        }
    }

    if ($action === 'delete_wishlist_item' && $has_user_wishlist_table) {
        $id = (int)($_POST['wishlist_id'] ?? 0);
        if ($id > 0) {
            if (mysqli_query($con, "DELETE FROM user_wishlist WHERE id=$id")) $_SESSION['admin_flash']='Wishlist item deleted successfully.';
            else { $_SESSION['admin_flash']='Failed to delete wishlist item.'; $_SESSION['admin_flash_type']='danger'; }
        }
    }

    if ($action === 'delete_review') {
        $id = (int)($_POST['review_id'] ?? 0);
        if ($id > 0) {
            if (mysqli_query($con, "DELETE FROM product_reviews WHERE id=$id")) $_SESSION['admin_flash']='Review deleted successfully.';
            else { $_SESSION['admin_flash']='Failed to delete review.'; $_SESSION['admin_flash_type']='danger'; }
        }
    }

    header('Location: admin_dashboard.php?tab='.$tab);
    exit();
}
$active_tab = $_GET['tab'] ?? 'dashboard';
if (!in_array($active_tab, ['dashboard','users','products','orders','contacts','carts','wishlists','reviews'], true)) $active_tab='dashboard';

$edit_product_id = (int)($_GET['edit_product'] ?? 0);
$product_to_edit = null;
if ($edit_product_id > 0) {
    $e = mysqli_query($con, "SELECT * FROM products WHERE id=$edit_product_id");
    if ($e && mysqli_num_rows($e)>0) $product_to_edit = mysqli_fetch_assoc($e);
}

$users_count = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) total FROM register"))['total'] ?? 0);
$active_users_count = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) total FROM register WHERE status='Active'"))['total'] ?? 0);
$products_count = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) total FROM products"))['total'] ?? 0);
$orders_count = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) total FROM orders"))['total'] ?? 0);
$contacts_count = $has_contact_table ? (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) total FROM contact_messages"))['total'] ?? 0) : 0;
$carts_count = $has_user_cart_table ? (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) total FROM user_cart"))['total'] ?? 0) : 0;
$wishlists_count = $has_user_wishlist_table ? (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) total FROM user_wishlist"))['total'] ?? 0) : 0;
$reviews_count = (int)(mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) total FROM product_reviews"))['total'] ?? 0);

$per = 10;

$u_search=q('users_search'); $u_role=q('users_role'); $u_status=q('users_status'); $u_page=qi('users_page');
$uw=" WHERE 1=1 ";
if($u_search!==''){ $s=mysqli_real_escape_string($con,$u_search); $uw.=" AND (name LIKE '%$s%' OR email LIKE '%$s%' OR mobile LIKE '%$s%')"; }
if(in_array($u_role,['user','admin'],true)){ $r=mysqli_real_escape_string($con,$u_role); $uw.=" AND role='$r'"; }
if(in_array($u_status,['Active','Inactive'],true)){ $st=mysqli_real_escape_string($con,$u_status); $uw.=" AND status='$st'"; }
$u_total=(int)(mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) total FROM register $uw"))['total']??0);
[$u_pages,$u_page,$u_off]=pg($u_total,$u_page,$per);
$users_result=mysqli_query($con,"SELECT id,name,email,mobile,role,status,created_at,address,gender,profile_pic,email_verified FROM register $uw ORDER BY id DESC LIMIT $per OFFSET $u_off");

$p_search=q('products_search'); $p_cat=q('products_category'); $p_page=qi('products_page');
$pw=" WHERE 1=1 ";
if($p_search!==''){ $s=mysqli_real_escape_string($con,$p_search); $pw.=" AND (name LIKE '%$s%' OR brand LIKE '%$s%')"; }
if($p_cat!==''){ $c=mysqli_real_escape_string($con,$p_cat); $pw.=" AND category='$c'"; }
$p_total=(int)(mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) total FROM products $pw"))['total']??0);
[$p_pages,$p_page,$p_off]=pg($p_total,$p_page,$per);
$products_result=mysqli_query($con,"SELECT * FROM products $pw ORDER BY id DESC LIMIT $per OFFSET $p_off");
$category_options=[]; $cr=mysqli_query($con,"SELECT DISTINCT category FROM products WHERE category<>'' ORDER BY category");
if($cr){ while($row=mysqli_fetch_assoc($cr)){ $category_options[]=$row['category']; } }

$o_search=q('orders_search'); $o_status=q('orders_status'); $o_page=qi('orders_page');
$ow=" WHERE 1=1 ";
if($o_search!==''){ $s=mysqli_real_escape_string($con,$o_search); $ow.=" AND (r.name LIKE '%$s%' OR p.name LIKE '%$s%' OR o.city LIKE '%$s%')"; }
if(in_array($o_status,['Pending','Shipped','Delivered'],true)){ $st=mysqli_real_escape_string($con,$o_status); $ow.=" AND COALESCE(o.order_status,'Pending')='$st'"; }
$o_total=(int)(mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) total FROM orders o LEFT JOIN register r ON o.user_id=r.id LEFT JOIN products p ON o.product_id=p.id $ow"))['total']??0);
[$o_pages,$o_page,$o_off]=pg($o_total,$o_page,$per);
$orders_result=mysqli_query($con,"SELECT o.*,COALESCE(o.order_status,'Pending') order_status,r.name customer_name,p.name product_name FROM orders o LEFT JOIN register r ON o.user_id=r.id LEFT JOIN products p ON o.product_id=p.id $ow ORDER BY o.created_at DESC LIMIT $per OFFSET $o_off");

$c_search=q('contacts_search'); $c_page=qi('contacts_page');
$cw=" WHERE 1=1 ";
if($c_search!==''){ $s=mysqli_real_escape_string($con,$c_search); $cw.=" AND (name LIKE '%$s%' OR email LIKE '%$s%' OR phone LIKE '%$s%' OR message LIKE '%$s%')"; }
$c_total = 0; $c_pages = 1; $c_off = 0; $contacts_result = false;
if ($has_contact_table) {
    $c_total=(int)(mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) total FROM contact_messages $cw"))['total']??0);
    [$c_pages,$c_page,$c_off]=pg($c_total,$c_page,$per);
    $contacts_result=mysqli_query($con,"SELECT * FROM contact_messages $cw ORDER BY id DESC LIMIT $per OFFSET $c_off");
}

$crt_search=q('carts_search'); $crt_page=qi('carts_page');
$crtw=" WHERE 1=1 ";
if($crt_search!==''){
    $s=mysqli_real_escape_string($con,$crt_search);
    $crtw.=" AND (r.name LIKE '%$s%' OR r.email LIKE '%$s%' OR p.name LIKE '%$s%')";
}
$crt_total = 0; $crt_pages = 1; $crt_off = 0; $carts_result = false;
if ($has_user_cart_table) {
    $crt_total=(int)(mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) total FROM user_cart c LEFT JOIN register r ON c.user_id=r.id LEFT JOIN products p ON c.product_id=p.id $crtw"))['total']??0);
    [$crt_pages,$crt_page,$crt_off]=pg($crt_total,$crt_page,$per);
    $carts_result=mysqli_query($con,"SELECT c.*, r.name user_name, r.email user_email, p.name product_name FROM user_cart c LEFT JOIN register r ON c.user_id=r.id LEFT JOIN products p ON c.product_id=p.id $crtw ORDER BY c.id DESC LIMIT $per OFFSET $crt_off");
}

$wlt_search=q('wishlists_search'); $wlt_page=qi('wishlists_page');
$wltw=" WHERE 1=1 ";
if($wlt_search!==''){
    $s=mysqli_real_escape_string($con,$wlt_search);
    $wltw.=" AND (r.name LIKE '%$s%' OR r.email LIKE '%$s%' OR p.name LIKE '%$s%')";
}
$wlt_total = 0; $wlt_pages = 1; $wlt_off = 0; $wishlists_result = false;
if ($has_user_wishlist_table) {
    $wlt_total=(int)(mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) total FROM user_wishlist w LEFT JOIN register r ON w.user_id=r.id LEFT JOIN products p ON w.product_id=p.id $wltw"))['total']??0);
    [$wlt_pages,$wlt_page,$wlt_off]=pg($wlt_total,$wlt_page,$per);
    $wishlists_result=mysqli_query($con,"SELECT w.*, r.name user_name, r.email user_email, p.name product_name FROM user_wishlist w LEFT JOIN register r ON w.user_id=r.id LEFT JOIN products p ON w.product_id=p.id $wltw ORDER BY w.id DESC LIMIT $per OFFSET $wlt_off");
}

$r_search=q('reviews_search'); $r_rating=q('reviews_rating'); $r_page=qi('reviews_page');
$rw=" WHERE 1=1 ";
if($r_search!==''){ $s=mysqli_real_escape_string($con,$r_search); $rw.=" AND (r.name LIKE '%$s%' OR r.email LIKE '%$s%' OR p.name LIKE '%$s%' OR pr.review_text LIKE '%$s%')"; }
if(in_array($r_rating,['1','2','3','4','5'],true)){ $rt=(int)$r_rating; $rw.=" AND pr.rating=$rt"; }
$r_total=(int)(mysqli_fetch_assoc(mysqli_query($con,"SELECT COUNT(*) total FROM product_reviews pr LEFT JOIN register r ON pr.user_id=r.id LEFT JOIN products p ON pr.product_id=p.id $rw"))['total']??0);
[$r_pages,$r_page,$r_off]=pg($r_total,$r_page,$per);
$reviews_result=mysqli_query($con,"SELECT pr.*, r.name user_name, r.email user_email, p.name product_name FROM product_reviews pr LEFT JOIN register r ON pr.user_id=r.id LEFT JOIN products p ON pr.product_id=p.id $rw ORDER BY pr.created_at DESC LIMIT $per OFFSET $r_off");
?>
<?php include 'includes/admin_header.php'; ?>
<div class="admin-page py-4">
  <div class="container-fluid">
    <div class="row">
      <aside class="col-lg-2 mb-3">
        <div class="admin-card h-100">
          <div class="d-flex align-items-center gap-2 mb-3">
            <img src="uploads/<?= htmlspecialchars($admin_pic); ?>" alt="Admin" class="rounded-circle" style="width:48px;height:48px;object-fit:cover;">
            <div>
              <div class="fw-bold"><?= htmlspecialchars($admin_name); ?></div>
              <small class="text-muted">Administrator</small>
            </div>
          </div>
          <h6 class="mb-2">Manage</h6>
          <div class="list-group list-group-flush admin-sidebar">
            <a class="list-group-item list-group-item-action <?= $active_tab==='dashboard'?'active':''; ?>" href="admin_dashboard.php?tab=dashboard"><i class="fa-solid fa-chart-line me-2"></i>Dashboard</a>
            <a class="list-group-item list-group-item-action <?= $active_tab==='users'?'active':''; ?>" href="admin_dashboard.php?tab=users"><i class="fa-solid fa-users me-2"></i>Users</a>
            <a class="list-group-item list-group-item-action <?= $active_tab==='products'?'active':''; ?>" href="admin_dashboard.php?tab=products"><i class="fa-solid fa-box-open me-2"></i>Products</a>
            <a class="list-group-item list-group-item-action <?= $active_tab==='orders'?'active':''; ?>" href="admin_dashboard.php?tab=orders"><i class="fa-solid fa-truck me-2"></i>Orders</a>
            <a class="list-group-item list-group-item-action <?= $active_tab==='contacts'?'active':''; ?>" href="admin_dashboard.php?tab=contacts"><i class="fa-solid fa-envelope me-2"></i>Contacts</a>
            <a class="list-group-item list-group-item-action <?= $active_tab==='carts'?'active':''; ?>" href="admin_dashboard.php?tab=carts"><i class="fa-solid fa-cart-shopping me-2"></i>Carts</a>
            <a class="list-group-item list-group-item-action <?= $active_tab==='wishlists'?'active':''; ?>" href="admin_dashboard.php?tab=wishlists"><i class="fa-regular fa-heart me-2"></i>Wishlists</a>
            <a class="list-group-item list-group-item-action <?= $active_tab==='reviews'?'active':''; ?>" href="admin_dashboard.php?tab=reviews"><i class="fa-solid fa-star me-2"></i>Reviews</a>
            <div class="border-top my-2"></div>
            <a class="list-group-item list-group-item-action" href="admin_profile.php"><i class="fa-solid fa-id-badge me-2"></i>Profile</a>
            <a class="list-group-item list-group-item-action" href="admin_change_password.php"><i class="fa-solid fa-key me-2"></i>Change Password</a>
            <a class="list-group-item list-group-item-action text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a>
          </div>
          <div class="mt-3 small text-muted">Admin tools only. Shopping disabled.</div>
        </div>
      </aside>

      <main class="col-lg-10">
        <div class="admin-hero mb-4"><h2>Admin Control Panel</h2><p>Manage users, products, orders, carts, wishlists and messages.</p></div>

        <?php if($flash!==''): ?><div class="alert alert-<?= h($flash_type); ?>"><?= h($flash); ?></div><?php endif; ?>

        <div class="row g-3 mb-4">
          <div class="col-md-4 col-lg-2"><div class="admin-stat-card"><span>Total Users</span><strong><?= $users_count; ?></strong></div></div>
          <div class="col-md-4 col-lg-2"><div class="admin-stat-card"><span>Active Users</span><strong><?= $active_users_count; ?></strong></div></div>
          <div class="col-md-4 col-lg-2"><div class="admin-stat-card"><span>Total Products</span><strong><?= $products_count; ?></strong></div></div>
          <div class="col-md-4 col-lg-2"><div class="admin-stat-card"><span>Total Orders</span><strong><?= $orders_count; ?></strong></div></div>
          <div class="col-md-4 col-lg-2"><div class="admin-stat-card"><span>Total Contacts</span><strong><?= $contacts_count; ?></strong></div></div>
          <div class="col-md-4 col-lg-2"><div class="admin-stat-card"><span>Total Carts</span><strong><?= $carts_count; ?></strong></div></div>
          <div class="col-md-4 col-lg-2"><div class="admin-stat-card"><span>Total Wishlists</span><strong><?= $wishlists_count; ?></strong></div></div>
          <div class="col-md-4 col-lg-2"><div class="admin-stat-card"><span>Total Reviews</span><strong><?= $reviews_count; ?></strong></div></div>
        </div>
        <?php if($active_tab==='dashboard'): ?>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="admin-card">
                <h6 class="mb-3">Quick Actions</h6>
                <div class="d-grid gap-2">
                  <a class="btn btn-primary" href="admin_dashboard.php?tab=products">Add / Edit Products</a>
                  <a class="btn btn-outline-primary" href="admin_dashboard.php?tab=orders">Review Orders</a>
                  <a class="btn btn-outline-secondary" href="admin_dashboard.php?tab=users">Manage Users</a>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="admin-card">
                <h6 class="mb-3">Admin Notes</h6>
                <p class="text-muted mb-0">Use the sidebar to navigate management sections. Customer-facing pages and shopping actions are disabled for admin accounts.</p>
              </div>
            </div>
          </div>
        <?php endif; ?>
        <?php if($active_tab==='users'): ?>
          <ul class="nav nav-pills admin-tabs mb-3">
            <li class="nav-item"><a class="nav-link active" href="#">Users</a></li>
          </ul>
          <div class="admin-card mb-3">
            <form method="get" class="admin-toolbar row g-2 align-items-end">
              <input type="hidden" name="tab" value="users">
              <div class="col-md-5"><label class="form-label">Search User</label><input name="users_search" class="form-control" value="<?= h($u_search); ?>" placeholder="Name, email, mobile"></div>
              <div class="col-md-3"><label class="form-label">Role</label><select name="users_role" class="form-select"><option value="">All</option><option value="user" <?= $u_role==='user'?'selected':''; ?>>User</option><option value="admin" <?= $u_role==='admin'?'selected':''; ?>>Admin</option></select></div>
              <div class="col-md-3"><label class="form-label">Status</label><select name="users_status" class="form-select"><option value="">All</option><option value="Active" <?= $u_status==='Active'?'selected':''; ?>>Active</option><option value="Inactive" <?= $u_status==='Inactive'?'selected':''; ?>>Inactive</option></select></div>
              <div class="col-md-1 d-grid"><button class="btn btn-primary">Go</button></div>
            </form>
          </div>
          <div class="admin-card"><div class="table-responsive"><table class="table align-middle admin-table"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Mobile</th><th>Created</th><th>Role/Status</th><th>Actions</th></tr></thead><tbody>
          <?php if($users_result && mysqli_num_rows($users_result)>0): while($u=mysqli_fetch_assoc($users_result)): ?>
            <tr>
              <td><?= (int)$u['id']; ?></td>
              <td><?= h($u['name']); ?></td>
              <td><?= h($u['email']); ?></td>
              <td><?= h($u['mobile']); ?></td>
              <td><?= !empty($u['created_at'])?date('d M Y',strtotime($u['created_at'])):'-'; ?></td>
              <td>
              <form method="post" class="d-flex gap-2 flex-wrap"><input type="hidden" name="action" value="update_user"><input type="hidden" name="tab" value="users"><input type="hidden" name="user_id" value="<?= (int)$u['id']; ?>"><select name="role" class="form-select form-select-sm" style="width:110px;"><option value="user" <?= ($u['role']??'')==='user'?'selected':''; ?>>User</option><option value="admin" <?= ($u['role']??'')==='admin'?'selected':''; ?>>Admin</option></select><select name="status" class="form-select form-select-sm" style="width:120px;"><option value="Active" <?= ($u['status']??'')==='Active'?'selected':''; ?>>Active</option><option value="Inactive" <?= ($u['status']??'')==='Inactive'?'selected':''; ?>>Inactive</option></select><button class="btn btn-sm btn-primary">Save</button></form>
              </td>
              <td>
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <button type="button"
                          class="btn btn-sm btn-outline-secondary view-user-btn"
                          data-id="<?= (int)$u['id']; ?>"
                          data-name="<?= h($u['name']); ?>"
                          data-email="<?= h($u['email']); ?>"
                          data-mobile="<?= h($u['mobile']); ?>"
                          data-role="<?= h($u['role']); ?>"
                          data-status="<?= h($u['status']); ?>"
                          data-created="<?= !empty($u['created_at'])?h(date('d M Y h:i A',strtotime($u['created_at']))):'-'; ?>"
                          data-address="<?= h($u['address'] ?? '-'); ?>"
                          data-gender="<?= h($u['gender'] ?? '-'); ?>"
                          data-profile="<?= h($u['profile_pic'] ?? ''); ?>"
                          data-verified="<?= (int)($u['email_verified'] ?? 0); ?>"
                  >View</button>
                  <form method="post" onsubmit="return confirm('Delete this user?');" class="m-0">
                    <input type="hidden" name="action" value="delete_user"><input type="hidden" name="tab" value="users"><input type="hidden" name="user_id" value="<?= (int)$u['id']; ?>"><button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endwhile; else: ?><tr><td colspan="7" class="text-center">No users found.</td></tr><?php endif; ?>
          </tbody></table></div>
          <?php if($u_pages>1): ?><div class="admin-pagination mt-3"><?php for($i=1;$i<=$u_pages;$i++): ?><a class="<?= $i===$u_page?'active':''; ?>" href="admin_dashboard.php?tab=users&users_page=<?= $i; ?>&users_search=<?= urlencode($u_search); ?>&users_role=<?= urlencode($u_role); ?>&users_status=<?= urlencode($u_status); ?>"><?= $i; ?></a><?php endfor; ?></div><?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if($active_tab==='products'): ?>
          <ul class="nav nav-pills admin-tabs mb-3">
            <li class="nav-item"><a class="nav-link active" href="#">Products</a></li>
          </ul>
          <div class="admin-card mb-3"><h5 class="mb-3"><?= $product_to_edit?'Edit Product':'Add New Product'; ?></h5>
            <form method="post" enctype="multipart/form-data" class="row g-3"><input type="hidden" name="action" value="<?= $product_to_edit?'update_product':'add_product'; ?>"><input type="hidden" name="tab" value="products"><?php if($product_to_edit): ?><input type="hidden" name="product_id" value="<?= (int)$product_to_edit['id']; ?>"><input type="hidden" name="existing_image" value="<?= h($product_to_edit['image']); ?>"><?php endif; ?>
              <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required value="<?= h($product_to_edit['name']??''); ?>"></div>
              <div class="col-md-6"><label class="form-label">Brand</label><input name="brand" class="form-control" required value="<?= h($product_to_edit['brand']??''); ?>"></div>
              <div class="col-md-4"><label class="form-label">Category</label><input name="category" class="form-control" required value="<?= h($product_to_edit['category']??''); ?>"></div>
              <div class="col-md-4"><label class="form-label">Price</label><input type="number" step="0.01" min="0" name="price" class="form-control" required value="<?= h($product_to_edit['price']??''); ?>"></div>
              <div class="col-md-4"><label class="form-label">Old Price</label><input type="number" step="0.01" min="0" name="old_price" class="form-control" value="<?= h($product_to_edit['old_price']??''); ?>"></div>
              <div class="col-md-6"><label class="form-label">Image</label><input type="file" name="image" class="form-control" accept="image/*"></div>
              <div class="col-md-6"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?= h($product_to_edit['description']??''); ?></textarea></div>
              <div class="col-12 d-flex gap-2"><button class="btn btn-primary"><?= $product_to_edit?'Update Product':'Add Product'; ?></button><?php if($product_to_edit): ?><a href="admin_dashboard.php?tab=products" class="btn btn-outline-secondary">Cancel Edit</a><?php endif; ?></div>
            </form>
          </div>
          <div class="admin-card mb-3"><form method="get" class="admin-toolbar row g-2 align-items-end"><input type="hidden" name="tab" value="products"><div class="col-md-6"><label class="form-label">Search Product</label><input name="products_search" class="form-control" value="<?= h($p_search); ?>" placeholder="Name or brand"></div><div class="col-md-5"><label class="form-label">Category</label><select name="products_category" class="form-select"><option value="">All</option><?php foreach($category_options as $cat): ?><option value="<?= h($cat); ?>" <?= $p_cat===$cat?'selected':''; ?>><?= h(ucfirst($cat)); ?></option><?php endforeach; ?></select></div><div class="col-md-1 d-grid"><button class="btn btn-primary">Go</button></div></form></div>
          <div class="admin-card"><div class="table-responsive"><table class="table align-middle admin-table"><thead><tr><th>ID</th><th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Action</th></tr></thead><tbody>
          <?php if($products_result && mysqli_num_rows($products_result)>0): while($p=mysqli_fetch_assoc($products_result)): ?>
            <tr>
              <td><?= (int)$p['id']; ?></td>
              <td><img src="img/<?= h($p['image']); ?>" class="admin-thumb" alt="<?= h($p['name']); ?>"></td>
              <td><?= h($p['name']); ?><br><small class="text-muted"><?= h($p['brand']); ?></small></td>
              <td><?= h($p['category']); ?></td>
              <td>$<?= number_format((float)$p['price'],2); ?></td>
              <td>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                  <a class="btn btn-sm btn-outline-primary" href="admin_dashboard.php?tab=products&edit_product=<?= (int)$p['id']; ?>">Edit</a>
                  <form method="post" class="m-0" onsubmit="return confirm('Delete this product?');">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="tab" value="products">
                    <input type="hidden" name="product_id" value="<?= (int)$p['id']; ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endwhile; else: ?><tr><td colspan="6" class="text-center">No products found.</td></tr><?php endif; ?>
          </tbody></table></div><?php if($p_pages>1): ?><div class="admin-pagination mt-3"><?php for($i=1;$i<=$p_pages;$i++): ?><a class="<?= $i===$p_page?'active':''; ?>" href="admin_dashboard.php?tab=products&products_page=<?= $i; ?>&products_search=<?= urlencode($p_search); ?>&products_category=<?= urlencode($p_cat); ?>"><?= $i; ?></a><?php endfor; ?></div><?php endif; ?></div>
        <?php endif; ?>
        <?php if($active_tab==='orders'): ?>
          <ul class="nav nav-pills admin-tabs mb-3">
            <li class="nav-item"><a class="nav-link active" href="#">Orders</a></li>
          </ul>
          <div class="admin-card mb-3"><form method="get" class="admin-toolbar row g-2 align-items-end"><input type="hidden" name="tab" value="orders"><div class="col-md-7"><label class="form-label">Search Order</label><input name="orders_search" class="form-control" value="<?= h($o_search); ?>" placeholder="Customer, product or city"></div><div class="col-md-4"><label class="form-label">Order Status</label><select name="orders_status" class="form-select"><option value="">All</option><option value="Pending" <?= $o_status==='Pending'?'selected':''; ?>>Pending</option><option value="Shipped" <?= $o_status==='Shipped'?'selected':''; ?>>Shipped</option><option value="Delivered" <?= $o_status==='Delivered'?'selected':''; ?>>Delivered</option></select></div><div class="col-md-1 d-grid"><button class="btn btn-primary">Go</button></div></form></div>
          <div class="admin-card"><div class="table-responsive"><table class="table align-middle admin-table"><thead><tr><th>ID</th><th>Customer</th><th>Product</th><th>Qty</th><th>Total</th><th>Status</th><th>Address</th><th>Date</th><th>Action</th></tr></thead><tbody>
          <?php if($orders_result && mysqli_num_rows($orders_result)>0): while($o=mysqli_fetch_assoc($orders_result)): ?>
            <tr><td><?= (int)$o['id']; ?></td><td><?= h($o['customer_name']??'Unknown'); ?></td><td><?= h($o['product_name']??'Deleted Product'); ?></td><td><?= (int)$o['quantity']; ?></td><td>$<?= number_format((float)$o['total_price'],2); ?></td><td><form method="post" class="d-flex gap-2 align-items-center"><input type="hidden" name="action" value="update_order_status"><input type="hidden" name="tab" value="orders"><input type="hidden" name="order_id" value="<?= (int)$o['id']; ?>"><select name="order_status" class="form-select form-select-sm" style="width:130px;"><option value="Pending" <?= $o['order_status']==='Pending'?'selected':''; ?>>Pending</option><option value="Shipped" <?= $o['order_status']==='Shipped'?'selected':''; ?>>Shipped</option><option value="Delivered" <?= $o['order_status']==='Delivered'?'selected':''; ?>>Delivered</option></select><button class="btn btn-sm btn-primary">Save</button></form></td><td><?= h($o['address']); ?>, <?= h($o['city']); ?>, <?= h($o['state']); ?> - <?= h($o['zip']); ?></td><td><?= date('d M Y',strtotime($o['created_at'])); ?></td><td><form method="post" onsubmit="return confirm('Delete this order record?');"><input type="hidden" name="action" value="delete_order"><input type="hidden" name="tab" value="orders"><input type="hidden" name="order_id" value="<?= (int)$o['id']; ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
          <?php endwhile; else: ?><tr><td colspan="9" class="text-center">No orders found.</td></tr><?php endif; ?>
          </tbody></table></div><?php if($o_pages>1): ?><div class="admin-pagination mt-3"><?php for($i=1;$i<=$o_pages;$i++): ?><a class="<?= $i===$o_page?'active':''; ?>" href="admin_dashboard.php?tab=orders&orders_page=<?= $i; ?>&orders_search=<?= urlencode($o_search); ?>&orders_status=<?= urlencode($o_status); ?>"><?= $i; ?></a><?php endfor; ?></div><?php endif; ?></div>
        <?php endif; ?>
        <?php if($active_tab==='contacts'): ?>
          <ul class="nav nav-pills admin-tabs mb-3">
            <li class="nav-item"><a class="nav-link active" href="#">Contacts</a></li>
          </ul>
          <?php if(!$has_contact_table): ?>
            <div class="admin-card">
              <div class="alert alert-warning mb-0">`contact_messages` table not found. Create it first, then contacts will appear here.</div>
            </div>
          <?php else: ?>
          <div class="admin-card mb-3">
            <form method="get" class="admin-toolbar row g-2 align-items-end">
              <input type="hidden" name="tab" value="contacts">
              <div class="col-md-11"><label class="form-label">Search Contact Message</label><input name="contacts_search" class="form-control" value="<?= h($c_search); ?>" placeholder="Name, email, phone, message"></div>
              <div class="col-md-1 d-grid"><button class="btn btn-primary">Go</button></div>
            </form>
          </div>
          <div class="admin-card"><div class="table-responsive"><table class="table align-middle admin-table"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Message</th><th>Date</th><th>Action</th></tr></thead><tbody>
          <?php if($contacts_result && mysqli_num_rows($contacts_result)>0): while($c=mysqli_fetch_assoc($contacts_result)): ?>
            <tr><td><?= (int)$c['id']; ?></td><td><?= h($c['name']); ?></td><td><?= h($c['email']); ?></td><td><?= h($c['phone']); ?></td><td style="min-width:260px;"><?= nl2br(h($c['message'])); ?></td><td><?= !empty($c['created_at'])?date('d M Y h:i A',strtotime($c['created_at'])):'-'; ?></td><td><form method="post" onsubmit="return confirm('Delete this contact message?');"><input type="hidden" name="action" value="delete_contact"><input type="hidden" name="tab" value="contacts"><input type="hidden" name="contact_id" value="<?= (int)$c['id']; ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
          <?php endwhile; else: ?><tr><td colspan="7" class="text-center">No contact messages found.</td></tr><?php endif; ?>
          </tbody></table></div><?php if($c_pages>1): ?><div class="admin-pagination mt-3"><?php for($i=1;$i<=$c_pages;$i++): ?><a class="<?= $i===$c_page?'active':''; ?>" href="admin_dashboard.php?tab=contacts&contacts_page=<?= $i; ?>&contacts_search=<?= urlencode($c_search); ?>"><?= $i; ?></a><?php endfor; ?></div><?php endif; ?></div>
          <?php endif; ?>
        <?php endif; ?>
        <?php if($active_tab==='carts'): ?>
          <ul class="nav nav-pills admin-tabs mb-3">
            <li class="nav-item"><a class="nav-link active" href="#">Carts</a></li>
          </ul>
          <?php if(!$has_user_cart_table): ?>
            <div class="admin-card">
              <div class="alert alert-warning mb-0">`user_cart` table not found. Create it first, then carts will appear here.</div>
            </div>
          <?php else: ?>
          <div class="admin-card mb-3">
            <form method="get" class="admin-toolbar row g-2 align-items-end">
              <input type="hidden" name="tab" value="carts">
              <div class="col-md-11"><label class="form-label">Search Cart Items</label><input name="carts_search" class="form-control" value="<?= h($crt_search); ?>" placeholder="User name, email, product"></div>
              <div class="col-md-1 d-grid"><button class="btn btn-primary">Go</button></div>
            </form>
          </div>
          <div class="admin-card"><div class="table-responsive"><table class="table align-middle admin-table"><thead><tr><th>ID</th><th>User</th><th>Email</th><th>Product</th><th>Qty</th><th>Added</th><th>Action</th></tr></thead><tbody>
          <?php if($carts_result && mysqli_num_rows($carts_result)>0): while($cart=mysqli_fetch_assoc($carts_result)): ?>
            <tr><td><?= (int)$cart['id']; ?></td><td><?= h($cart['user_name']??'Unknown'); ?></td><td><?= h($cart['user_email']??'-'); ?></td><td><?= h($cart['product_name']??'Deleted Product'); ?></td><td><?= (int)$cart['quantity']; ?></td><td><?= !empty($cart['created_at'])?date('d M Y h:i A',strtotime($cart['created_at'])):'-'; ?></td><td><form method="post" onsubmit="return confirm('Delete this cart item?');"><input type="hidden" name="action" value="delete_cart_item"><input type="hidden" name="tab" value="carts"><input type="hidden" name="cart_id" value="<?= (int)$cart['id']; ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
          <?php endwhile; else: ?><tr><td colspan="7" class="text-center">No cart items found.</td></tr><?php endif; ?>
          </tbody></table></div><?php if($crt_pages>1): ?><div class="admin-pagination mt-3"><?php for($i=1;$i<=$crt_pages;$i++): ?><a class="<?= $i===$crt_page?'active':''; ?>" href="admin_dashboard.php?tab=carts&carts_page=<?= $i; ?>&carts_search=<?= urlencode($crt_search); ?>"><?= $i; ?></a><?php endfor; ?></div><?php endif; ?></div>
          <?php endif; ?>
        <?php endif; ?>
        <?php if($active_tab==='wishlists'): ?>
          <ul class="nav nav-pills admin-tabs mb-3">
            <li class="nav-item"><a class="nav-link active" href="#">Wishlists</a></li>
          </ul>
          <?php if(!$has_user_wishlist_table): ?>
            <div class="admin-card">
              <div class="alert alert-warning mb-0">`user_wishlist` table not found. Create it first, then wishlists will appear here.</div>
            </div>
          <?php else: ?>
          <div class="admin-card mb-3">
            <form method="get" class="admin-toolbar row g-2 align-items-end">
              <input type="hidden" name="tab" value="wishlists">
              <div class="col-md-11"><label class="form-label">Search Wishlist Items</label><input name="wishlists_search" class="form-control" value="<?= h($wlt_search); ?>" placeholder="User name, email, product"></div>
              <div class="col-md-1 d-grid"><button class="btn btn-primary">Go</button></div>
            </form>
          </div>
          <div class="admin-card"><div class="table-responsive"><table class="table align-middle admin-table"><thead><tr><th>ID</th><th>User</th><th>Email</th><th>Product</th><th>Added</th><th>Action</th></tr></thead><tbody>
          <?php if($wishlists_result && mysqli_num_rows($wishlists_result)>0): while($wl=mysqli_fetch_assoc($wishlists_result)): ?>
            <tr><td><?= (int)$wl['id']; ?></td><td><?= h($wl['user_name']??'Unknown'); ?></td><td><?= h($wl['user_email']??'-'); ?></td><td><?= h($wl['product_name']??'Deleted Product'); ?></td><td><?= !empty($wl['created_at'])?date('d M Y h:i A',strtotime($wl['created_at'])):'-'; ?></td><td><form method="post" onsubmit="return confirm('Delete this wishlist item?');"><input type="hidden" name="action" value="delete_wishlist_item"><input type="hidden" name="tab" value="wishlists"><input type="hidden" name="wishlist_id" value="<?= (int)$wl['id']; ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
          <?php endwhile; else: ?><tr><td colspan="6" class="text-center">No wishlist items found.</td></tr><?php endif; ?>
          </tbody></table></div><?php if($wlt_pages>1): ?><div class="admin-pagination mt-3"><?php for($i=1;$i<=$wlt_pages;$i++): ?><a class="<?= $i===$wlt_page?'active':''; ?>" href="admin_dashboard.php?tab=wishlists&wishlists_page=<?= $i; ?>&wishlists_search=<?= urlencode($wlt_search); ?>"><?= $i; ?></a><?php endfor; ?></div><?php endif; ?></div>
          <?php endif; ?>
        <?php endif; ?>
        <?php if($active_tab==='reviews'): ?>
          <ul class="nav nav-pills admin-tabs mb-3">
            <li class="nav-item"><a class="nav-link active" href="#">Reviews</a></li>
          </ul>
          <div class="admin-card mb-3">
            <form method="get" class="admin-toolbar row g-2 align-items-end">
              <input type="hidden" name="tab" value="reviews">
              <div class="col-md-8"><label class="form-label">Search Review</label><input name="reviews_search" class="form-control" value="<?= h($r_search); ?>" placeholder="User name, email, product, review text"></div>
              <div class="col-md-3"><label class="form-label">Rating</label><select name="reviews_rating" class="form-select"><option value="">All</option><option value="5" <?= $r_rating==='5'?'selected':''; ?>>5 Stars</option><option value="4" <?= $r_rating==='4'?'selected':''; ?>>4 Stars</option><option value="3" <?= $r_rating==='3'?'selected':''; ?>>3 Stars</option><option value="2" <?= $r_rating==='2'?'selected':''; ?>>2 Stars</option><option value="1" <?= $r_rating==='1'?'selected':''; ?>>1 Star</option></select></div>
              <div class="col-md-1 d-grid"><button class="btn btn-primary">Go</button></div>
            </form>
          </div>
          <div class="admin-card"><div class="table-responsive"><table class="table align-middle admin-table"><thead><tr><th>ID</th><th>User</th><th>Email</th><th>Product</th><th>Rating</th><th>Review</th><th>Date</th><th>Action</th></tr></thead><tbody>
          <?php if($reviews_result && mysqli_num_rows($reviews_result)>0): while($rev=mysqli_fetch_assoc($reviews_result)): ?>
            <tr><td><?= (int)$rev['id']; ?></td><td><?= h($rev['user_name']??'Unknown'); ?></td><td><?= h($rev['user_email']??'-'); ?></td><td><?= h($rev['product_name']??'Deleted Product'); ?></td><td><?php for($i=1;$i<=5;$i++): ?><i class="fa<?= $i<=(int)$rev['rating']?'s':'-regular'; ?> fa-star text-warning"></i><?php endfor; ?> (<?= (int)$rev['rating']; ?>)</td><td style="min-width:260px;"><?= nl2br(h($rev['review_text'])); ?></td><td><?= !empty($rev['created_at'])?date('d M Y h:i A',strtotime($rev['created_at'])):'-'; ?></td><td><form method="post" onsubmit="return confirm('Delete this review?');"><input type="hidden" name="action" value="delete_review"><input type="hidden" name="tab" value="reviews"><input type="hidden" name="review_id" value="<?= (int)$rev['id']; ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr>
          <?php endwhile; else: ?><tr><td colspan="8" class="text-center">No reviews found.</td></tr><?php endif; ?>
          </tbody></table></div><?php if($r_pages>1): ?><div class="admin-pagination mt-3"><?php for($i=1;$i<=$r_pages;$i++): ?><a class="<?= $i===$r_page?'active':''; ?>" href="admin_dashboard.php?tab=reviews&reviews_page=<?= $i; ?>&reviews_search=<?= urlencode($r_search); ?>&reviews_rating=<?= urlencode($r_rating); ?>"><?= $i; ?></a><?php endfor; ?></div><?php endif; ?></div>
        <?php endif; ?>
      </main>
    </div>
  </div>
</div>

<!-- User detail modal -->
<div class="modal fade" id="userDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">User Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex gap-3 align-items-center mb-3">
          <img id="userModalAvatar" src="https://via.placeholder.com/96" alt="Profile" class="rounded-circle" style="width:96px;height:96px;object-fit:cover;border:1px solid #e5e7eb;">
          <div>
            <div class="d-flex align-items-center gap-2 mb-1">
              <h5 class="mb-0" id="userModalName">-</h5>
              <span class="badge bg-secondary" id="userModalRole">User</span>
              <span class="badge bg-success" id="userModalStatus">Active</span>
              <span class="badge bg-info text-dark" id="userModalVerified">Email Verified</span>
            </div>
            <div class="text-muted small" id="userModalCreated">Created: -</div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="border rounded p-3 h-100">
              <div class="fw-semibold mb-1">Contact</div>
              <div class="small text-muted">Email</div>
              <div id="userModalEmail" class="mb-2">-</div>
              <div class="small text-muted">Mobile</div>
              <div id="userModalMobile">-</div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="border rounded p-3 h-100">
              <div class="fw-semibold mb-1">Profile</div>
              <div class="small text-muted">Gender</div>
              <div id="userModalGender" class="mb-2">-</div>
              <div class="small text-muted">Address</div>
              <div id="userModalAddress" class="mb-0">-</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modalEl = document.getElementById('userDetailModal');
  const nameEl = document.getElementById('userModalName');
  const emailEl = document.getElementById('userModalEmail');
  const mobileEl = document.getElementById('userModalMobile');
  const roleEl = document.getElementById('userModalRole');
  const statusEl = document.getElementById('userModalStatus');
  const createdEl = document.getElementById('userModalCreated');
  const addressEl = document.getElementById('userModalAddress');
  const genderEl = document.getElementById('userModalGender');
  const verifiedEl = document.getElementById('userModalVerified');
  const avatarEl = document.getElementById('userModalAvatar');

  document.querySelectorAll('.view-user-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      nameEl.textContent = btn.dataset.name || '-';
      emailEl.textContent = btn.dataset.email || '-';
      mobileEl.textContent = btn.dataset.mobile || '-';
      roleEl.textContent = (btn.dataset.role || 'user').toUpperCase();
      statusEl.textContent = btn.dataset.status || 'Active';
      statusEl.className = 'badge ' + (btn.dataset.status === 'Inactive' ? 'bg-secondary' : 'bg-success');
      createdEl.textContent = 'Created: ' + (btn.dataset.created || '-');
      addressEl.textContent = btn.dataset.address || '-';
      genderEl.textContent = btn.dataset.gender || '-';
      const verified = btn.dataset.verified === '1';
      verifiedEl.textContent = verified ? 'Email Verified' : 'Email Pending';
      verifiedEl.className = 'badge ' + (verified ? 'bg-info text-dark' : 'bg-warning text-dark');
      const profile = btn.dataset.profile || '';
      avatarEl.src = profile ? ('uploads/' + profile) : 'https://via.placeholder.com/96';

      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    });
  });
});
</script>
</body>
</html>
