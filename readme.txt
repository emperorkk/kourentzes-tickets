=== Kourentzes Tickets ===
Tags: tickets, privacy, support
Requires at least: 7.1.3
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Private anonymous ticket conversations with local abuse protection, separate privacy intake and no email or visitor accounts.

== Description ==

Kourentzes Tickets provides private, anonymous request handling through access links. It uses local security controls and custom database tables. No email, attachments, external analytics, AI or anti-spam services are included.

Only an explicitly selected existing WordPress user receives initial ticket-management capabilities. Privacy requests have separate quotas and restricted access. Public submissions are disabled until the owner completes setup and live verification.

The plugin includes standalone form and portal layouts, public replies, internal notes, assignment, local attack-mode quarantine, bounded JSON exports and six-calendar-month retention after closure. A privacy manager can apply a documented retention hold with a review date.

Browser visual/keyboard testing and verification of the live site's cache, analytics, security integrations and hosting privacy arrangements remain required. See docs/RUNBOOK.md and docs/TEST-REPORT.md.

== Installation ==

1. Upload and activate the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Open Settings > Ticket setup and explicitly select the owner's existing account.
3. Sign in as that user and open Tickets > Settings and health.
4. Complete the published privacy notice and verify HTTPS, cache exclusions, analytics isolation and synthetic visitor flows.
5. Enable public intake only after verification. See docs/RUNBOOK.md for the full procedure.

== Frequently Asked Questions ==

= Does the visitor need an email address or account? =

No. The visitor saves a private access link and returns to check for replies. Lost links cannot be recovered from a ticket number.

= Does the plugin send ticket content to external services? =

No external services are called by the included ticket flows. Verify that hosting and other plugins do not capture sensitive request data.

= Does deactivation delete data? =

No. Deactivation unschedules cleanup and keeps ticket data. Uninstall also preserves data unless the owner explicitly enabled permanent deletion on uninstall.

= Can I install translations on a self-hosted site? =

Yes. Use the supplied POT template and install the matching MO file in wp-content/languages/plugins as kourentzes-tickets-LOCALE.mo. WordPress loads it on demand.

== Changelog ==

= 0.1.1 =

Fix Plugin Check findings: literal translation strings, explicit output escaping, visible nonce validation, normalized inputs, WordPress dependency-based isolated assets and WordPress readme metadata.

= 0.1.0 =

Initial implementation candidate with private guest sessions, owner-only setup, local abuse protection, privacy handling and retention.

== Upgrade Notice ==

= 0.1.1 =

Maintenance release for Plugin Check compatibility. Existing tickets, capabilities and settings are preserved. Public intake remains subject to live verification.
