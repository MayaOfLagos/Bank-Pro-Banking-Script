<?php
include_once("./layout/header.php");

$fullName = $row['firstname'] . " " . $row['lastname'];

if (isset($_POST['upload_picture']) && isset($_FILES['image']) && $_FILES['image']['name']) {
    $file = $_FILES['image'];
    $name = $file['name'];
    $folder = "../assets/profile/";
    $n = $row['firstname'] . $name;
    $destination = $folder . $n;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $stmt = $conn->prepare("UPDATE admin SET image=:image WHERE id ='1'");
        $stmt->execute(['image' => $n]);
        toast_alert('success', 'Image Uploaded Successfully', 'Thanks!');
    }
}

if (isset($_POST['profile'])) {
    // Read the sign-in address before the write so the alert can name the
    // address it was changed from.
    $before   = $conn->prepare("SELECT admin_email FROM admin WHERE id=1");
    $before->execute();
    $oldEmail = (string)$before->fetchColumn();

    $stmt = $conn->prepare("UPDATE admin SET admin_email=:admin_email, firstname=:firstname WHERE id=1");
    $stmt->execute([
        'admin_email' => $_POST['email'],
        'firstname'   => $_POST['firstname'],
    ]);

    // Changing this address moves administrator sign-in and every admin alert
    // to a different mailbox, so alert whenever it actually changed.
    $newEmail = (string)$_POST['email'];
    if ($newEmail !== $oldEmail) {
        admin_notify(
            (new AdminAlert)->adminAccountEmailChangedMsg(admin_actor_name(), $oldEmail, $newEmail, admin_actor_ip()),
            'Admin sign-in address changed'
        );
    }

    toast_alert('success', 'Profile updated successfully', 'Approved');
}

if (isset($_POST['change_password'])) {
    $old_password     = inputValidation($_POST['old_password']);
    $new_password     = inputValidation($_POST['new_password']);
    $confirm_password = inputValidation($_POST['confirm_password']);
    if (!password_verify($old_password, $row['admin_password'])) {
        toast_alert('error', 'Incorrect Old Password');
    } elseif ($new_password !== $confirm_password) {
        toast_alert('error', 'Confirm Password not matched');
    } elseif ($new_password === $old_password) {
        toast_alert('error', 'New Password matches Old Password');
    } else {
        $stmt = $conn->prepare("UPDATE admin SET admin_password=:p WHERE id=1");
        $stmt->execute(['p' => password_hash((string)$new_password, PASSWORD_BCRYPT)]);

        // The admin password is the key to every customer account, so a change
        // here is reported even when it was expected.
        admin_notify(
            (new AdminAlert)->adminPasswordChangedMsg(admin_actor_name(), admin_actor_ip()),
            'Admin password changed'
        );

        toast_alert('success', 'Password Changed Successfully', 'Approved');
    }
}
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Admin Profile</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Profile</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-4">
                <div class="card card-primary card-outline">
                    <div class="card-body box-profile">
                        <div class="text-center">
                            <img class="profile-user-img img-fluid img-circle" src="../assets/profile/<?= htmlspecialchars($row['image']) ?>" alt="Admin">
                        </div>
                        <h3 class="profile-username text-center"><?= htmlspecialchars($fullName) ?></h3>
                        <p class="text-muted text-center">Administrator</p>
                        <form method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label>Change Picture</label>
                                <input type="file" name="image" class="form-control-file" accept="image/*">
                            </div>
                            <button class="btn btn-primary btn-block" name="upload_picture">Upload</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">General Information</h3></div>
                    <form method="post">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>First Name</label>
                                        <input type="text" name="firstname" class="form-control" value="<?= htmlspecialchars($row['firstname']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($row['admin_email']) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer"><button class="btn btn-primary" name="profile">Save</button></div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Change Password</h3></div>
                    <form method="post">
                        <div class="card-body">
                            <div class="form-group"><label>Old Password</label><input type="password" name="old_password" class="form-control"></div>
                            <div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control"></div>
                            <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" class="form-control"></div>
                        </div>
                        <div class="card-footer"><button class="btn btn-primary" name="change_password">Change Password</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
