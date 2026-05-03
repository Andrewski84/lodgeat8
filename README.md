# Lodging at 8 PHP Site

A small, self-contained PHP website for Lodging at 8. The project includes a public website, a protected admin area, editable content stored as JSON, image galleries, a booking dropdown and a contact form.

## Requirements

- PHP 8.1 or newer is recommended.
- The `storage/` directory must be writable by PHP.
- The `assets/img/` directory and its subdirectories must be writable by PHP if images are uploaded or physically deleted through the admin area.
- Apache should use the included `.htaccess` file in production.

## Local Development

Start the built-in PHP server from the project root:

```bash
php -S localhost:8000
```

Open the site at `http://localhost:8000` and the admin area at `http://localhost:8000/beheer/`.

## Project Structure

- `index.php` is the public front controller.
- `beheer/` contains the admin interface.
- `beheer/sections/` contains section-specific admin templates.
- `admin/` redirects old admin URLs to `beheer/`.
- `includes/` contains shared PHP logic for content, routing, rendering, contact handling and admin actions.
- `includes/admin/` contains modular admin logic (settings, session/auth, media and content save helpers).
- `views/layout.php` is the public page shell.
- `views/page.php` selects the correct page template.
- `views/pages/` contains page-specific templates.
- `views/partials/` contains shared visual pieces such as header, footer, galleries and booking UI.
- `assets/css/style.css` contains public site styles.
- `assets/css/admin.css` contains admin styles.
- `assets/js/app.js` contains public interactions.
- `assets/js/admin.js` contains admin interactions.
- `assets/img/` contains all public and admin-managed images.
- `storage/content/` contains editable site content split across logical JSON files.
- `storage/admin.php` contains local runtime admin credentials after setup and is not committed.
- `storage/contact-messages.json` stores contact form submissions.
- `tools/go-live-check.php` validates the site before deployment.

## Content Model

Default content lives in `includes/data.php`. Admin edits are saved to `storage/content/*.json` (with backward-compatible read support for legacy `storage/content.json`) and merged with those defaults at runtime, so missing optional keys can still fall back to the default structure.

Image references are stored as paths relative to `assets/img/`. Galleries use ordered arrays of image objects with `file` and optional `title` or `alt` values.

## Admin

Open `http://localhost:8000/beheer/`. On first setup, create an admin login with an email address and password. The admin area supports multilingual page text, room information, galleries, logo management, booking settings, contact details, links and login changes.

Security highlights:
- Session hardening with strict cookies and inactivity timeout.
- Login throttling to slow brute-force attempts.
- One-time password reset links by email from the login screen or from the admin access section.

## Go Live

Before deploying, run:

```bash
php tools/go-live-check.php
```

Upload the project contents directly into the public web root where `index.php` should load. Keep `.htaccess` files in place because they protect internal folders such as `storage/`, `includes/`, `views/`, `tools/`, `beheer/partials/` and `beheer/sections/`.

Do not upload local logs, temporary files, one-off test artifacts or a development `storage/admin.php`. Let production create its own admin login on first setup, or replace it only with the intended production credentials.

## Contact Form

Contact messages are written to `storage/contact-messages.json`. The site also attempts to send an email to the configured site address. If hosting blocks PHP `mail()`, the JSON copy remains available.

The form includes CSRF protection, a honeypot, minimum submit-time validation and basic rate limiting (stored in `storage/contact-rate-limit.json`).
