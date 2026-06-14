# Hive Break — Website

Static marketing site for hivebreak.com. No build step. Contact form intake runs
through a small PHP handler that calls the Brevo API (hosted on Hostinger).

## Files

- `index.html` — splash / home page
- `contact.html` — "Work With Us" contact form (posts to `contact-handler.php`)
- `privacy.html` — privacy policy
- `styles.css` — design system & layout
- `app.js` — honeycomb canvas, scroll reveals, counters
- `contact-handler.php` — receives the form → emails you + adds the contact, via Brevo
- `config.sample.php` — template for the server-side config (Brevo key, etc.)
- `assets/` — logo, favicon, founder photo

## Local preview

Open `index.html`, or run `python3 -m http.server 8000`.
Note: the contact form's submit needs PHP, so it only fully works once deployed to
Hostinger (or any PHP host). The pages themselves preview fine without it.

## Deploy (Hostinger)

1. Upload all files to `public_html` (File Manager, FTP, or Hostinger's Git deploy).
2. Copy `config.sample.php` → `config.php` on the server and fill in:
   - your **Brevo API key** (Brevo → SMTP & API → API Keys → v3 key, `xkeysib-…`)
   - the notification recipient (defaults to `info@hivebreak.com`)
   - a **verified Brevo sender** for the "from" address
   - (optional) a Brevo list ID to add submitters to
3. `config.php` holds the secret key and is git-ignored — it never goes in the repo.

The form degrades gracefully: with JavaScript it submits inline; without it, the PHP
handler redirects back to `contact.html?sent=1`.
