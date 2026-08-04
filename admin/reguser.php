<?php
include_once("./layout/header.php");

if (isset($_POST['register'])) {
    $firstname        = inputValidation($_POST['firstname']);
    $acct_username    = uniqid();
    $lastname         = inputValidation($_POST['lastname']);
    $acct_limit       = inputValidation($_POST['acct_limit']);
    $limit_remain     = inputValidation($_POST['limit_remain']);
    $acct_no          = "9909" . substr(number_format(time() * rand(), 0, '', ''), 0, 6);
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

            $message = $sendMail->regMsg($currency, $acct_balance, $fullName, $acct_type, $acct_password, $APP_NAME, $APP_URL, $BANK_PHONE, $acct_no, $acct_pin);
            $email_message->send_mail($email, $message, "Welcome $fullName - $APP_NAME");
            $email_message->send_mail(WEB_EMAIL, $message, "[New Account Registered] $fullName - $APP_NAME");

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

            toast_alert('success', 'Account Created Successfully', 'Approved');
        }
    }
}
?>

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
            <form method="post" autocomplete="off">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>First Name</label><input type="text" name="firstname" class="form-control" placeholder="First Name" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Last Name</label><input type="text" name="lastname" class="form-control" placeholder="Last Name" required></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Account Balance</label><input type="number" step="0.01" name="acct_balance" class="form-control" placeholder="Account Balance" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Pending Balance</label><input type="number" step="0.01" name="avail_balance" class="form-control" placeholder="Pending Balance" required><input type="hidden" name="ssn"></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Account Type</label>
                                <select name="acct_type" class="form-control" required>
                                    <option value="">Select Account Type</option>
                                    <option value="Savings">Savings Account</option>
                                    <option value="Current">Current Account</option>
                                    <option value="Checkings">Checking Account</option>
                                    <option value="Fixed Deposit">Fixed Deposit</option>
                                    <option value="Non Resident">Non Resident Account</option>
                                    <option value="Online Banking">Online Banking</option>
                                    <option value="Domicilary Account">Domicilary Account</option>
                                    <option value="Joint Account">Joint Account</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Gender</label>
                                <select name="acct_gender" class="form-control" required>
                                    <option value="">Select Gender</option>
                                    <option value="female">Female</option>
                                    <option value="male">Male</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Address</label>
                                <input type="text" name="acct_address" class="form-control" placeholder="Address" required>
                                <input type="hidden" name="marital_status" value="single">
                                <input type="hidden" name="acct_limit" value="500000">
                                <input type="hidden" name="limit_remain" value="500000">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Currency</label>
                                <select name="acct_currency" class="form-control" required>
                                    <option value="">Account Currency</option>
                                    <option value="USD">USD</option>
                                    <option value="Euro">Euro</option>
                                    <option value="Yuan">Yuan</option>
                                    <option value="GBP">GBP</option>
                                    <option value="CAD">CAD</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Email</label><input type="email" name="acct_email" class="form-control" placeholder="Email" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Phone</label><input type="text" name="acct_phone" class="form-control" placeholder="Phone" required></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Occupation</label><input type="text" name="acct_occupation" class="form-control" placeholder="Occupation" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Date of Birth</label><input type="date" name="acct_dob" class="form-control" required></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Country</label>
                                <select name="country" class="form-control select2" required>
                                    <option value="">Select Country</option>
                                    <?php include __DIR__ . '/layout/_countries.php'; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6"><div class="form-group"><label>State</label><input type="text" name="state" class="form-control" placeholder="State" required></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3"><div class="form-group"><label>COT Code</label><input type="text" name="acct_cot" class="form-control" placeholder="COT" required></div></div>
                        <div class="col-md-3"><div class="form-group"><label>IMF Code</label><input type="text" name="acct_imf" class="form-control" placeholder="IMF" required></div></div>
                        <div class="col-md-3"><div class="form-group"><label>TAX Code</label><input type="text" name="acct_tax" class="form-control" placeholder="TAX" required></div></div>
                        <div class="col-md-3"><div class="form-group"><label>Account PIN</label><input type="text" name="acct_pin" class="form-control" placeholder="1234" required></div></div>
                    </div>

                    <h5 class="mt-4">Account Manager</h5>
                    <hr>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Manager Name</label><input type="text" name="mgr_name" class="form-control" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Manager Phone</label><input type="text" name="mgr_no" class="form-control" required></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Manager Email</label><input type="email" name="mgr_email" class="form-control" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Manager ID</label><input type="text" name="mgr_id" class="form-control" required><input type="hidden" name="mgr_image" value="account1.png"></div></div>
                    </div>

                    <h5 class="mt-4">Password</h5>
                    <hr>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Password</label><input type="password" name="acct_password" class="form-control" placeholder="Password" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required></div></div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" name="register" class="btn btn-primary"><i class="fas fa-user-plus"></i> Create Account</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
