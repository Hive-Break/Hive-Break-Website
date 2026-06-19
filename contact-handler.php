<?php
/* ============================================================
   Hive Break — contact form handler
   Receives the contact form POST, then via the Brevo API:
     1. Emails the inquiry to you (transactional email)
     2. Adds / updates the submitter as a Brevo contact
   No frameworks, no Composer — just PHP + cURL.
   Config (incl. the Brevo API key) lives in config.php (git-ignored).
   ============================================================ */

declare(strict_types=1);

/* ---------- helpers ---------- */

/**
 * Send a response and exit.
 * - For fetch/AJAX requests (Accept: application/json) → JSON.
 * - For a plain browser form post (no JS) → redirect back to the
 *   contact page with a status flag so the user sees a message.
 */
function respond(bool $ok, string $message, int $status = 200): void {
    $wantsJson = (
        (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch')
    );

    http_response_code($status);

    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, ($ok ? 'message' : 'error') => $message]);
    } else {
        $flag = $ok ? 'sent=1' : 'error=' . rawurlencode($message);
        header('Location: contact.html?' . $flag . '#contact-form');
    }
    exit;
}

function clean(string $v): string {
    return trim(filter_var($v, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
}

function e(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/* ---------- guards ---------- */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(false, 'Method not allowed.', 405);
}

/* The Brevo key lives in config.php. Prefer a copy ONE LEVEL ABOVE the web
   root (outside the git checkout) so a redeploy can't wipe it and it can never
   be served as a file. Fall back to an in-folder copy for local development. */
$configPath = null;
foreach ([__DIR__ . '/../config.php', __DIR__ . '/config.php'] as $candidate) {
    if (is_file($candidate)) { $configPath = $candidate; break; }
}
if ($configPath === null) {
    // Misconfiguration on the server, not the visitor's fault.
    error_log('contact-handler: config.php missing (looked in ../ and ./)');
    respond(false, 'The form is not configured yet. Please email info@hivebreak.com.', 500);
}
$config = require $configPath;

/* Honeypot: real users never fill this hidden field; bots do. */
if (!empty($_POST['company_website'])) {
    // Pretend success so bots don't learn they were caught.
    respond(true, 'Thank you — your inquiry is in.');
}

/* ---------- collect + validate ---------- */

$name     = clean($_POST['name']         ?? '');
$email    = trim($_POST['email']         ?? '');
$channel  = trim($_POST['channel_url']   ?? '');
$org      = clean($_POST['organization'] ?? '');
$context  = clean($_POST['context']      ?? '');
$links    = trim($_POST['links']         ?? '');
$consent  = !empty($_POST['consent']);
$interests = $_POST['interests'] ?? [];
if (!is_array($interests)) { $interests = [$interests]; }
$interests = array_values(array_filter(array_map('clean', $interests)));

$errors = [];
if ($name === '')                                          { $errors[] = 'your name'; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL))            { $errors[] = 'a valid email'; }
if ($channel !== '' && !filter_var($channel, FILTER_VALIDATE_URL)) { $errors[] = 'a valid YouTube channel URL (or leave it blank)'; }
if (empty($interests))                                     { $errors[] = 'what brings you here'; }
if ($context === '')                                       { $errors[] = 'some context'; }
if (!$consent)                                             { $errors[] = 'consent to the privacy policy'; }

if ($links !== '' && !filter_var($links, FILTER_VALIDATE_URL)) {
    $errors[] = 'a valid links URL (or leave it blank)';
}

if ($errors) {
    respond(false, 'Please add ' . implode(', ', $errors) . '.', 422);
}

/* ---------- Brevo: transactional email to you ---------- */

$interestsStr = implode(', ', $interests);

$rows = [
    ['Name',          $name],
    ['Email',         $email],
    ['YouTube',       $channel !== '' ? $channel : '—'],
    ['Organization',  $org !== '' ? $org : '—'],
    ['Interested in', $interestsStr],
    ['Links',         $links !== '' ? $links : '—'],
];
$rowsHtml = '';
foreach ($rows as [$label, $val]) {
    $rowsHtml .= '<tr>'
        . '<td style="padding:6px 14px 6px 0;color:#8a8a8a;vertical-align:top;white-space:nowrap;">' . e($label) . '</td>'
        . '<td style="padding:6px 0;color:#111;">' . e($val) . '</td>'
        . '</tr>';
}
$contextHtml = nl2br(e($context));

$html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;">'
    . '<h2 style="margin:0 0 4px;color:#111;">New inquiry — Hive Break</h2>'
    . '<p style="margin:0 0 18px;color:#8a8a8a;font-size:13px;">Submitted via hivebreak.com contact form</p>'
    . '<table style="border-collapse:collapse;font-size:15px;width:100%;">' . $rowsHtml . '</table>'
    . '<h3 style="margin:22px 0 6px;color:#111;font-size:15px;">Context</h3>'
    . '<p style="margin:0;color:#111;font-size:15px;line-height:1.55;">' . $contextHtml . '</p>'
    . '</div>';

$emailPayload = [
    'sender'      => ['name' => $config['sender_name'], 'email' => $config['sender_email']],
    'to'          => [['email' => $config['notify_to'], 'name' => $config['notify_to_name']]],
    'replyTo'     => ['email' => $email, 'name' => $name !== '' ? $name : $email],
    'subject'     => 'New inquiry — ' . $name . ' (' . $interestsStr . ')',
    'htmlContent' => $html,
];

[$emailHttp, $emailResp] = brevo_post('https://api.brevo.com/v3/smtp/email', $emailPayload, $config['brevo_api_key']);

if ($emailHttp < 200 || $emailHttp >= 300) {
    error_log('contact-handler: Brevo email failed (' . $emailHttp . '): ' . $emailResp);
    respond(false, "Sorry, something went wrong sending your inquiry. Please email info@hivebreak.com directly.", 502);
}

/* ---------- Brevo: add / update the contact (best-effort) ---------- */
/* The email above is the critical path. Adding the contact to Brevo is a
   nice-to-have; if attributes/list aren't set up it must NOT fail the form. */

$parts = preg_split('/\s+/', $name, 2);
$attributes = [
    'FIRSTNAME' => $parts[0] ?? '',
    'LASTNAME'  => $parts[1] ?? '',
];

$contactPayload = [
    'email'         => $email,
    'attributes'    => $attributes,
    'updateEnabled' => true,
];
if (!empty($config['list_id'])) {
    $contactPayload['listIds'] = [(int) $config['list_id']];
}

[$cHttp, $cResp] = brevo_post('https://api.brevo.com/v3/contacts', $contactPayload, $config['brevo_api_key']);
if ($cHttp < 200 || $cHttp >= 300) {
    // Log only — do not fail the submission.
    error_log('contact-handler: Brevo contact upsert non-fatal error (' . $cHttp . '): ' . $cResp);
}

/* ---------- done ---------- */
respond(true, "Thank you — your inquiry is in. We'll be in touch personally, usually within two business days.");


/* ============================================================
   Brevo API POST via cURL. Returns [httpStatus, responseBody].
   ============================================================ */
function brevo_post(string $url, array $payload, string $apiKey): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        error_log('contact-handler: cURL error: ' . $err);
        return [0, $err];
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, (string) $body];
}
