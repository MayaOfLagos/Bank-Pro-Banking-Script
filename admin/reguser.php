<?php
include_once("./layout/header.php");

// Whether the just-submitted POST created an account. Governs two things:
//   1. Whether to repopulate the wizard inputs with the submitted values
//      (a validation failure would otherwise lose 20+ fields of typing).
//   2. Which step the wizard opens on when the page re-renders.
$accountCreated = false;

if (isset($_POST['register'])) {
    $firstname        = inputValidation($_POST['firstname']);
    $acct_username    = uniqid();
    $lastname         = inputValidation($_POST['lastname']);
    $acct_limit       = inputValidation($_POST['acct_limit']);
    $limit_remain     = inputValidation($_POST['limit_remain']);
    // Account number: was "9909" . the first 6 digits of time()*rand(). On a
    // 32-bit build that product overflows to a float and number_format() renders
    // it in a form whose leading digits barely change between calls, and nothing
    // ever checked the result against the table — two customers could be
    // registered under the same account number, which is the key half the panel
    // looks accounts up by. Draw from a CSPRNG and confirm it is free.
    $acct_no = '';
    $lookup  = $conn->prepare("SELECT 1 FROM users WHERE acct_no = :acct_no LIMIT 1");
    for ($attempt = 0; $attempt < 25; $attempt++) {
        $candidate = '9909' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $lookup->execute(['acct_no' => $candidate]);
        if ($lookup->fetchColumn() === false) {
            $acct_no = $candidate;
            break;
        }
    }
    $ssn              = inputValidation($_POST['ssn']);
    $acct_balance     = inputValidation($_POST['acct_balance']);
    $avail_balance    = inputValidation($_POST['avail_balance']);
    $acct_type        = inputValidation($_POST['acct_type']);
    $acct_gender      = inputValidation($_POST['acct_gender']);
    $marital_status   = inputValidation($_POST['marital_status']);
    $acct_currency    = inputValidation($_POST['acct_currency']);
    $acct_email       = inputValidation($_POST['acct_email']);
    $acct_phone       = inputValidation($_POST['acct_phone']);
    $acct_occupation  = inputValidation($_POST['acct_occupation']);
    $acct_dob         = inputValidation($_POST['acct_dob']);
    $country          = inputValidation($_POST['country']);
    $state            = inputValidation($_POST['state']);
    $acct_address     = inputValidation($_POST['acct_address']);
    $acct_password    = inputValidation($_POST['acct_password']);
    $confirm_password = inputValidation($_POST['confirm_password']);
    $acct_cot         = inputValidation($_POST['acct_cot']);
    $acct_imf         = inputValidation($_POST['acct_imf']);
    $acct_tax         = inputValidation($_POST['acct_tax']);
    $acct_pin         = inputValidation($_POST['acct_pin']);
    $mgr_name         = inputValidation($_POST['mgr_name']);
    $mgr_no           = inputValidation($_POST['mgr_no']);
    $mgr_email        = inputValidation($_POST['mgr_email']);
    $mgr_id           = inputValidation($_POST['mgr_id']);
    $mgr_image        = inputValidation($_POST['mgr_image']);

    if ($acct_password !== $confirm_password) {
        toast_alert('error', 'Password does not match');
    } elseif ($acct_no === '') {
        toast_alert('error', 'Could not allocate a free account number after 25 attempts. Nothing was saved — try again.', 'Not registered');
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE acct_email=:acct_email");
        $stmt->execute(['acct_email' => $acct_email]);

        if ($stmt->rowCount() > 0) {
            toast_alert('error', 'Email already exists');
        } else {
            $registered = "INSERT INTO users (acct_username,firstname,lastname,acct_limit,limit_remain,acct_email,acct_password,acct_no,ssn,acct_balance,avail_balance,acct_type,acct_gender,marital_status,acct_currency,acct_phone,acct_occupation,country,state,acct_address,acct_dob,acct_cot,acct_imf,acct_pin,acct_tax,mgr_name,mgr_no,mgr_email,mgr_id,mgr_image) VALUES(:acct_username,:firstname,:lastname,:acct_limit,:limit_remain,:acct_email,:acct_password,:acct_no,:ssn,:acct_balance,:avail_balance,:acct_type,:acct_gender,:marital_status,:acct_currency,:acct_phone,:acct_occupation,:country,:state,:acct_address,:acct_dob,:acct_cot,:acct_imf,:acct_pin,:acct_tax,:mgr_name,:mgr_no,:mgr_email,:mgr_id,:mgr_image)";
            $reg = $conn->prepare($registered);
            $reg->execute([
                'acct_username'   => $acct_username,
                'firstname'       => $firstname,
                'lastname'        => $lastname,
                'acct_limit'      => $acct_limit,
                'limit_remain'    => $limit_remain,
                'acct_email'      => $acct_email,
                'acct_password'   => password_hash((string)$acct_password, PASSWORD_BCRYPT),
                'acct_no'         => $acct_no,
                'ssn'             => $ssn,
                'acct_balance'    => $acct_balance,
                'avail_balance'   => $avail_balance,
                'acct_type'       => $acct_type,
                'acct_gender'     => $acct_gender,
                'marital_status'  => $marital_status,
                'acct_currency'   => $acct_currency,
                'acct_phone'      => $acct_phone,
                'acct_occupation' => $acct_occupation,
                'country'         => $country,
                'state'           => $state,
                'acct_address'    => $acct_address,
                'acct_dob'        => $acct_dob,
                'acct_cot'        => $acct_cot,
                'acct_imf'        => $acct_imf,
                'acct_tax'        => $acct_tax,
                'acct_pin'        => $acct_pin,
                'mgr_name'        => $mgr_name,
                'mgr_no'          => $mgr_no,
                'mgr_email'       => $mgr_email,
                'mgr_id'          => $mgr_id,
                'mgr_image'       => $mgr_image,
            ]);

            $currencyMap = ['USD' => '$', 'Euro' => '€', 'Yuan' => '¥', 'GBP' => '£', 'CAD' => '¢'];
            $currency    = $currencyMap[$acct_currency] ?? '$';

            $fullName = $firstname . " " . $lastname;
            $email    = $acct_email;
            $APP_NAME = $pageTitle;
            $APP_URL  = WEB_URL;

            // Two bodies, not one. The customer's copy carries the temporary
            // password and PIN because they need them to sign in; the admin
            // copy passes $forAdmin = true, which swaps that block for a
            // notice. Sending the single shared body to WEB_EMAIL — as this
            // did — left every customer's plaintext password and transaction
            // PIN sitting in the shared operations mailbox in perpetuity,
            // even though admin/reguser.php only ever stores the bcrypt hash.
            $message = $sendMail->regMsg($currency, $acct_balance, $fullName, $acct_type, $acct_password, $APP_NAME, $APP_URL, $BANK_PHONE, $acct_no, $acct_pin);
            $adminCopy = $sendMail->regMsg($currency, $acct_balance, $fullName, $acct_type, $acct_password, $APP_NAME, $APP_URL, $BANK_PHONE, $acct_no, $acct_pin, true);
            $email_message->send_mail($email, $message, "Welcome $fullName - $APP_NAME");
            $email_message->send_mail(WEB_EMAIL, $adminCopy, "[New Account Registered] $fullName - $APP_NAME");

            if (function_exists('audit_log')) {
                // target_id is the newly-issued account number since we
                // don't cheaply have the AUTO_INCREMENT id back here — the
                // acct_no is human-searchable in the audit viewer anyway.
                audit_log('user.created', 'user', (string)$acct_no, [
                    'name'         => $fullName,
                    'acct_email'   => $email,
                    'acct_type'    => $acct_type,
                    'acct_currency' => $acct_currency,
                    'acct_balance' => $acct_balance,
                ]);
            }

            // Operator feed only. The customer already gets the welcome email
            // with their credentials; a bell notification on an account they
            // have not signed into yet has nowhere to be read.
            notify_admin(
                $conn,
                'admin.user_created',
                'New customer account created',
                admin_actor_name() . ' registered ' . $fullName . ' (' . $acct_no . ', ' . $acct_type . ').',
                array(
                    'severity'            => 'info',
                    'link'                => '/admin/view_users.php',
                    'source'              => 'admin',
                    'created_by_admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
                    'meta'                => array('acct_no' => (string)$acct_no, 'acct_type' => $acct_type),
                )
            );

            $accountCreated = true;
            toast_alert('success', 'Account Created Successfully', 'Approved');
        }
    }
}

// Repopulate the wizard when a POST failed mid-validation so the operator does
// not lose 20+ fields of typing over a duplicate email or a mistyped password.
$fill = (isset($_POST['register']) && !$accountCreated) ? $_POST : [];
$val = static function ($key, $default = '') use ($fill) {
    return isset($fill[$key]) ? (string)$fill[$key] : $default;
};
$sel = static function ($key, $optionValue) use ($fill) {
    return (isset($fill[$key]) && (string)$fill[$key] === (string)$optionValue) ? 'selected' : '';
};
?>

<!-- bs-stepper is bundled at admin/plugins/bs-stepper/ (already used by
     AdminLTE's own /forms/wizard.html demo). Loaded here rather than in
     layout/header.php because no other admin page needs it. -->
<link rel="stylesheet" href="./plugins/bs-stepper/css/bs-stepper.min.css">
<style>
    .bs-stepper .step-trigger { padding: 12px 14px; }
    .bs-stepper .bs-stepper-header { padding: 0.75rem 0.25rem; }
    .bs-stepper-content > form > .content { padding: 1rem 0.25rem 0; }
    /* On narrow phones the labels wrap under the circles and eat vertical
       space — the circles alone still communicate position. */
    @media (max-width: 575.98px) {
        .bs-stepper .bs-stepper-label { display: none; }
        .bs-stepper .step-trigger { padding: 10px 8px; }
    }
    .wizard-hidden-defaults { display: none; }
</style>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Register New Account</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">New Account</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Customer Details</h3></div>
            <div class="card-body">
                <div id="regWizard" class="bs-stepper">
                    <div class="bs-stepper-header" role="tablist">
                        <div class="step" data-target="#step-identity">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-identity" id="step-identity-trigger">
                                <span class="bs-stepper-circle">1</span>
                                <span class="bs-stepper-label">Identity</span>
                            </button>
                        </div>
                        <div class="line"></div>
                        <div class="step" data-target="#step-contact">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-contact" id="step-contact-trigger">
                                <span class="bs-stepper-circle">2</span>
                                <span class="bs-stepper-label">Contact &amp; address</span>
                            </button>
                        </div>
                        <div class="line"></div>
                        <div class="step" data-target="#step-account">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-account" id="step-account-trigger">
                                <span class="bs-stepper-circle">3</span>
                                <span class="bs-stepper-label">Account</span>
                            </button>
                        </div>
                        <div class="line"></div>
                        <div class="step" data-target="#step-access">
                            <button type="button" class="step-trigger" role="tab" aria-controls="step-access" id="step-access-trigger">
                                <span class="bs-stepper-circle">4</span>
                                <span class="bs-stepper-label">Manager &amp; password</span>
                            </button>
                        </div>
                    </div>

                    <div class="bs-stepper-content">
                        <!-- novalidate: HTML5 validation cannot focus fields
                             inside hidden bs-stepper panes, so we drive
                             checkValidity() ourselves per step below. -->
                        <form method="post" novalidate autocomplete="off">

                            <!-- Hidden defaults preserved from the pre-wizard
                                 form. mgr_image is a filename placeholder used
                                 by the customer's Account Manager screen;
                                 acct_limit / limit_remain seed the transfer
                                 allowance; ssn stays unpopulated (the customer
                                 app does not display it anywhere). -->
                            <div class="wizard-hidden-defaults">
                                <input type="hidden" name="marital_status" value="<?= htmlspecialchars($val('marital_status', 'single')) ?>" data-hidden-fallback>
                                <input type="hidden" name="acct_limit" value="<?= htmlspecialchars($val('acct_limit', '500000')) ?>">
                                <input type="hidden" name="limit_remain" value="<?= htmlspecialchars($val('limit_remain', '500000')) ?>">
                                <input type="hidden" name="ssn" value="<?= htmlspecialchars($val('ssn', '')) ?>">
                                <input type="hidden" name="mgr_image" value="<?= htmlspecialchars($val('mgr_image', 'account1.png')) ?>">
                            </div>

                            <!-- ─── Step 1: Identity ───────────────────── -->
                            <div id="step-identity" class="content" role="tabpanel" aria-labelledby="step-identity-trigger">
                                <div class="row">
                                    <div class="col-md-6"><div class="form-group"><label>First name</label><input type="text" name="firstname" class="form-control" placeholder="First name" value="<?= htmlspecialchars($val('firstname')) ?>" required></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Last name</label><input type="text" name="lastname" class="form-control" placeholder="Last name" value="<?= htmlspecialchars($val('lastname')) ?>" required></div></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Gender</label>
                                            <select name="acct_gender" class="form-control" required>
                                                <option value="">Select gender</option>
                                                <option value="female" <?= $sel('acct_gender', 'female') ?>>Female</option>
                                                <option value="male"   <?= $sel('acct_gender', 'male')   ?>>Male</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6"><div class="form-group"><label>Date of birth</label><input type="date" name="acct_dob" class="form-control" value="<?= htmlspecialchars($val('acct_dob')) ?>" required></div></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Marital status</label>
                                            <select name="marital_status_visible" class="form-control" data-mirrors="marital_status" required>
                                                <?php foreach (['single','married','divorced','widowed','separated','prefer_not_to_say'] as $m): ?>
                                                    <option value="<?= $m ?>" <?= (string)($fill['marital_status'] ?? 'single') === $m ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(str_replace('_', ' ', $m))) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6"><div class="form-group"><label>Occupation</label><input type="text" name="acct_occupation" class="form-control" placeholder="Occupation" value="<?= htmlspecialchars($val('acct_occupation')) ?>" required></div></div>
                                </div>
                                <div class="d-flex justify-content-end pt-3">
                                    <button type="button" class="btn btn-primary" data-action="next">Next <i class="fas fa-arrow-right ml-1"></i></button>
                                </div>
                            </div>

                            <!-- ─── Step 2: Contact & address ──────────── -->
                            <div id="step-contact" class="content" role="tabpanel" aria-labelledby="step-contact-trigger">
                                <div class="row">
                                    <div class="col-md-6"><div class="form-group"><label>Email</label><input type="email" name="acct_email" class="form-control" placeholder="name@example.com" value="<?= htmlspecialchars($val('acct_email')) ?>" required></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Phone</label><input type="text" name="acct_phone" class="form-control" placeholder="Phone" value="<?= htmlspecialchars($val('acct_phone')) ?>" required></div></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Country</label>
                                            <select name="country" class="form-control wizard-select2" data-current="<?= htmlspecialchars($val('country')) ?>" required>
                                                <option value="">Select country</option>
                                                <?php include __DIR__ . '/layout/_countries.php'; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6"><div class="form-group"><label>State / Region</label><input type="text" name="state" class="form-control" placeholder="State" value="<?= htmlspecialchars($val('state')) ?>" required></div></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12"><div class="form-group"><label>Address</label><textarea name="acct_address" rows="2" class="form-control" placeholder="Address" required><?= htmlspecialchars($val('acct_address')) ?></textarea></div></div>
                                </div>
                                <div class="d-flex justify-content-between pt-3">
                                    <button type="button" class="btn btn-secondary" data-action="prev"><i class="fas fa-arrow-left mr-1"></i> Back</button>
                                    <button type="button" class="btn btn-primary" data-action="next">Next <i class="fas fa-arrow-right ml-1"></i></button>
                                </div>
                            </div>

                            <!-- ─── Step 3: Account ───────────────────── -->
                            <div id="step-account" class="content" role="tabpanel" aria-labelledby="step-account-trigger">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Account type</label>
                                            <select name="acct_type" class="form-control" required>
                                                <option value="">Select account type</option>
                                                <option value="Savings"             <?= $sel('acct_type', 'Savings')             ?>>Savings Account</option>
                                                <option value="Current"             <?= $sel('acct_type', 'Current')             ?>>Current Account</option>
                                                <option value="Checkings"           <?= $sel('acct_type', 'Checkings')           ?>>Checking Account</option>
                                                <option value="Fixed Deposit"       <?= $sel('acct_type', 'Fixed Deposit')       ?>>Fixed Deposit</option>
                                                <option value="Non Resident"        <?= $sel('acct_type', 'Non Resident')        ?>>Non Resident Account</option>
                                                <option value="Online Banking"      <?= $sel('acct_type', 'Online Banking')      ?>>Online Banking</option>
                                                <option value="Domicilary Account"  <?= $sel('acct_type', 'Domicilary Account')  ?>>Domicilary Account</option>
                                                <option value="Joint Account"       <?= $sel('acct_type', 'Joint Account')       ?>>Joint Account</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Currency</label>
                                            <select name="acct_currency" class="form-control" required>
                                                <option value="">Account currency</option>
                                                <?php foreach (['USD','Euro','Yuan','GBP','CAD'] as $c): ?>
                                                    <option value="<?= $c ?>" <?= $sel('acct_currency', $c) ?>><?= $c ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6"><div class="form-group"><label>Opening balance</label><input type="number" step="0.01" name="acct_balance" class="form-control" placeholder="0.00" value="<?= htmlspecialchars($val('acct_balance')) ?>" required></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Pending balance</label><input type="number" step="0.01" name="avail_balance" class="form-control" placeholder="0.00" value="<?= htmlspecialchars($val('avail_balance')) ?>" required></div></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3"><div class="form-group"><label>COT code</label><input type="text" name="acct_cot" class="form-control" placeholder="COT" value="<?= htmlspecialchars($val('acct_cot')) ?>" required></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>IMF code</label><input type="text" name="acct_imf" class="form-control" placeholder="IMF" value="<?= htmlspecialchars($val('acct_imf')) ?>" required></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>TAX code</label><input type="text" name="acct_tax" class="form-control" placeholder="TAX" value="<?= htmlspecialchars($val('acct_tax')) ?>" required></div></div>
                                    <div class="col-md-3"><div class="form-group"><label>Transaction PIN</label><input type="text" name="acct_pin" class="form-control" placeholder="1234" pattern="\d{4}" maxlength="4" inputmode="numeric" value="<?= htmlspecialchars($val('acct_pin')) ?>" required></div></div>
                                </div>
                                <div class="d-flex justify-content-between pt-3">
                                    <button type="button" class="btn btn-secondary" data-action="prev"><i class="fas fa-arrow-left mr-1"></i> Back</button>
                                    <button type="button" class="btn btn-primary" data-action="next">Next <i class="fas fa-arrow-right ml-1"></i></button>
                                </div>
                            </div>

                            <!-- ─── Step 4: Manager & password ─────────── -->
                            <div id="step-access" class="content" role="tabpanel" aria-labelledby="step-access-trigger">
                                <h5>Account manager</h5>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6"><div class="form-group"><label>Manager name</label><input type="text" name="mgr_name" class="form-control" value="<?= htmlspecialchars($val('mgr_name')) ?>" required></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Manager phone</label><input type="text" name="mgr_no" class="form-control" value="<?= htmlspecialchars($val('mgr_no')) ?>" required></div></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6"><div class="form-group"><label>Manager email</label><input type="email" name="mgr_email" class="form-control" value="<?= htmlspecialchars($val('mgr_email')) ?>" required></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Manager ID</label><input type="text" name="mgr_id" class="form-control" value="<?= htmlspecialchars($val('mgr_id')) ?>" required></div></div>
                                </div>

                                <h5 class="mt-4">Sign-in password</h5>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6"><div class="form-group"><label>Password</label><input type="password" name="acct_password" class="form-control" placeholder="Password" required></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Confirm password</label><input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required></div></div>
                                </div>
                                <p class="text-muted small mb-0">The customer will receive this password and the transaction PIN by email once the account is created.</p>

                                <div class="d-flex justify-content-between pt-3">
                                    <button type="button" class="btn btn-secondary" data-action="prev"><i class="fas fa-arrow-left mr-1"></i> Back</button>
                                    <button type="submit" class="btn btn-success" name="register" value="1">
                                        <i class="fas fa-user-plus mr-1"></i> Create account
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="./plugins/bs-stepper/js/bs-stepper.min.js"></script>
<script>
(function () {
    // Kick off after DOM ready and after footer.php has initialised jQuery/Select2.
    document.addEventListener('DOMContentLoaded', function () {
        var wizardEl = document.getElementById('regWizard');
        if (!wizardEl || typeof Stepper === 'undefined') { return; }

        var stepper = new Stepper(wizardEl, {
            linear: true,   // steps can only advance in order
            animation: true
        });

        // ── Marital status: the visible <select> mirrors into the hidden
        // input the PHP handler reads. Kept as two inputs (rather than one
        // named "marital_status") so the pre-wizard hidden default still
        // wins for edge cases like server-side POSTs from other pages.
        var maritalVisible = wizardEl.querySelector('[data-mirrors="marital_status"]');
        var maritalHidden  = wizardEl.querySelector('input[type="hidden"][name="marital_status"]');
        if (maritalVisible && maritalHidden) {
            var syncMarital = function () { maritalHidden.value = maritalVisible.value || 'single'; };
            maritalVisible.addEventListener('change', syncMarital);
            syncMarital();
        }

        // ── Per-step client validation. Native checkValidity() only, so this
        // stays in step with the "required" markers already on each input —
        // no separate schema to drift.
        function validateCurrentPane() {
            var panes = wizardEl.querySelectorAll('.bs-stepper-content .content');
            var idx = 0;
            for (var i = 0; i < panes.length; i++) {
                if (panes[i].classList.contains('active')) { idx = i; break; }
            }
            var pane = panes[idx];
            var fields = pane.querySelectorAll('input, select, textarea');
            var ok = true;
            var firstBad = null;
            for (var j = 0; j < fields.length; j++) {
                if (!fields[j].checkValidity()) {
                    ok = false;
                    if (!firstBad) { firstBad = fields[j]; }
                }
            }
            if (!ok && firstBad) {
                firstBad.reportValidity();
            }
            return ok;
        }

        wizardEl.querySelectorAll('[data-action="next"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (validateCurrentPane()) { stepper.next(); }
            });
        });
        wizardEl.querySelectorAll('[data-action="prev"]').forEach(function (btn) {
            btn.addEventListener('click', function () { stepper.previous(); });
        });

        // Final submit: validate the last pane too. If the pane is clean the
        // form submits normally through the button's default action.
        var form = wizardEl.querySelector('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                if (!validateCurrentPane()) {
                    e.preventDefault();
                }
            });
        }

        // ── Select2 on the Country dropdown.
        // The plugin needs the element to be visible to compute its dropdown
        // width, and steps 2-4 are display:none at first paint. Bind lazily on
        // pane show and destroy on hide so re-entering a step doesn't stack
        // instances.
        function initSelect2In(pane) {
            if (!window.jQuery || !pane) { return; }
            var $ = window.jQuery;
            $(pane).find('.wizard-select2').each(function () {
                var $el = $(this);
                if ($el.data('select2')) { $el.select2('destroy'); }
                $el.select2({
                    theme: 'bootstrap4',
                    dropdownParent: $(pane),
                    width: '100%'
                });
                var want = $el.attr('data-current') || '';
                if (want) { $el.val(want).trigger('change'); }
            });
        }
        wizardEl.addEventListener('shown.bs-stepper', function (event) {
            var pane = event.detail && event.detail.indexStep !== undefined
                ? wizardEl.querySelectorAll('.bs-stepper-content .content')[event.detail.indexStep]
                : null;
            initSelect2In(pane);
        });

        <?php if (isset($_POST['register']) && !$accountCreated): ?>
        // Server-side rejection just landed. Jump to the step whose fields
        // failed so the operator sees the toast in context — otherwise the
        // wizard would sit back on step 1 and hide the actual problem.
        (function () {
            <?php
                // Map the two known validation failures to their pane index.
                // Duplicate email lives on step 2 (Contact), password mismatch
                // on step 4 (Manager & password).
                $jumpTo = 0;
                if (isset($_POST['acct_password'], $_POST['confirm_password'])
                    && $_POST['acct_password'] !== $_POST['confirm_password']) {
                    $jumpTo = 3;
                } else {
                    // Assume email conflict / any other server-side failure —
                    // step 2 is where the operator can meaningfully correct
                    // course (change email) or use Back to revisit earlier
                    // steps.
                    $jumpTo = 1;
                }
            ?>
            try { stepper.to(<?= (int)$jumpTo + 1 ?>); } catch (e) { /* swallow */ }
        })();
        <?php endif; ?>
    });
})();
</script>

<?php include_once("./layout/footer.php"); ?>
