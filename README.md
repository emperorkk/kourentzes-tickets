# Kourentzes Tickets

Standalone WordPress plugin for private anonymous conversations, without email or visitor accounts.

**v0.1.1 — implementation candidate.** Source, an installable ZIP and repeatable tests are included. Public intake starts disabled. This repository has not been deployed to the owner's live website.

## Install

1. Download `dist/kourentzes-tickets-0.1.1.zip` and upload it under **Plugins → Add New → Upload Plugin**. Activate it.
2. Open **Settings → Ticket setup**, explicitly select Konstantinos Kourentzes' existing WordPress account, and create the three ticket pages. No administrator role or other user receives ticket access automatically.
3. As that selected owner, open **Tickets → Settings and health**. Publish the completed privacy notice, enter its HTTPS URL and verify the created page IDs.
4. Follow [the deployment runbook](docs/RUNBOOK.md), including live cache/analytics exclusions and synthetic tests. Then acknowledge verification and enable intake.

Initial project: **Soft1 Code assistant** (`soft1-code-assistant`). Categories: Technical issue, Suggestion, Privacy / personal data, Other. The engine is independent of Soft1 and ChatGPT.

## Included

- Anonymous submission, access-file download/copy and ticket-specific guest sessions.
- Separate general/privacy intake with independent creation quotas.
- Local signed forms, bounded rolling quotas, idempotent creation, risk flags, attack-mode quarantine and capacity reservation.
- Admin list/search/filter, public replies, visibly separate internal notes, assignment, category/status changes and revision guards.
- Privacy metadata/deadline assistance, necessary retention holds with review dates, key rotation, paginated JSON export and confirmed deletion.
- Six-calendar-month closed-ticket retention, scheduled bounded cleanup, health reporting and optional `wp ktickets cleanup`.
- Standalone local-asset ticket pages, no theme scripts, no third-party processing, no public ticket directory.

Shortcodes:

```text
[ktickets_form project="soft1-code-assistant"]
[ktickets_privacy_form project="soft1-code-assistant"]
[ktickets_portal]
```

All interface text and documentation are English; a translation template is included. Attachments, email, push, AI, external anti-spam, SaaS and multisite are excluded.

## Verification and limits

See [TEST-REPORT.md](docs/TEST-REPORT.md) for executed checks and [ARCHITECTURE.md](docs/ARCHITECTURE.md) for security/limit choices. Reported deployment targets are WordPress 7.1.3 / PHP 8.2.34 / MariaDB 10.11.19; exact target PHP/DB versions and the production plugin stack remain unverified. Browser visual/keyboard QA is outstanding.

The [specification](docs/SPECIFICATION.md), [environment](docs/ENVIRONMENT.md) and [privacy notice draft](docs/PRIVACY-NOTICE.md) remain the product/deployment baseline. No production credentials or real tickets are stored here.

## Development

Run `python3 scripts/package.py` to rebuild the deterministic distributable. PHP syntax checks and real WordPress/MariaDB integration tests are configured in `.github/workflows/tests.yml`.

For a disposable WordPress database, set `KTICKETS_TESTING=1`, `KTICKETS_WP_PATH`, `KTICKETS_DB_NAME`, `KTICKETS_DB_USER`, `KTICKETS_DB_PASSWORD` and `KTICKETS_DB_HOST`, then run `bash scripts/test-integration.sh`. It installs synthetic fixture users and **deletes ticket data**; never run against production. `tests/browser.cjs` and `tests/http_smoke.py` require a separate fresh local fixture, documented in the runbook.

License: GPL-2.0-or-later.
