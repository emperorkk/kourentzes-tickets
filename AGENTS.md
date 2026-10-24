# Implementation instructions

## Read first

Read README.md, docs/SPECIFICATION.md, docs/ENVIRONMENT.md, docs/IMPLEMENTATION.md and docs/PRIVACY-NOTICE.md before coding. The English specification consolidates source specification v0.2 and the owner's subsequent decisions. Later explicit owner decisions take precedence.

## Scope and language

Build a standalone WordPress plugin named Kourentzes Tickets, slug/text domain kourentzes-tickets. All code comments, documentation and user-facing text must be English. Use WordPress translation functions; no Greek translation is required now.

Implement real functionality, not mock screens or placeholder security. Keep the reusable engine independent of Soft1/ChatGPT. No email (including wp_mail/webhooks), attachments, AI processing, external anti-spam services, SaaS, public ticket browsing, push, live chat or multisite.

## Security boundaries

Use custom prefixed database tables, prepared queries, strict validation and escaped plain-text rendering. Implement object-level authorization on every route. Guest WP nonces are not authentication. Separate guest public messages from internal notes and restrict privacy data everywhere, including counts/search/exports.

Only the explicitly selected owner account receives initial ticket-management capabilities. There are three WordPress users; never grant all administrators ticket access merely because they are administrators. Document that database/server administrators remain infrastructure-privileged.

Use cryptographic access secrets, hashed persistence, ticket-scoped sessions, CSRF protection, atomic quotas and transactionally consistent writes. Preserve full anti-abuse requirements in the specification. No unbounded storage, scans or silent limiter bypass.

## Environment and integration

Target the owner's reported WordPress 7.1.3 / PHP 8.2.34 / MariaDB 10.11.19 environment; these are reported targets, not tested compatibility claims. Support subdirectory installation and index.php permalinks using WordPress URL APIs. No hardcoded hostname, filesystem path, table prefix or credentials.

Use scoped assets and a dedicated private portal layout without theme/third-party scripts. Verify LiteSpeed cache exclusions, analytics exclusion and privacy against the real plugin stack. Do not globally disable unrelated plugins or modify the site's theme.

## Delivery and verification

Implement schema/authorization/session flow first, then UI, then retention/exports and operational checks. Run meaningful integration/security tests, including concurrency and privacy separation. Record actual test versions and results, unavailable checks and known limitations. Deliver a ZIP with a single kourentzes-tickets/ root.

Use synthetic data only. Keep production environment details free of sensitive paths/usernames in this public repository.

The owner chose live installation. This handoff authorizes repository preparation, not an unattended production change. Prepare and test the package first; production installation requires the owner's deployment instruction/access. Public submission must default to disabled until setup and live verification are complete. Do not ask again for settled product decisions.

Make routine implementation decisions autonomously, document them, and ask only for a genuine unresolved blocker. No additional account, email service, SSH access or staging environment is a prerequisite for coding.
