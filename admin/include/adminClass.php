<?php
/**
 * Email templates for the admin panel.
 *
 * Two groups of messages live here:
 *
 *   1. CUSTOMER-FACING receipts triggered by an admin action (a deposit
 *      approved, a transfer declined, a balance adjusted). These keep their
 *      original method names and parameter order because they are called from
 *      admin/*.php pages — any new parameter is appended and optional.
 *
 *   2. ADMIN-FACING alerts. Before this rewrite there was exactly one
 *      (adminLoginAlertMsg). Every other "admin notification" was a side
 *      effect of message::send_to_both(), which BCCs WEB_EMAIL a verbatim copy
 *      of the customer's own receipt — so the operations inbox could see that
 *      a balance changed but never which operator did it, from which IP, or
 *      what the value was before. The adminXxxMsg() family below fixes that:
 *      each one names the actor, the target, the before/after, and links back
 *      to the record.
 *
 * Rendering is delegated to MailTemplate (include/mail_template.php), a PHP
 * port of Laravel's default markdown-mail theme. Nothing in this file writes
 * HTML by hand. That is what removed ~2,600 lines: the file used to hold 14
 * standalone ~200-line HTML documents whose <head>, <style>, wrapper tables
 * and footer were byte-identical, and which between them shipped
 *
 *   * a competitor bank's logo hotlinked into the wire-transfer mail
 *     (midlandstrustonline.com) and a clipart handshake from img.icons8.com;
 *   * `style='... font-family: 'Lato' ...'` — nested single quotes that closed
 *     the style attribute early, so every declaration after font-family was
 *     discarded in all 13 legacy templates;
 *   * unescaped interpolation of admin- and customer-supplied values
 *     (beneficiary names, loan notes, remarks) straight into the HTML body;
 *   * "Welcome!" as the <h1> on withdrawal-declined, transfer-declined and
 *     loan-declined mail;
 *   * dead href='#' "unsubscribe" and "Need more help?" links.
 *
 * NOTE ON THE CLASS NAME. `emailMessage` is also declared in
 * include/userClass.php:33 with an overlapping method set. The two are never
 * loaded in the same process — admin pages require this file, the customer API
 * requires userClass.php — so PHP never sees a redeclare. It is still a real
 * hazard: a single stray require of the wrong one fatals the request. Any
 * future consolidation should merge them onto this MailTemplate base rather
 * than adding a third copy.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../include/vendor/autoload.php';
require_once __DIR__ . '/../../include/smtp.php';
require_once __DIR__ . '/../../include/branding.php';
require_once __DIR__ . '/../../include/mail_template.php';

class emailMessage
{
    // =================================================================
    // Brand + formatting plumbing
    // =================================================================

    /** @var array|null Cached brand array; see brand(). */
    private static ?array $brandCache = null;

    /**
     * Brand details for the mail layout, read once per request from the
     * `settings` row and cached.
     *
     * Resolved lazily rather than in a constructor so simply loading this file
     * never touches the database. Every failure path falls back to the WEB_*
     * constants, so a missing settings table degrades to a plain but correct
     * email instead of a fatal.
     */
    public static function brand(): array
    {
        if (self::$brandCache !== null) {
            return self::$brandCache;
        }

        $settings = [];
        try {
            if (function_exists('dbConnect')) {
                $stmt = dbConnect()->query("SELECT * FROM settings WHERE id = '1' LIMIT 1");
                $settings = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
            }
        } catch (\Throwable $e) {
            error_log('[emailMessage] brand lookup failed, using constants: ' . $e->getMessage());
        }

        return self::$brandCache = mail_brand_from_settings($settings);
    }

    /** The operator-configured platform name, for subject lines. */
    public static function brandName(): string
    {
        return (string)(self::brand()['appName'] ?? 'BankPro');
    }

    /**
     * Start a template pre-loaded with the brand.
     *
     * $appName lets a caller override the name for this one message. The
     * legacy customer methods all take an $APP_NAME argument (usually
     * $pageTitle from the admin layout), and honouring it keeps their output
     * identical in intent to before.
     */
    private static function tpl(string $appName = ''): MailTemplate
    {
        $brand = self::brand();
        if ($appName !== '') {
            $brand['appName'] = $appName;
        }

        return MailTemplate::make($brand);
    }

    /** Absolute URL into the admin panel, or '' when WEB_URL is not set. */
    private static function adminUrl(string $path): string
    {
        $base = (string)(self::brand()['appUrl'] ?? '');
        if ($base === '') {
            return '';
        }

        return rtrim($base, '/') . '/admin/' . ltrim($path, '/');
    }

    /**
     * Map a human status word onto a MailTemplate accent.
     *
     * The call sites use four different vocabularies for the same three
     * outcomes — "Successful"/"Approved"/"Complete", "Declined"/"Cancelled",
     * "ON HOLD"/"On Hold"/"Processing" — so match loosely on the lowercased
     * text rather than on an exact set.
     */
    private static function statusTone(string $status): string
    {
        $s = strtolower(trim($status));
        if ($s === '') {
            return 'info';
        }
        foreach (['success', 'complete', 'approve', 'credit', 'active'] as $needle) {
            if (str_contains($s, $needle)) {
                return 'success';
            }
        }
        foreach (['declin', 'cancel', 'reject', 'fail', 'delet', 'block'] as $needle) {
            if (str_contains($s, $needle)) {
                return 'error';
            }
        }

        return 'warning';
    }

    /** `$1,234.56` — one money format for every template. */
    private static function money(string $currency, $amount): string
    {
        return $currency . number_format((float)$amount, 2, '.', ',');
    }

    /** "Wed, 05 Aug 2026 14:03:11 UTC" — used when a caller passes no timestamp. */
    private static function now(): string
    {
        return date('D, d M Y H:i:s T');
    }

    /**
     * The who/where/when rows every admin alert carries.
     *
     * This is the single most important addition in this file: the previous
     * admin copies were customer receipts and never recorded who acted.
     */
    private static function actorRows(string $actor, string $ip = '', string $when = ''): array
    {
        return [
            'Performed by' => $actor !== '' ? $actor : 'Unknown operator',
            'IP address'   => $ip,
            'When'         => $when !== '' ? $when : self::now(),
        ];
    }

    /**
     * Render a "changed fields" table from an old→new diff.
     *
     * admin/settings.php and admin/view_users.php already build exactly this
     * shape for audit_log(); accepting it directly means a caller can pass the
     * array it already has.
     *
     * Accepts either ['field' => ['from' => x, 'to' => y]] or a flat
     * ['field' => 'new value'].
     */
    private static function changeRows(array $changes): array
    {
        $rows = [];
        foreach ($changes as $field => $change) {
            $label = ucfirst(str_replace('_', ' ', (string)$field));
            if (is_array($change) && (array_key_exists('from', $change) || array_key_exists('to', $change))) {
                $from = (string)($change['from'] ?? '');
                $to   = (string)($change['to'] ?? '');
                $rows[$label] = ($from === '' ? '(empty)' : $from) . '  →  ' . ($to === '' ? '(empty)' : $to);
            } else {
                $rows[$label] = is_scalar($change) ? (string)$change : json_encode($change);
            }
        }

        return $rows;
    }

    /**
     * Core renderer for every admin-facing alert.
     *
     * @param string   $heading     Subject-like title, e.g. "Customer balance adjusted".
     * @param string   $severity    success|warning|error|info — drives the accent bar.
     * @param string   $alertText   One-line severity banner.
     * @param string[] $lines       Body paragraphs.
     * @param array    $details     Label => value rows. Blank values are dropped.
     * @param string   $actionLabel CTA text (omitted when $actionUrl is empty).
     * @param string   $actionUrl   Absolute URL into the admin panel.
     * @param string   $footnote    Fine print under the rule.
     */
    private function adminNotice(
        string $heading,
        string $severity,
        string $alertText,
        array $lines,
        array $details,
        string $actionLabel = '',
        string $actionUrl = '',
        string $footnote = ''
    ): string {
        $mail = self::tpl()
            ->preheader($alertText)
            ->heading($heading)
            ->accent($severity)
            ->alert($alertText, $severity);

        foreach ($lines as $line) {
            $mail->line($line);
        }

        $mail->table($details);

        if ($actionUrl !== '' && $actionLabel !== '') {
            $mail->button($actionLabel, $actionUrl);
        }

        return $mail
            ->subcopy($footnote !== '' ? $footnote : 'You are receiving this because you are listed as an operator on ' . self::brandName() . '. If this action was not expected, treat it as a security incident.')
            ->render();
    }

    // =================================================================
    // CUSTOMER-FACING RECEIPTS
    // Signatures are load-bearing — see the file header.
    // =================================================================

    /**
     * Welcome letter for an account created by an admin (admin/reguser.php).
     *
     * SECURITY NOTE: this template used to print the customer's plaintext
     * password and transaction PIN in the body, and admin/reguser.php:91 mails
     * an identical copy to WEB_EMAIL — so every customer's credentials landed
     * permanently in the shared operations mailbox. The credentials are now
     * rendered ONLY in the copy addressed to the customer, behind an explicit
     * "change these immediately" instruction. Pass $forAdmin = true to get the
     * same letter with the credential block replaced by a notice.
     *
     * The right long-term fix is a one-time set-password link instead of a
     * mailed password; that needs a token table and is out of scope here.
     */
    public function regMsg(
        $currency,
        $amount_balance,
        $fullName,
        $acct_type,
        $acct_password,
        $APP_NAME,
        $APP_URL,
        $BANK_PHONE,
        $acct_no,
        $acct_pin,
        bool $forAdmin = false
    ): string {
        $brandName = (string)$APP_NAME;
        $mail = self::tpl($brandName)
            ->preheader('Your ' . $brandName . ' account is open — account number ' . $acct_no)
            ->heading('Your account is open')
            ->greeting('Hello ' . $fullName . ',')
            ->line('Thank you for opening an account with ' . $brandName . '. Your account is active and ready to use.')
            ->table([
                'Account holder'    => $fullName,
                'Account number'    => $acct_no,
                'Account type'      => $acct_type,
                'Available balance' => self::money((string)$currency, $amount_balance),
            ]);

        if ($forAdmin) {
            $mail->panel('Sign-in credentials were sent to the customer only and are deliberately not repeated in this copy.');
        } else {
            $mail
                ->line('Use the temporary credentials below for your first sign-in.')
                ->table([
                    'Temporary password' => $acct_password,
                    'Transaction PIN'    => $acct_pin,
                ])
                ->panel('Change your password and PIN as soon as you sign in. Anyone with this email can access your account until you do — never forward it.');
        }

        if (is_string($APP_URL) && $APP_URL !== '') {
            $mail->button('Sign in to your account', $APP_URL);
        }

        $support = 'Questions? Reply to this email';
        if (is_string($BANK_PHONE) && trim((string)$BANK_PHONE) !== '') {
            $support .= ' or call us on ' . $BANK_PHONE;
        }

        return $mail->salutation('Welcome aboard,', $brandName)->subcopy($support . '.')->render();
    }

    /**
     * Crypto deposit approved / declined / held (admin/viewcrypto-trans.php).
     */
    public function depositMsg($currency, $amount, $amount_balance, $crypto_name, $fullName, $APP_NAME, $tran_status, $reference_id): string
    {
        $status = (string)$tran_status;

        return self::tpl((string)$APP_NAME)
            ->preheader('Your deposit of ' . self::money((string)$currency, $amount) . ' was ' . $status)
            ->heading('Deposit ' . strtolower($status))
            ->accent(self::statusTone($status))
            ->greeting('Hello ' . $fullName . ',')
            ->alert('Deposit ' . $status, self::statusTone($status))
            ->line('Your deposit of ' . self::money((string)$currency, $amount) . ' was ' . $status . '.')
            ->table([
                'Reference'         => $reference_id,
                'Amount'            => self::money((string)$currency, $amount),
                'Payment method'    => $crypto_name,
                'Status'            => $status,
                'Available balance' => self::money((string)$currency, $amount_balance),
            ])
            ->salutation()
            ->subcopy('If you have any questions about this deposit, reply to this email and we will help.')
            ->render();
    }

    /**
     * Withdrawal status update (admin/viewwithdraw.php).
     *
     * $tran_status and $reference_id are appended and optional: the original
     * template had neither, so approve and decline produced identical emails
     * under the same subject and the customer could not tell whether their
     * money was on its way or had been refunded. Existing calls keep working;
     * passing the status makes the message correct.
     */
    public function WithdrawMsg($currency, $amount, $amount_balance, $fullName, $APP_NAME, $tran_status = '', $reference_id = ''): string
    {
        $status = trim((string)$tran_status);
        $tone   = $status === '' ? 'info' : self::statusTone($status);

        $mail = self::tpl((string)$APP_NAME)
            ->preheader('Your withdrawal of ' . self::money((string)$currency, $amount) . ($status === '' ? ' has been updated' : ' was ' . $status))
            ->heading($status === '' ? 'Withdrawal update' : 'Withdrawal ' . strtolower($status))
            ->accent($tone)
            ->greeting('Hello ' . $fullName . ',');

        if ($status !== '') {
            $mail->alert('Withdrawal ' . $status, $tone);
            $mail->line('Your withdrawal of ' . self::money((string)$currency, $amount) . ' was ' . $status . '.');
        } else {
            $mail->line('The status of your withdrawal of ' . self::money((string)$currency, $amount) . ' has been updated.');
        }

        return $mail
            ->table([
                'Reference'         => $reference_id,
                'Amount'            => self::money((string)$currency, $amount),
                'Status'            => $status,
                'Available balance' => self::money((string)$currency, $amount_balance),
            ])
            ->salutation()
            ->subcopy('If you have any questions about this withdrawal, reply to this email and we will help.')
            ->render();
    }

    /**
     * Domestic transfer approved / held / cancelled (admin/view-domtrans.php).
     *
     * $bank_name, $acct_name, $acct_number and $acct_type were accepted by the
     * old template and never rendered, so the customer was never told where
     * their money went. They are shown now — through table(), which escapes
     * them (they originate from customer input via api_field(), which is
     * trim() only).
     */
    public function DoMMsg($currency, $amount, $amount_balance, $trans_type, $bank_name, $acct_name, $acct_number, $acct_type, $fullName, $APP_NAME, $tran_status, $reference_id): string
    {
        $status = (string)$tran_status;

        return self::tpl((string)$APP_NAME)
            ->preheader('Your transfer of ' . self::money((string)$currency, $amount) . ' was ' . $status)
            ->heading('Domestic transfer ' . strtolower($status))
            ->accent(self::statusTone($status))
            ->greeting('Hello ' . $fullName . ',')
            ->alert('Transfer ' . $status, self::statusTone($status))
            ->line('Your ' . strtolower((string)$trans_type) . ' of ' . self::money((string)$currency, $amount) . ' was ' . $status . '.')
            ->table([
                'Reference'         => $reference_id,
                'Beneficiary'       => $acct_name,
                'Bank'              => $bank_name,
                'Account number'    => $acct_number,
                'Account type'      => $acct_type,
                'Amount'            => self::money((string)$currency, $amount),
                'Status'            => $status,
                'Available balance' => self::money((string)$currency, $amount_balance),
            ])
            ->salutation()
            ->subcopy('If you did not authorise this transfer, contact us immediately.')
            ->render();
    }

    /** Wire transfer approved / declined / held (admin/viewwire-trans.php). */
    public function wireMsg($currency, $amount, $amount_balance, $trans_type, $fullName, $APP_NAME, $tran_status, $reference_id): string
    {
        return $this->transferOutcome(
            'Wire transfer',
            (string)$currency,
            $amount,
            $amount_balance,
            (string)$trans_type,
            (string)$fullName,
            (string)$APP_NAME,
            (string)$tran_status,
            (string)$reference_id
        );
    }

    /**
     * Generic transaction approved / declined / held (admin/view-trans.php).
     * Shares a body with wireMsg — the two templates were near-duplicates.
     */
    public function creditMsg($currency, $amount, $amount_balance, $transfer_type, $fullName, $APP_NAME, $tran_status, $reference_id): string
    {
        return $this->transferOutcome(
            'Transaction',
            (string)$currency,
            $amount,
            $amount_balance,
            (string)$transfer_type,
            (string)$fullName,
            (string)$APP_NAME,
            (string)$tran_status,
            (string)$reference_id
        );
    }

    /** Shared body behind wireMsg() and creditMsg(). */
    private function transferOutcome(string $noun, string $currency, $amount, $balance, string $type, string $fullName, string $appName, string $status, string $reference): string
    {
        return self::tpl($appName)
            ->preheader($noun . ' of ' . self::money($currency, $amount) . ' was ' . $status)
            ->heading($noun . ' ' . strtolower($status))
            ->accent(self::statusTone($status))
            ->greeting('Hello ' . $fullName . ',')
            ->alert($noun . ' ' . $status, self::statusTone($status))
            ->line('Your ' . strtolower($noun) . ' of ' . self::money($currency, $amount) . ' was ' . $status . '.')
            ->table([
                'Reference'         => $reference,
                'Type'              => $type,
                'Amount'            => self::money($currency, $amount),
                'Status'            => $status,
                'Available balance' => self::money($currency, $balance),
            ])
            ->salutation()
            ->subcopy('If you did not authorise this transaction, contact us immediately.')
            ->render();
    }

    /**
     * Loan decision (admin/viewloan-trans.php).
     *
     * $fullName is appended and optional — the original had no personalisation
     * at all and opened with the raw admin note. $messageText is admin-authored
     * free text from $_POST and goes through panel(), which escapes it; it must
     * never be passed to raw().
     */
    public function loanMsg($currency, $amount, $amount_balance, $available_loan, $APP_NAME, $tran_status, $messageText, $fullName = ''): string
    {
        $status = (string)$tran_status;

        $mail = self::tpl((string)$APP_NAME)
            ->preheader('Your loan application was ' . $status)
            ->heading('Loan ' . strtolower($status))
            ->accent(self::statusTone($status));

        if (trim((string)$fullName) !== '') {
            $mail->greeting('Hello ' . $fullName . ',');
        }

        $mail->alert('Loan ' . $status, self::statusTone($status));

        if (trim((string)$messageText) !== '') {
            $mail->panel((string)$messageText);
        }

        return $mail
            ->table([
                'Loan amount'       => self::money((string)$currency, $amount),
                'Status'            => $status,
                'Available loan'    => self::money((string)$currency, $available_loan),
                'Available balance' => self::money((string)$currency, $amount_balance),
            ])
            ->salutation()
            ->subcopy('If you have any questions about this decision, reply to this email and we will help.')
            ->render();
    }

    /**
     * Wire transfer booked by an admin on the customer's behalf
     * (admin/transfer.php).
     *
     * Every beneficiary field here comes straight from $_POST with no
     * validation at the call site and used to be interpolated raw into the HTML
     * — the worst injection hole in the old file. table() escapes all of them.
     */
    public function adwireTransfer($currency, $amount, $available_balance, $fullName, $APP_NAME, $tran_status, $bank_name, $acct_name, $acct_number, $acct_country, $created_at, $reference_id, $transfer_type): string
    {
        $status = (string)$tran_status;

        return self::tpl((string)$APP_NAME)
            ->preheader('A ' . strtolower((string)$transfer_type) . ' of ' . self::money((string)$currency, $amount) . ' was processed on your account')
            ->heading('Wire transfer ' . strtolower($status))
            ->accent(self::statusTone($status))
            ->greeting('Hello ' . $fullName . ',')
            ->alert('Wire transfer ' . $status, self::statusTone($status))
            ->line('A ' . strtolower((string)$transfer_type) . ' of ' . self::money((string)$currency, $amount) . ' was processed on your account.')
            ->table([
                'Reference'         => $reference_id,
                'Transfer method'   => $transfer_type,
                'Beneficiary'       => $acct_name,
                'Bank'              => $bank_name,
                'Account number'    => $acct_number,
                'Country'           => $acct_country,
                'Amount'            => self::money((string)$currency, $amount),
                'Status'            => $status,
                'Date'              => $created_at,
                'Available balance' => self::money((string)$currency, $available_balance),
            ])
            ->salutation()
            ->subcopy('If you did not authorise this transfer, contact us immediately.')
            ->render();
    }

    /**
     * Manual credit or debit applied by an admin (admin/funduser.php).
     *
     * The old template hardcoded "You just recieved a Transaction" for BOTH
     * branches, so a debited customer was told they had received money, and it
     * rendered the raw numeric $trans_type ("Transfer Type: 1"). Direction is
     * derived from $trans_type here: 1 = credit, 2 = debit.
     */
    public function FundUsers($fullName, $currency, $sender_name, $amount, $available_balance, $description, $created_at, $trans_type, $APP_NAME, $reference_id = ''): string
    {
        $isDebit = (string)$trans_type === '2';
        $money   = self::money((string)$currency, $amount);

        $mail = self::tpl((string)$APP_NAME)
            ->preheader(($isDebit ? 'Your account was debited ' : 'Your account was credited ') . $money)
            ->heading($isDebit ? 'Account debited' : 'Account credited')
            ->accent($isDebit ? 'warning' : 'success')
            ->greeting('Hello ' . $fullName . ',')
            ->alert($isDebit ? 'Debit of ' . $money : 'Credit of ' . $money, $isDebit ? 'warning' : 'success')
            ->line($isDebit
                ? 'A debit of ' . $money . ' has been applied to your account.'
                : 'A credit of ' . $money . ' has been applied to your account.');

        if (trim((string)$description) !== '') {
            $mail->panel((string)$description);
        }

        return $mail
            ->table([
                'Reference'         => $reference_id,
                'Amount'            => $money,
                'Type'              => $isDebit ? 'Debit' : 'Credit',
                $isDebit ? 'Beneficiary' : 'Sender' => $sender_name,
                'Date'              => $created_at,
                'Available balance' => self::money((string)$currency, $available_balance),
            ])
            ->salutation()
            ->subcopy('If you do not recognise this transaction, contact us immediately.')
            ->render();
    }

    // =================================================================
    // ADMIN-FACING ALERTS
    //
    // Wiring status is tracked in .claude/agent-notes/ACTIVE-WORK.md.
    // adminLoginAlertMsg and adminFailedLoginAlertMsg are wired (in
    // admin/include/adminloginFunction.php); the rest are ready for their
    // trigger points, which live in admin pages owned by other branches.
    // =================================================================

    /**
     * Successful admin sign-in.
     *
     * $userAgent is fully attacker-controlled (it is the raw User-Agent
     * header) and used to be interpolated into the HTML unescaped, so a
     * crafted header could inject markup — links, spoofed instructions — into
     * the very alert meant to warn about the breach. It is escaped by table()
     * now, and the caller caps its length.
     *
     * The trailing $APP_NAME parameter was dropped: the brand comes from the
     * settings row. The only caller is admin/include/adminloginFunction.php.
     */
    public function adminLoginAlertMsg(string $adminName, string $ip, string $location, string $userAgent, string $loginTime): string
    {
        return $this->adminNotice(
            'New sign-in to your admin account',
            'info',
            'Security notice — successful admin sign-in',
            ['Hello ' . $adminName . ', a successful sign-in was recorded on your administrator account. If this was you, no action is needed.'],
            [
                'When'            => $loginTime,
                'IP address'      => $ip,
                'Location'        => $location,
                'Browser/device'  => $userAgent,
            ],
            'Review account security',
            self::adminUrl('profile.php'),
            'If you do not recognise this sign-in, change your admin password immediately and review the audit log.'
        );
    }

    /**
     * Failed admin sign-in, or a lockout being enforced.
     *
     * Wired at admin/include/adminloginFunction.php. Previously a thousand
     * failed guesses produced no signal of any kind and only the attacker's
     * eventual success generated mail.
     */
    public function adminFailedLoginAlertMsg(string $attemptedEmail, string $ip, string $userAgent, int $attempts, string $lockedUntil = '', string $when = ''): string
    {
        $locked = $lockedUntil !== '';

        return $this->adminNotice(
            $locked ? 'Admin account locked after repeated failures' : 'Failed admin sign-in attempt',
            $locked ? 'error' : 'warning',
            $locked
                ? 'Account locked — ' . $attempts . ' consecutive failed sign-in attempts'
                : 'Failed sign-in attempt on the admin panel',
            $locked
                ? ['The admin account has been temporarily locked after ' . $attempts . ' consecutive failed sign-in attempts. If this was not you, someone is attempting to guess the administrator password.']
                : ['A sign-in attempt against the admin panel failed. A single failure is usually a typo; repeated failures are worth investigating.'],
            [
                'Attempted address' => $attemptedEmail,
                'IP address'        => $ip,
                'Browser/device'    => $userAgent,
                'Failed attempts'   => $attempts > 0 ? (string)$attempts : '',
                'Locked until'      => $lockedUntil,
                'When'              => $when !== '' ? $when : self::now(),
            ],
            'Review the audit log',
            self::adminUrl('audit-log.php'),
            'Repeated alerts from one address indicate a brute-force attempt. Consider adding the address to the sign-in blocklist under Auth Policy.'
        );
    }

    /** The admin panel password was changed. */
    public function adminPasswordChangedMsg(string $adminName, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Your admin password was changed',
            'error',
            'Security notice — admin password changed',
            [
                'The password for the ' . self::brandName() . ' administrator account was just changed.',
                'If you made this change, no action is needed. If you did not, your bank is compromised — restore access and review the audit log immediately.',
            ],
            self::actorRows($adminName, $ip, $when),
            'Review the audit log',
            self::adminUrl('audit-log.php')
        );
    }

    /**
     * The admin sign-in address was changed.
     *
     * Send this to the OLD address as well as the new one — it is the only way
     * a displaced operator finds out, since the login flow authenticates
     * against the new address from the moment it is saved.
     */
    public function adminAccountEmailChangedMsg(string $adminName, string $oldEmail, string $newEmail, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Your admin sign-in address was changed',
            'error',
            'Security notice — admin sign-in address changed',
            [
                'The email address used to sign in to the ' . self::brandName() . ' admin panel was just changed. Future sign-ins and admin security alerts will use the new address.',
                'If you did not make this change, act immediately — whoever made it now controls administrator access.',
            ],
            array_merge(
                ['Previous address' => $oldEmail, 'New address' => $newEmail],
                self::actorRows($adminName, $ip, $when)
            ),
            'Review the audit log',
            self::adminUrl('audit-log.php')
        );
    }

    /** A full database dump was downloaded off the server. */
    public function adminBackupDownloadedMsg(string $adminName, string $filename, string $size = '', string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Database backup downloaded',
            'error',
            'Data export — a full database dump left the server',
            [
                'A complete database backup was downloaded from the admin panel. The file contains every customer record, including personal details, password hashes and card data.',
                'If this download was not part of planned maintenance, treat it as a data breach.',
            ],
            array_merge(
                ['File' => $filename, 'Size' => $size],
                self::actorRows($adminName, $ip, $when)
            ),
            'Open backup manager',
            self::adminUrl('db-backup.php')
        );
    }

    /** A backup was created or deleted. */
    public function adminBackupLifecycleMsg(string $adminName, string $action, string $filename, string $ip = '', string $when = ''): string
    {
        $deleted = stripos($action, 'delet') !== false;

        return $this->adminNotice(
            $deleted ? 'Database backup deleted' : 'Database backup created',
            $deleted ? 'warning' : 'info',
            $deleted ? 'A recovery point was destroyed' : 'A new recovery point was created',
            [$deleted
                ? 'A database backup was deleted from the admin panel. Deleting recovery points is a common precursor to tampering — confirm this was intentional.'
                : 'A new database backup was created from the admin panel.'],
            array_merge(['File' => $filename], self::actorRows($adminName, $ip, $when)),
            'Open backup manager',
            self::adminUrl('db-backup.php')
        );
    }

    /**
     * The sign-in IP allowlist/blocklist or the idle timeout was changed.
     *
     * A non-empty allowlist gates both the admin and the customer login before
     * credentials are checked, so one bad save locks everybody out of the
     * platform — including out of the page needed to undo it.
     */
    public function adminAuthPolicyChangedMsg(string $adminName, array $changes, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Sign-in policy changed',
            'error',
            'Access control — sign-in policy was modified',
            [
                'The sign-in access policy was changed. A non-empty IP allowlist blocks every address not on the list, for administrators and customers alike, before any password is checked.',
                'If you did not make this change, restore the previous policy now — while you can still reach the panel.',
            ],
            array_merge(self::changeRows($changes), self::actorRows($adminName, $ip, $when)),
            'Open auth policy',
            self::adminUrl('auth_policy.php')
        );
    }

    /** A crypto deposit wallet address was added, edited or removed. */
    public function adminDepositWalletChangedMsg(string $adminName, string $action, string $coin, string $oldAddress = '', string $newAddress = '', string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Deposit wallet address ' . strtolower($action),
            'error',
            'Funds routing — a customer deposit address was changed',
            [
                'The wallet address customers are told to send ' . ($coin !== '' ? $coin : 'crypto') . ' deposits to has been ' . strtolower($action) . '.',
                'Verify this address against your own records before dismissing this alert. If it is wrong, every inbound deposit from now on goes to whoever controls it, and the platform will show nothing unusual.',
            ],
            array_merge(
                [
                    'Currency'         => $coin,
                    'Previous address' => $oldAddress,
                    'New address'      => $newAddress,
                ],
                self::actorRows($adminName, $ip, $when)
            ),
            'Review deposit wallets',
            self::adminUrl('crypto-currrency.php')
        );
    }

    /** The virtual bank deposit destination (account/routing/SWIFT) changed. */
    public function adminBankDepositDetailsChangedMsg(string $adminName, array $changes, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Bank deposit details changed',
            'error',
            'Funds routing — the deposit bank account was changed',
            [
                'The bank account customers are instructed to deposit into has been changed. Confirm the new details against your own records.',
                'If these details are wrong, inbound customer deposits will be paid to the wrong account with no other visible symptom.',
            ],
            array_merge(self::changeRows($changes), self::actorRows($adminName, $ip, $when)),
            'Review deposit settings',
            self::adminUrl('deposits.php')
        );
    }

    /** Platform settings were saved (limits, toggles, support address, ...). */
    public function adminSettingsChangedMsg(string $adminName, array $changes, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Platform settings changed',
            'warning',
            'Configuration — platform settings were modified',
            ['The settings below were changed in the admin panel. Transfer limits, the global transfer switch, the signup default balance and the support address all affect customer money or customer trust.'],
            array_merge(self::changeRows($changes), self::actorRows($adminName, $ip, $when)),
            'Open settings',
            self::adminUrl('settings.php')
        );
    }

    /** A settled transaction was edited after the fact. */
    public function adminTransactionEditedMsg(string $adminName, string $reference, array $changes, string $customer = '', string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Posted transaction edited',
            'error',
            'Ledger integrity — a settled transaction was rewritten',
            [
                'A transaction that had already posted was edited. The customer may hold a receipt showing the previous values.',
                'Retroactive edits to a posted ledger are a books-and-records issue. Confirm there is a documented reason for this change.',
            ],
            array_merge(
                ['Reference' => $reference, 'Customer' => $customer],
                self::changeRows($changes),
                self::actorRows($adminName, $ip, $when)
            ),
            'Review transactions',
            self::adminUrl('credit_debit_trans.php')
        );
    }

    /** A transaction row was hard-deleted from the ledger. */
    public function adminTransactionDeletedMsg(string $adminName, string $reference, array $snapshot, string $customer = '', string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Transaction deleted from the ledger',
            'error',
            'Ledger integrity — a transaction record was destroyed',
            [
                'A transaction row was permanently deleted. There is no soft-delete and no reversal: the customer balance is unchanged, so any amount this row explained is now unexplained.',
                'The values below are the only remaining record of it.',
            ],
            array_merge(
                ['Reference' => $reference, 'Customer' => $customer],
                self::changeRows($snapshot),
                self::actorRows($adminName, $ip, $when)
            ),
            'Review transactions',
            self::adminUrl('credit_debit_trans.php')
        );
    }

    /** A wire / withdrawal / domestic / deposit record was hard-deleted. */
    public function adminMoneyRecordDeletedMsg(string $adminName, string $recordType, string $reference, array $snapshot, string $customer = '', string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            ucfirst($recordType) . ' record deleted',
            'error',
            'Ledger integrity — a money record was destroyed',
            [
                'A ' . strtolower($recordType) . ' record was permanently deleted. The customer balance was debited when the request was created and is not restored by this deletion.',
                'The values below are the only remaining record of it.',
            ],
            array_merge(
                ['Record type' => $recordType, 'Reference' => $reference, 'Customer' => $customer],
                self::changeRows($snapshot),
                self::actorRows($adminName, $ip, $when)
            ),
            'Review the audit log',
            self::adminUrl('audit-log.php')
        );
    }

    /** An operator credited or debited a customer balance by hand. */
    public function adminManualBalanceAdjustmentMsg(string $adminName, string $direction, string $customer, string $currency, $amount, $balanceBefore, $balanceAfter, string $reference = '', string $note = '', string $ip = '', string $when = ''): string
    {
        $isDebit = stripos($direction, 'debit') !== false;

        $mail = [
            'A customer balance was adjusted by hand from the admin panel. This writes the balance directly and is not the result of any customer action.',
        ];
        if ($note !== '') {
            $mail[] = 'Operator note: ' . $note;
        }

        return $this->adminNotice(
            'Customer account ' . ($isDebit ? 'debited' : 'credited') . ' manually',
            $isDebit ? 'warning' : 'error',
            'Manual ' . ($isDebit ? 'debit' : 'credit') . ' of ' . self::money($currency, $amount),
            $mail,
            array_merge(
                [
                    'Customer'        => $customer,
                    'Direction'       => $isDebit ? 'Debit' : 'Credit',
                    'Amount'          => self::money($currency, $amount),
                    'Balance before'  => self::money($currency, $balanceBefore),
                    'Balance after'   => self::money($currency, $balanceAfter),
                    'Reference'       => $reference,
                ],
                self::actorRows($adminName, $ip, $when)
            ),
            'Review transactions',
            self::adminUrl('credit_debit_trans.php')
        );
    }

    /** An operator originated a wire out of a customer account. */
    public function adminOriginatedWireMsg(string $adminName, string $customer, string $currency, $amount, array $beneficiary, string $status, string $reference = '', string $ip = '', string $when = ''): string
    {
        $immediate = self::statusTone($status) === 'success';

        return $this->adminNotice(
            'Wire transfer originated by an operator',
            'error',
            'Money out — ' . self::money($currency, $amount) . ' sent from a customer account',
            array_filter([
                'An operator created a wire transfer out of a customer account. The customer did not initiate this.',
                $immediate
                    ? 'It was created already marked "' . $status . '", which skips the approve/hold/decline review entirely. No second pair of eyes saw this transfer.'
                    : '',
            ]),
            array_merge(
                [
                    'Customer'  => $customer,
                    'Amount'    => self::money($currency, $amount),
                    'Status'    => $status,
                    'Reference' => $reference,
                ],
                self::changeRows($beneficiary),
                self::actorRows($adminName, $ip, $when)
            ),
            'Review wire transfers',
            self::adminUrl('wire-trans.php')
        );
    }

    /** An approval crossed the configured value threshold. */
    public function adminLargeValueApprovalMsg(string $adminName, string $kind, string $customer, string $currency, $amount, string $reference = '', $threshold = null, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Large ' . strtolower($kind) . ' approved',
            'warning',
            'High-value approval — ' . self::money($currency, $amount),
            ['A ' . strtolower($kind) . ' above the review threshold was approved. Confirm it was expected.'],
            array_merge(
                [
                    'Type'      => $kind,
                    'Customer'  => $customer,
                    'Amount'    => self::money($currency, $amount),
                    'Threshold' => $threshold === null ? '' : self::money($currency, $threshold),
                    'Reference' => $reference,
                ],
                self::actorRows($adminName, $ip, $when)
            ),
            'Review the audit log',
            self::adminUrl('audit-log.php')
        );
    }

    /**
     * A customer submitted a transfer that is now waiting for approval.
     *
     * The only major customer money-movement path that pushed no notification
     * at all — an operator had to notice pending money-out by refreshing a
     * page, so a Friday-evening transfer sat unseen all weekend.
     */
    public function adminPendingTransferSubmittedMsg(string $customer, string $kind, string $currency, $amount, array $beneficiary = [], string $reference = '', string $when = ''): string
    {
        return $this->adminNotice(
            strtoupper(substr($kind, 0, 1)) . substr(strtolower($kind), 1) . ' awaiting approval',
            'warning',
            'Action required — ' . self::money($currency, $amount) . ' is waiting for review',
            [
                'A customer submitted a ' . strtolower($kind) . ' that needs an approval decision. The amount has already been debited from their available balance and is held pending your review.',
            ],
            array_merge(
                [
                    'Customer'  => $customer,
                    'Type'      => $kind,
                    'Amount'    => self::money($currency, $amount),
                    'Reference' => $reference,
                    'Submitted' => $when !== '' ? $when : self::now(),
                ],
                self::changeRows($beneficiary)
            ),
            'Review pending transfers',
            self::adminUrl('wire-trans.php'),
            'This message is sent because the transfer queue requires an operator decision before funds move.'
        );
    }

    /** An operator reset a customer's password or transaction PIN. */
    public function adminCustomerCredentialsResetMsg(string $adminName, string $customer, string $whatChanged, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Customer credentials reset by an operator',
            'error',
            'Account takeover risk — a customer credential was reset',
            [
                'An operator set a new ' . strtolower($whatChanged) . ' on a customer account. The operator now knows a credential for an account they do not own.',
                'Any activity performed with it will look like ordinary customer activity in the ledger.',
            ],
            array_merge(
                ['Customer' => $customer, 'Credential' => $whatChanged],
                self::actorRows($adminName, $ip, $when)
            ),
            'Review the audit log',
            self::adminUrl('audit-log.php')
        );
    }

    /** An operator edited a customer's balance or email directly. */
    public function adminCustomerBalanceOrEmailEditedMsg(string $adminName, string $customer, array $changes, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Customer profile edited by an operator',
            'error',
            'Customer record changed outside the normal flow',
            [
                'A customer profile was edited directly. A balance edited here writes no row to the transactions ledger, so it will not appear in any transaction report. An email edited here bypasses the customer-side verification flow and redirects all future notifications.',
            ],
            array_merge(
                ['Customer' => $customer],
                self::changeRows($changes),
                self::actorRows($adminName, $ip, $when)
            ),
            'Review the audit log',
            self::adminUrl('audit-log.php')
        );
    }

    /** A customer account was deleted, held, suspended or blocked. */
    public function adminCustomerAccountLifecycleMsg(string $adminName, string $customer, string $action, string $reason = '', string $ip = '', string $when = ''): string
    {
        $deleted = stripos($action, 'delet') !== false;

        return $this->adminNotice(
            'Customer account ' . strtolower($action),
            'error',
            'Customer impact — account ' . strtolower($action),
            array_filter([
                $deleted
                    ? 'A customer of record was permanently deleted. There is no soft-delete flag and no undo.'
                    : 'A customer account status was changed. The customer is cut off from their money until it is restored.',
                $reason !== '' ? 'Reason given: ' . $reason : '',
            ]),
            array_merge(
                ['Customer' => $customer, 'Action' => $action, 'Reason' => $reason],
                self::actorRows($adminName, $ip, $when)
            ),
            'Review customers',
            self::adminUrl('users.php')
        );
    }

    /** A bulk approve/suspend ran across many accounts at once. */
    public function adminBulkAccountActionMsg(string $adminName, string $action, int $count, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Bulk account action: ' . strtolower($action),
            'error',
            'Bulk action affected ' . $count . ' customer account' . ($count === 1 ? '' : 's'),
            ['A single action changed the status of ' . $count . ' customer account' . ($count === 1 ? '' : 's') . '. This is the widest-reaching action available in the panel.'],
            array_merge(
                ['Action' => $action, 'Accounts affected' => (string)$count],
                self::actorRows($adminName, $ip, $when)
            ),
            'Review customers',
            self::adminUrl('users.php')
        );
    }

    /** A card was activated or deactivated by an operator. */
    public function adminCardStatusChangedMsg(string $adminName, string $customer, string $cardMasked, string $action, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Customer card ' . strtolower($action),
            'warning',
            'Payment instrument ' . strtolower($action),
            ['An operator changed the status of a customer payment card. An activated card is a live spending instrument.'],
            array_merge(
                ['Customer' => $customer, 'Card' => $cardMasked, 'Action' => $action],
                self::actorRows($adminName, $ip, $when)
            ),
            'Review cards',
            self::adminUrl('cards.php')
        );
    }

    /** Customer feature access was revoked, or all their sessions killed. */
    public function adminCustomerAccessRevokedMsg(string $adminName, string $customer, array $changes, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Customer access changed',
            'warning',
            'Service restriction applied to a customer',
            ['A customer\'s ability to use the platform was changed. Cleared capability flags strand the customer\'s funds; a forced sign-out ends every live session immediately.'],
            array_merge(
                ['Customer' => $customer],
                self::changeRows($changes),
                self::actorRows($adminName, $ip, $when)
            ),
            'Review customers',
            self::adminUrl('users.php')
        );
    }

    /**
     * A customer changed their own identity details.
     *
     * Distinct from adminCustomerBalanceOrEmailEditedMsg, which covers an
     * OPERATOR editing a customer. This is the customer-initiated path
     * (api/user/profile.php), which today writes name, date of birth, country,
     * address and phone with no notification and no audit row — the fields a
     * KYC file is built on can be rewritten silently.
     */
    public function adminCustomerProfileChangedMsg(string $customer, array $changes, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Customer identity details changed',
            'warning',
            'KYC-relevant details were changed by the customer',
            ['A customer updated their own identity details. These are the fields a KYC file is built on, so a change here may need re-verification.'],
            array_merge(
                ['Customer' => $customer],
                self::changeRows($changes),
                ['Source IP' => $ip, 'When' => $when !== '' ? $when : self::now()]
            ),
            'Review customers',
            self::adminUrl('users.php')
        );
    }

    /** A customer asked for a password reset link. */
    public function adminPasswordResetRequestedMsg(string $customer, string $email, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Customer password reset requested',
            'info',
            'A password reset link was issued',
            ['A customer requested a password reset. A burst of these across different accounts is an account-takeover probe rather than ordinary forgetfulness.'],
            [
                'Customer'  => $customer,
                'Address'   => $email,
                'Source IP' => $ip,
                'When'      => $when !== '' ? $when : self::now(),
            ],
            'Review customers',
            self::adminUrl('users.php')
        );
    }

    /** A customer hit the failed-attempt lockout — credential-stuffing signal. */
    public function adminCustomerLockoutMsg(string $customer, int $attempts, string $lockedUntil, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Customer account locked after failed attempts',
            'warning',
            'Possible credential stuffing — customer account locked',
            [
                'A customer account was locked after ' . $attempts . ' consecutive failed verification attempts.',
                'One lockout is usually a forgotten password. Several across different accounts in a short window is a credential-stuffing run.',
            ],
            [
                'Customer'        => $customer,
                'Failed attempts' => (string)$attempts,
                'Locked until'    => $lockedUntil,
                'Source IP'       => $ip,
                'When'            => $when !== '' ? $when : self::now(),
            ],
            'Review customers',
            self::adminUrl('users.php')
        );
    }

    /**
     * A system-health probe went red.
     *
     * Note the meta-failure this covers: when SMTP is unconfigured, every
     * notification in this file is silently dropped by include/smtp.php — the
     * notification system going dark is itself unnotifiable, so this alert is
     * only useful while mail still works. Pair it with an external uptime check.
     *
     * @param array<string,string> $problems Probe name => failure description.
     */
    public function adminSystemHealthAlertMsg(array $problems, string $when = ''): string
    {
        return $this->adminNotice(
            'System health check failed',
            'error',
            count($problems) . ' health check' . (count($problems) === 1 ? '' : 's') . ' failing',
            ['One or more platform health probes are reporting a failure. Customers may be unable to sign in or transact.'],
            array_merge(self::changeRows($problems), ['Checked' => $when !== '' ? $when : self::now()]),
            'Open system health',
            self::adminUrl('system-health.php')
        );
    }

    /** A schema migration was executed against the live database. */
    public function adminMigrationRunMsg(string $adminName, string $file, int $statements = 0, string $ip = '', string $when = ''): string
    {
        return $this->adminNotice(
            'Database migration executed',
            'warning',
            'Schema change applied to the live database',
            ['A schema migration was run against the production database from the web console. An unscheduled migration is a strong compromise signal, since it means someone was able to stage DDL on the server.'],
            array_merge(
                ['Migration' => $file, 'Statements' => $statements > 0 ? (string)$statements : ''],
                self::actorRows($adminName, $ip, $when)
            )
        );
    }
}

if (!function_exists('admin_notify')) {
    /**
     * Deliver an admin alert to the operations mailbox.
     *
     * One-liner wiring for a trigger point:
     *
     *   admin_notify(
     *       (new emailMessage())->adminSettingsChangedMsg($actor, $changed, $ip),
     *       'Settings changed'
     *   );
     *
     * Recipients: the configured support address (settings.url_email, falling
     * back to WEB_EMAIL) plus every row in the `admin` table, de-duplicated —
     * so a second operator is reachable even if the first account is the one
     * being compromised. Best-effort and never throws: a notification failure
     * must not roll back the admin action that triggered it.
     *
     * @param string $html    Body from one of the adminXxxMsg() methods.
     * @param string $subject Subject without the brand suffix — it is appended.
     * @return bool True when at least one delivery succeeded.
     */
    function admin_notify(string $html, string $subject): bool
    {
        try {
            $recipients = [];

            $support = (string)(emailMessage::brand()['supportEmail'] ?? '');
            if ($support !== '' && filter_var($support, FILTER_VALIDATE_EMAIL)) {
                $recipients[strtolower($support)] = $support;
            }
            if (defined('WEB_EMAIL') && filter_var(WEB_EMAIL, FILTER_VALIDATE_EMAIL)) {
                $recipients[strtolower(WEB_EMAIL)] = WEB_EMAIL;
            }

            if (function_exists('dbConnect')) {
                $stmt = dbConnect()->query('SELECT admin_email FROM admin');
                foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [] as $addr) {
                    $addr = trim((string)$addr);
                    if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                        $recipients[strtolower($addr)] = $addr;
                    }
                }
            }

            if ($recipients === []) {
                error_log('[admin_notify] no valid recipient for: ' . $subject);
                return false;
            }

            $mailer = new message();
            $line   = '[' . emailMessage::brandName() . '] ' . $subject;
            $sent   = false;
            foreach ($recipients as $addr) {
                $sent = $mailer->send_mail($addr, $html, $line) || $sent;
            }

            return $sent;
        } catch (\Throwable $e) {
            error_log('[admin_notify] failed for "' . $subject . '": ' . $e->getMessage());
            return false;
        }
    }
}
