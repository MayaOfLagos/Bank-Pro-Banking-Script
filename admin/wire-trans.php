<?php include_once("./layout/header.php"); ?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Wire Transfers</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Wire</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">All Wire Transfers</h3></div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Sender</th>
                            <th>Amount</th>
                            <th>Bank</th>
                            <th>Account Name</th>
                            <th>Account No</th>
                            <th>Country</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sql = "SELECT * FROM wire_transfer LEFT JOIN users ON wire_transfer.acct_id = users.id ORDER BY wire_transfer.wire_id DESC";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute();
                    $sn = 1;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $wire_status = wireStatus($row);
                        $currency = currency($row);
                        $fullName = htmlspecialchars($row['firstname'] . " " . $row['lastname']);
                    ?>
                        <tr>
                            <td><?= $sn++ ?></td>
                            <td><?= $fullName ?></td>
                            <td><?= $currency . htmlspecialchars($row['amount']) ?></td>
                            <td><?= htmlspecialchars($row['bank_name']) ?></td>
                            <td><?= htmlspecialchars($row['acct_name']) ?></td>
                            <td><?= htmlspecialchars($row['acct_number']) ?></td>
                            <td><?= htmlspecialchars($row['acct_country']) ?></td>
                            <td><?= $wire_status ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                            <td><a href="./viewwire-trans.php?id=<?= htmlspecialchars($row['refrence_id']) ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
