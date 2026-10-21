<?php
session_start();
include 'db_config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = (int)$_SESSION['user_id'];
$success = '';
$error = '';

// Fetch admin info
$admin_query = mysqli_query($con, "SELECT * FROM register WHERE id='$admin_id' LIMIT 1");
$admin = $admin_query && mysqli_num_rows($admin_query) ? mysqli_fetch_assoc($admin_query) : [];

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name   = mysqli_real_escape_string($con, $_POST['name'] ?? '');
    $email  = mysqli_real_escape_string($con, $_POST['email'] ?? '');
    $mobile = mysqli_real_escape_string($con, $_POST['mobile'] ?? '');
    $gender = mysqli_real_escape_string($con, $_POST['gender'] ?? '');
    $address= mysqli_real_escape_string($con, $_POST['address'] ?? '');

    $profile_pic_sql = "";
    if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['name'] != ''){
        $file_name = time().'_'.basename($_FILES['profile_pic']['name']);
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        if (move_uploaded_file($file_tmp, "uploads/".$file_name)) {
            $profile_pic_sql = ", profile_pic='$file_name'";
            $admin['profile_pic'] = $file_name;
        }
    }

    $update_sql = "UPDATE register SET 
                    name='$name',
                    email='$email',
                    mobile='$mobile',
                    gender='$gender',
                    address='$address'
                    $profile_pic_sql
                    WHERE id='$admin_id'";

    if(mysqli_query($con, $update_sql)){
        $_SESSION['user_name'] = $name;
        $success = "Profile updated successfully!";
        // refresh admin data
        $admin_query = mysqli_query($con, "SELECT * FROM register WHERE id='$admin_id' LIMIT 1");
        $admin = $admin_query && mysqli_num_rows($admin_query) ? mysqli_fetch_assoc($admin_query) : $admin;
    } else {
        $error = "Failed to update profile. Please try again.";
    }
}

$admin_pic = !empty($admin['profile_pic']) ? $admin['profile_pic'] : 'default.png';
?>

<?php include 'includes/admin_header.php'; ?>

<div class="account-page">
    <div class="container py-5">
        <div class="account-hero mb-4">
            <h2>Admin Profile</h2>
            <p>View and edit your administrator details.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="account-card account-sidebar h-100">
                    <div class="text-center">
                        <img src="uploads/<?= htmlspecialchars($admin_pic); ?>" alt="Profile" class="account-avatar">
                        <h4 class="mb-1"><?= htmlspecialchars($admin['name'] ?? 'Admin'); ?></h4>
                        <p class="text-muted mb-0">Role: <?= htmlspecialchars($admin['role'] ?? 'admin'); ?></p>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            Edit Profile
                        </button>
                        <a href="admin_change_password.php" class="btn btn-outline-primary">Change Password</a>
                        <a href="logout.php" class="btn btn-outline-danger">Logout</a>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <?php if($success): ?><div class="alert alert-success"><?= htmlspecialchars($success); ?></div><?php endif; ?>
                <?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error); ?></div><?php endif; ?>

                <div class="account-card">
                    <div class="account-section-head d-flex justify-content-between align-items-center">
                        <h5>Profile Details</h5>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editProfileModal">Edit</button>
                    </div>
                    <div class="row g-3 mt-2">
                        <div class="col-sm-6">
                            <div class="small text-muted">Full Name</div>
                            <div class="fw-semibold"><?= htmlspecialchars($admin['name'] ?? '-'); ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted">Role</div>
                            <div class="fw-semibold"><?= htmlspecialchars($admin['role'] ?? 'admin'); ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted">Email</div>
                            <div class="fw-semibold"><?= htmlspecialchars($admin['email'] ?? '-'); ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted">Mobile</div>
                            <div class="fw-semibold"><?= htmlspecialchars($admin['mobile'] ?? '-'); ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted">Gender</div>
                            <div class="fw-semibold"><?= htmlspecialchars($admin['gender'] ?? '-'); ?></div>
                        </div>
                        <div class="col-sm-12">
                            <div class="small text-muted">Address</div>
                            <div class="fw-semibold"><?= htmlspecialchars($admin['address'] ?? '-'); ?></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- EDIT PROFILE MODAL -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($admin['name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin['email'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mobile</label>
                        <input type="text" name="mobile" class="form-control" value="<?= htmlspecialchars($admin['mobile'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select" required>
                            <option value="male" <?= ($admin['gender'] ?? '')==='male'?'selected':''; ?>>Male</option>
                            <option value="female" <?= ($admin['gender'] ?? '')==='female'?'selected':''; ?>>Female</option>
                            <option value="others" <?= ($admin['gender'] ?? '')==='others'?'selected':''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="3" required><?= htmlspecialchars($admin['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Profile Picture</label>
                        <input type="file" name="profile_pic" class="form-control">
                        <?php if (!empty($admin['profile_pic'])) { ?>
                            <img src="uploads/<?= htmlspecialchars($admin['profile_pic']); ?>" width="80" class="mt-2 rounded-circle" alt="Profile Preview">
                        <?php } ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_profile" class="btn btn-success rounded-pill px-4">Update</button>
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
