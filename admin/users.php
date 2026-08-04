<?php include_once("./layout/header.php"); ?>

<?php
// Bulk approve / reject held accounts. Only responds to authenticated admin
// POSTs (the header.php guard above already redirects anonymous visitors to
// the login screen), then defensively re-checks that every submitted id is
// still on 'hold' before mutating anything — a tampered form that sneaks in
// an already-active id can't be used to demote it.
$bulkMessage = null;
$bulkType    = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $rawIds = isset($_POST['user_ids']) && is_array($_POST['user_ids']) ? $_POST['user_ids'] : [];
    $ids = [];
    foreach ($rawIds as $rid) {
        $intId = (int)$rid;
        if ($intId > 0) {
            $ids[] = $intId;
        }
    }
    $ids = array_values(array_unique($ids));

    $action = (string)$_POST['bulk_action'];
    // 'approve' → active, 'reject' → suspended. Anything else is a no-op so a
    // malformed dropdown never triggers an UPDATE with a strange status.
    $targetStatus = null;
    if ($action === 'approve') {
        $targetStatus = 'active';
    } elseif ($action === 'reject') {
        $targetStatus = 'suspended';
    }

    if ($targetStatus === null) {
        $bulkMessage = 'Choose an action to apply.';
        $bulkType    = 'warning';
    } elseif (empty($ids)) {
        $bulkMessage = 'Select at least one held account first.';
        $bulkType    = 'warning';
    } else {
        // Confirm every id is currently on 'hold' — otherwise skip it. This
        // is the "defence against tampered form" step: even if the browser
        // sent an id for a user whose row was already actioned by another
        // admin, we won't overwrite their status.
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $verify = $conn->prepare("SELECT id, acct_email, firstname, lastname, acct_no, acct_type, acct_currency FROM users WHERE id IN ($placeholders) AND acct_status = 'hold'");
        $verify->execute($ids);
        $eligible = $verify->fetchAll(PDO::FETCH_ASSOC);
        $eligibleIds = array_column($eligible, 'id');

        if (empty($eligibleIds)) {
            $bulkMessage = 'None of the selected accounts are still on hold.';
            $bulkType    = 'warning';
        } else {
            $updatePlaceholders = implode(',', array_fill(0, count($eligibleIds), '?'));
            $update = $conn->prepare("UPDATE users SET acct_status = ? WHERE id IN ($updatePlaceholders) AND acct_status = 'hold'");
            $update->execute(array_merge([$targetStatus], $eligibleIds));

            // Best-effort welcome mail for newly-approved users. Never fail
            // the whole bulk action because of an SMTP hiccup, and skip
            // rows without an email address.
            if ($targetStatus === 'active') {
                $currencyMap = ['USD' => '$', 'Euro' => '€', 'Yuan' => '¥', 'GBP' => '£', 'CAD' => '¢', 'EUR' => '€'];
                foreach ($eligible as $u) {
                    if (empty($u['acct_email'])) {
                        continue;
                    }
                    try {
                        $currencySym = $currencyMap[(string)($u['acct_currency'] ?? 'USD')] ?? '$';
                        $fullName    = trim(($u['firstname'] ?? '') . ' ' . ($u['lastname'] ?? ''));
                        $APP_NAME    = $pageTitle;
                        $APP_URL     = WEB_URL;
                        $message     = $sendMail->regMsgUser(
                            $fullName,
                            (string)($u['acct_no'] ?? ''),
                            'active',
                            (string)($u['acct_email'] ?? ''),
                            '',
                            (string)($u['acct_type'] ?? ''),
                            '****',
                            $APP_NAME,
                            $APP_URL
                        );
                        $email_message->send_mail((string)$u['acct_email'], $message, "Welcome $fullName - $APP_NAME");
                    } catch (Throwable $e) {
                        error_log('[admin bulk approve] mail send failed: ' . $e->getMessage());
                    }
                }
            }

            $count = count($eligibleIds);
            $verb  = $targetStatus === 'active' ? 'approved' : 'rejected';
            $bulkMessage = "$count account(s) $verb.";
            $bulkType    = 'success';
        }
    }
}
?>

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
        <?php if ($bulkMessage !== null): ?>
            <div class="alert alert-<?= htmlspecialchars($bulkType) ?> alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <?= htmlspecialchars($bulkMessage) ?>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">User Accounts</h3>
                <div class="card-tools">
                    <a href="./reguser.php" class="btn btn-primary btn-sm"><i class="fas fa-user-plus"></i> New Account</a>
                </div>
            </div>
            <div class="card-body">
                <form method="post" id="bulk-users-form"
                      onsubmit="return confirm('Apply the selected bulk action to the checked accounts?');">
                    <div class="row align-items-center mb-3">
                        <div class="col-md-8">
                            <div class="form-inline">
                                <label class="mr-2 mb-0"><strong>Bulk actions (held accounts):</strong></label>
                                <select name="bulk_action" class="form-control form-control-sm mr-2" required>
                                    <option value="">-- select --</option>
                                    <option value="approve">Approve selected</option>
                                    <option value="reject">Reject selected</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-right">
                            <small class="text-muted"><span id="bulk-selected-count">0</span> selected</small>
                        </div>
                    </div>
                    <table class="table table-bordered table-striped data-table" style="width:100%">
                        <thead>
                            <tr>
                                <th style="width:36px">
                                    <input type="checkbox" id="bulk-select-all" title="Select all held accounts">
                                </th>
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
                            $status = strtolower((string)$row['acct_status']);
                            $isHold = $status === 'hold';
                            if ($status === 'active') {
                                $statusClass = 'badge-success';
                            } elseif ($isHold) {
                                $statusClass = 'badge-warning';
                            } else {
                                $statusClass = 'badge-secondary';
                            }
                        ?>
                            <tr>
                                <td>
                                    <?php if ($isHold): ?>
                                        <input type="checkbox" name="user_ids[]" value="<?= (int)$row['id'] ?>" class="bulk-user-checkbox">
                                    <?php else: ?>
                                        <input type="checkbox" disabled title="Only held accounts can be bulk-actioned">
                                    <?php endif; ?>
                                </td>
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
                </form>
            </div>
        </div>
    </div>
</section>

<script>
    // Bulk-select wiring lives here rather than the shared footer so this
    // page can be viewed without booting the DataTables plugin (checkboxes
    // still work even if the table script fails).
    (function () {
        var selectAll = document.getElementById('bulk-select-all');
        var counter   = document.getElementById('bulk-selected-count');
        function checkboxes() {
            return document.querySelectorAll('.bulk-user-checkbox');
        }
        function updateCount() {
            var n = 0;
            checkboxes().forEach(function (cb) { if (cb.checked) n++; });
            if (counter) counter.textContent = String(n);
        }
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes().forEach(function (cb) { cb.checked = selectAll.checked; });
                updateCount();
            });
        }
        document.addEventListener('change', function (e) {
            if (e.target && e.target.classList && e.target.classList.contains('bulk-user-checkbox')) {
                updateCount();
            }
        });
        updateCount();
    })();
</script>

<?php include_once("./layout/footer.php"); ?>
