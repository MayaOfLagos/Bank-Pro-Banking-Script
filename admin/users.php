<?php include_once("./layout/header.php"); ?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>All Users</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Users</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">User Accounts</h3>
                <div class="card-tools">
                    <a href="./reguser.php" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> New Account</a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Name</th>
                            <th>Account No</th>
                            <th>Currency</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Email</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sql = "SELECT * FROM users ORDER BY id ASC";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute();
                    $sn = 1;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $fullName = htmlspecialchars($row['firstname'] . " " . $row['lastname']);
                        $statusClass = strtolower($row['acct_status']) === 'active' ? 'badge-success' : 'badge-warning';
                    ?>
                        <tr>
                            <td><?= $sn++ ?></td>
                            <td><?= $fullName ?></td>
                            <td><?= htmlspecialchars($row['acct_no']) ?></td>
                            <td><?= htmlspecialchars($row['acct_currency']) ?></td>
                            <td><?= htmlspecialchars($row['acct_type']) ?></td>
                            <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($row['acct_status']) ?></span></td>
                            <td><?= htmlspecialchars($row['acct_email']) ?></td>
                            <td>
                                <a href="./view_users.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> View</a>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
