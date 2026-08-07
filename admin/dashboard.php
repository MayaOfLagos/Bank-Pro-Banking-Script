<?php
include_once("./layout/header.php");

$sql = "SELECT * FROM users";
$stmt = $conn->prepare($sql);
$stmt->execute();
$users_count = $stmt->rowCount();

$totalBalance = (int) $conn->query("SELECT COALESCE(SUM(acct_balance),0) FROM users")->fetchColumn();
$totalWire    = (int) $conn->query("SELECT COALESCE(SUM(amount),0) FROM wire_transfer")->fetchColumn();
$totalDeposit = (int) $conn->query("SELECT COALESCE(SUM(amount),0) FROM deposit")->fetchColumn();
$totalLoan       = (int) $conn->query("SELECT COALESCE(SUM(amount),0) FROM loan")->fetchColumn();
$totalDom        = (int) $conn->query("SELECT COALESCE(SUM(amount),0) FROM domestic_transfer")->fetchColumn();
$totalWithdrawal = (int) $conn->query("SELECT COALESCE(SUM(amount),0) FROM withdrawal")->fetchColumn();
$totalTx         = (int) $conn->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>Admin Analytics</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Dashboard</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?= number_format($users_count) ?></h3>
                        <p>Total Users</p>
                    </div>
                    <div class="icon"><i class="fas fa-users"></i></div>
                    <a href="./users.php" class="small-box-footer">View Users <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3>$<?= number_format($totalBalance) ?></h3>
                        <p>Total Balance</p>
                    </div>
                    <div class="icon"><i class="fas fa-wallet"></i></div>
                    <a href="./funduser.php" class="small-box-footer">Adjust <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3>$<?= number_format($totalWire) ?></h3>
                        <p>Total Wire Transfers</p>
                    </div>
                    <div class="icon"><i class="fas fa-exchange-alt"></i></div>
                    <a href="./wire-trans.php" class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>

            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3>$<?= number_format($totalDeposit) ?></h3>
                        <p>Total Deposits</p>
                    </div>
                    <div class="icon"><i class="fas fa-piggy-bank"></i></div>
                    <a href="./deposits.php" class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 col-md-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3>$<?= number_format($totalLoan) ?></h3>
                        <p>Total Loan Requests</p>
                    </div>
                    <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <a href="./loan-trans.php" class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3>$<?= number_format($totalDom) ?></h3>
                        <p>Total Domestic Transfers</p>
                    </div>
                    <div class="icon"><i class="fas fa-paper-plane"></i></div>
                    <a href="./domestic-trans.php" class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <div class="small-box bg-dark">
                    <div class="inner">
                        <h3>$<?= number_format($totalWithdrawal) ?></h3>
                        <p>Total Withdrawals</p>
                    </div>
                    <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
                    <a href="./withdraw-trans.php" class="small-box-footer">View <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Users</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped">
                            <div class="responsive">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Balance</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->prepare("SELECT firstname,lastname,acct_email,acct_balance,createdAt FROM users ORDER BY id DESC LIMIT 10");
                                    $stmt->execute();
                                    $i = 1;
                                    while ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<tr>";
                                        echo "<td>" . $i++ . "</td>";
                                        echo "<td>" . htmlspecialchars($u['firstname'] . ' ' . $u['lastname']) . "</td>";
                                        echo "<td>" . htmlspecialchars($u['acct_email']) . "</td>";
                                        echo "<td>$" . number_format((float)$u['acct_balance'], 2) . "</td>";
                                        echo "<td>" . htmlspecialchars($u['createdAt']) . "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </div>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>