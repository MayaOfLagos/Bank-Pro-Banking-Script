<script src="./plugins/jquery/jquery.min.js"></script>
<script src="./plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="./plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="./plugins/toastr/toastr.min.js"></script>
<script src="./dist/js/adminlte.min.js"></script>
<script>
$(function () {
    if (typeof toastr !== 'undefined') {
        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 4500,
            extendedTimeOut: 1500,
            preventDuplicates: true,
            newestOnTop: true
        };
        (window.__toasts || []).forEach(function (t) {
            var method = t[0], msg = t[1], title = t[2];
            if (typeof toastr[method] === 'function') { toastr[method](msg, title || ''); }
        });
        window.__toasts = [];
    }
});
</script>
</body>
</html>
