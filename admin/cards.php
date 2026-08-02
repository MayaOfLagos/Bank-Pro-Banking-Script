<?php include_once("./layout/header.php"); ?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Virtual Cards</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Cards</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Requested Cards</h3></div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Card Name</th>
                            <th>Card No</th>
                            <th>Expiration</th>
                            <th>CVC</th>
                            <th>Card Type</th>
                            <th>Created At</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $stmt = $conn->query("SELECT * FROM card ORDER BY id DESC");
                    $sn = 1;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $cardStatus = getCardStatus($row);
                        $card_type  = getCardType($row);
                    ?>
                        <tr>
                            <td><?= $sn++ ?></td>
                            <td><?= htmlspecialchars($row['card_name']) ?></td>
                            <td><?= htmlspecialchars($row['card_number']) ?></td>
                            <td><?= htmlspecialchars($row['card_expiration']) ?></td>
                            <td><?= htmlspecialchars($row['card_security']) ?></td>
                            <td><?= htmlspecialchars($card_type) ?></td>
                            <td><?= htmlspecialchars($row['createdAt']) ?></td>
                            <td><?= $cardStatus ?></td>
                            <td><a href="./viewcard.php?id=<?= htmlspecialchars($row['seria_key']) ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
