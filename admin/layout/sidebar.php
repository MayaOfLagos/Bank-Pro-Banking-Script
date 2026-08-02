<?php
$active = function ($pages) use ($currentPage) {
    return in_array($currentPage, (array) $pages, true) ? 'active' : '';
};
$open = function ($pages) use ($currentPage) {
    return in_array($currentPage, (array) $pages, true) ? 'menu-open' : '';
};

$usersPages    = ['users.php', 'reguser.php', 'funduser.php', 'transfer.php', 'view_users.php'];
$txPages       = ['credit_debit_trans.php', 'withdraw-trans.php', 'wire-trans.php', 'crypto-transaction.php', 'domestic-trans.php', 'loan-trans.php', 'view-trans.php', 'view-domtrans.php', 'viewwire-trans.php', 'viewcrypto-trans.php', 'viewloan-trans.php', 'viewwithdraw.php', 'edit-trans.php'];
$cardPages     = ['cards.php', 'viewcard.php'];
$settingsPages = ['profile.php', 'crypto-currrency.php', 'deposits.php', 'settings.php'];
?>
<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="./dashboard.php" class="brand-link">
        <img src="../assets/settings/<?= htmlspecialchars($page['image']) ?>" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light"><?= htmlspecialchars($title) ?></span>
    </a>

    <div class="sidebar">
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="../assets/profile/<?= htmlspecialchars($row['image']) ?>" class="img-circle elevation-2" alt="Admin">
            </div>
            <div class="info">
                <a href="./profile.php" class="d-block"><?= htmlspecialchars($adminName) ?></a>
            </div>
        </div>

        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                <li class="nav-item">
                    <a href="./dashboard.php" class="nav-link <?= $active('dashboard.php') ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <li class="nav-item <?= $open($usersPages) ?>">
                    <a href="#" class="nav-link <?= $active($usersPages) ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>Users<i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="./users.php" class="nav-link <?= $active('users.php') ?>"><i class="far fa-circle nav-icon"></i><p>All Users</p></a></li>
                        <li class="nav-item"><a href="./reguser.php" class="nav-link <?= $active('reguser.php') ?>"><i class="far fa-circle nav-icon"></i><p>Create New Account</p></a></li>
                        <li class="nav-item"><a href="./funduser.php" class="nav-link <?= $active('funduser.php') ?>"><i class="far fa-circle nav-icon"></i><p>Credit / Debit User</p></a></li>
                        <li class="nav-item"><a href="./transfer.php" class="nav-link <?= $active('transfer.php') ?>"><i class="far fa-circle nav-icon"></i><p>Wire Transfer User</p></a></li>
                    </ul>
                </li>

                <li class="nav-item <?= $open($txPages) ?>">
                    <a href="#" class="nav-link <?= $active($txPages) ?>">
                        <i class="nav-icon fas fa-credit-card"></i>
                        <p>Transactions<i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="./credit_debit_trans.php" class="nav-link <?= $active('credit_debit_trans.php') ?>"><i class="far fa-circle nav-icon"></i><p>Credit / Debit</p></a></li>
                        <li class="nav-item"><a href="./withdraw-trans.php" class="nav-link <?= $active('withdraw-trans.php') ?>"><i class="far fa-circle nav-icon"></i><p>Withdraw</p></a></li>
                        <li class="nav-item"><a href="./wire-trans.php" class="nav-link <?= $active('wire-trans.php') ?>"><i class="far fa-circle nav-icon"></i><p>Wire</p></a></li>
                        <li class="nav-item"><a href="./crypto-transaction.php" class="nav-link <?= $active('crypto-transaction.php') ?>"><i class="far fa-circle nav-icon"></i><p>Crypto</p></a></li>
                        <li class="nav-item"><a href="./domestic-trans.php" class="nav-link <?= $active('domestic-trans.php') ?>"><i class="far fa-circle nav-icon"></i><p>Domestic</p></a></li>
                        <li class="nav-item"><a href="./loan-trans.php" class="nav-link <?= $active('loan-trans.php') ?>"><i class="far fa-circle nav-icon"></i><p>Loan Requests</p></a></li>
                    </ul>
                </li>

                <li class="nav-item <?= $open($cardPages) ?>">
                    <a href="#" class="nav-link <?= $active($cardPages) ?>">
                        <i class="nav-icon far fa-credit-card"></i>
                        <p>Cards<i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="./cards.php" class="nav-link <?= $active('cards.php') ?>"><i class="far fa-circle nav-icon"></i><p>Virtual Cards</p></a></li>
                    </ul>
                </li>

                <li class="nav-item <?= $open($settingsPages) ?>">
                    <a href="#" class="nav-link <?= $active($settingsPages) ?>">
                        <i class="nav-icon fas fa-cog"></i>
                        <p>Settings<i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item"><a href="./profile.php" class="nav-link <?= $active('profile.php') ?>"><i class="far fa-circle nav-icon"></i><p>Profile</p></a></li>
                        <li class="nav-item"><a href="./crypto-currrency.php" class="nav-link <?= $active('crypto-currrency.php') ?>"><i class="far fa-circle nav-icon"></i><p>Deposit Method</p></a></li>
                        <li class="nav-item"><a href="./deposits.php" class="nav-link <?= $active('deposits.php') ?>"><i class="far fa-circle nav-icon"></i><p>Virtual Deposit</p></a></li>
                        <li class="nav-item"><a href="./settings.php" class="nav-link <?= $active('settings.php') ?>"><i class="far fa-circle nav-icon"></i><p>Site Settings</p></a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="./logout.php" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>Logout</p>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</aside>
