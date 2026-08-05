<?php
/**
 * Customer-facing email templates triggered by an admin action — a deposit
 * approved, a transfer declined, a balance adjusted.
 *
 * These keep their original method names and parameter order because they are
 * called from admin/*.php pages; any new parameter is appended and optional.
 *
 * The OPERATOR-facing alerts that used to live here now live in
 * include/admin_alerts.php as `class AdminAlert`. They moved because four of
 * the events an operator most needs to hear about fire from api/ code, which
 * loads include/userClass.php — and that file declares its own
 * `class emailMessage`, so requiring this file from api/ is a fatal redeclare.
 * The alerts were unreachable from exactly the paths that needed them.
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
require_once __DIR__ . '/../../include/admin_alerts.php';

class emailMessage
{
    // =================================================================
    // Brand + formatting plumbing
    // =================================================================


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
        return MailBrand::resolve();
    }

    /** The operator-configured platform name, for subject lines. */
    public static function brandName(): string
    {
        return MailBrand::name();
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
        $mail = MailBrand::template($brandName)
            ->preheader('Your ' . $brandName . ' account is open — account number ' . $acct_no)
            ->heading('Your account is open')
            ->greeting('Hello ' . $fullName . ',')
            ->line('Thank you for opening an account with ' . $brandName . '. Your account is active and ready to use.')
            ->table([
                'Account holder'    => $fullName,
                'Account number'    => $acct_no,
                'Account type'      => $acct_type,
                'Available balance' => MailBrand::money((string)$currency, $amount_balance),
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

        return MailBrand::template((string)$APP_NAME)
            ->preheader('Your deposit of ' . MailBrand::money((string)$currency, $amount) . ' was ' . $status)
            ->heading('Deposit ' . strtolower($status))
            ->accent(MailBrand::statusTone($status))
            ->greeting('Hello ' . $fullName . ',')
            ->alert('Deposit ' . $status, MailBrand::statusTone($status))
            ->line('Your deposit of ' . MailBrand::money((string)$currency, $amount) . ' was ' . $status . '.')
            ->table([
                'Reference'         => $reference_id,
                'Amount'            => MailBrand::money((string)$currency, $amount),
                'Payment method'    => $crypto_name,
                'Status'            => $status,
                'Available balance' => MailBrand::money((string)$currency, $amount_balance),
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
        $tone   = $status === '' ? 'info' : MailBrand::statusTone($status);

        $mail = MailBrand::template((string)$APP_NAME)
            ->preheader('Your withdrawal of ' . MailBrand::money((string)$currency, $amount) . ($status === '' ? ' has been updated' : ' was ' . $status))
            ->heading($status === '' ? 'Withdrawal update' : 'Withdrawal ' . strtolower($status))
            ->accent($tone)
            ->greeting('Hello ' . $fullName . ',');

        if ($status !== '') {
            $mail->alert('Withdrawal ' . $status, $tone);
            $mail->line('Your withdrawal of ' . MailBrand::money((string)$currency, $amount) . ' was ' . $status . '.');
        } else {
            $mail->line('The status of your withdrawal of ' . MailBrand::money((string)$currency, $amount) . ' has been updated.');
        }

        return $mail
            ->table([
                'Reference'         => $reference_id,
                'Amount'            => MailBrand::money((string)$currency, $amount),
                'Status'            => $status,
                'Available balance' => MailBrand::money((string)$currency, $amount_balance),
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

        return MailBrand::template((string)$APP_NAME)
            ->preheader('Your transfer of ' . MailBrand::money((string)$currency, $amount) . ' was ' . $status)
            ->heading('Domestic transfer ' . strtolower($status))
            ->accent(MailBrand::statusTone($status))
            ->greeting('Hello ' . $fullName . ',')
            ->alert('Transfer ' . $status, MailBrand::statusTone($status))
            ->line('Your ' . strtolower((string)$trans_type) . ' of ' . MailBrand::money((string)$currency, $amount) . ' was ' . $status . '.')
            ->table([
                'Reference'         => $reference_id,
                'Beneficiary'       => $acct_name,
                'Bank'              => $bank_name,
                'Account number'    => $acct_number,
                'Account type'      => $acct_type,
                'Amount'            => MailBrand::money((string)$currency, $amount),
                'Status'            => $status,
                'Available balance' => MailBrand::money((string)$currency, $amount_balance),
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
        return MailBrand::template($appName)
            ->preheader($noun . ' of ' . MailBrand::money($currency, $amount) . ' was ' . $status)
            ->heading($noun . ' ' . strtolower($status))
            ->accent(MailBrand::statusTone($status))
            ->greeting('Hello ' . $fullName . ',')
            ->alert($noun . ' ' . $status, MailBrand::statusTone($status))
            ->line('Your ' . strtolower($noun) . ' of ' . MailBrand::money($currency, $amount) . ' was ' . $status . '.')
            ->table([
                'Reference'         => $reference,
                'Type'              => $type,
                'Amount'            => MailBrand::money($currency, $amount),
                'Status'            => $status,
                'Available balance' => MailBrand::money($currency, $balance),
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

        $mail = MailBrand::template((string)$APP_NAME)
            ->preheader('Your loan application was ' . $status)
            ->heading('Loan ' . strtolower($status))
            ->accent(MailBrand::statusTone($status));

        if (trim((string)$fullName) !== '') {
            $mail->greeting('Hello ' . $fullName . ',');
        }

        $mail->alert('Loan ' . $status, MailBrand::statusTone($status));

        if (trim((string)$messageText) !== '') {
            $mail->panel((string)$messageText);
        }

        return $mail
            ->table([
                'Loan amount'       => MailBrand::money((string)$currency, $amount),
                'Status'            => $status,
                'Available loan'    => MailBrand::money((string)$currency, $available_loan),
                'Available balance' => MailBrand::money((string)$currency, $amount_balance),
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

        return MailBrand::template((string)$APP_NAME)
            ->preheader('A ' . strtolower((string)$transfer_type) . ' of ' . MailBrand::money((string)$currency, $amount) . ' was processed on your account')
            ->heading('Wire transfer ' . strtolower($status))
            ->accent(MailBrand::statusTone($status))
            ->greeting('Hello ' . $fullName . ',')
            ->alert('Wire transfer ' . $status, MailBrand::statusTone($status))
            ->line('A ' . strtolower((string)$transfer_type) . ' of ' . MailBrand::money((string)$currency, $amount) . ' was processed on your account.')
            ->table([
                'Reference'         => $reference_id,
                'Transfer method'   => $transfer_type,
                'Beneficiary'       => $acct_name,
                'Bank'              => $bank_name,
                'Account number'    => $acct_number,
                'Country'           => $acct_country,
                'Amount'            => MailBrand::money((string)$currency, $amount),
                'Status'            => $status,
                'Date'              => $created_at,
                'Available balance' => MailBrand::money((string)$currency, $available_balance),
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
        $money   = MailBrand::money((string)$currency, $amount);

        $mail = MailBrand::template((string)$APP_NAME)
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
                'Available balance' => MailBrand::money((string)$currency, $available_balance),
            ])
            ->salutation()
            ->subcopy('If you do not recognise this transaction, contact us immediately.')
            ->render();
    }
}
