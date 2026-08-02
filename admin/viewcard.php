<?php
include_once("./layout/header.php");

$id   = $_GET['id'] ?? '';
$stmt = $conn->prepare("SELECT * FROM card WHERE seria_key=:k");
$stmt->execute(['k' => $id]);
$cardCheck = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cardCheck) {
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger">Card not found.</div></div></section>';
    include_once("./layout/footer.php");
    exit;
}

if (isset($_POST['hold_card'])) {
    $stmt = $conn->prepare("UPDATE card SET card_status=:s WHERE seria_key=:k");
    $stmt->execute(['s' => 3, 'k' => $id]);
    toast_alert('success', 'Card placed on hold', 'Done');
    header('Location:' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
    exit;
}

if (isset($_POST['active_card'])) {
    $stmt = $conn->prepare("UPDATE card SET card_status=:s WHERE seria_key=:k");
    $stmt->execute(['s' => 1, 'k' => $id]);
    toast_alert('success', 'Card activated', 'Done');
    header('Location:' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM card WHERE seria_key=:k");
$stmt->execute(['k' => $id]);
$cardCheck = $stmt->fetch(PDO::FETCH_ASSOC);

$card_type   = getCardType($cardCheck);
$cardStatus  = getCardStatus($cardCheck);
$card_number = explode(' ', $cardCheck['card_number']);
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Card Details</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="./cards.php">Cards</a></li>
                    <li class="breadcrumb-item active">View</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card bg-gradient-primary text-white" style="border-radius: 15px;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="text-white"><?= htmlspecialchars($pageTitle) ?></h4>
                                <p class="mb-0 text-white-50">Debit Card</p>
                            </div>
                            <h5 class="text-white"><?= htmlspecialchars($card_type) ?></h5>
                        </div>
                        <h3 class="mt-4 mb-1 text-white" style="letter-spacing:3px;"><?= htmlspecialchars($cardCheck['card_number']) ?></h3>
                        <div class="d-flex justify-content-between mt-3">
                            <div>
                                <small class="text-white-50">Card Holder</small>
                                <div class="text-white text-uppercase"><?= htmlspecialchars($cardCheck['card_name']) ?></div>
                            </div>
                            <div>
                                <small class="text-white-50">Expires</small>
                                <div class="text-white"><?= htmlspecialchars($cardCheck['card_expiration']) ?></div>
                            </div>
                            <div>
                                <small class="text-white-50">CVC</small>
                                <div class="text-white"><?= htmlspecialchars($cardCheck['card_security']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <p class="text-center mb-3">Status: <?= $cardStatus ?></p>
                        <div class="row">
                            <div class="col-6">
                                <small>Card Limit</small>
                                <h5>$<?= number_format((float)$cardCheck['card_limit'], 2) ?></h5>
                            </div>
                            <div class="col-6 text-right">
                                <small>Limit Remaining</small>
                                <h5 class="text-danger">$<?= number_format((float)$cardCheck['card_limit_remain'], 2) ?></h5>
                            </div>
                        </div>
                        <form method="post" class="mt-3">
                            <?php if ((string)$cardCheck['card_status'] === '1'): ?>
                                <button class="btn btn-danger btn-block" name="hold_card">Deactivate Card</button>
                            <?php elseif (in_array((string)$cardCheck['card_status'], ['2','3'], true)): ?>
                                <button class="btn btn-success btn-block" name="active_card">Activate Card</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
