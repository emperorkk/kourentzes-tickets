# Kourentzes Tickets — implementation specification

Version 0.3, 24 October 2026. English consolidation of source v0.2 and subsequent owner decisions. Repository preparation only; implementation has not yet been completed.

## 1. Purpose and confirmed scope

Standalone reusable WordPress plugin for private voluntary requests. Visitors submit without email/accounts and receive a secret access link; staff reply in wp-admin. No email via wp_mail, webhook or another channel. Reporting a technical problem does not create a support contract, SLA or guarantee of resolution. No automatic ingestion of Soft1 Code assistant conversations.

Name/slug/text domain: Kourentzes Tickets / kourentzes-tickets / kourentzes-tickets. Initial project: Soft1 Code assistant, slug soft1-code-assistant. Categories: Technical issue, Suggestion, Privacy / personal data, Other. All interfaces, messages, comments, privacy notice and documentation are English and translation-ready. Owner Konstantinos Kourentzes is the sole initial operator, including privacy tickets.

MVP includes public submission, private conversation, admin list/search/filter/replies, internal notes, categories/status/assignment, privacy segregation, secret rotation/revocation, manual/automatic deletion, export, abuse controls and setup/health screens.

Out of scope: attachments, email, push, live chat, external AI/ERP/ChatGPT APIs, external anti-spam, SaaS, public ticket lists and multisite. Future integrations require separate decisions and updated privacy information. Future attachments would require private storage, authenticated downloads, validation/malware policy and coordinated deletion, never public media URLs.

## 2. Visitor submission and portal

Shortcodes:
- [ktickets_form project="soft1-code-assistant"]
- [ktickets_portal]

Provide a distinct public privacy intake path with independent quotas/admin queue. Its concrete shortcode/route can be chosen and documented by the implementer.

Fields:
- Project fixed by page and checked against enabled server-side projects.
- Category from permitted categories.
- Subject required, 5–160 Unicode characters.
- Description required, 20–20,000 Unicode characters; plain text.
- Optional alias up to 80 characters.
- Optional plugin version, Soft1 version and execution environment; no unnecessary technical fields for privacy requests.
- Required acknowledgment that the link is an access key and cannot be recovered when lost.
- Privacy notice link beside submission; do not portray reading it as blanket consent.

Warn against passwords, tokens, databases and personal data of others. Enforce separate byte and character limits before expensive work.

Successful submission displays a non-secret reference, private link, copy action and user-initiated download of a small access file. Idempotency makes retries safe and does not consume another quota. Never report success before durable acceptance.

Portal shows only the authorized ticket: subject, public messages, status, timestamps stored in UTC and displayed locally, retention information and reply action. Never expose internal notes, staff email, other tickets or full audit logs. Clearly explain that visitors must return to check replies; there are no notifications. Ticket reference, subject or content knowledge cannot recover a lost key.

Closed tickets are read-only; offer a new request. Guest replies reopen non-closed tickets to open. Respect quarantine and communicate review status.

## 3. Access secrets and sessions

- CSPRNG secret of at least 32 random bytes, URL-safe encoding.
- Opaque public ticket ID independent of sequential database ID.
- Private link uses a fragment, for example a configured portal permalink followed by #ticket=<publicId>&key=<secret>. Never put secrets in URL paths/query parameters.
- Read fragment, immediately remove it with history.replaceState and POST the secret over HTTPS to exchange. Preserve only the copy the visitor deliberately saves.
- Persist SHA-256 of random secrets; constant-time comparison where applicable. Never store raw keys in business tables/logs/exports.
- Exchange creates opaque server-side ticket-scoped sessions; Secure, HttpOnly, SameSite=Strict cookies; maximum lifetime 12 hours.
- Multiple tickets in one browser must not collide or widen authorization. Document the session/grant model and logout behavior.
- Every guest mutation requires session-bound CSRF, origin/host validation and authorization for that ticket. Anonymous WordPress nonces are not identity.
- Rotation revokes previous access and all ticket sessions; show the new raw key once to the authorized operator. Keys otherwise expire on ticket deletion; sessions expire independently.
- Possessing a key proves access, not a person's identity or ownership on another service.
- Chosen implementation default: generate the initial secret with browser Web Crypto, retain it only for the bounded pending submission/retry flow, and submit over HTTPS. Fail clearly if secure randomness is unavailable. Bind idempotency to the browser/project/payload/key digest, reject mismatched reuse, and return the original ticket metadata on a genuine retry without persisting/replaying a server-side plaintext secret. Remove pending sensitive browser state after success/expiry; no analytics or persistent localStorage key history.
- If evidence requires a different design, document it before implementation. The only permitted server replay alternative is encrypted secret caching with a separate server key for no more than 15 minutes.

## 4. Security and page isolation

Require production HTTPS; disable guest functionality on insecure origins with an admin notice, except explicit local development. Permission callback on every REST route; object-level checks regardless of ID knowledge. Prepared SQL, validation, context-aware escaping and plain-text rendering with preserved line breaks/code readability in frontend and admin.

Uniform unknown-ticket/invalid-key errors; no stack traces. No raw secrets, credentials or message bodies in application logs. No credentialed CORS wildcard.

Portal/API/exchange/export responses: Cache-Control: no-store, private and Referrer-Policy: no-referrer. Portal: noindex, nofollow, noarchive; excluded from sitemap, feeds, search and public content APIs. noindex is not authorization.

Use a dedicated portal layout and local assets without theme analytics, pixels, social widgets, embeds, external fonts or third-party scripts. Ensure the submission/privacy intake pages also do not disclose their content to analytics. Document and verify cache/analytics exclusions, request-payload logging and session replay at deployment. Do not assume plugin headers override a reverse proxy or injected scripts. Scope CSP to ticket surfaces and document compatibility.

Local abuse protection only. Do not call Akismet, Cloudflare, CAPTCHA providers or reputation services. No raw guest IP/email/user IDs in business tables. Temporary IP-derived HMAC network keys use rotating secrets; abuse records expire within 24 hours, and rotation must preserve active windows. Trust forwarded proxy headers only for explicitly configured trusted proxies.

## 5. Anti-abuse policy

Owner-approved policy: anonymous public intake, local controls, suspicious accepted tickets flagged in the normal queue, temporary automatic blocking only, attack-mode quarantine for general tickets, independent public privacy intake. Expected normal volume is approximately 3–4 tickets/month; thresholds below are tunable initial values, not measured production results.

### Normal operation

- General creation: 2 per 10 minutes AND 5 per rolling 24 hours per network key, and independently per server-issued browser token. Cookie deletion does not reset network quotas; browser token is neither identity nor fingerprint.
- Replies: 5/minute and 20/10 minutes per ticket/session, plus a documented configurable network limit.
- Exchange: 10 failed attempts/10 minutes per network. Never permanently lock a ticket because of third-party failures.
- Signed, short-lived form token bound to project/browser session; replay protection compatible with successful retries. Missing/tampered token rejects. Tokens do not prove human identity.
- Local risk score uses honeypot, completion time, frequency, duplicate content and unusual link density. Speed, one URL, pasted code or a shared corporate IP alone must not reject. No blanket bans on URLs/code.
- Valid suspicious submissions within quotas create open tickets with abuse_flag and admin-only reason codes in the normal queue. Flags do not alter category/lifecycle; authorized operator can clear them.
- Exceeded quota rejects before writing a ticket/message: HTTP 429, Retry-After. Cooldown initially 15 minutes, escalating at most to 24 hours. No permanent blacklist or manual sender blocking. Blocked retries do not perpetually extend cooldown.

### Attack mode

- Trigger on 20 valid new general creation attempts/10 minutes OR 50 failed/rate-limited creation attempts/5 minutes. Count attempts with documented semantics, avoid counting a successful idempotent retry as new intake. Admin displays trigger reason/time; thresholds configurable.
- General creation becomes 1/30 minutes per network/browser. Stricter scoring; all accepted new general tickets enter temporary quarantine.
- Quarantine is moderation_state, separate from lifecycle. Visitor receives access link and pending-review status. Admin can approve/delete with appropriate permissions. No automatic deletion of ambiguous/privacy requests as spam.
- Existing conversations remain accessible under separate limits.
- Exit after 30 continuous minutes below thresholds. Existing quarantine is not automatically approved. Operator may enable attack mode proactively.
- General quarantine capacity initially 100 tickets, reserved atomically. At capacity reject new general tickets before writing, with a clear temporary-unavailability response and Retry-After, never false success. Privacy tickets do not consume this capacity.

### Privacy intake

Separate public route/form, quota buckets, counters and privacy-authorized admin queue. Initially same normal network/browser creation limits but independent buckets. A general cooldown/full quarantine must not block this path automatically.

Keep form validation, payload bounds, temporary limits and local flags; selecting privacy is not unlimited bypass. Only privacy-authorized staff may reclassify or flag privacy requests; never automatically reject based solely on content/spam classification.

Record received_at at accepted receipt regardless of moderation; flags must not hide requests or artificially delay handling. Clearly state pre-receipt rejection.

### Implementation constraints

Enforce server-side controls on all writes/direct REST calls. Atomic counters/quota reservations and unique idempotency constraints prevent parallel bypass. Controlled failure if limiter unavailable; no silent unlimited fallback. Define rollback/reservation semantics.

Bound request/response bytes, pagination/polling, counter cardinality and storage; expire/clean abuse data. No full-ticket scan for duplicates. Content fingerprints are short-lived keyed HMACs, not plaintext copies. Privacy risk reasons/content must not leak through general counts or logs.

Application controls cannot guarantee protection against distributed traffic exhausting hosting/PHP before execution; document that boundary without claiming zero spam.

## 6. Administration and authorization

Tickets menu: list/detail, projects/categories, settings, retention/health; quarantine queue and privacy queue.

Capabilities:
- manage_ktickets_settings
- view_ktickets
- reply_ktickets
- assign_ktickets
- view_ktickets_privacy
- manage_ktickets_privacy
- delete_ktickets

Owner-only setup supersedes v0.2's broad administrator grant: an administrator may initialize the plugin, but no ticket access is auto-granted to all three users/all administrators. Setup explicitly selects the owner's existing account; assign all ticket capabilities only to it. Do not infer the account from an email string. No other users have operational ticket access initially. Server/database administrators retain infrastructure access; do not promise cryptographic isolation from them.

Preserve capability/object checks and assignment model for future agents. An optional agent sees only assigned general tickets unless explicitly given all-general access. Assignment never bypasses privacy restrictions.

Privacy restrictions cover counts, badges, search, filters, exports, notes and API. Minimal redacted audit events for reply/status/assignment/revocation/export/deletion. Public reply vs internal note are clearly separate actions and guest DTOs never include notes. Revision guards prevent lost concurrent edits.

## 7. Lifecycle and privacy handling

Statuses: open, in_progress, waiting_requester, resolved, closed.
- Creation: open.
- Admin reply chooses in_progress or waiting_requester.
- Guest reply: open unless closed.
- resolved proposes a solution; retention countdown has not started.
- closed records closed_at and purge_at.
- Only authorized admin reopens, with reason; cancel old purge_at and start a new six-month period only at the next close.

Privacy request types: access, rectification, erasure, restriction, objection, portability, other. Track received_at, verification_status, target_due_at, extension_reason and outcome. Deadline assistance is for human handling; no automated legal determinations. Response deadlines and post-closure retention are different.

No default identity upload or excessive identification. A ticket key is not proof of identity on another platform. Implement necessary verification metadata without introducing attachments.

## 8. Data model

Use actual $wpdb->prefix. Custom tables, never public posts/comments/media.

| Table suffix | Required baseline fields |
| --- | --- |
| ktickets_projects | id, slug, name, enabled, allowed_categories |
| ktickets_tickets | id, public_id, project_id, category, subject, status, moderation_state, abuse_flag, abuse_reason_codes, alias, assigned_to, created_at, updated_at, closed_at, purge_at, revision |
| ktickets_messages | id, ticket_id, author_type, author_user_id, visibility, body, created_at |
| ktickets_access | ticket_id, secret_hash, revoked_at, created_at |
| ktickets_sessions | session_hash, ticket_id, csrf_hash, expires_at, revoked_at |
| ktickets_events | id, ticket_id, event_type, actor_user_id, created_at, redacted_metadata |
| ktickets_privacy | ticket_id, request_type, received_at, verification_status, target_due_at, extension_reason, outcome, hold_reason, hold_review_at |
| ktickets_abuse | scoped_hash, bucket, count, expires_at |

This is a baseline, not a restriction against necessary schema additions: persist optional environment fields, idempotency/replay records, rotating-key metadata and hold actor as required. Document additions, TTLs and indexes. Hold support must also work for general tickets when applied by the privacy manager.

Guest messages do not store WP user ID, raw IP or email. Staff IDs support audit/assignment. Dates UTC. Index unique public_id/secret_hash/session_hash; ticket+created_at, status+updated_at, assigned_to+status, category+status, purge_at and expires_at. Index privacy/queue filters as needed.

Versioned migrations; inspect encoding and engine. Consistent child/parent deletion with transactions where supported; no orphan messages/sessions/privacy metadata. Do not silently degrade guarantees on unsupported storage engines.

## 9. Retention and deletion

Closed tickets: six calendar months after closed_at, clamp day to last valid day of target month. No accidental overflow into the following month. Review open tickets after 90 idle days; no automatic privacy closure or silent indefinite retention.

Daily small-batch cleanup with lock/idempotency, registered automatically via WP-Cron; optional WP-CLI command. Admin health shows success time, overdue purges and failures. WP-Cron may run late on low traffic; document optional hosting scheduler, no SSH requirement.

Only privacy manager can apply a necessary deletion hold: reason, responsible operator and review date. No indefinite-retention checkbox. Guest may request deletion but cannot erase pending privacy evidence unilaterally. Authorized admin can delete immediately after confirmation.

Purge ticket/messages/notes/access/sessions/metadata/linked events. Only anonymous aggregate statistics may remain. Abuse data independently expires within 24 hours. No tracking archive.

Owner maintains no backups; provider backups/logs unknown. Do not promise deletion from every backup. Document provider verification and post-restore deletion handling. No backup feature.

Deactivation stops scheduled work without deleting data. Uninstall retains data by default; explicit delete-on-uninstall setting with clear warning.

## 10. REST contract

Namespace ktickets/v1; proposed plugin routes, not existing WordPress functionality.

| Method and path | Authorization / purpose |
| --- | --- |
| POST /tickets | Guest form token, anti-abuse, idempotency; create |
| POST /access/exchange | Secret plus exchange limits; session |
| POST /access/logout | Session plus CSRF; revoke session |
| GET /tickets/{publicId} | Ticket-scoped session; public conversation |
| POST /tickets/{publicId}/messages | Ticket session plus CSRF; reply |
| POST /tickets/{publicId}/privacy-request | Ticket session plus CSRF; privacy/deletion request |
| GET /admin/tickets | WP auth plus scoped capabilities; list |
| GET /admin/tickets/{id} | WP auth plus object authorization; detail |
| POST /admin/tickets/{id}/messages | WP auth, nonce, authorization; reply/note |
| PATCH /admin/tickets/{id} | WP auth, nonce, authorization; status/assignment |
| POST /admin/tickets/{id}/rotate-access | Explicit capability/object check; rotate |
| POST /admin/tickets/{id}/export | Scoped privacy/admin permission; export |
| DELETE /admin/tickets/{id} | Delete and privacy permission when applicable |

Add documented endpoints for form bootstrap/token issuance, separate privacy intake, project/settings/health/moderation as needed. Independent privacy buckets must be server-selected, not trusted from arbitrary client flags. Every route requires an explicit permission callback.

Paginated/bounded lists/messages; machine-readable errors and safe translated messages. No raw secrets in routine responses; only initial creation/exchange input or explicit rotation as appropriate. Export format default JSON with public/internal visibility explicitly scoped; never export keys, session hashes or abuse credentials.

## 11. UI, settings and extension

Mobile-first, keyboard navigation, labels, focus management, aria-live status, contrast and readable code wrapping. Neutral scoped CSS; configurable titles/project; no mandatory Soft1 Prime branding. Portal isolation takes precedence over copying theme widgets.

Settings: projects/categories, portal/form/privacy pages, privacy/terms URLs, owner/agent grants, notices, tunable limits, retention and scheduler health. Validate portal exists and flag unverified caching/HTTPS/configuration. Public intake disabled until setup is complete.

Service/repository/controller architecture with hooks. No speculative implementation of out-of-scope integrations.

## 12. Deliverables and truthfulness

Installable ZIP with kourentzes-tickets/ root; source repository; migrations/capability setup; English translation-ready strings; installation/cache/analytics/cron/backup/uninstall documentation; changelog; automated security/integration tests; synthetic examples; manual desktop/mobile/keyboard QA; actual test report and known gaps.

Target versions are in ENVIRONMENT.md. Claim support only after actual testing. See IMPLEMENTATION.md for required acceptance checks. No simulated security, false deployment claims or hidden unimplemented requirements.
