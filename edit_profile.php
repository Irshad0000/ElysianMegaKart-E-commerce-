<?php
session_start();
include 'db_config.php';

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch current user info
$user_query = mysqli_query($con,"SELECT * FROM register WHERE id='$user_id'");
$user = mysqli_fetch_assoc($user_query);

// Handle form submission
if(isset($_POST['update_profile'])){

    $name = mysqli_real_escape_string($con, $_POST['name']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $mobile = mysqli_real_escape_string($con, $_POST['mobile']);
    $gender = mysqli_real_escape_string($con, $_POST['gender']);

    // Handle profile picture upload
    if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['name'] != ''){
        $file_name = time().'_'.$_FILES['profile_pic']['name'];
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        move_uploaded_file($file_tmp, "uploads/".$file_name);
        $profile_pic_sql = ", profile_pic='$file_name'";
    } else {
        $profile_pic_sql = "";
    }

    // Update database
    $update_sql = "UPDATE register SET 
                    name='$name',
                    email='$email',
                    mobile='$mobile',
                    gender='$gender'
                    $profile_pic_sql
                    WHERE id='$user_id'";

    if(mysqli_query($con, $update_sql)){
        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: profile.php");
        exit();
    } else {
        $error = "Failed to update profile. Please try again.";
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body">
                    <h4 class="fw-bold mb-3">Edit Profile</h4>

                    <?php if(isset($error)) { ?>
                        <div class="alert alert-danger"><?= $error; ?></div>
                    <?php } ?>

                    <form method="POST" enctype="multipart/form-data">

                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($user['name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mobile</label>
                            <input type="text" name="mobile" class="form-control" 
                                   value="<?= htmlspecialchars($user['mobile']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="male" <?= $user['gender']=='male'?'selected':''; ?>>Male</option>
                                <option value="female" <?= $user['gender']=='female'?'selected':''; ?>>Female</option>
                                <option value="other" <?= $user['gender']=='other'?'selected':''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Profile Picture</label>
                            <input type="file" name="profile_pic" class="form-control">
                            <?php if(!empty($user['profile_pic'])){ ?>
                                <img src="uploads/<?= $user['profile_pic']; ?>" 
                                     width="80" class="mt-2 rounded-circle">
                            <?php } ?>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-success rounded-pill px-4">
                            Update Profile
                        </button>

                        <a href="profile.php" class="btn btn-secondary rounded-pill px-4 ms-2">
                            Cancel
                        </a>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
