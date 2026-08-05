    </div>
    <!-- /.content-wrapper -->

    <footer class="main-footer">
        <strong>Copyright &copy; <?= date('Y') ?> <?= htmlspecialchars($title ?? 'Admin') ?>.</strong>
        All rights reserved.
        <div class="float-right d-none d-sm-inline-block">
            <b>Admin</b> Powered by AdminLTE 3
        </div>
    </footer>
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="./plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="./plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- DataTables  & Plugins -->
<script src="./plugins/datatables/jquery.dataTables.min.js"></script>
<script src="./plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="./plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="./plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<!-- Select2 -->
<script src="./plugins/select2/js/select2.full.min.js"></script>
<!-- SweetAlert2 -->
<script src="./plugins/sweetalert2/sweetalert2.min.js"></script>
<!-- Toastr -->
<script src="./plugins/toastr/toastr.min.js"></script>
<!-- AdminLTE -->
<script src="./dist/js/adminlte.min.js"></script>

<?php
if (!empty($_SESSION['admin_welcome_name'])) {
    $welcomeName = htmlspecialchars($_SESSION['admin_welcome_name'], ENT_QUOTES);
    echo "<script>(window.__toasts = window.__toasts || []).push(['success', 'Welcome back, {$welcomeName}!', 'Login Successful']);</script>";
    unset($_SESSION['admin_welcome_name']);
}

// Drain toasts queued by handlers that redirected (see toast_flash()). Same
// JSON_HEX_TAG escaping as toast_alert() so a filename or customer name in the
// message cannot break out of this <script> block.
if (!empty($_SESSION['admin_toasts']) && is_array($_SESSION['admin_toasts'])) {
    $flashFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
    foreach ($_SESSION['admin_toasts'] as $flash) {
        $flashType = in_array($flash[0] ?? '', ['success', 'error', 'warning', 'info'], true) ? $flash[0] : 'info';
        echo "<script>(window.__toasts = window.__toasts || []).push("
           . json_encode([$flashType, (string)($flash[1] ?? ''), (string)($flash[2] ?? '')], $flashFlags)
           . ");</script>";
    }
    unset($_SESSION['admin_toasts']);
}
?>
<script>
    $(function () {
        $('.data-table').DataTable({
            "responsive": true,
            "autoWidth": false
        });
        $('.select2').select2({ theme: 'bootstrap4' });

        if (typeof toastr !== 'undefined') {
            toastr.options = {
                closeButton: true,
                progressBar: true,
                positionClass: 'toast-top-right',
                timeOut: 4000,
                extendedTimeOut: 1500,
                preventDuplicates: true,
                newestOnTop: true
            };

            (window.__toasts || []).forEach(function (t) {
                var method = t[0], msg = t[1], title = t[2];
                if (typeof toastr[method] === 'function') {
                    toastr[method](msg, title);
                }
            });
            window.__toasts = [];
        }
    });
</script>
</body>
</html>
