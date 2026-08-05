<?php
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/smtp.php';

class USER{
    private $conn;

    public function add_transaction($data){
        $amount        = $data['amount'];
        $acct_id       = $data['acct_id'];
        $image         = $data['image'];
        $wallet_address = $data['wallet_address'];
        $crypto_id     = $data['crypto_id'];

        $this->conn->prepare("INSERT INTO deposit (amount,user_id,image,wallet_address,crypto_id)VALUES('$amount','$acct_id','$image','$wallet_address','$crypto_id') ");

        return $this->conn->lastInsertId();
    }
}

class pageTitle{
    public $dashboard = 'Dashboard';
    public function getDashboard(){
        return $this->dashboard;
    }
}

class emailMessage{
    private $settings;

    public function __construct(array $settings = array()){
        $this->settings = $settings;
    }

    private function _e(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function _footerEmail(): string {
        $e = trim((string)($this->settings['url_email'] ?? (defined('WEB_EMAIL') ? WEB_EMAIL : '')));
        return $this->_e($e);
    }

    private function _footerPhone(): string {
        return $this->_e(trim((string)($this->settings['url_tel'] ?? '')));
    }

    private function _footerAddress(): string {
        return trim((string)($this->settings['about_us'] ?? ''));
    }

    private function _logoUrl(): string {
        $base = defined('WEB_URL') ? rtrim((string)WEB_URL, '/') : '';
        return $base . '/assets/settings/logo.png';
    }

    private function _table(array $rows): string {
        $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
              . ' style="border-collapse:collapse;margin:20px 0;border:1px solid #e5e7eb;border-radius:6px;">';
        $i = 0;
        foreach ($rows as $row) {
            $label = $this->_e((string)$row[0]);
            $value = $this->_e((string)$row[1]);
            $bt    = ($i > 0) ? 'border-top:1px solid #e5e7eb;' : '';
            $html .= '<tr>'
                   . '<td style="padding:10px 16px;' . $bt . 'font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#6b7280;width:40%;background:#f9fafb;vertical-align:top;">' . $label . '</td>'
                   . '<td style="padding:10px 16px;' . $bt . 'font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#111827;font-weight:500;vertical-align:top;">' . $value . '</td>'
                   . '</tr>';
            $i++;
        }
        $html .= '</table>';
        return $html;
    }

    private function _codeBlock(string $code): string {
        return '<div style="text-align:center;margin:28px 0;">'
             . '<span style="display:inline-block;background:#f3f4f6;border:2px solid #d1d5db;border-radius:8px;'
             . 'padding:14px 40px;font-size:30px;font-weight:700;letter-spacing:8px;color:#111827;'
             . 'font-family:Courier,Courier New,monospace;">' . $this->_e($code) . '</span>'
             . '<p style="margin:12px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#6b7280;">'
             . 'Do not share this code with anyone. It expires shortly.'
             . '</p></div>';
    }

    private function _layout(string $title, string $bodyHtml, string $appName): string {
        $logoUrl    = $this->_logoUrl();
        $appNameEsc = $this->_e($appName);
        $titleEsc   = $this->_e($title);

        $fe = $this->_footerEmail();
        $fp = $this->_footerPhone();
        $fa = $this->_footerAddress();

        $contactParts = array();
        if ($fe !== '') { $contactParts[] = $fe; }
        if ($fp !== '') { $contactParts[] = $fp; }

        $contactLine = '';
        if (!empty($contactParts)) {
            $contactLine = '<p style="margin:0 0 6px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#6b7280;">'
                         . implode(' &nbsp;&bull;&nbsp; ', $contactParts)
                         . '</p>';
        }

        $addressLine = '';
        if ($fa !== '') {
            $addressLine = '<p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#9ca3af;">'
                         . nl2br($this->_e($fa))
                         . '</p>';
        }

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>' . $titleEsc . '</title>
<style type="text/css">
body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
table,td{mso-table-lspace:0pt;mso-table-rspace:0pt}
img{-ms-interpolation-mode:bicubic;border:0;height:auto;line-height:100%;outline:none;text-decoration:none}
table{border-collapse:collapse!important}
body{height:100%!important;margin:0!important;padding:0!important;width:100%!important}
a[x-apple-data-detectors]{color:inherit!important;text-decoration:none!important;font-size:inherit!important;font-family:inherit!important;font-weight:inherit!important;line-height:inherit!important}
div[style*="margin: 16px 0;"]{margin:0!important}
</style>
</head>
<body style="background-color:#f4f5f7;margin:0;padding:0;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f4f5f7;">
<tr><td align="center" style="padding:32px 16px;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;">

  <tr>
    <td align="center" style="background:#ffffff;padding:28px 40px 20px;border-radius:8px 8px 0 0;border-bottom:3px solid #111827;">
      <img src="' . $this->_e($logoUrl) . '" alt="' . $appNameEsc . '" width="160"
           style="max-height:72px;max-width:160px;height:auto;width:auto;display:block;margin:0 auto;">
    </td>
  </tr>

  <tr>
    <td style="background:#ffffff;padding:28px 40px 0;">
      <h1 style="margin:0 0 20px;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:700;color:#111827;line-height:1.3;">' . $titleEsc . '</h1>
      <div style="border-top:1px solid #e5e7eb;"></div>
    </td>
  </tr>

  <tr>
    <td style="background:#ffffff;padding:24px 40px 36px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.65;color:#374151;">
      ' . $bodyHtml . '
    </td>
  </tr>

  <tr>
    <td align="center" style="background:#f9fafb;padding:24px 40px;border-top:1px solid #e5e7eb;border-radius:0 0 8px 8px;">
      <p style="margin:0 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:600;color:#374151;">' . $appNameEsc . '</p>
      ' . $contactLine . '
      ' . $addressLine . '
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
    }

    public function BankWithdrawMsg($currency, $full_name, $amount, $bankname, $account_number, $routineno, $acctname, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($full_name) . ',</p>'
              . '<p style="margin:0 0 16px;">Your bank withdrawal request has been received and is being processed.</p>'
              . $this->_table(array(
                    array('Amount',       $currency . $amount),
                    array('Bank Name',    $bankname),
                    array('Account No.', $account_number),
                    array('Routing No.', $routineno),
                    array('Account Name',$acctname),
                    array('Status',      'Processing'),
                ))
              . '<p style="margin:16px 0 0;font-size:13px;color:#6b7280;">If you did not initiate this request, contact support immediately.</p>';
        return $this->_layout('Withdrawal Request', $body, $APP_NAME);
    }

    public function ForgotMsg($full_name, $email, $user_acctno, $reset_token, $APP_NAME, $APP_URL){
        $resetUrl = rtrim($APP_URL, '/') . '/update-password?email=' . rawurlencode($email) . '&reset_token=' . rawurlencode($reset_token);
        $body = '<p style="margin:0 0 16px;">Hi ' . $this->_e($full_name) . ',</p>'
              . '<p style="margin:0 0 16px;">We received a request to reset the password for account <strong>' . $this->_e($user_acctno) . '</strong>.</p>'
              . '<p style="margin:0 0 24px;">Click the button below to set a new password:</p>'
              . '<div style="text-align:center;margin:0 0 24px;">'
              . '<a href="' . $this->_e($resetUrl) . '" style="display:inline-block;background:#111827;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:600;padding:12px 32px;border-radius:6px;text-decoration:none;">Reset Password</a>'
              . '</div>'
              . '<p style="margin:0 0 8px;font-size:13px;color:#6b7280;">Or copy this link into your browser:</p>'
              . '<p style="margin:0;font-size:12px;color:#6b7280;word-break:break-all;">' . $this->_e($resetUrl) . '</p>'
              . '<p style="margin:20px 0 0;font-size:13px;color:#9ca3af;">If you did not request a password reset, you can safely ignore this email.</p>';
        return $this->_layout('Password Reset Request', $body, $APP_NAME);
    }

    public function CardGenMsg($full_name, $card_name, $card_number, $card_expiration, $card_security, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($full_name) . ',</p>'
              . '<p style="margin:0 0 16px;">Your card request has been processed. Here are your card details:</p>'
              . $this->_table(array(
                    array('Account Name', $card_name),
                    array('Card Number',  $card_number),
                    array('Expiry',       $card_expiration),
                    array('Status',       'Processing'),
                ))
              . '<p style="margin:16px 0 0;font-size:13px;color:#6b7280;">Keep your card details private. Never share your card number or security code.</p>';
        return $this->_layout('Card Update', $body, $APP_NAME);
    }

    public function CardMsg($full_name, $card_number, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($full_name) . ',</p>'
              . '<p style="margin:0 0 16px;">A change has been made to your card.</p>'
              . $this->_table(array(
                    array('Card Number', $card_number),
                ))
              . '<p style="margin:16px 0 0;font-size:13px;color:#6b7280;">If you did not make this change, contact support immediately.</p>';
        return $this->_layout('Card Update', $body, $APP_NAME);
    }

    public function WithdrawMsg($currency, $full_name, $amount, $withdraw_method, $wallet_address, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($full_name) . ',</p>'
              . '<p style="margin:0 0 16px;">Your withdrawal request has been received and is being processed.</p>'
              . $this->_table(array(
                    array('Amount',         $currency . $amount),
                    array('Method',         $withdraw_method),
                    array('Wallet Address', $wallet_address),
                    array('Status',         'Processing'),
                ))
              . '<p style="margin:16px 0 0;font-size:13px;color:#6b7280;">If you did not initiate this withdrawal, contact support immediately.</p>';
        return $this->_layout('Withdrawal Request', $body, $APP_NAME);
    }

    public function depositMsg($currency, $amount, $crypto_name, $fullName, $trans_id, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 16px;">Your deposit has been received and is being verified.</p>'
              . $this->_table(array(
                    array('Amount',         $currency . ' ' . $amount),
                    array('Transaction ID', $trans_id),
                    array('Payment Method', $crypto_name),
                    array('Status',         'Processing'),
                ));
        return $this->_layout('Deposit Received', $body, $APP_NAME);
    }

    public function PassChange($full_name, $APP_EMAIL, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($full_name) . ',</p>'
              . '<p style="margin:0 0 16px;">Your account password was changed successfully.</p>'
              . '<p style="margin:0;font-size:13px;color:#6b7280;">If you did not make this change, contact support at <strong>' . $this->_e($APP_EMAIL) . '</strong> immediately.</p>';
        return $this->_layout('Password Changed', $body, $APP_NAME);
    }

    public function domTrans($currency, $amount, $fullName, $refrence_id, $bank_name, $acct_name, $acct_number, $trans_status, $trans_type, $created_at, $availableBalance, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 16px;">A transaction has been processed on your account.</p>'
              . $this->_table(array(
                    array('Amount',            $currency . $amount),
                    array('Transfer Type',     $trans_type),
                    array('Reference ID',      $refrence_id),
                    array('Bank Name',         $bank_name),
                    array('Account Name',      $acct_name),
                    array('Account Number',    $acct_number),
                    array('Date',              $created_at),
                    array('Available Balance', $currency . $availableBalance),
                    array('Status',            $trans_status),
                ));
        return $this->_layout('Transaction Notification', $body, $APP_NAME);
    }

    public function LoginMsg($full_name, $device, $ipAddress, $nowDate, $APP_NAME, $APP_URL, $BANK_PHONE){
        $phoneNote = ($BANK_PHONE !== '') ? ' at <strong>' . $this->_e($BANK_PHONE) . '</strong>' : '';
        $body = '<p style="margin:0 0 16px;">Hello, ' . $this->_e($full_name) . ',</p>'
              . '<p style="margin:0 0 16px;">A new login was detected on your account.</p>'
              . $this->_table(array(
                    array('Device',     $device),
                    array('IP Address', $ipAddress),
                    array('Date',       $nowDate),
                ))
              . '<p style="margin:16px 0 0;font-size:13px;color:#6b7280;">If this was not you, contact support immediately' . $phoneNote . '.</p>';
        return $this->_layout('Login Alert', $body, $APP_NAME);
    }

    public function LoginAlert($full_name, $device, $ipAddress, $nowDate, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Hello, ' . $this->_e($full_name) . ',</p>'
              . '<p style="margin:0 0 16px;">You have successfully signed in to your account.</p>'
              . $this->_table(array(
                    array('Device',     $device),
                    array('IP Address', $ipAddress),
                    array('Date &amp; Time', $nowDate),
                ))
              . '<p style="margin:16px 0 0;padding:12px 16px;background:#fef3c7;border-left:3px solid #d97706;border-radius:0 4px 4px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#92400e;">'
              . 'Not you? Contact support immediately to secure your account.'
              . '</p>';
        return $this->_layout('New Login Detected', $body, $APP_NAME);
    }

    public function ContactMsg($message_body, $APP_NAME, $BANK_PHONE, $APP_URL){
        $phoneNote = ($BANK_PHONE !== '') ? '<p style="margin:16px 0 0;font-size:13px;color:#6b7280;">You can also reach us by phone at <strong>' . $this->_e($BANK_PHONE) . '</strong>.</p>' : '';
        $body = '<p style="margin:0 0 16px;">Your message has been received. Our support team will get back to you as soon as possible.</p>'
              . '<div style="background:#f9fafb;border-left:3px solid #d1d5db;padding:16px 20px;margin:16px 0;border-radius:0 4px 4px 0;">'
              . '<p style="margin:0;font-size:14px;color:#374151;line-height:1.6;">' . nl2br($this->_e($message_body)) . '</p>'
              . '</div>'
              . $phoneNote;
        return $this->_layout('Message Received', $body, $APP_NAME);
    }

    public function wireTransfer($currency, $amount, $crypto_name, $fullName, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 16px;">A wire transfer has been initiated on your account.</p>'
              . $this->_table(array(
                    array('Amount',         $currency . ' ' . $amount),
                    array('Payment Method', $crypto_name),
                    array('Status',         'Processing'),
                ));
        return $this->_layout('Wire Transfer Initiated', $body, $APP_NAME);
    }

    public function UserDomTransfer($currency, $amount, $fullName, $bank_name, $acct_name, $acct_number, $acct_type, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 16px;">Your domestic transfer has been submitted and is being processed.</p>'
              . $this->_table(array(
                    array('Amount',       $currency . ' ' . $amount),
                    array('Bank Name',    $bank_name),
                    array('Account Name',$acct_name),
                    array('Account No.', $acct_number),
                    array('Account Type',$acct_type),
                    array('Status',      'Processing'),
                ));
        return $this->_layout('Domestic Transfer Initiated', $body, $APP_NAME);
    }

    public function UserWireTransfer($currency, $amount, $fullName, $bank_name, $acct_name, $acct_number, $acct_country, $acct_swift, $acct_routing, $acct_type, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 16px;">Your international wire transfer has been submitted and is being processed.</p>'
              . $this->_table(array(
                    array('Amount',       $currency . ' ' . $amount),
                    array('Bank Name',    $bank_name),
                    array('Account Name',$acct_name),
                    array('Account No.', $acct_number),
                    array('Account Type',$acct_type),
                    array('Country',     $acct_country),
                    array('SWIFT / BIC', $acct_swift),
                    array('Routing No.', $acct_routing),
                    array('Status',      'Processing'),
                ));
        return $this->_layout('International Wire Transfer Initiated', $body, $APP_NAME);
    }

    public function debitTransaction($currency, $amount, $crypto_name, $fullName, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 16px;">A debit transaction has been recorded on your account.</p>'
              . $this->_table(array(
                    array('Amount',         $currency . ' ' . $amount),
                    array('Payment Method', $crypto_name),
                    array('Status',         'Processing'),
                ));
        return $this->_layout('Debit Transaction', $body, $APP_NAME);
    }

    public function creditTransaction($currency, $amount, $crypto_name, $fullName, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 16px;">A credit has been applied to your account.</p>'
              . $this->_table(array(
                    array('Amount',         $currency . ' ' . $amount),
                    array('Payment Method', $crypto_name),
                    array('Status',         'Processing'),
                ));
        return $this->_layout('Credit Transaction', $body, $APP_NAME);
    }

    public function pinRequest($currency, $amount, $fullName, $code, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 4px;">A transaction of <strong>' . $this->_e($currency . $amount) . '</strong> requires verification. Use the code below to complete it:</p>'
              . $this->_codeBlock($code);
        return $this->_layout('Transaction Verification Code', $body, $APP_NAME);
    }

    public function otpRequest($fullName, $code, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 4px;">Use the one-time code below to complete your transaction:</p>'
              . $this->_codeBlock($code);
        return $this->_layout('Transaction OTP', $body, $APP_NAME);
    }

    public function otpRequestLogin($fullName, $code, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 4px;">A login attempt was made on your <strong>' . $this->_e($APP_NAME) . '</strong> account. Use the code below to confirm:</p>'
              . $this->_codeBlock($code)
              . '<p style="margin:0;font-size:13px;color:#6b7280;">If you did not request this, ignore this email — your account remains secure.</p>';
        return $this->_layout('Login Verification Code', $body, $APP_NAME);
    }

    public function emailChangeOtp($fullName, $code, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 4px;">Use the code below to confirm this address as the new sign-in email for your <strong>' . $this->_e($APP_NAME) . '</strong> account:</p>'
              . $this->_codeBlock($code)
              . '<p style="margin:0;font-size:13px;color:#6b7280;">The code expires in 15 minutes. If you did not request this change, ignore this email and the address on your account stays the same.</p>';
        return $this->_layout('Confirm Your New Email Address', $body, $APP_NAME);
    }

    public function emailChangeConfirmed($fullName, $newEmail, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 16px;">This address is now the sign-in email for your <strong>' . $this->_e($APP_NAME) . '</strong> account.</p>'
              . $this->_table(array(
                    array('New Sign-in Email', $newEmail),
                ));
        return $this->_layout('Email Address Updated', $body, $APP_NAME);
    }

    // Sent to the OLD address so a customer whose account was taken over still
    // learns the login email moved, at an inbox the attacker no longer controls.
    public function emailChangeAlert($fullName, $newEmail, $APP_EMAIL, $APP_NAME){
        $body = '<p style="margin:0 0 16px;">Dear ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 16px;">The sign-in email on your <strong>' . $this->_e($APP_NAME) . '</strong> account was changed.</p>'
              . $this->_table(array(
                    array('New Sign-in Email', $newEmail),
                ))
              . '<p style="margin:16px 0 0;font-size:13px;color:#6b7280;">If you did not make this change, contact support at <strong>' . $this->_e($APP_EMAIL) . '</strong> immediately.</p>';
        return $this->_layout('Security Alert: Email Address Changed', $body, $APP_NAME);
    }

    public function regMsgUser($fullName, $acct_no, $acct_status, $acct_email, $acct_phone, $acct_type, $acct_pin, $APP_NAME, $APP_URL){
        $body = '<p style="margin:0 0 16px;">Hello, ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 16px;">Welcome to <strong>' . $this->_e($APP_NAME) . '</strong>! We are glad you chose us as your financial institution. Your account is being reviewed and we will notify you once verification is complete.</p>'
              . $this->_table(array(
                    array('Account Number',  $acct_no),
                    array('Transaction PIN', $acct_pin),
                    array('Email',           $acct_email),
                    array('Phone',           $acct_phone),
                    array('Account Type',    $acct_type),
                    array('Status',          $acct_status),
                ))
              . '<p style="margin:16px 0 0;padding:12px 16px;background:#fef3c7;border-left:3px solid #d97706;border-radius:0 4px 4px 0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#92400e;">'
              . 'Please change your transaction PIN after your first login.'
              . '</p>';
        return $this->_layout('Welcome to ' . $APP_NAME, $body, $APP_NAME);
    }

    public function LoanMsg($currency, $amount, $loan_remarks, $fullName, $APP_NAME, $APP_URL){
        $body = '<p style="margin:0 0 16px;">Hello, ' . $this->_e($fullName) . ',</p>'
              . '<p style="margin:0 0 16px;">Your loan application has been received and is currently under review. We will contact you once a decision has been made.</p>'
              . $this->_table(array(
                    array('Amount',  $currency . ' ' . $amount),
                    array('Remarks', $loan_remarks),
                    array('Status',  'Under Review'),
                ));
        return $this->_layout('Loan Application Received', $body, $APP_NAME);
    }
}
