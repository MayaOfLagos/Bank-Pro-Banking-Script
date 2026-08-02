<?php include_once("./layout/header.php"); ?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Domestic Transfers</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Domestic</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">All Domestic Transfers</h3></div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Amount</th>
                            <th>Bank</th>
                            <th>Account Name</th>
                            <th>Account No</th>
                            <th>Account Type</th>
                            <th>Transfer Type</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sql = "SELECT * FROM domestic_transfer LEFT JOIN users ON domestic_transfer.acct_id = users.id ORDER BY domestic_transfer.dom_id DESC";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute();
                    $sn = 1;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $dom_status = domesticTransaction($row);
                        $currency = currency($row);
                    ?>
                        <tr>
                            <td><?= $sn++ ?></td>
                            <td><?= $currency . htmlspecialchars($row['amount']) ?></td>
                            <td><?= htmlspecialchars($row['bank_name']) ?></td>
                            <td><?= htmlspecialchars($row['acct_name']) ?></td>
                            <td><?= htmlspecialchars($row['acct_number']) ?></td>
                            <td><?= htmlspecialchars($row['acct_type']) ?></td>
                            <td><?= ucwords(htmlspecialchars($row['trans_type'])) ?></td>
                            <td><?= $dom_status ?></td>
                            <td><a href="./view-domtrans.php?id=<?= htmlspecialchars($row['refrence_id']) ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
