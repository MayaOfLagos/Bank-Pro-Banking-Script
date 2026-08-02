<?php include_once("./layout/header.php"); ?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Withdrawal Transactions</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Withdrawals</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">All Withdrawals</h3></div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Reference ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sql = "SELECT * FROM withdrawal w LEFT JOIN users u ON w.user_id = u.id ORDER BY w.id DESC";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute();
                    $sn = 1;
                    $statusMap = [
                        '0' => '<span class="badge badge-secondary">In Progress</span>',
                        '1' => '<span class="badge badge-success">Completed</span>',
                        '2' => '<span class="badge badge-warning">Hold</span>',
                        '3' => '<span class="badge badge-danger">Cancelled</span>',
                    ];
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $status = $statusMap[(string)$row['status']] ?? '<span class="badge badge-light">Unknown</span>';
                        $currency = currency($row);
                        $fullName = htmlspecialchars($row['firstname'] . " " . $row['lastname']);
                    ?>
                        <tr>
                            <td><?= $sn++ ?></td>
                            <td><?= $fullName ?></td>
                            <td><?= htmlspecialchars($row['acct_email']) ?></td>
                            <td><?= htmlspecialchars($row['reference_id']) ?></td>
                            <td><?= $currency . htmlspecialchars($row['amount']) ?></td>
                            <td><?= $status ?></td>
                            <td><?= htmlspecialchars($row['createdAt']) ?></td>
                            <td><a href="./viewwithdraw.php?id=<?= htmlspecialchars($row['reference_id']) ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
