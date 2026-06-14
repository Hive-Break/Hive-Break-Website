# Deploy & Setup Guide — Hive Break Website

Everything left to take this repo live on **Hostinger** with the contact form wired
to **Brevo**. This static site is intended to **replace the current WordPress site**
at hivebreak.com.

> Picking up on a new machine? `git clone https://github.com/Hive-Break/Hive-Break-Website.git`
> gets you everything here **except** `config.php` (the Brevo secret), which lives only
> on the server and is intentionally git-ignored. Recreate it on the server per Step 2.

---

## Status at a glance

**Done (in this repo):**
- [x] `index.html` — splash page; all "Get in touch" buttons point to the contact page
- [x] `contact.html` — branded "Let's Connect" form (name, email, YouTube URL, org, interests, context, links, consent)
- [x] `contact-handler.php` — emails the inquiry to you **and** adds the contact to Brevo
- [x] `privacy.html` — tailored privacy policy (replaces the old boilerplate)
- [x] `styles.css` — form + legal page styles
- [x] `config.sample.php` — template for the server-side secret
- [x] `.gitignore` — keeps `config.php` (the key) out of git

**Left to do (this guide):**
- [ ] Step 1 — Get the files onto Hostinger
- [ ] Step 2 — Create `config.php` on the server
- [ ] Step 3 — Set up Brevo (API key + verified sender + optional list)
- [ ] Step 4 — Point the domain at the new site (replace WordPress)
- [ ] Step 5 — Test the form end-to-end on the live server

---

## How the contact form works (architecture)

There is no WordPress and no build step. The flow:

```
contact.html  --(POST form)-->  contact-handler.php  --(Brevo API)-->  1. Email to you
                                       |                                2. Add/update Brevo contact
                                  reads config.php (your Brevo API key, never in git)
```

- **With JavaScript:** the form submits in the background and shows an inline success/error message.
- **Without JavaScript:** the form posts normally; PHP redirects back to `contact.html?sent=1`.
- The PHP calls two Brevo endpoints: `POST /v3/smtp/email` (the notification to you) and
  `POST /v3/contacts` (adds the submitter). The email is the critical path; if the contact-add
  fails (e.g. a list/attribute isn't set up), the visitor still sees success and you still get the email.

---

## Step 1 — Get the files onto Hostinger

Requirements on the host: **PHP 7.4+ (8.x preferred) with cURL enabled** — Hostinger's
default. Check/set the version in hPanel → **Advanced → PHP Configuration**.

**Recommended: stage it first.** Don't overwrite the live WordPress site until the form is
proven. Create a subdomain (hPanel → **Domains → Subdomains**), e.g. `new.hivebreak.com`,
and deploy there first.

Upload options (any one):
- **hPanel File Manager** — zip the repo, upload to the target `public_html`, extract.
- **FTP** (FileZilla) — drag the files into the target `public_html`.
- **Hostinger Git** (hPanel → **Advanced → GIT**) — connect this GitHub repo and pull. Cleanest
  for ongoing updates: push to GitHub, then "Deploy" in hPanel.

What to upload: everything in the repo **except** `.git/`, `README.md`, `DEPLOY.md`,
and `config.sample.php` are optional on the server (harmless if present). You **must** upload
`index.html`, `contact.html`, `privacy.html`, `styles.css`, `app.js`, `contact-handler.php`,
and the `assets/` folder.

> **Back up the WordPress site first** (hPanel → Files → Backups, or export it). Keep it until the
> new site is verified — it holds your old form submissions and current Brevo/Forminator config
> for reference.

---

## Step 2 — Create `config.php` on the server

`config.php` holds your Brevo API key and is **never** committed to git. Create it on the server
(File Manager → New File, or upload), in the **same folder as `contact-handler.php`**:

1. Copy the contents of `config.sample.php`.
2. Save it as `config.php`.
3. Fill in the values from Step 3 below.

```php
<?php
return [
    'brevo_api_key'  => 'xkeysib-....................',  // from Brevo (Step 3a)
    'notify_to'      => 'info@hivebreak.com',            // where inquiries are emailed
    'notify_to_name' => 'Hive Break',
    'sender_email'   => 'info@hivebreak.com',            // MUST be a verified Brevo sender (Step 3b)
    'sender_name'    => 'Hive Break Website',
    'list_id'        => 0,                               // optional Brevo list ID (Step 3c), 0 = skip
];
```

---

## Step 3 — Set up Brevo

### 3a. Get a v3 API key
Brevo dashboard → top-right account menu → **SMTP & API** → **API Keys** → **Generate a new API key**.
It starts with `xkeysib-`. Paste it into `config.php` as `brevo_api_key`.

### 3b. Verify the sender (required, or emails get rejected)
Brevo → **Senders, Domains & Dedicated IPs**.
- At minimum, add and verify `info@hivebreak.com` as a sender.
- **Better:** authenticate the whole `hivebreak.com` domain (SPF + DKIM records). This dramatically
  improves deliverability so your notifications don't land in spam. Hostinger DNS is in
  hPanel → **Domains → DNS / Nameservers**; add the records Brevo gives you.

### 3c. (Optional) Contact list
If you want submitters added to a specific list: Brevo → **Contacts → Lists**, note the list's
numeric ID, and set `list_id` in `config.php`. Leave `0` to skip.

> **Optional polish:** the notification email already contains every field. The Brevo *contact*
> record only stores standard fields (first/last name) by default. If you want the YouTube URL,
> organization, interests, etc. stored on the contact too, create matching **custom attributes**
> in Brevo (Contacts → Settings → Contact Attributes) and tell me — I'll map them in
> `contact-handler.php`.

---

## Step 4 — Point the domain at the new site

The DNS for hivebreak.com already points to Hostinger, so this is about which files the domain
serves, not DNS.

1. Verify everything on the staging subdomain from Step 1 (run Step 5 there first).
2. When happy, move the new files into the **primary domain's `public_html`** (replacing the
   WordPress files). Keep the WordPress backup.
3. Preserve the canonical redirect: the old site sent `www.hivebreak.com` → `hivebreak.com`.
   Add a `.htaccess` in `public_html` if it's not already handled:

   ```apache
   # Force HTTPS + non-www
   RewriteEngine On
   RewriteCond %{HTTPS} off [OR]
   RewriteCond %{HTTP_HOST} ^www\. [NC]
   RewriteCond %{HTTP_HOST} ^(?:www\.)?(.+)$ [NC]
   RewriteRule ^ https://%1%{REQUEST_URI} [L,R=301]
   ```

---

## Step 5 — Test on the live server

On the deployed URL (staging or production):
1. Open `/contact.html`, fill the form with your own email, submit.
2. Confirm you see the green success message.
3. Confirm the inquiry email arrives at `info@hivebreak.com` (check spam too).
4. Confirm the contact appears in Brevo (Contacts).
5. Click "Submit" with empty required fields → you should see validation, not a send.
6. Open `/privacy.html` and the footer "Privacy" link from each page.

If the email doesn't arrive: it's almost always Step 3b (sender not verified) or a wrong API key.
Hostinger error logs (hPanel → Advanced → or the `error_log` file) will show `contact-handler:` lines.

---

## Local preview (any machine)

```bash
cd "Hive-Break-Website"
python3 -m http.server 8000   # or: python -m http.server 8000
```
Open http://localhost:8000/. The pages render fully; the **form Submit won't work locally**
(no PHP) — that's expected. It only sends once on a PHP host.

---

## Notes for future-you

- **Secret hygiene:** the Brevo key lives only in `config.php` on the server. Never commit it.
  If it ever leaks, rotate it in Brevo (SMTP & API → API Keys) and update `config.php`.
- **Spam protection:** the form has a hidden honeypot field. If spam becomes a problem, add
  Cloudflare Turnstile or hCaptcha — ask and I'll wire it in.
- **Editing copy:** form fields live in `contact.html`; the policy text in `privacy.html`;
  the handler logic and email layout in `contact-handler.php`.
