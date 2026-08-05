<?php
/**
 * Renders every customer email template and asserts the output is a sane,
 * complete HTML document with the shared chrome and the caller's data in it.
 * Also proves values are HTML-escaped rather than injected raw.
 */
require_once __DIR__ . '/../include/userClass.php';

$settings = [
    'url_name'  => 'Probe Bank',
    'url_email' => 'support@probebank.test',
    'url_tel'   => '+1 555 0100',
    'about_us'  => '100 Example Street, Lagos, Nigeria',
];
$t = new emailMessage($settings);

$pass = 0; $fail = 0;
function ok(string $label, bool $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; } else { $fail++; echo "  FAIL  {$label}\n"; }
}

// name => [args, must-appear substrings]
$cases = [
    'BankWithdrawMsg' => [['$', 'Ada Lovelace', 250.00, 'First Bank', '0123456789', '0110', 'Ada L', 'Probe Bank'], ['Ada Lovelace', 'First Bank']],
    'ForgotMsg'       => [['Ada Lovelace', 'a@b.test', '1001', 'tok123', 'Probe Bank', 'https://probebank.test'], ['tok123']],
    'CardGenMsg'      => [['Ada Lovelace', 'Ada L', '4012888888881881', '12/29', '123', 'Probe Bank'], ['Ada Lovelace']],
    'CardMsg'         => [['Ada Lovelace', '4012888888881881', 'Probe Bank'], ['Ada Lovelace']],
    'WithdrawMsg'     => [['$', 'Ada Lovelace', 90.5, 'Crypto', 'bc1qxyz', 'Probe Bank'], ['bc1qxyz']],
    'depositMsg'      => [['$', 500.00, 'Bitcoin', 'Ada Lovelace', 'TX99', 'Probe Bank'], ['TX99']],
    'PassChange'      => [['Ada Lovelace', 'support@probebank.test', 'Probe Bank'], ['Ada Lovelace']],
    'domTrans'        => [['$', 75.25, 'Ada Lovelace', 'REF77', 'First Bank', 'Payee', '0123', 'Completed', 'domestic', '2026-08-05', 900.00, 'Probe Bank'], ['REF77']],
    'LoginMsg'        => [['Ada Lovelace', 'Chrome', '1.2.3.4', '2026-08-05 10:00', 'Probe Bank', 'https://probebank.test', '+1 555 0100'], ['1.2.3.4']],
    'LoginAlert'      => [['Ada Lovelace', 'Chrome on Windows', '1.2.3.4', '2026-08-05 10:00', 'Probe Bank'], ['1.2.3.4', 'Chrome on Windows']],
    'ContactMsg'      => [['Hello there', 'Probe Bank', '+1 555 0100', 'https://probebank.test'], ['Hello there']],
    'wireTransfer'    => [['$', 300.00, 'Wire', 'Ada Lovelace', 'Probe Bank'], ['Ada Lovelace']],
    'UserDomTransfer' => [['$', 120.00, 'Ada Lovelace', 'First Bank', 'Payee', '0123', 'savings', 'Probe Bank'], ['First Bank']],
    'UserWireTransfer'=> [['$', 400.00, 'Ada Lovelace', 'HSBC', 'Payee', '0123', 'UK', 'SWIFT99', 'RT44', 'checking', 'Probe Bank'], ['SWIFT99']],
    'debitTransaction'=> [['$', 60.00, 'Debit', 'Ada Lovelace', 'Probe Bank'], ['Ada Lovelace']],
    'creditTransaction'=>[['$', 80.00, 'Credit', 'Ada Lovelace', 'Probe Bank'], ['Ada Lovelace']],
    'pinRequest'      => [['$', 150.00, 'Ada Lovelace', '482913', 'Probe Bank'], ['482913']],
    'otpRequest'      => [['Ada Lovelace', '123456', 'Probe Bank'], ['123456']],
    'otpRequestLogin' => [['Ada Lovelace', '654321', 'Probe Bank'], ['654321']],
    'regMsgUser'      => [['Ada Lovelace', '1001', 'active', 'a@b.test', '+1', 'savings', '1234', 'Probe Bank', 'https://probebank.test'], ['1001']],
    'LoanMsg'         => [['$', 5000.00, 'Home loan', 'Ada Lovelace', 'Probe Bank', 'https://probebank.test'], ['Home loan']],
    'emailChangeOtp'  => [['Ada Lovelace', '778899', 'Probe Bank'], ['778899']],
    'emailChangeConfirmed' => [['Ada Lovelace', 'new@probebank.test', 'Probe Bank'], ['new@probebank.test']],
    'emailChangeAlert'=> [['Ada Lovelace', 'new@probebank.test', 'support@probebank.test', 'Probe Bank'], ['new@probebank.test']],
];

echo "Rendering " . count($cases) . " templates\n\n";
foreach ($cases as $method => [$args, $needles]) {
    if (!method_exists($t, $method)) { $fail++; echo "  FAIL  {$method} does not exist\n"; continue; }
    try {
        $html = $t->$method(...$args);
    } catch (Throwable $e) {
        $fail++; echo "  FAIL  {$method} threw: " . $e->getMessage() . "\n"; continue;
    }
    ok("{$method} returns a non-trivial string", is_string($html) && strlen($html) > 400);
    ok("{$method} has html shell", stripos($html, '<html') !== false && stripos($html, '</html>') !== false);
    ok("{$method} embeds the logo", strpos($html, '/assets/settings/logo.png') !== false);
    ok("{$method} shows support email", strpos($html, 'support@probebank.test') !== false);
    ok("{$method} shows phone", strpos($html, '+1 555 0100') !== false);
    ok("{$method} shows address", strpos($html, '100 Example Street') !== false);
    ok("{$method} has no unresolved placeholder", strpos($html, '{{') === false);
    foreach ($needles as $needle) {
        ok("{$method} contains '" . substr($needle, 0, 24) . "'", strpos($html, $needle) !== false);
    }
}

echo "\nEscaping\n";
$evil = '<script>alert(1)</script>';
$html = $t->LoginAlert($evil, 'dev', '1.1.1.1', 'now', 'Probe Bank');
ok('caller data is escaped, not injected raw', strpos($html, '<script>alert(1)</script>') === false);
ok('escaped form is present', strpos($html, '&lt;script&gt;') !== false);
if (strpos($html, '<script>alert(1)</script>') !== false) echo "  FAIL  XSS: raw script tag reached the body\n";

echo "\n{$pass} assertions passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
