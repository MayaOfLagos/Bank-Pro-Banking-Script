<?php include_once("./layout/header.php"); ?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Loan Requests</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Loans</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">All Loan Requests</h3></div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Remarks</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sql = "SELECT * FROM loan LEFT JOIN users ON loan.acct_id = users.id ORDER BY loan.loan_id DESC";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute();
                    $sn = 1;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $currency = currency($row);
                        $statusMap = [
                            '0' => '<span class="badge badge-secondary">Processing</span>',
                            '1' => '<span class="badge badge-success">Approved</span>',
                            '2' => '<span class="badge badge-warning">On Hold</span>',
                            '3' => '<span class="badge badge-danger">Declined</span>',
                        ];
                        $tran_status = $statusMap[(string)$row['loan_status']] ?? '<span class="badge badge-light">Unknown</span>';
                        $fullName = htmlspecialchars($row['firstname'] . " " . $row['lastname']);
                    ?>
                        <tr>
                            <td><?= $sn++ ?></td>
                            <td><?= $fullName ?></td>
                            <td><?= $currency . htmlspecialchars($row['amount']) ?></td>
                            <td><?= htmlspecialchars($row['loan_remarks']) ?></td>
                            <td><?= $tran_status ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                            <td><a href="./viewloan-trans.php?id=<?= htmlspecialchars($row['loan_reference_id']) ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
