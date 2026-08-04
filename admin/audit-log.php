<?php
include_once("./layout/header.php");

/**
 * Admin audit log viewer.
 *
 * Reads from admin_audit_log (see SQL File/migrations/2026_08_04_02_admin_audit_log.sql).
 * Filters: date range, action, admin, target search. Paginated 50 rows/page.
 *
 * Defensive on schema: if the table is missing (migration hasn't been run
 * yet), render a clear notice instead of blowing up the whole admin panel.
 */

// -------- Table presence check ---------------------------------------------
$tableExists = false;
try {
    $probe = $conn->query("SHOW TABLES LIKE 'admin_audit_log'");
    $tableExists = ($probe && $probe->fetch() !== false);
} catch (\Throwable $e) {
    $tableExists = false;
}

// -------- Filter inputs (all optional) -------------------------------------
$filterDateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$filterDateTo   = isset($_GET['date_to'])   ? trim($_GET['date_to'])   : '';
$filterAction   = isset($_GET['action'])    ? trim($_GET['action'])    : '';
$filterAdminId  = isset($_GET['admin_id'])  ? (int)$_GET['admin_id']   : 0;
$filterTarget   = isset($_GET['target'])    ? trim($_GET['target'])    : '';

// Basic date-format guard — YYYY-MM-DD. If invalid, drop silently.
if ($filterDateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateFrom)) {
    $filterDateFrom = '';
}
if ($filterDateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateTo)) {
    $filterDateTo = '';
}

// -------- Pagination -------------------------------------------------------
$perPage = 50;
$page_no = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset  = ($page_no - 1) * $perPage;

// -------- Build WHERE + params --------------------------------------------
$where  = [];
$params = [];

if ($filterDateFrom !== '') {
    $where[] = 'created_at >= :date_from';
    $params['date_from'] = $filterDateFrom . ' 00:00:00';
}
if ($filterDateTo !== '') {
    $where[] = 'created_at <= :date_to';
    $params['date_to'] = $filterDateTo . ' 23:59:59';
}
if ($filterAction !== '') {
    $where[] = 'action = :action';
    $params['action'] = $filterAction;
}
if ($filterAdminId > 0) {
    $where[] = 'admin_id = :admin_id';
    $params['admin_id'] = $filterAdminId;
}
if ($filterTarget !== '') {
    $where[] = '(target_id LIKE :target OR target_type LIKE :target)';
    $params['target'] = '%' . $filterTarget . '%';
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// -------- Query rows + total ----------------------------------------------
$rows       = [];
$totalRows  = 0;
$actions    = [];
$admins     = [];
$queryError = null;

if ($tableExists) {
    try {
        // Dropdown data — cheap, tiny, only distinct values ever used so far.
        $actions = $conn->query("SELECT DISTINCT action FROM admin_audit_log ORDER BY action")
            ->fetchAll(PDO::FETCH_COLUMN);
        $admins  = $conn->query("SELECT DISTINCT admin_id, admin_name FROM admin_audit_log ORDER BY admin_name")
            ->fetchAll(PDO::FETCH_ASSOC);

        // Total (for pagination + summary)
        $countSql  = "SELECT COUNT(*) FROM admin_audit_log" . $whereSql;
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();

        // Page slice — LIMIT/OFFSET as bound integers (bindValue for
        // PDO::PARAM_INT so MySQL sees a real integer literal).
        $sql = "SELECT * FROM admin_audit_log" . $whereSql . " ORDER BY created_at DESC, id DESC LIMIT :lim OFFSET :off";
        $stmt = $conn->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        $queryError = $e->getMessage();
    }
}

$totalPages = $perPage > 0 ? (int)max(1, ceil($totalRows / $perPage)) : 1;

// Helper: rebuild the current filter query string for pagination links.
$baseParams = [
    'date_from' => $filterDateFrom,
    'date_to'   => $filterDateTo,
    'action'    => $filterAction,
    'admin_id'  => $filterAdminId > 0 ? $filterAdminId : '',
    'target'    => $filterTarget,
];
$pageLink = function ($p) use ($baseParams) {
    $q = $baseParams;
    $q['p'] = $p;
    return '?' . http_build_query(array_filter($q, function ($v) { return $v !== '' && $v !== 0; }));
};
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Audit Log</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Audit Log</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">

        <?php if (!$tableExists): ?>
            <div class="alert alert-warning">
                <h5><i class="icon fas fa-exclamation-triangle"></i> Audit log table is missing.</h5>
                Run the migration <code>SQL File/migrations/2026_08_04_02_admin_audit_log.sql</code> on your database to create the <code>admin_audit_log</code> table, then reload this page.
            </div>
        <?php elseif ($queryError): ?>
            <div class="alert alert-danger">
                <h5><i class="icon fas fa-times-circle"></i> Failed to load audit log.</h5>
                <?= htmlspecialchars($queryError) ?>
            </div>
        <?php else: ?>

            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title">Filters</h3></div>
                <form method="get" class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <label>Date From</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filterDateFrom) ?>">
                        </div>
                        <div class="col-md-2">
                            <label>Date To</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filterDateTo) ?>">
                        </div>
                        <div class="col-md-3">
                            <label>Action</label>
                            <select name="action" class="form-control">
                                <option value="">— All actions —</option>
                                <?php foreach ($actions as $a): ?>
                                    <option value="<?= htmlspecialchars($a) ?>" <?= $filterAction === $a ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($a) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Admin</label>
                            <select name="admin_id" class="form-control">
                                <option value="">— All admins —</option>
                                <?php foreach ($admins as $a): ?>
                                    <?php $aid = (int)$a['admin_id']; ?>
                                    <option value="<?= $aid ?>" <?= $filterAdminId === $aid ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($a['admin_name'] ?: ('#' . $aid)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Target (id/type)</label>
                            <input type="text" name="target" class="form-control" placeholder="e.g. 42 or user" value="<?= htmlspecialchars($filterTarget) ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                        <a href="./audit-log.php" class="btn btn-default">Reset</a>
                        <span class="text-muted ml-2"><?= (int)$totalRows ?> matching row<?= $totalRows === 1 ? '' : 's' ?></span>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Entries — page <?= (int)$page_no ?> of <?= (int)$totalPages ?></h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Admin</th>
                                <th>Action</th>
                                <th>Target</th>
                                <th>Details</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No entries match these filters.</td></tr>
                        <?php else: foreach ($rows as $r):
                            $details = $r['details'];
                            // Pretty-print JSON when possible; fall back to raw string.
                            if (is_string($details) && $details !== '') {
                                $decoded = json_decode($details, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $details = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                }
                            }
                        ?>
                            <tr>
                                <td><small><?= htmlspecialchars($r['created_at']) ?></small></td>
                                <td>
                                    <?= htmlspecialchars($r['admin_name'] ?: ('#' . (int)$r['admin_id'])) ?>
                                    <br><small class="text-muted">id: <?= (int)$r['admin_id'] ?></small>
                                </td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($r['action']) ?></span></td>
                                <td>
                                    <?php if ($r['target_type'] || $r['target_id']): ?>
                                        <?= htmlspecialchars((string)$r['target_type']) ?>
                                        <?php if ($r['target_id']): ?>
                                            <br><small class="text-muted">#<?= htmlspecialchars((string)$r['target_id']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($details): ?>
                                        <pre class="mb-0" style="font-size:11px; max-height:150px; overflow:auto; white-space:pre-wrap;"><?= htmlspecialchars((string)$details) ?></pre>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= htmlspecialchars((string)$r['ip_address']) ?></small></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="card-footer clearfix">
                    <ul class="pagination pagination-sm m-0 float-right">
                        <?php
                        $window = 3;
                        $start  = max(1, $page_no - $window);
                        $end    = min($totalPages, $page_no + $window);
                        ?>
                        <li class="page-item <?= $page_no <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= htmlspecialchars($pageLink(max(1, $page_no - 1))) ?>">«</a>
                        </li>
                        <?php if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($pageLink(1)) ?>">1</a></li>
                            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $page_no ? 'active' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars($pageLink($i)) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($pageLink($totalPages)) ?>"><?= $totalPages ?></a></li>
                        <?php endif; ?>
                        <li class="page-item <?= $page_no >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= htmlspecialchars($pageLink(min($totalPages, $page_no + 1))) ?>">»</a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
