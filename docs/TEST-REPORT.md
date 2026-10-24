# Test report — v0.1.0 implementation candidate

Date: 24 October 2026. Synthetic data only. No production installation was performed.

## Executed locally

| Check | Actual environment / result |
| --- | --- |
| PHP syntax | PHP 8.3.6 CLI; all plugin and fixture PHP files passed |
| JavaScript syntax | `node --check assets/tickets.js` passed |
| WordPress | 7.1.3, real WordPress bootstrap/database; subdirectory home URL |
| Database | MariaDB 10.11.14; InnoDB, utf8mb4, non-default `sample_` table prefix |
| Integration/security suite | 79 assertions passed, including the final category authorization, export secrecy and reply/session limit checks |
| HTTP fixture | Owner setup, settings enablement, standalone pages, no-store/no-referrer/CSP, subdirectory cookie scope, create/retry/exchange/read/reply/logout, CSRF rejection, privacy form and admin REST passed |
| Concurrent workers | Eight independent PHP/WordPress processes per case: one ticket for matching retries; two accepted distinct requests at quota 2; one quarantine slot across independent browser/network identities; concurrent cleanup safe |
| Outbound behavior | Ticket operations triggered no email or HTTP calls through instrumented WordPress hooks |

Security cases cover object isolation, assigned-agent/other-administrator restrictions, privacy queue separation including later privacy requests, internal-note exclusion, hashed key persistence, revision conflicts, rotation/session invalidation, origin/CSRF/direct-REST rejection, payload bounds, cookie reset/network quotas, attack entry/quiet exit, expiry/daily-key overlap, closed replies, calendar-month clamps, holds, complete child purge, migration repetition, controlled DB failure and deactivation/uninstall behavior. See the executable assertions for their precise scope; this is not a proof against every attack.

## Not verified

- Exact reported PHP 8.2.34 and MariaDB 10.11.19 versions. Local PHP was 8.3.6; local MariaDB was 10.11.14. PHP 8.2 CI is configured, but no successful remote workflow is claimed in this report.
- Production LiteSpeed, CDN/caching, MonsterInsights, Complianz, AIOS, Age Gate, Yoast, llms.txt/custom exporters and the rest of the live plugin stack.
- Browser visual/desktop/mobile and complete keyboard/accessibility QA. Playwright was attempted, but the local runner rejected Chromium's Unix socket creation before any page loaded. The supplied browser test therefore did not run to completion. HTTP tests are not a substitute for rendered UI testing.
- Production TLS/Secure-cookie behavior in a real HTTPS browser. The local HTTP fixture uses the explicit local-development exception; production code requires HTTPS and emits Secure cookies.
- Live low-memory/30-second resource behavior, large real queues, hosting scheduler reliability, provider logging/backups/region and the final legal/privacy notice.
- WordPress Plugin Check/PHPCS and a professional security/accessibility review.

## Deliberate first-version bounds

- JSON export is paginated (20 messages, latest 30 audit events), not an unlimited all-history dump.
- Existing-user capability grants for future agents use WordPress user-management tooling; the plugin does not add a second role editor.
- Privacy category changes do not declassify historical privacy conversations automatically.
- Temporary abuse storage has a shared 10,000-row fail-closed bound. Independent privacy quotas do not guarantee service availability under total database/hosting exhaustion.
- Forwarded proxy headers are ignored. REMOTE_ADDR must be correct at the server boundary.
- Pending submission secrets live only in memory; reload before saving loses them. Creation retry guarantees are bounded to 15 minutes.
- WP-Cron can be late. Expired credentials/counters stop working immediately, while physical row deletion depends on execution of maintenance.

Public intake defaults to disabled. Complete the runbook and outstanding live/browser checks before enabling it for real visitors.

## Final local result

79 integration/security assertions passed. All plugin/fixture PHP files and the frontend JavaScript passed syntax checks. The HTTP suite passed with namespace-specific CORS restrictions and cross-origin guest-read rejection included. Package integrity/root-path checks passed when building the distributable. Remote CI is configured but remains a separate, unclaimed verification result.


## v0.1.1 maintenance verification

The owner supplied a Plugin Check report for the installed v0.1.0 package. The follow-up fixes replace dynamic gettext arguments with literal declarations, perform configuration escaping at the output boundary, make nonce checks visible in each admin write handler, normalize superglobal inputs, isolate global variables in uninstall, prepare DROP TABLE identifiers with `%i`, use independent WordPress asset registries and add the required WordPress readme metadata.

Local results on the same WordPress 7.1.3 / PHP 8.3.6 / MariaDB 10.11.14 fixture:

- 82 integration/security assertions passed, including three new checks for isolated asset queues and translated/escaped field labels.
- The HTTP suite passed again, including rejection of invalid nonces for owner setup, settings and project changes, as well as the existing guest/session/origin/CORS checks.
- Plugin Check **2.1.0** was run with default checks against the extracted **v0.1.1 distributable**: **no errors or warnings were returned**. No CLI checks/codes/warnings were excluded, and no AI analysis was used. Narrow source-level annotations explain unavoidable internal typed exception data, three-page privacy exclusions, external cache integration names and explicit destructive uninstall schema operations.
- PHP syntax, JavaScript syntax and ZIP integrity checks passed.

The original v0.1.0 GitHub Actions run also completed successfully on the configured PHP 8.2 job. The updated v0.1.1 workflow adds a Plugin Check gate against the extracted ZIP. Its remote outcome is separate from these executed local results. Exact production patch versions, live-stack verification and browser visual/keyboard QA remain outstanding.
