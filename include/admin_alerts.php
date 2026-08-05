<?php
/**
 * Operator-facing notification templates.
 *
 * Split out of admin/include/adminClass.php for one concrete reason: four of
 * the events an operator most needs to hear about originate on the CUSTOMER
 * side — a transfer entering the approval queue
 * (api/user/transfer-verify-pin.php), an account lockout (api/_security.php),
 * a KYC-relevant profile edit (api/user/profile.php) and a password-reset
 * request (api/auth/forgot-password.php).
 *
 * Those files load include/userClass.php, which declares its own
 * `class emailMessage`. adminClass.php declares a class of the same name, so
 * requiring it from api/ is an instant "Cannot redeclare class emailMessage"
 * fatal. The alerts were therefore unreachable from exactly the code paths
 * that needed them. A distinct class name in a neutral file is loadable from
 * both trees.
 *
 * Wiring a trigger is one line:
 *
 *     admin_notify(
 *         (new AdminAlert)->adminSettingsChangedMsg($who, $changed, $ip),
 *         'Settings changed'
 *     );
 *
 * Brand resolution, money formatting and the status vocabulary come from
 * MailBrand in include/mail_template.php, shared with the customer templates
 * so neither side drifts.
 */

declare(strict_types=1);

require_once __DIR__ . '/mail_template.php';

if (!class_exists('AdminAlert')) {

class AdminAlert
{
    /**
     * The who/where/when rows every alert carries.
     *
     * This is the point of the whole file: the previous "admin notifications"
     * were BCC'd copies of the customer's own receipt, which named no operator.
     * The panel could show that a balance changed but not who changed it.
     */
    private static function actorRows(string $actor, string $ip = '', string $when = ''): array
    {
        return [
            'Performed by' => $actor !== '' ? $actor : 'Unknown operator',
            'IP address'   => $ip,
            'When'         => $when !== '' ? $when : MailBrand::now(),
        ];
    }

    /**
     * Core renderer for every alert below.
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
    private function notice(
        string $heading,
        string $severity,
        string $alertText,
        array $lines,
        array $details,
        string $actionLabel = '',
        string $actionUrl = '',
        string $footnote = ''
    ): string {
        $mail = MailBrand::template()
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
            ->subcopy($footnote !== '' ? $footnote : 'You are receiving this because you are listed as an operator on ' . MailBrand::name() . '. If this action was not expected, treat it as a security incident.')
            ->render();
    }

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
        return $this->notice(
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
            MailBrand::adminUrl('profile.php'),
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

        return $this->notice(
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
                'When'              => $when !== '' ? $when : MailBrand::now(),
            ],
            'Review the audit log',
            MailBrand::adminUrl('audit-log.php'),
            'Repeated alerts from one address indicate a brute-force attempt. Consider adding the address to the sign-in blocklist under Auth Policy.'
        );
    }

    /** The admin panel password was changed. */
    public function adminPasswordChangedMsg(string $adminName, string $ip = '', string $when = ''): string
    {
        return $this->notice(
            'Your admin password was changed',
            'error',
            'Security notice — admin password changed',
            [
                'The password for the ' . MailBrand::name() . ' administrator account was just changed.',
                'If you made this change, no action is needed. If you did not, your bank is compromised — restore access and review the audit log immediately.',
            ],
            self::actorRows($adminName, $ip, $when),
            'Review the audit log',
            MailBrand::adminUrl('audit-log.php')
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
        return $this->notice(
            'Your admin sign-in address was changed',
            'error',
            'Security notice — admin sign-in address changed',
            [
                'The email address used to sign in to the ' . MailBrand::name() . ' admin panel was just changed. Future sign-ins and admin security alerts will use the new address.',
                'If you did not make this change, act immediately — whoever made it now controls administrator access.',
            ],
            array_merge(
                ['Previous address' => $oldEmail, 'New address' => $newEmail],
                self::actorRows($adminName, $ip, $when)
            ),
            'Review the audit log',
            MailBrand::adminUrl('audit-log.php')
        );
    }

    /** A full database dump was downloaded off the server. */
    public function adminBackupDownloadedMsg(string $adminName, string $filename, string $size = '', string $ip = '', string $when = ''): string
    {
        return $this->notice(
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
            MailBrand::adminUrl('db-backup.php')
        );
    }

    /** A backup was created or deleted. */
    public function adminBackupLifecycleMsg(string $adminName, string $action, string $filename, string $ip = '', string $when = ''): string
    {
        $deleted = stripos($action, 'delet') !== false;

        return $this->notice(
            $deleted ? 'Database backup deleted' : 'Database backup created',
            $deleted ? 'warning' : 'info',
            $deleted ? 'A recovery point was destroyed' : 'A new recovery point was created',
            [$deleted
                ? 'A database backup was deleted from the admin panel. Deleting recovery points is a common precursor to tampering — confirm this was intentional.'
                : 'A new database backup was created from the admin panel.'],
            array_merge(['File' => $filename], self::actorRows($adminName, $ip, $when)),
            'Open backup manager',
            MailBrand::adminUrl('db-backup.php')
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
        return $this->notice(
            'Sign-in policy changed',
            'error',
            'Access control — sign-in policy was modified',
            [
                'The sign-in access policy was changed. A non-empty IP allowlist blocks every address not on the list, for administrators and customers alike, before any password is checked.',
                'If you did not make this change, restore the previous policy now — while you can still reach the panel.',
            ],
            array_merge(MailBrand::changeRows($changes), self::actorRows($adminName, $ip, $when)),
            'Open auth policy',
            MailBrand::adminUrl('auth_policy.php')
        );
    }

    /** A crypto deposit wallet address was added, edited or removed. */
    public function adminDepositWalletChangedMsg(string $adminName, string $action, string $coin, string $oldAddress = '', string $newAddress = '', string $ip = '', string $when = ''): string
    {
        return $this->notice(
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
            MailBrand::adminUrl('crypto-currrency.php')
        );
    }

    /** The virtual bank deposit destination (account/routing/SWIFT) changed. */
    public function adminBankDepositDetailsChangedMsg(string $adminName, array $changes, string $ip = '', string $when = ''): string
    {
        return $this->notice(
            'Bank deposit details changed',
            'error',
            'Funds routing — the deposit bank account was changed',
            [
                'The bank account customers are instructed to deposit into has been changed. Confirm the new details against your own records.',
                'If these details are wrong, inbound customer deposits will be paid to the wrong account with no other visible symptom.',
            ],
            array_merge(MailBrand::changeRows($changes), self::actorRows($adminName, $ip, $when)),
            'Review deposit settings',
            MailBrand::adminUrl('deposits.php')
        );
    }

    /** Platform settings were saved (limits, toggles, support address, ...). */
    public function adminSettingsChangedMsg(string $adminName, array $changes, string $ip = '', string $when = ''): string
    {
        return $this->notice(
            'Platform settings changed',
            'warning',
            'Configuration — platform settings were modified',
            ['The settings below were changed in the admin panel. Transfer limits, the global transfer switch, the signup default balance and the support address all affect customer money or customer trust.'],
            array_merge(MailBrand::changeRows($changes), self::actorRows($adminName, $ip, $when)),
            'Open settings',
            MailBrand::adminUrl('settings.php')
        );
    }

    /** A settled transaction was edited after the fact. */
    public function adminTransactionEditedMsg(string $adminName, string $reference, array $changes, string $customer = '', string $ip = '', string $when = ''): string
    {
        return $this->notice(
            'Posted transaction edited',
            'error',
            'Ledger integrity — a settled transaction was rewritten',
            [
                'A transaction that had already posted was edited. The customer may hold a receipt showing the previous values.',
                'Retroactive edits to a posted ledger are a books-and-records issue. Confirm there is a documented reason for this change.',
            ],
            array_merge(
                ['Reference' => $reference, 'Customer' => $customer],
                MailBrand::changeRows($changes),
                self::actorRows($adminName, $ip, $when)
            ),
            'Review transactions',
            MailBrand::adminUrl('credit_debit_trans.php')
        );
    }

    /** A transaction row was hard-deleted from the ledger. */
    public function adminTransactionDeletedMsg(string $adminName, string $reference, array $snapshot, string $customer = '', string $ip = '', string $when = ''): string
    {
        return $this->notice(
            'Transaction deleted from the ledger',
            'error',
            'Ledger integrity — a transaction record was destroyed',
            [
                'A transaction row was permanently deleted. There is no soft-delete and no reversal: the customer balance is unchanged, so any amount this row explained is now unexplained.',
                'The values below are the only remaining record of it.',
            ],
            array_merge(
                ['Reference' => $reference, 'Customer' => $customer],
                MailBrand::changeRows($snapshot),
                self::actorRows($adminName, $ip, $when)
            ),
            'Review transactions',
            MailBrand::adminUrl('credit_debit_trans.php')
        );
    }

    /** A wire / withdrawal / domestic / deposit record was hard-deleted. */
    public function adminMoneyRecordDeletedMsg(string $adminName, string $recordType, string $reference, array $snapshot, string $customer = '', string $ip = '', string $when = ''): string
    {
        return $this->notice(
            ucfirst($recordType) . ' record deleted',
            'error',
            'Ledger integrity — a money record was destroyed',
            [
                'A ' . strtolower($recordType) . ' record was permanently deleted. The customer balance was debited when the request was created and is not restored by this deletion.',
                'The values below are the only remaining record of it.',
            ],
            array_merge(
                ['Record type' => $recordType, 'Reference' => $reference, 'Customer' => $customer],
                MailBrand::changeRows($snapshot),
                self::actorRows($adminName, $ip, $when)
            ),
            'Review the audit log',
            MailBrand::adminUrl('audit-log.php')
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

        return $this->notice(
            'Customer account ' . ($isDebit ? 'debited' : 'credited') . ' manually',
            $isDebit ? 'warning' : 'error',
            'Manual ' . ($isDebit ? 'debit' : 'credit') . ' of ' . MailBrand::money($currency, $amount),
            $mail,
            array_merge(
                [
                    'Customer'        => $customer,
                    'Direction'       => $isDebit ? 'Debit' : 'Credit',
                    'Amount'          => MailBrand::money($currency, $amount),
                    'Balance before'  => MailBrand::money($currency, $balanceBefore),
                    'Balance after'   => MailBrand::money($currency, $balanceAfter),
                    'Reference'       => $reference,
                ],
                self::actorRows($adminName, $ip, $when)
            ),
            'Review transactions',
            MailBrand::adminUrl('credit_debit_trans.php')
        );
    }

    /** An operator originated a wire out of a customer account. */
    public function adminOriginatedWireMsg(string $adminName, string $customer, string $currency, $amount, array $beneficiary, string $status, string $reference = '', string $ip = '', string $when = ''): string
    {
        $immediate = MailBrand::statusTone($status) === 'success';

        return $this->notice(
            'Wire transfer originated by an operator',
            'error',
            'Money out — ' . MailBrand::money($currency, $amount) . ' sent from a customer account',
            array_filter([
                'An operator created a wire transfer out of a customer account. The customer did not initiate this.',
                $immediate
                    ? 'It was created already marked "' . $status . '", which skips the approve/hold/decline review entirely. No second pair of eyes saw this transfer.'
                    : '',
            ]),
            array_merge(
                [
                    'Customer'  => $customer,
                    'Amount'    => MailBrand::money($currency, $amount),
                    'Status'    => $status,
                    'Reference' => $reference,
                ],
                MailBrand::changeRows($beneficiary),
                self::actorRows($adminName, $ip, $when)
            ),
            'Review wire transfers',
            MailBrand::adminUrl('wire-trans.php')
        );
    }

    /** An approval crossed the configured value threshold. */
    public function adminLargeValueApprovalMsg(string $adminName, string $kind, string $customer, string $currency, $amount, string $reference = '', $threshold = null, string $ip = '', string $when = ''): string
    {
        return $this->notice(
            'Large ' . strtolower($kind) . ' approved',
            'warning',
            'High-value approval — ' . MailBrand::money($currency, $amount),
            ['A ' . strtolower($kind) . ' above the review threshold was approved. Confirm it was expected.'],
            array_merge(
                [
                    'Type'      => $kind,
                    'Customer'  => $customer,
                    'Amount'    => MailBrand::money($currency, $amount),
                    'Threshold' => $threshold === null ? '' : MailBrand::money($currency, $threshold),
                    'Reference' => $reference,
                ],
                self::actorRows($adminName, $ip, $when)
            ),
            'Review the audit log',
            MailBrand::adminUrl('audit-log.php')
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
        return $this->notice(
            strtoupper(substr($kind, 0, 1)) . substr(strtolower($kind), 1) . ' awaiting approval',
            'warning',
            'Action required — ' . MailBrand::money($currency, $amount) . ' is waiting for review',
            [
                'A customer submitted a ' . strtolower($kind) . ' that needs an approval decision. The amount has already been debited from their available balance and is held pending your review.',
            ],
            array_merge(
                [
                    'Customer'  => $customer,
                    'Type'      => $kind,
                    'Amount'    => MailBrand::money($currency, $amount),
                    'Reference' => $reference,
                    'Submitted' => $when !== '' ? $when : MailBrand::now(),
                ],
                MailBrand::changeRows($beneficiary)
            ),
            'Review pending transfers',
            MailBrand::adminUrl('wire-trans.php'),
            'This message is sent because the transfer queue requires an operator decision before funds move.'
        );
    }

    /** An operator reset a customer's password or transaction PIN. */
    public function adminCustomerCredentialsResetMsg(string $adminName, string $customer, string $whatChanged, string $ip = '', string $when = ''): string
    {
        return $this->notice(
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
            MailBrand::adminUrl('audit-log.php')
        );
    }

    /** An operator edited a customer's balance or email directly. */
    public function adminCustomerBalanceOrEmailEditedMsg(string $adminName, string $customer, array $changes, string $ip = '', string $when = ''): string
    {
        return $this->notice(
            'Customer profile edited by an operator',
            'error',
            'Customer record changed outside the normal flow',
            [
                'A customer profile was edited directly. A balance edited here writes no row to the transactions ledger, so it will not appear in any transaction report. An email edited here bypasses the customer-side verification flow and redirects all future notifications.',
            ],
            array_merge(
                ['Customer' => $customer],
                MailBrand::changeRows($changes),
                self::actorRows($adminName, $ip, $when)
            ),
            'Review the audit log',
            MailBrand::adminUrl('audit-log.php')
        );
    }

    /** A customer account was deleted, held, suspended or blocked. */
    public function adminCustomerAccountLifecycleMsg(string $adminName, string $customer, string $action, string $reason = '', string $ip = '', string $when = ''): string
    {
        $deleted = stripos($action, 'delet') !== false;

        return $this->notice(
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
            MailBrand::adminUrl('users.php')
        );
    }

    /** A bulk approve/suspend ran across many accounts at once. */
    public function adminBulkAccountActionMsg(string $adminName, string $action, int $count, string $ip = '', string $when = ''): string
    {
        return $this->notice(
            'Bulk account action: ' . strtolower($action),
            'error',
            'Bulk action affected ' . $count . ' customer account' . ($count === 1 ? '' : 's'),
            ['A single action changed the status of ' . $count . ' customer account' . ($count === 1 ? '' : 's') . '. This is the widest-reaching action available in the panel.'],
            array_merge(
                ['Action' => $action, 'Accounts affected' => (string)$count],
                self::actorRows($adminName, $ip, $when)
            ),
            'Review customers',
            MailBrand::adminUrl('users.php')
        );
    }

    /** A card was activated or deactivated by an operator. */
    public function adminCardStatusChangedMsg(string $adminName, string $customer, string $cardMasked, string $action, string $ip = '', string $when = ''): string
    {
        return $this->notice(
            'Customer card ' . strtolower($action),
            'warning',
            'Payment instrument ' . strtolower($action),
            ['An operator changed the status of a customer payment card. An activated card is a live spending instrument.'],
            array_merge(
                ['Customer' => $customer, 'Card' => $cardMasked, 'Action' => $action],
                self::actorRows($adminName, $ip, $when)
            ),
            'Review cards',
            MailBrand::adminUrl('cards.php')
        );
    }

    /** Customer feature access was revoked, or all their sessions killed. */
    public function adminCustomerAccessRevokedMsg(string $adminName, string $customer, array $changes, string $ip = '', string $when = ''): string
    {
        return $this->notice(
            'Customer access changed',
            'warning',
            'Service restriction applied to a customer',
            ['A customer\'s ability to use the platform was changed. Cleared capability flags strand the customer\'s funds; a forced sign-out ends every live session immediately.'],
            array_merge(
                ['Customer' => $customer],
                MailBrand::changeRows($changes),
                self::actorRows($adminName, $ip, $when)
            ),
            'Review customers',
            MailBrand::adminUrl('users.php')
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
        return $this->notice(
            'Customer identity details changed',
            'warning',
            'KYC-relevant details were changed by the customer',
            ['A customer updated their own identity details. These are the fields a KYC file is built on, so a change here may need re-verification.'],
            array_merge(
                ['Customer' => $customer],
                MailBrand::changeRows($changes),
                ['Source IP' => $ip, 'When' => $when !== '' ? $when : MailBrand::now()]
            ),
            'Review customers',
            MailBrand::adminUrl('users.php')
        );
    }

    /** A customer asked for a password reset link. */
    public function adminPasswordResetRequestedMsg(string $customer, string $email, string $ip = '', string $when = ''): string
    {
        return $this->notice(
            'Customer password reset requested',
            'info',
            'A password reset link was issued',
            ['A customer requested a password reset. A burst of these across different accounts is an account-takeover probe rather than ordinary forgetfulness.'],
            [
                'Customer'  => $customer,
                'Address'   => $email,
                'Source IP' => $ip,
                'When'      => $when !== '' ? $when : MailBrand::now(),
            ],
            'Review customers',
            MailBrand::adminUrl('users.php')
        );
    }

    /** A customer hit the failed-attempt lockout — credential-stuffing signal. */
    public function adminCustomerLockoutMsg(string $customer, int $attempts, string $lockedUntil, string $ip = '', string $when = ''): string
    {
        return $this->notice(
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
                'When'            => $when !== '' ? $when : MailBrand::now(),
            ],
            'Review customers',
            MailBrand::adminUrl('users.php')
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
        return $this->notice(
            'System health check failed',
            'error',
            count($problems) . ' health check' . (count($problems) === 1 ? '' : 's') . ' failing',
            ['One or more platform health probes are reporting a failure. Customers may be unable to sign in or transact.'],
            array_merge(MailBrand::changeRows($problems), ['Checked' => $when !== '' ? $when : MailBrand::now()]),
            'Open system health',
            MailBrand::adminUrl('system-health.php')
        );
    }

    /** A schema migration was executed against the live database. */
    public function adminMigrationRunMsg(string $adminName, string $file, int $statements = 0, string $ip = '', string $when = ''): string
    {
        return $this->notice(
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

}

if (!function_exists('admin_notify')) {
    /**
     * Deliver an operator alert.
     *
     * Recipients: the admin-configured support address (settings.url_email),
     * the WEB_EMAIL constant, and every row in the `admin` table, de-duplicated.
     * That combination is deliberate — include/smtp.php's send_to_both() only
     * ever reaches WEB_EMAIL, a deploy-time constant, while operators edit a
     * separate address in settings. The two silently diverge, so an alert sent
     * to just one of them can land in an inbox nobody reads. Including the
     * admin rows also means a second operator still hears about it when the
     * first account is the one being compromised.
     *
     * Best-effort and never throws: a notification failure must not roll back
     * the admin action that triggered it.
     *
     * @param string $html    Body from one of the AdminAlert methods.
     * @param string $subject Subject without the brand prefix — it is added.
     * @return bool True when at least one delivery succeeded.
     */
    function admin_notify(string $html, string $subject): bool
    {
        try {
            if (!class_exists('message')) {
                error_log('[admin_notify] mailer unavailable for: ' . $subject);
                return false;
            }

            $recipients = [];

            $support = (string)(MailBrand::resolve()['supportEmail'] ?? '');
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
            $line   = '[' . MailBrand::name() . '] ' . $subject;
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
