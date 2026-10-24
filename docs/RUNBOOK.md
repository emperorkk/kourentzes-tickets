# Installation, verification and operations

## Install with public intake disabled

Upload `dist/kourentzes-tickets-0.1.1.zip` through wp-admin and activate. Use **Settings → Ticket setup** to explicitly choose the owner's existing account. Setup creates published general, privacy and portal page shells, but submissions remain disabled. Those pages render only ticket shortcodes in a dedicated template; theme page content/widgets are intentionally omitted.

Log in as the selected owner. Under **Tickets → Settings and health**, inspect page IDs and scheduler state, publish a completed ticket-specific privacy notice and enter its HTTPS URL. Additional projects and permitted categories can be saved here by slug. Use separate general/privacy form pages for each project if needed. The single configured portal serves all projects. Do not enable destructive uninstall casually.

Only the selected account receives operational capabilities. To recover from accidentally removing that owner's capabilities, an infrastructure administrator can grant the capabilities listed in `Store::caps()` to that same user with appropriate WordPress user-management tooling. Do not add them to the administrator role. Future agents receive individual grants and explicit assignments; privacy access is separate.

## Required live checks before enabling intake

The plugin does not change wp-config.php, the theme, CDN settings or other plugins.

1. Confirm canonical home/site URLs, HTTPS, database InnoDB/utf8mb4 and PHP/DB versions. The supplied production environment is reported, not locally reproduced exactly.
2. Disable `WP_DEBUG_DISPLAY` in deployment configuration. Disable/save-exclude ticket requests in query monitors, session replay, request-body logging, database general logs and analytics/content-capture tools.
3. In LiteSpeed Cache, exclude all three ticket page paths, any additional project forms, and every URL matching the `ktickets/v1` REST namespace. Handle both pretty REST URLs and `?rest_route=/ktickets/v1/...`. Purge previously cached copies after exclusions. Exclude `assets/tickets.js` from script combining/delay/optimization. If there is any upstream cache, repeat exclusions there. Do not rely only on plugin PHP headers.
4. Inspect actual HTML/network requests on general/privacy/portal pages. They must load only local ticket CSS/JS, no MonsterInsights, ads, social widgets, theme masthead, external fonts, AI processing, Akismet or session replay. The dedicated template omits wp_head/wp_footer, but plugin/proxy injection before/after rendering still needs verification.
5. Verify AIOS, Age Gate, Complianz, custom permalink rules and any hosting security controls permit signed form bootstrap, guest REST exchange, essential cookies and rate-limit responses. Privacy intake must not require login or an unrelated consent gate.
6. Verify search, feeds, built-in/Yoast sitemaps, public page REST, llms.txt and custom JSON exporters expose no conversation or note data. Tickets are custom tables, not posts/media. Inspect the static robots.txt independently; noindex/robots are never authorization.
7. Complete the privacy notice using verified provider identity/location, logs/backups, retention and transfers. The owner having no personal backup does not establish that provider backups do not exist.
8. With synthetic data, enable intake briefly for the test, verify create/save-link/retry/portal/reply/internal-note isolation, separate privacy intake, agent restrictions, rotation, close/hold/purge and deletion. Check mobile/desktop, all controls by keyboard, and headers from two independent browsers. Disable intake again if any check fails; remove test tickets when finished.
9. Record results, acknowledge verification and enable intake for ordinary use.

A `KTICKETS_LOCAL_DEV=true` constant is honored **only** when WordPress environment type is `local`. It permits HTTP and non-Secure cookies for localhost testing; never configure this in production. Web Crypto additionally requires a browser secure context (localhost qualifies). Origin checks use the WordPress home URL's origin; aliases/cross-origin API installations must be corrected rather than bypassing checks.

## Regular operations

Visitors must save their private link and return for replies; there are no notifications or lost-link recovery. A browser refresh without the fragment intentionally asks for the saved link again, as CSRF credentials are memory-only. Copy/download happens only on explicit action. Treat a key like a password; it proves ticket access, not identity on another platform.

Use the privacy category filter to see original privacy tickets and privacy requests raised on general conversations. General agents lose access to the latter. Privacy metadata assists human handling; verify deadlines, identity needs and extensions yourself. Status `resolved` does not start retention. Status `closed` does; reopening requires a reason. Internal notes are a separate form and never part of a guest response.

Risk flags are review cues, not permanent blacklists. Attack mode applies stricter creation limits and quarantine to general tickets only. Review/approve quarantine manually; leaving attack mode does not release it. At capacity the service rejects new general intake honestly. Temporary rate limits remain on privacy intake, with independent counters. Hosting/database exhaustion may affect all routes.

Exports contain at most 20 messages and the latest 30 redacted audit events per JSON file. Load older messages and export each page for longer conversations. Exports include notes for authorized operators and must be stored/shared carefully. No access secrets or session hashes are exported.

## Cleanup and retention

Activation registers `ktickets_cleanup` daily, even with intake disabled. Check **Tickets → Settings and health** for next run, last success/error, overdue purges, overdue privacy dates, inactive requests and holds due for review. Manual cleanup runs one bounded batch: at most 20 tickets, 10,000 expired abuse rows and 1,000 expired replay/session rows each. Repeat batches for a backlog. Failures retain data for a retry; no success is reported for an uncommitted purge.

WP-Cron depends on visits and can run late. If a hosting scheduler is available, trigger WordPress cron regularly through the provider's supported method; do not disable WP-Cron without a working replacement. Optional WP-CLI command: `wp ktickets cleanup`. For a larger queue, run it repeatedly. Neither exact-time deletion nor physical expiry of security rows can be guaranteed on an idle site without a reliable scheduler. Expired security credentials/counters are rejected/ignored immediately regardless of physical cleanup.

Review open requests after 90 idle days. Privacy requests are never auto-closed. A necessary retention hold requires a reason, responsible operator and future review date. Review date expiry is an alert, not an automatic release. Remove the reason explicitly once the hold no longer applies; due purge becomes eligible again.

## Backups, restore and rollback

The plugin does not create backups and cannot erase provider backups. Verify provider retention/access and record the actual policy in the privacy notice. Before a planned restore, record the required deletion decisions without copying message content. Keep intake disabled during restore, run repeated cleanup batches, reapply any manual deletions that the restore resurrected and verify no expired/deleted conversation is publicly accessible before reopening. Historical restored holds and salts must be reviewed. A provider snapshot can resurrect a previously valid key; rotate affected keys or delete affected restored tickets as appropriate. Do not promise erasure from every backup.

To roll back code, disable intake and deactivate the plugin. Data remains. Restore a reviewed compatible plugin version; do not downgrade across incompatible schema versions without a migration plan. Deactivation unschedules cleanup, so retained data needs another authorized deletion process if the plugin stays inactive.

Uninstall retains data by default. If the owner explicitly enables **Permanently delete ALL ticket data and capabilities on uninstall**, WordPress uninstall removes custom tables, plugin options and individual ticket capabilities. Created page shells are left in place but no longer render a functional plugin; remove them manually if desired. This is irreversible and distinct from deactivation.

## Test tooling

`tests/integration.php` is run through WP-CLI with `KTICKETS_TESTING=1` and `KTICKETS_WP_PATH` pointing to a disposable installation. It destroys plugin fixtures and tests uninstall. Concurrent workers start independent WordPress/database connections; they are not sequential simulations. `scripts/test-integration.sh` provisions a disposable instance using the supplied database environment variables. Never use production credentials or a production database.

`tests/http_smoke.py` uses Python `requests` and `beautifulsoup4`. It expects a fresh owner-unconfigured WordPress fixture at `http://localhost:8099/konstantinos` (override `KTICKETS_BASE`), fixture owner `fixture_owner` / `fixture-password-only`, local development constants above and a running HTTP server. It enables intake only in that disposable fixture. It checks actual HTTP cookies/headers/setup/create/retry/exchange/reply/logout/admin permissions.

`tests/browser.cjs` uses Playwright with the same fresh fixture. Set `KTICKETS_PLAYWRIGHT` to its module location and optionally `KTICKETS_CHROME` to a browser executable. It exercises UI submission/replies/note separation and mobile/keyboard smoke checks. It is supplied but has not completed in the restricted local runner; full manual visual QA remains mandatory. Neither test fixture password is a production credential.


## Plugin Check and translations

Version 0.1.1 includes a WordPress-format readme.txt and extractable literal translation strings. Install site-specific MO catalogs under wp-content/languages/plugins/kourentzes-tickets-LOCALE.mo; WordPress loads them on demand. The POT template remains in languages/.

Run Plugin Check against the extracted distributable, not the development repository: the repository intentionally contains tests, workflow files, shell tooling and ZIP artifacts that must never be included in an installed plugin. Example: `wp plugin check /path/to/extracted/kourentzes-tickets --format=json`. No checks, result codes or warnings are excluded by the CI command. Narrow source annotations document the internal machine-code exception, fixed private-page exclusions, externally defined LiteSpeed/cache names and destructive uninstall operations; they do not disable the checks for entire files.

Standalone ticket assets are registered, enqueued and printed through independent WordPress dependency registries. The global theme/plugin asset queues and wp_head/wp_footer remain excluded. Verify actual output after cache/optimization changes, as before.
