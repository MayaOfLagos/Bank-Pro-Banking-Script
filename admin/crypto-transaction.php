<?php include_once("./layout/header.php"); ?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Crypto Transactions</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Crypto</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">All Crypto Transactions</h3></div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Amount</th>
                            <th>Trans ID</th>
                            <th>Name</th>
                            <th>Wallet Address</th>
                            <th>Crypto</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sql = "SELECT d.*, u.*, c.crypto_name FROM deposit d INNER JOIN crypto_currency c ON d.crypto_id = c.id LEFT JOIN users u ON d.user_id = u.id ORDER BY d.d_id DESC";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute();
                    $sn = 1;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $crypto_status = cryptoTransaction($row);
                        $currency = currency($row);
                        $fullName = htmlspecialchars($row['firstname'] . " " . $row['lastname']);
                    ?>
                        <tr>
                            <td><?= $sn++ ?></td>
                            <td><?= $currency . htmlspecialchars($row['amount']) ?></td>
                            <td><?= htmlspecialchars($row['refrence_id']) ?></td>
                            <td><?= $fullName ?></td>
                            <td><?= htmlspecialchars($row['wallet_address']) ?></td>
                            <td><?= htmlspecialchars($row['crypto_name']) ?></td>
                            <td><?= $crypto_status ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                            <td><a href="./viewcrypto-trans.php?id=<?= htmlspecialchars($row['refrence_id']) ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
