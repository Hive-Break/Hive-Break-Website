<?php
/**
 * Hive Break — contact form configuration (TEMPLATE)
 *
 * SETUP:
 *   1. Copy this file to `config.php` in the same folder on the server.
 *   2. Fill in your Brevo API key and sender details below.
 *   3. `config.php` is git-ignored, so your key never lands in the repo.
 *
 * Where to find the Brevo API key:
 *   Brevo dashboard → top-right account menu → "SMTP & API" → "API Keys"
 *   → create a v3 key (starts with "xkeysib-").
 *
 * IMPORTANT: `sender_email` must be a VERIFIED sender/domain in Brevo
 *   (Brevo dashboard → "Senders, Domains & Dedicated IPs"), or the
 *   notification email will be rejected.
 */

return [
    // Brevo v3 API key (xkeysib-...). Used for BOTH the notification email
    // and adding the contact to Brevo.
    'brevo_api_key'  => 'PASTE-YOUR-BREVO-API-KEY-HERE',

    // Where inquiry notifications are emailed (your inbox).
    'notify_to'      => 'info@hivebreak.com',
    'notify_to_name' => 'Hive Break',

    // The "from" identity for the notification email.
    // MUST be a verified sender in Brevo.
    'sender_email'   => 'info@hivebreak.com',
    'sender_name'    => 'Hive Break Website',

    // Optional: Brevo contact list ID to add submitters to.
    // Find it under Brevo → "Contacts" → "Lists" (the # next to the list).
    // Leave as 0 to skip adding contacts to a list.
    'list_id'        => 0,
];
