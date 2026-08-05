<?php
/**
 * Shared transactional-email layout.
 *
 * Every email the platform sends — customer receipts and admin alerts alike —
 * is composed through this builder instead of hand-written HTML. It is a PHP
 * port of Laravel's default markdown-mail theme: light grey canvas, a single
 * white card, a bold left-aligned heading, body copy, and optional panel /
 * table / button / subcopy blocks.
 *
 * Why this exists
 * ---------------
 * The previous templates were ~200 lines of copy-pasted table markup per
 * message (14 near-identical copies in admin/include/adminClass.php alone).
 * That duplication carried three real defects into every message:
 *
 *   1. `style='... font-family: 'Lato', Helvetica ...'` — the nested single
 *      quotes closed the style attribute early, so the rest of it leaked into
 *      the tag as bare attributes and the declared font never applied.
 *   2. Caller-supplied values (names, bank names, references) were dropped
 *      into HTML unescaped, so any `<` in the data broke — or rewrote — the
 *      message body.
 *   3. Remote @font-face downloads from fonts.gstatic.com, which nearly every
 *      mail client blocks, plus dead `href='#'` "unsubscribe" links.
 *
 * All three are structurally impossible here: markup is emitted by this file
 * only, every value goes through htmlspecialchars() on the way in, and the
 * font stack is system-local.
 *
 * Usage
 * -----
 *   $html = MailTemplate::make()
 *       ->heading('Wire transfer received')
 *       ->greeting('Hello Ada,')
 *       ->line('We have received your wire transfer request.')
 *       ->table(['Amount' => '$1,000.00', 'Reference' => 'WIRE123'])
 *       ->button('View transaction', $url)
 *       ->subcopy('If you did not authorise this, contact us immediately.')
 *       ->render();
 *
 * Escaping contract: every public method escapes its arguments. Pass plain
 * text. `raw()` is the single deliberate opt-out and is documented at its
 * definition — do not feed it caller-controlled data.
 */

declare(strict_types=1);

if (!class_exists('MailTemplate')) {

final class MailTemplate
{
    // ---------------------------------------------------------------
    // Laravel default theme palette. Kept as constants (rather than
    // scattered literals) so a future brand-colour setting only has to
    // override `$accent` — the rest of the theme stays neutral grey and
    // keeps working with any accent.
    // ---------------------------------------------------------------
    private const CANVAS      = '#edf2f7';
    private const CARD        = '#ffffff';
    private const CARD_BORDER = '#e8e5ef';
    private const HEADING     = '#3d4852';
    private const TEXT        = '#718096';
    private const FOOTER_TEXT = '#b0adc5';
    private const PANEL_BG    = '#edf2f7';
    private const RULE        = '#e8e5ef';

    private const FONT = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";

    /** Button / alert accents, mirroring Laravel's button-primary|success|error. */
    private const COLORS = [
        'primary' => '#2d3748',
        'success' => '#48bb78',
        'error'   => '#e53e3e',
        'warning' => '#ed8936',
        'info'    => '#4299e1',
    ];

    /** @var array<int,array{0:string,1:mixed}> Ordered body blocks. */
    private array $blocks = [];

    private string $preheader = '';
    private string $heading   = '';
    private array  $subcopy   = [];

    private string $appName;
    private string $appUrl;
    private ?string $logoUrl;
    private string $supportEmail;
    private string $supportPhone;
    private string $accent;

    private function __construct(array $brand)
    {
        $this->appName      = (string)($brand['appName'] ?? (defined('WEB_TITLE') ? WEB_TITLE : 'BankPro'));
        $this->appUrl       = rtrim((string)($brand['appUrl'] ?? (defined('WEB_URL') ? WEB_URL : '')), '/');
        $this->logoUrl      = isset($brand['logoUrl']) && $brand['logoUrl'] !== '' ? (string)$brand['logoUrl'] : null;
        $this->supportEmail = (string)($brand['supportEmail'] ?? (defined('WEB_EMAIL') ? WEB_EMAIL : ''));
        $this->supportPhone = (string)($brand['supportPhone'] ?? '');
        $this->accent       = self::COLORS['primary'];

        // A relative logo path is useless in an inbox — mail clients have no
        // origin to resolve it against. Promote it to absolute, or drop it.
        if ($this->logoUrl !== null && !preg_match('~^https?://~i', $this->logoUrl)) {
            $this->logoUrl = $this->appUrl !== ''
                ? $this->appUrl . '/' . ltrim($this->logoUrl, '/')
                : null;
        }
    }

    /**
     * @param array{appName?:string,appUrl?:string,logoUrl?:?string,supportEmail?:string,supportPhone?:string} $brand
     *        Anything omitted falls back to the WEB_* constants from
     *        include/config.php. `mail_brand_from_settings()` below builds
     *        this array straight from a `settings` row.
     */
    public static function make(array $brand = []): self
    {
        return new self($brand);
    }

    // -----------------------------------------------------------------
    // Header / framing
    // -----------------------------------------------------------------

    /**
     * Hidden preview line — what the inbox list shows next to the subject.
     * Worth setting on every message: without it clients scrape the first
     * visible text, which is usually just the greeting.
     */
    public function preheader(string $text): self
    {
        $this->preheader = $text;
        return $this;
    }

    /** The bold `# Heading` at the top of the card. */
    public function heading(string $text): self
    {
        $this->heading = $text;
        return $this;
    }

    /**
     * Accent colour for buttons and alert bars: primary|success|error|warning|info.
     * Unknown names fall back to primary rather than emitting a broken style.
     */
    public function accent(string $color): self
    {
        $this->accent = self::COLORS[$color] ?? self::COLORS['primary'];
        return $this;
    }

    // -----------------------------------------------------------------
    // Body blocks — rendered in call order
    // -----------------------------------------------------------------

    /** Greeting paragraph, rendered slightly darker than body copy. */
    public function greeting(string $text): self
    {
        $this->blocks[] = ['greeting', $text];
        return $this;
    }

    /** A body paragraph. */
    public function line(string $text): self
    {
        $this->blocks[] = ['line', $text];
        return $this;
    }

    /** @param string[] $lines Convenience for several paragraphs in a row. */
    public function lines(array $lines): self
    {
        foreach ($lines as $line) {
            $this->line((string)$line);
        }
        return $this;
    }

    /** Laravel's `mail::panel` — an indented, tinted callout block. */
    public function panel(string $text): self
    {
        $this->blocks[] = ['panel', $text];
        return $this;
    }

    /**
     * Key/value detail table — the workhorse for transaction receipts.
     *
     * @param array<string,string|int|float|null> $rows Label => value.
     *        Null and '' values are dropped so optional fields (a memo, a
     *        reference that has not been issued yet) simply do not render
     *        rather than showing an empty row.
     */
    public function table(array $rows): self
    {
        $clean = [];
        foreach ($rows as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $clean[(string)$label] = (string)$value;
        }
        if ($clean !== []) {
            $this->blocks[] = ['table', $clean];
        }
        return $this;
    }

    /**
     * Call-to-action button. Non-http(s) URLs are rejected outright — a
     * javascript: or data: href in an email is never legitimate here.
     */
    public function button(string $label, string $url, ?string $color = null): self
    {
        if (!preg_match('~^https?://~i', $url)) {
            return $this;
        }
        $this->blocks[] = ['button', [
            'label' => $label,
            'url'   => $url,
            'color' => self::COLORS[$color ?? ''] ?? $this->accent,
        ]];
        return $this;
    }

    /** Monospaced, letter-spaced block for a one-time code or PIN. */
    public function code(string $value, string $caption = ''): self
    {
        $this->blocks[] = ['code', ['value' => $value, 'caption' => $caption]];
        return $this;
    }

    /**
     * Coloured status bar — used by the admin alerts to make severity
     * legible at a glance.
     *
     * @param string $level success|error|warning|info
     */
    public function alert(string $text, string $level = 'warning'): self
    {
        $this->blocks[] = ['alert', [
            'text'  => $text,
            'color' => self::COLORS[$level] ?? self::COLORS['warning'],
        ]];
        return $this;
    }

    /** Sign-off. Defaults to the app name when no second argument is given. */
    public function salutation(string $text = 'Regards,', ?string $signature = null): self
    {
        $this->blocks[] = ['salutation', [
            'text'      => $text,
            'signature' => $signature ?? $this->appName,
        ]];
        return $this;
    }

    /** Fine print below the horizontal rule at the foot of the card. */
    public function subcopy(string $text): self
    {
        $this->subcopy[] = $text;
        return $this;
    }

    /**
     * Escape hatch for pre-built markup.
     *
     * SECURITY: the string is emitted verbatim. Only ever pass literals
     * written in this repository — never a database value, a request
     * parameter, or anything else a user can influence.
     */
    public function raw(string $html): self
    {
        $this->blocks[] = ['raw', $html];
        return $this;
    }

    // -----------------------------------------------------------------
    // Rendering
    // -----------------------------------------------------------------

    public function render(): string
    {
        $font   = self::FONT;
        $appEsc = self::e($this->appName);

        $bodyHtml = '';
        foreach ($this->blocks as [$type, $payload]) {
            $bodyHtml .= $this->renderBlock($type, $payload);
        }

        $headingHtml = $this->heading === '' ? '' : sprintf(
            "<h1 style=\"color:%s;font-family:%s;font-size:18px;font-weight:bold;margin:0 0 18px;text-align:left;\">%s</h1>\n",
            self::HEADING,
            $font,
            self::e($this->heading)
        );

        $subcopyHtml = '';
        if ($this->subcopy !== []) {
            $paras = '';
            foreach ($this->subcopy as $text) {
                $paras .= sprintf(
                    "<p style=\"color:%s;font-family:%s;font-size:14px;line-height:1.5em;margin:0 0 12px;text-align:left;\">%s</p>\n",
                    self::TEXT,
                    $font,
                    self::e($text)
                );
            }
            $subcopyHtml = sprintf(
                "<tr><td style=\"border-top:1px solid %s;padding:25px 32px 32px;\">\n%s</td></tr>\n",
                self::RULE,
                $paras
            );
        }

        // Zero-width joiners pad the preheader so clients that show a preview
        // snippet do not spill the greeting in after it.
        $preheaderHtml = $this->preheader === '' ? '' : sprintf(
            "<div style=\"display:none;font-size:1px;color:%s;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;\">%s%s</div>\n",
            self::CANVAS,
            self::e($this->preheader),
            str_repeat('&#8199;&#65279;&#847; ', 30)
        );

        $brandHtml = $this->logoUrl !== null
            ? sprintf(
                '<img src="%s" alt="%s" style="border:0;height:auto;max-height:56px;max-width:200px;line-height:100%%;outline:none;text-decoration:none;">',
                self::e($this->logoUrl),
                $appEsc
            )
            : sprintf(
                '<span style="color:%s;font-family:%s;font-size:19px;font-weight:bold;text-decoration:none;">%s</span>',
                self::HEADING,
                $font,
                $appEsc
            );

        if ($this->appUrl !== '') {
            $brandHtml = sprintf('<a href="%s" style="text-decoration:none;">%s</a>', self::e($this->appUrl), $brandHtml);
        }

        $footerBits = [sprintf('&copy; %s %s. All rights reserved.', date('Y'), $appEsc)];
        if ($this->supportEmail !== '') {
            $footerBits[] = sprintf(
                'Questions? <a href="mailto:%1$s" style="color:%2$s;text-decoration:underline;">%1$s</a>',
                self::e($this->supportEmail),
                self::FOOTER_TEXT
            );
        }
        if ($this->supportPhone !== '') {
            $footerBits[] = self::e($this->supportPhone);
        }
        $footerHtml = implode('<br>', $footerBits);

        $canvas = self::CANVAS;
        $card   = self::CARD;
        $border = self::CARD_BORDER;
        $footerText = self::FOOTER_TEXT;

        return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{$appEsc}</title>
<style type="text/css">
  body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}
  table,td{mso-table-lspace:0pt;mso-table-rspace:0pt;}
  img{-ms-interpolation-mode:bicubic;border:0;height:auto;line-height:100%;outline:none;text-decoration:none;}
  table{border-collapse:collapse!important;}
  body{height:100%!important;margin:0!important;padding:0!important;width:100%!important;}
  a[x-apple-data-detectors]{color:inherit!important;text-decoration:none!important;font-size:inherit!important;font-family:inherit!important;font-weight:inherit!important;line-height:inherit!important;}
  @media only screen and (max-width:600px){
    .mt-card{width:100%!important;}
    .mt-cell{padding:24px!important;}
  }
</style>
</head>
<body style="background-color:{$canvas};margin:0!important;padding:0!important;width:100%!important;">
{$preheaderHtml}<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:{$canvas};margin:0;padding:0;width:100%;">
  <tr>
    <td align="center" style="padding:25px 10px;">

      <!-- Header -->
      <table border="0" cellpadding="0" cellspacing="0" class="mt-card" width="570" style="max-width:570px;">
        <tr><td align="center" style="padding:0 0 25px;">{$brandHtml}</td></tr>
      </table>

      <!-- Card -->
      <table border="0" cellpadding="0" cellspacing="0" class="mt-card" width="570" style="background-color:{$card};border:1px solid {$border};border-radius:2px;max-width:570px;">
        <tr>
          <td class="mt-cell" style="padding:32px;">
{$headingHtml}{$bodyHtml}          </td>
        </tr>
{$subcopyHtml}      </table>

      <!-- Footer -->
      <table border="0" cellpadding="0" cellspacing="0" class="mt-card" width="570" style="max-width:570px;">
        <tr>
          <td align="center" style="padding:32px;">
            <p style="color:{$footerText};font-family:{$font};font-size:12px;line-height:1.5em;margin:0;text-align:center;">{$footerHtml}</p>
          </td>
        </tr>
      </table>

    </td>
  </tr>
</table>
</body>
</html>
HTML;
    }

    /**
     * Plain-text alternative built from the same blocks, so the text part can
     * never drift out of sync with the HTML. Feed it to PHPMailer's AltBody —
     * a message with a real text part scores materially better with spam
     * filters than an HTML-only one.
     */
    public function toText(): string
    {
        $out = [];
        if ($this->heading !== '') {
            $out[] = strtoupper($this->heading);
            $out[] = str_repeat('=', min(60, max(10, strlen($this->heading))));
            $out[] = '';
        }

        foreach ($this->blocks as [$type, $payload]) {
            switch ($type) {
                case 'greeting':
                case 'line':
                    $out[] = wordwrap((string)$payload, 74);
                    $out[] = '';
                    break;
                case 'panel':
                    $out[] = wordwrap('> ' . (string)$payload, 74, "\n> ");
                    $out[] = '';
                    break;
                case 'alert':
                    $out[] = wordwrap('*** ' . $payload['text'] . ' ***', 74);
                    $out[] = '';
                    break;
                case 'table':
                    $width = 0;
                    foreach (array_keys($payload) as $label) {
                        $width = max($width, strlen((string)$label));
                    }
                    foreach ($payload as $label => $value) {
                        $out[] = str_pad((string)$label, $width) . ' : ' . $value;
                    }
                    $out[] = '';
                    break;
                case 'button':
                    $out[] = $payload['label'] . ': ' . $payload['url'];
                    $out[] = '';
                    break;
                case 'code':
                    if ($payload['caption'] !== '') {
                        $out[] = $payload['caption'];
                    }
                    $out[] = '    ' . $payload['value'];
                    $out[] = '';
                    break;
                case 'salutation':
                    $out[] = $payload['text'];
                    $out[] = $payload['signature'];
                    $out[] = '';
                    break;
                case 'raw':
                    $plain = trim(html_entity_decode(strip_tags((string)$payload), ENT_QUOTES, 'UTF-8'));
                    if ($plain !== '') {
                        $out[] = wordwrap($plain, 74);
                        $out[] = '';
                    }
                    break;
            }
        }

        foreach ($this->subcopy as $text) {
            $out[] = str_repeat('-', 60);
            $out[] = wordwrap($text, 74);
        }

        $out[] = '';
        $out[] = sprintf('(c) %s %s', date('Y'), $this->appName);
        if ($this->supportEmail !== '') {
            $out[] = 'Questions? ' . $this->supportEmail;
        }

        return implode("\n", $out);
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /** @param mixed $payload */
    private function renderBlock(string $type, $payload): string
    {
        $font = self::FONT;
        $text = self::TEXT;

        switch ($type) {
            case 'greeting':
                return sprintf(
                    "            <p style=\"color:%s;font-family:%s;font-size:16px;font-weight:600;line-height:1.5em;margin:0 0 16px;text-align:left;\">%s</p>\n",
                    self::HEADING,
                    $font,
                    self::e((string)$payload)
                );

            case 'line':
                return sprintf(
                    "            <p style=\"color:%s;font-family:%s;font-size:16px;line-height:1.5em;margin:0 0 16px;text-align:left;\">%s</p>\n",
                    $text,
                    $font,
                    self::e((string)$payload)
                );

            case 'panel':
                return sprintf(
                    "            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%%\" style=\"margin:0 0 21px;\">\n"
                    . "              <tr><td style=\"background-color:%s;border-left:4px solid %s;padding:16px;\">\n"
                    . "                <p style=\"color:%s;font-family:%s;font-size:15px;line-height:1.5em;margin:0;text-align:left;\">%s</p>\n"
                    . "              </td></tr>\n            </table>\n",
                    self::PANEL_BG,
                    $this->accent,
                    $text,
                    $font,
                    self::e((string)$payload)
                );

            case 'alert':
                return sprintf(
                    "            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%%\" style=\"margin:0 0 21px;\">\n"
                    . "              <tr><td style=\"background-color:%s;border-radius:4px;padding:14px 16px;\">\n"
                    . "                <p style=\"color:#ffffff;font-family:%s;font-size:15px;font-weight:600;line-height:1.4em;margin:0;text-align:left;\">%s</p>\n"
                    . "              </td></tr>\n            </table>\n",
                    $payload['color'],
                    $font,
                    self::e((string)$payload['text'])
                );

            case 'table':
                $rows = '';
                foreach ($payload as $label => $value) {
                    $rows .= sprintf(
                        "                <tr>\n"
                        . "                  <td style=\"border-bottom:1px solid %s;color:%s;font-family:%s;font-size:15px;line-height:1.4em;padding:10px 12px 10px 0;text-align:left;vertical-align:top;width:45%%;\">%s</td>\n"
                        . "                  <td style=\"border-bottom:1px solid %s;color:%s;font-family:%s;font-size:15px;font-weight:600;line-height:1.4em;padding:10px 0;text-align:right;vertical-align:top;\">%s</td>\n"
                        . "                </tr>\n",
                        self::RULE,
                        $text,
                        $font,
                        self::e((string)$label),
                        self::RULE,
                        self::HEADING,
                        $font,
                        self::e((string)$value)
                    );
                }
                return "            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%\" style=\"margin:0 0 21px;width:100%;\">\n"
                    . $rows
                    . "            </table>\n";

            case 'button':
                // Border-as-padding is deliberate: Outlook ignores padding on
                // an <a>, but honours borders, so this is the only shape that
                // renders as a real button everywhere.
                return sprintf(
                    "            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%%\" style=\"margin:0 0 21px;\">\n"
                    . "              <tr><td align=\"center\">\n"
                    . "                <a href=\"%s\" target=\"_blank\" rel=\"noopener\" style=\"background-color:%s;border-top:10px solid %s;border-bottom:10px solid %s;border-left:20px solid %s;border-right:20px solid %s;border-radius:4px;color:#ffffff;display:inline-block;font-family:%s;font-size:15px;font-weight:600;line-height:1;text-decoration:none;\">%s</a>\n"
                    . "              </td></tr>\n            </table>\n",
                    self::e((string)$payload['url']),
                    $payload['color'],
                    $payload['color'],
                    $payload['color'],
                    $payload['color'],
                    $payload['color'],
                    $font,
                    self::e((string)$payload['label'])
                );

            case 'code':
                $caption = $payload['caption'] === '' ? '' : sprintf(
                    "                <p style=\"color:%s;font-family:%s;font-size:14px;margin:0 0 8px;text-align:center;\">%s</p>\n",
                    $text,
                    $font,
                    self::e((string)$payload['caption'])
                );
                return sprintf(
                    "            <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" width=\"100%%\" style=\"margin:0 0 21px;\">\n"
                    . "              <tr><td style=\"background-color:%s;border-radius:4px;padding:18px;\">\n"
                    . "%s"
                    . "                <p style=\"color:%s;font-family:'SFMono-Regular',Consolas,'Liberation Mono',Menlo,monospace;font-size:26px;font-weight:bold;letter-spacing:6px;margin:0;text-align:center;\">%s</p>\n"
                    . "              </td></tr>\n            </table>\n",
                    self::PANEL_BG,
                    $caption,
                    self::HEADING,
                    self::e((string)$payload['value'])
                );

            case 'salutation':
                return sprintf(
                    "            <p style=\"color:%s;font-family:%s;font-size:16px;line-height:1.5em;margin:24px 0 0;text-align:left;\">%s<br><strong style=\"color:%s;\">%s</strong></p>\n",
                    $text,
                    $font,
                    self::e((string)$payload['text']),
                    self::HEADING,
                    self::e((string)$payload['signature'])
                );

            case 'raw':
                return '            ' . $payload . "\n";
        }

        return '';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

}

if (!function_exists('mail_brand_from_settings')) {
    /**
     * Build the MailTemplate brand array from a `settings` row.
     *
     * Every caller already has this row in hand ($page in the admin layout,
     * the settings fetch in the API bootstraps), so templates get the
     * admin-configured logo and support details without a second query.
     * Falls back cleanly to the WEB_* constants when a field is blank or the
     * row is missing entirely.
     *
     * @param array $settings Row from `settings` (may be empty).
     */
    function mail_brand_from_settings(array $settings = []): array
    {
        $appUrl = defined('WEB_URL') ? WEB_URL : '';

        // resolve_logo_url() checks the file actually exists on disk before
        // returning a path, so a stale settings.image never yields a broken
        // <img> in someone's inbox.
        $logo = null;
        if (function_exists('resolve_logo_url')) {
            $logo = resolve_logo_url($settings, __DIR__ . '/..');
        }

        $name = trim((string)($settings['url_name'] ?? ''));
        $mail = trim((string)($settings['url_email'] ?? ''));
        $tel  = trim((string)($settings['url_tel'] ?? ''));

        return [
            'appName'      => $name !== '' ? $name : (defined('WEB_TITLE') ? WEB_TITLE : 'BankPro'),
            'appUrl'       => $appUrl,
            'logoUrl'      => $logo,
            'supportEmail' => $mail !== '' ? $mail : (defined('WEB_EMAIL') ? WEB_EMAIL : ''),
            'supportPhone' => $tel,
        ];
    }
}
