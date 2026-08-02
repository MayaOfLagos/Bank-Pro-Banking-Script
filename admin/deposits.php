<?php
include_once("./layout/header.php");

$stmt = $conn->prepare("SELECT * FROM v_bank WHERE id='48'");
$stmt->execute();
$deposit = $stmt->fetch(PDO::FETCH_ASSOC);

if (isset($_POST['save_deposit'])) {
    $stmt = $conn->prepare("UPDATE v_bank SET acct_no=:acct_no, bank_name=:bank_name, routine_no=:routine_no, swift_code=:swift_code WHERE id=48");
    $stmt->execute([
        'acct_no'    => $_POST['acct_no'],
        'bank_name'  => $_POST['bank_name'],
        'routine_no' => $_POST['routine_no'],
        'swift_code' => $_POST['swift_code'],
    ]);
    toast_alert('success', 'Virtual deposit updated successfully', 'Approved');

    $stmt = $conn->prepare("SELECT * FROM v_bank WHERE id='48'");
    $stmt->execute();
    $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Virtual Bank Deposit</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Virtual Deposit</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card card-primary">
                    <div class="card-header"><h3 class="card-title">Bank Deposit Details</h3></div>
                    <form method="post">
                        <div class="card-body">
                            <div class="form-group"><label>Bank Name</label><input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($deposit['bank_name']) ?>"></div>
                            <div class="form-group"><label>Routing Number</label><input type="text" name="routine_no" class="form-control" value="<?= htmlspecialchars($deposit['routine_no']) ?>"></div>
                            <div class="form-group"><label>Account No</label><input type="text" name="acct_no" class="form-control" value="<?= htmlspecialchars($deposit['acct_no']) ?>"></div>
                            <div class="form-group"><label>Swift Code</label><input type="text" name="swift_code" class="form-control" value="<?= htmlspecialchars($deposit['swift_code']) ?>"></div>
                        </div>
                        <div class="card-footer"><button class="btn btn-primary" name="save_deposit"><i class="fas fa-save"></i> Save</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
