<?php
include_once("./layout/auth_header.php");
require_once("./include/adminloginFunction.php");
if (@$_SESSION['admin']) {
    header('Location:./dashboard.php');
    exit;
}
?>
<div class="login-box">
    <div class="login-logo">
        <a href="./login.php"><b>Admin</b> Panel</a>
    </div>
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Sign in to start your session</p>

            <form action="" method="post" autocomplete="off">
                <div class="input-group mb-3">
                    <input type="email" name="admin_email" class="form-control" placeholder="Email" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="admin_password" class="form-control" placeholder="Password" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <button type="submit" name="admin_login" class="btn btn-primary btn-block">Sign In</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once("./layout/auth_footer.php"); ?>
