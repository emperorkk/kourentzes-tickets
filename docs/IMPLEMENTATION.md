# Implementation plan and acceptance checklist

Status: ready to implement; no plugin code or completed test results yet.

## Work order

1. Bootstrap plugin, tooling and versioned migrations. Check actual DB engine/encoding and test runtime. Add activation/deactivation/uninstall behavior and owner-only setup. Public intake disabled.
2. Implement repositories/services, object authorization, public-vs-internal DTOs, secret creation/exchange/session/CSRF and revision guards.
3. Implement bounded atomic abuse controls, signed form bootstrap, idempotency, independent privacy intake, quarantine and attack mode.
4. Build accessible English form/standalone portal and wp-admin list/detail/reply/note/settings/health/queues; JSON exports with permission checks.
5. Implement lifecycle, six-calendar-month purge, holds, inactive-ticket review, scheduled batches, health reporting and optional CLI.
6. Add deployment/privacy documentation, package ZIP, run integration/security tests and manual QA. Record actual results.
7. Owner installs on live with intake disabled. Verify stack-specific exclusions using synthetic data, delete test data, enable intake only after setup checks pass.

## Design decisions to record in code/docs

- Browser Web Crypto initial secret generation; ticket/payload-bound idempotency. No server plaintext key replay.
- Session model supporting multiple tickets without collision. Explicit cookie path/domain behavior on subdirectories.
- Atomic creation/quota/quarantine reservations, failure recovery and concurrency semantics.
- Numeric byte limits, list sizes, polling ceiling, form-token TTL, retry-record TTL and network reply quota; choose bounded values consistent with the specification.
- Rotating HMAC windows preserving rolling quotas, TTL and bounded cardinality.
- UTC calendar-month clamp semantics.
- Separate privacy intake route/shortcode and scoped counters.
- Capability map and explicit owner account selection.
- Export scope/format, audit redaction, transaction requirements and migration recovery.
- Portal template/CSP, LiteSpeed/MonsterInsights/Yoast exclusions and integration hooks used.
- Actual tested versions versus reported target versions.

## Automated acceptance checks

- [ ] Anonymous create/view/reply without email/account; no wp_mail, webhook, AI, Akismet or third-party transmission.
- [ ] Guest A cannot read/change B by replacing ticket/message IDs, cookies or routes.
- [ ] Internal notes and staff-only data absent from guest detail/list/export/error DTOs.
- [ ] Other existing WordPress users lack ticket capabilities; unauthorized agents cannot infer privacy tickets from counts, filters, search, notes, exports or API.
- [ ] Owner-only capability initialization and safe repeated activation/migrations.
- [ ] Secrets absent from DB, logs, exports, URL queries/paths/referrers; pending browser secret state expires/is removed.
- [ ] Safe idempotent retry after response loss; mismatched replay rejects; no duplicate quota/ticket under concurrent retries.
- [ ] HTTPS enforcement, origin/CSRF rejection and secure cookie attributes; no credentialed wildcard CORS.
- [ ] Key rotation revokes previous key/all ticket grants; logout and expiration work; multiple-ticket browser sessions stay isolated.
- [ ] Stored XSS rendered as plain text in portal/admin; SQL metacharacters harmless; Unicode, emoji and code preserved.
- [ ] Direct REST calls cannot bypass limiter; concurrent calls cannot exceed quota/quarantine capacity.
- [ ] Cookie reset does not reset network limits; key rotation/day boundaries preserve rolling windows.
- [ ] Limiter failure controlled; TTL/cardinality bounds enforced; no unlimited fallback.
- [ ] Fast typing, one URL, pasted code and shared IP do not alone cause rejection.
- [ ] Suspicious valid ticket appears open with flag in normal queue; authorized flag clearing audited.
- [ ] Rate-limit rejection occurs before durable ticket/message write; 429/Retry-After; bounded cooldown not extended by blocked retries.
- [ ] Attack-mode thresholds, manual enablement, 30-minute quiet exit and persistent quarantine work.
- [ ] Full general quarantine rejects honestly; privacy intake remains independently limited/visible.
- [ ] Privacy received_at retained regardless of moderation; no content-only automatic privacy rejection.
- [ ] Lifecycle transitions/revision conflict protection; closed guest reply rejects usefully.
- [ ] Calendar-month retention covers month ends/leap years; only close starts countdown; authorized reopening requires reason.
- [ ] Hold requires reason/actor/review date; inactive privacy requests not auto-closed.
- [ ] Purge removes all related data; concurrent cleanup locks, retry/failure handling and overdue health reporting.
- [ ] Deactivation preserves data; uninstall defaults to preserve and explicit delete removes tables/options/capabilities appropriately.
- [ ] Bounded pagination/payload/response sizes and low-memory batch behavior.
- [ ] Non-default table prefix, subdirectory URLs and index.php permalinks supported.

Use actual WordPress/MariaDB integration fixtures for security/transaction behavior; mocks alone do not establish these guarantees. Concurrency tests must issue concurrent operations, not only sequential approximations.

## Manual and live verification

- [ ] Form, portal, admin list and admin detail on desktop/mobile and keyboard.
- [ ] Private pages/REST/exchange/exports are never publicly cached; inspect headers and repeated requests from different browsers.
- [ ] No third-party scripts/requests, MonsterInsights, social widgets, AI/analytics content capture or external fonts on ticket surfaces.
- [ ] No ticket leakage through Yoast sitemap, WordPress search/feed/public REST, llms.txt or custom JSON exporters.
- [ ] Static robots.txt and actual indexing configuration inspected; authorization never relies on robots rules.
- [ ] AIOS/Age Gate/Complianz do not break submission, privacy intake, exchange or required cookies.
- [ ] Verify effective memory, PHP/DB versions, table engine/encoding and writable installation.
- [ ] WP_DEBUG_DISPLAY disabled through deployment configuration.
- [ ] Verify canonical WordPress URLs rather than control-panel alias.
- [ ] Automatic WP-Cron event exists; health shows real execution. Hosting scheduler optional.
- [ ] Verify provider logging/backups/region and finish privacy notice; do not assert no backups based on owner's own practice.
- [ ] Synthetic live tickets removed before public launch.
- [ ] Existing site pages still work; no global theme/plugin changes.

## Release artifacts

Provide source, versioned changelog, installable ZIP, setup/runbook, privacy notice, test report, limitations and rollback instructions. Report skipped tests and unverified target versions explicitly. Do not mark checkboxes complete before running them.

No deployment credentials are stored here. ZIP installation is sufficient; production SSH is not required.
