<?php include_once("./layout/header.php"); ?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Credit / Debit Transactions</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Credit / Debit</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">All Credit / Debit Transactions</h3></div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Name</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Sender</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Edit</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sql = "SELECT * FROM transactions LEFT JOIN users ON transactions.user_id = users.id ORDER BY transactions.trans_id DESC";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute();
                    $sn = 1;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $trans_type = ($row['trans_type'] === '1' || $row['trans_type'] === 1)
                            ? '<span class="badge badge-success">Credit</span>'
                            : '<span class="badge badge-danger">Debit</span>';
                        $currency = currency($row);
                        $fullName = htmlspecialchars($row['firstname'] . " " . $row['lastname']);
                    ?>
                        <tr>
                            <td><?= $sn++ ?></td>
                            <td><?= $fullName ?></td>
                            <td><?= $currency . htmlspecialchars($row['amount']) ?></td>
                            <td><?= $trans_type ?></td>
                            <td><?= htmlspecialchars($row['sender_name']) ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                            <td><?= htmlspecialchars($row['time_created']) ?></td>
                            <td><a href="./edit-trans.php?id=<?= (int)$row['trans_id'] ?>" target="_blank" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a></td>
                            <td><a href="./view-trans.php?id=<?= (int)$row['trans_id'] ?>" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
