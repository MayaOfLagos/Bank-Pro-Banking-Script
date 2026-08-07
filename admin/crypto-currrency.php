<?php
include_once("./layout/header.php");

if (isset($_POST['delete_crypto_currency'])) {
    // Read the row first: after the DELETE there is no record of which coin
    // and address were removed, and the alert has to name them.
    $prevStmt = $conn->prepare("SELECT crypto_name, wallet_address FROM crypto_currency WHERE id =:id");
    $prevStmt->execute(['id' => $_POST['crypto_id']]);
    $prev = $prevStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $conn->prepare("DELETE FROM crypto_currency WHERE id =:id");
    $stmt->execute(['id' => $_POST['crypto_id']]);
    // Removing a wallet withdraws a deposit route customers may already be
    // using, so operators need to know it happened and who did it.
    admin_notify(
        (new AdminAlert)->adminDepositWalletChangedMsg(
            admin_actor_name(),
            'deleted',
            (string)($prev['crypto_name'] ?? ''),
            (string)($prev['wallet_address'] ?? ''),
            '',
            admin_actor_ip()
        ),
        'Deposit wallet deleted'
    );
    toast_flash('success', 'Wallet deleted.', 'Removed');
    header("location:./crypto-currrency.php");
    exit;
}

if (isset($_POST['crypto_save'])) {
    $stmt = $conn->prepare("INSERT INTO crypto_currency (crypto_name, wallet_address) VALUES(:crypto_name,:wallet_address)");
    $stmt->execute([
        'crypto_name'    => $_POST['crypto_name'],
        'wallet_address' => $_POST['wallet_address'],
    ]);
    // A new deposit address goes live for customers immediately, so it is
    // worth confirming against your own records before the first deposit.
    admin_notify(
        (new AdminAlert)->adminDepositWalletChangedMsg(
            admin_actor_name(),
            'added',
            (string)$_POST['crypto_name'],
            '',
            (string)$_POST['wallet_address'],
            admin_actor_ip()
        ),
        'Deposit wallet added'
    );
    toast_alert('success', 'Wallet Added Successfully', 'Saved');
}

if (isset($_POST['crypto_edit'])) {
    // Capture the current address before overwriting it so the alert can show
    // the old → new pair; that diff is the whole point of this notification.
    $prevStmt = $conn->prepare("SELECT wallet_address FROM crypto_currency WHERE id=:id");
    $prevStmt->execute(['id' => $_POST['crypto_id']]);
    $prev = $prevStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $conn->prepare("UPDATE crypto_currency SET crypto_name=:crypto_name, wallet_address=:wallet_address WHERE id=:id");
    $stmt->execute([
        'crypto_name'    => $_POST['crypto_name'],
        'wallet_address' => $_POST['wallet_address'],
        'id'             => $_POST['crypto_id'],
    ]);
    // Editing the address reroutes every future customer deposit for this
    // coin, with no other visible symptom — the highest-risk change here.
    admin_notify(
        (new AdminAlert)->adminDepositWalletChangedMsg(
            admin_actor_name(),
            'updated',
            (string)$_POST['crypto_name'],
            (string)($prev['wallet_address'] ?? ''),
            (string)$_POST['wallet_address'],
            admin_actor_ip()
        ),
        'Deposit wallet updated'
    );
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
