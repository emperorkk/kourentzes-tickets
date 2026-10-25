# Architecture and operational bounds

## Layers

`Store` owns schema/migrations, SQL helpers, transactions and database locks. `Security` owns randomness, origin/session/CSRF checks, rotating network keys and temporary quotas. `Service` implements ticket lifecycle, scoped DTOs, privacy records and purge. `REST` declares explicit permissions and bounded responses. `UI` provides standalone ticket pages and wp-admin setup/operations.

Custom tables use the real WordPress prefix, InnoDB and utf8mb4. Migration failure leaves guest access unavailable and displays a generic administrative error. Schema version 0.1.0 adds environment JSON, expiring replay records and the retention-hold actor to the baseline specification. Timestamps use UTC.

## Access and application privileges

Only an explicitly selected existing user receives individual ticket capabilities. No role receives them. Future agents can receive `view_ktickets` and `reply_ktickets`; assigned general tickets are visible by default. `view_all_ktickets` expands general visibility. Privacy visibility and changes require their separate capabilities. Configuration accepts `manage_options` or `manage_ktickets_settings`; rotation/export still require `manage_ktickets_settings` plus operational object access. Assignment and deletion require their respective capabilities. Administrators retain configuration access and can explicitly transfer the operational owner without automatically seeing tickets. Infrastructure and plugin administrators remain technically privileged.

Raising a privacy request on an existing general ticket adds privacy metadata and immediately excludes the entire conversation from agents without privacy access. It also appears in the privacy queue. Changing the category away from privacy does not remove this historical restriction or erase privacy evidence.

The client creates a 32-byte access secret with Web Crypto. Only its SHA-256 reaches persistent access storage. An independent random 128-bit public reference identifies the ticket. Fragment secrets are removed from the URL before exchange; they never enter URL query/path parameters. Creation returns reference/portal metadata, never a replayed plaintext secret. Pending submission keys live only in JavaScript memory for up to 15 minutes; reload/navigation loses pending state. After success, the visitor chooses whether to save the displayed link or download the access file. No persistent key history exists.

Each ticket gets a different host-only `kt_s_<publicId>` cookie, scoped to the WordPress home path, HttpOnly, SameSite=Strict, Secure in production, maximum 12 hours. Session and CSRF values are persisted as hashes. Multiple tickets cannot borrow each other's grants. Exchanging a saved link replaces that browser's previous grant for that ticket; other tickets are untouched. Maximum 20 active grants per ticket. Rotation invalidates all ticket grants. Logout invalidates only the presented grant. A reload requires the saved private link to obtain a fresh CSRF-bound grant.

`kt_browser` is a signed random, server-issued essential browser token with a 24-hour expiry, not an identity/fingerprint. No raw guest IP, user ID or email is stored in ticket/message tables. Forwarded proxy headers are ignored: the operator must ensure REMOTE_ADDR represents the intended network boundary; no implicitly trusted proxy chain is used.

## Atomicity and abuse controls

Every guest write uses a database advisory lock shared by the site's plugin writes. This deliberately serializes the small expected volume instead of relying on race-prone read/modify/write counters. Creation and child records commit in one InnoDB transaction. Ticket revisions/updates and purge use row locks and transactions. Lock timeout is 3 seconds, with a controlled temporary-unavailability response; there is no unlocked fallback. Cleanup and moderation use the same serialization, so quarantine capacity and deletions cannot race against creation.

Creation validates its project, allowed category, character/byte bounds, acknowledgment, secret format and signed form. Tokens are bound to browser/project/privacy mode and expire after 15 minutes. A consumed token cannot create another ticket. The replay hash binds browser/idempotency key to a normalized payload/key digest. Concurrent matching retries reuse the original metadata without consuming more quota; mismatched reuse fails. Replay records expire after 15 minutes. A creation reservation can be conservatively consumed if the subsequent storage transaction fails; retry cannot bypass quotas. Ticket/form-use/fingerprint/replay writes roll back together.

Rolling windows store individually expiring HMAC-keyed counter events in the abuse table. Network HMAC keys derive from the WordPress authentication salt and UTC day; current and previous-day keys are queried together, preserving an entire active 24-hour window across midnight. Browser quota keys are independent and cookie resets do not reset network quotas. Changing WordPress salts is an exceptional infrastructure operation that invalidates browser/form credentials and resets network-derived history; do not rotate salts to tune abuse limits.

Defaults:

| Operation | Bound |
| --- | --- |
| General and separately privacy creation | 2/10 minutes AND 5/rolling 24 hours per network and per browser |
| General creation during attack | Additionally 1/30 minutes per network/browser |
| Form bootstrap | 20/minute per network, independent general/privacy buckets |
| Guest replies | 5/minute per session AND 20/10 minutes per ticket |
| Guest network replies | 40/10 minutes, configurable |
| Failed secret exchange | 10/10 minutes per network; no permanent ticket lock |
| All exchanges | 30/minute per network |
| Conversation reads | 30/minute per ticket session; no automatic polling |
| Session grants | 20 active per ticket; 12-hour maximum |
| Form/replay state | 15 minutes |
| Request body | 100,000 bytes; upstream web-server limits must also be configured |
| Message body | 1–20,000 Unicode characters (initial description minimum 20) |
| Subject / alias / each technical field | 160 / 80 / 160 characters |
| List/message pages | 20 records; guest max 20 public messages per response |
| Audit display/export page | Latest 30 events; at most 20 messages per export |
| Abuse table | 10,000 temporary rows per site, fail closed at capacity |
| Quarantine | 100 general tickets, configurable |
| Daily purge | Up to 20 tickets plus bounded expired security records |

Creation cooldown begins at 15 minutes and doubles on repeated episodes up to 24 hours; blocked retries do not extend an existing cooldown. Every security counter has an expiry no later than 24 hours. Expired rows are ignored immediately, removed in bounded write-time maintenance and by cleanup. Physical removal can be delayed if WP-Cron does not run: schedule cleanup reliably when strict removal timing matters. The global abuse-state ceiling is a shared resource bound, not a promise that privacy intake remains available during database/resource exhaustion. Its quotas and quarantine capacity are independent of general intake.

Risk flags include honeypot, fast completion, frequency, repeated content and link density. Flags do not reject valid submissions or change their lifecycle/category. Content fingerprints are expiring HMACs, never message copies. One URL, code or speed alone is never grounds for rejection.

General attack aggregates count new valid attempts after form/replay validation, including quota-rejected ones, and failed creation attempts separately. Matching successful retries are excluded. Defaults are 20 valid/10 minutes or 50 failed/5 minutes. The Nth-latest counter expiry determines when an aggregate drops below its threshold; exit occurs only after a further 30 quiet minutes. Accepted general tickets remain quarantined until manual review, even after exit. Privacy quotas, queue and receipt timestamp remain independent.

## Data visibility and isolation

Guest DTOs explicitly select public message fields; notes, audit, environment, staff IDs, assignment and risk reasons are absent. Admin list scope applies before search/filter/pagination, including historical privacy metadata. Plain text is escaped in PHP and rendered with textContent in JavaScript; SQL uses prepared values. Errors contain stable codes and generic translated messages. Sensitive operations suppress WordPress SQL error logging while they execute, then restore the previous setting. Hosting, query monitors, database general logs and SAVEQUERIES instrumentation must separately exclude sensitive payloads.

Ticket surfaces bypass the theme and output only recognized ticket shortcodes with local CSS/JS; no wp_head/wp_footer or arbitrary page blocks execute in that layout. Ticket pages/API use no-store/private and no-referrer headers. CSP is scoped to those pages: no third-party scripts/assets, frames or form destinations. REST removes WordPress's default reflected credentialed CORS headers for this namespace; unexpected Origin headers reject guest access. Published ticket pages are excluded from built-in/Yoast sitemaps, public page REST collections, search and feeds. Direct access to the static page shell contains no ticket data. Actual third-party plugins/CDN/auto-injected scripts require deployment verification.

## Retention and export

Only closing starts the six-calendar-month countdown, clamping month-end days. Reopening requires a reason and cancels the prior countdown. Privacy managers can apply a reason/actor/future-review-date hold to any ticket. An expired review date remains a visible review task and never silently removes evidence. Open requests idle for 90 days are highlighted in health; no automatic closure occurs.

Exports are explicitly bounded JSON pages, including staff notes for authorized operators, never a raw SQL backup or secret/session export. Load an older message page and export that page to retrieve longer conversations. Audit display/export includes the latest 30 redacted events. Purge removes all children, including notes and audit. There is no guest self-delete endpoint; a privacy/deletion request enters human handling.

WP-Cron runs the daily bounded cleanup; optional WP-CLI and manual health actions run the same routine. See the runbook for small-batch backlogs and restore handling. Uninstall preserves data unless the explicit destructive setting is enabled; deactivation only unschedules work.
