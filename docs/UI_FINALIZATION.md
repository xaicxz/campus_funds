# LSL Financial Portal — UI Finalization Notes

Date: 22 October 2026 | Build: Release 2.1

## Official brand colors

| Color | Hex | UI use |
|---|---|---|
| Deep Navy | `#001B48` | Sidebar, titles, dark login panel |
| Dark Blue | `#02457A` | Primary buttons, selected organization tabs |
| Ocean Blue | `#018ABE` | Active navigation marker, highlights |
| Light Blue | `#97CADB` | Chart accents, secondary indicators |
| Ice Blue | `#D6E8EE` | Avatar backgrounds and pale interface accents |

## Files modified / added

- `app/views.php` — full portal shell, logo, login, stat cards, statuses.
- `app/ui.php` — bundled outline SVG icons.
- `app/release2_views.php` — itemized liquidation entry enhancement.
- `public/assets/css/style.css` — full responsive design system.
- `public/assets/js/app.js` — mobile navigation, password visibility, liquidation repeater.
- `public/assets/images/favicon.svg` — portal mark.
- `public/ui-preview.html`, `public/login-preview.html` — stand-alone interface previews (no private data).
- `app/actions.php`, `app/report_service.php` — updated download filename branding.
- `public/index.php`, `composer.json`, `README.md` — presentation branding and documentation.

## Non-destructive upgrade

1. Back up `campus_funds_db`, existing PHP project, and `storage/uploads/`.
2. Overlay application files from this ZIP onto `C:\xampp\htdocs\campus_funds\` while retaining local configuration and uploaded evidence.
3. If Release 2 migration `003_release2.sql` was already applied, **do not run it again**. If upgrading from Release 1, follow `README.md` migration instructions.
4. Keep the existing accounts and passwords. No database migration is needed for the UI-only refinements to an already-upgraded Release 2 installation.
5. Visit `http://localhost/campus_funds/public/index.php?page=login`.
6. For UI previews, visit `/campus_funds/public/login-preview.html` and `/campus_funds/public/ui-preview.html`.
7. Run `php tests/smoke.php` and complete `tests/RELEASE2_ACCEPTANCE.md` against local MySQL.

## Security and testing notes

The branding does not change role or organization authorization rules. Request stages, financial posting rules, database names, and demo login addresses remain compatible with previous installation. UI previews are static and cannot display genuine records. Real database and policy compliance testing are required before handling actual funds.
