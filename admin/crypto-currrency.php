<?php
include_once("./layout/header.php");

if (isset($_POST['delete_crypto_currency'])) {
    $stmt = $conn->prepare("DELETE FROM crypto_currency WHERE id =:id");
    $stmt->execute(['id' => $_POST['crypto_id']]);
    header("location:./crypto-currrency.php");
    exit;
}

if (isset($_POST['crypto_save'])) {
    $stmt = $conn->prepare("INSERT INTO crypto_currency (crypto_name, wallet_address) VALUES(:crypto_name,:wallet_address)");
    $stmt->execute([
        'crypto_name'    => $_POST['crypto_name'],
        'wallet_address' => $_POST['wallet_address'],
    ]);
    toast_alert('success', 'Wallet Added Successfully', 'Saved');
}

if (isset($_POST['crypto_edit'])) {
    $stmt = $conn->prepare("UPDATE crypto_currency SET crypto_name=:crypto_name, wallet_address=:wallet_address WHERE id=:id");
    $stmt->execute([
        'crypto_name'    => $_POST['crypto_name'],
        'wallet_address' => $_POST['wallet_address'],
        'id'             => $_POST['crypto_id'],
    ]);
    toast_alert('success', 'Wallet Saved Successfully', 'Saved');
}
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Crypto Deposit Methods</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Crypto Wallets</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Crypto Wallets</h3>
                <div class="card-tools">
                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addCryptoModal">
                        <i class="fas fa-plus"></i> Add Crypto
                    </button>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Crypto Name</th>
                            <th>Wallet Address</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM crypto_currency ORDER BY crypto_name");
                    $stmt->execute();
                    $sn = 1;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    ?>
                        <tr>
                            <td><?= $sn++ ?></td>
                            <td><?= htmlspecialchars($row['crypto_name']) ?></td>
                            <td><?= htmlspecialchars($row['wallet_address']) ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning edit-crypto" data-id="<?= (int)$row['id'] ?>" data-name="<?= htmlspecialchars($row['crypto_name']) ?>" data-wallet="<?= htmlspecialchars($row['wallet_address']) ?>"><i class="fas fa-edit"></i></button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this wallet?')">
                                    <input type="hidden" name="crypto_id" value="<?= (int)$row['id'] ?>">
                                    <button class="btn btn-sm btn-danger" name="delete_crypto_currency"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Add Crypto Modal -->
<div class="modal fade" id="addCryptoModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="post">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Crypto</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label>Crypto Name</label><input type="text" class="form-control" name="crypto_name" required></div>
                    <div class="form-group"><label>Wallet Address</label><input type="text" class="form-control" name="wallet_address" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" name="crypto_save">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Crypto Modal -->
<div class="modal fade" id="editCryptoModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="post">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Crypto Wallet</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group"><label>Crypto Name</label><input type="text" class="form-control" name="crypto_name" id="edit_crypto_name" required></div>
                    <div class="form-group"><label>Wallet Address</label><input type="text" class="form-control" name="wallet_address" id="edit_wallet_address" required></div>
                    <input type="hidden" name="crypto_id" id="edit_crypto_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" name="crypto_edit">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
$(function () {
    $(document).on('click', '.edit-crypto', function () {
        $('#edit_crypto_id').val($(this).data('id'));
        $('#edit_crypto_name').val($(this).data('name'));
        $('#edit_wallet_address').val($(this).data('wallet'));
        $('#editCryptoModal').modal('show');
    });
});
</script>

<?php include_once("./layout/footer.php"); ?>
