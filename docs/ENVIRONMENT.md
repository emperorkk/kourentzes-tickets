# Confirmed environment and deployment decisions

Reported by the owner on 24 October 2026. These are implementation targets, not verified compatibility claims.

| Component | Reported value |
| --- | --- |
| WordPress | 7.1.3; single site; production |
| PHP | 8.2.34, 64-bit, LiteSpeed SAPI |
| Database | MariaDB 10.11.19-MariaDB-cll-lve; mysqli/mysqlnd |
| Web server | LiteSpeed on Linux |
| Theme | PressBook Masonry Dark 1.2.9 |
| Parent theme | PressBook 2.3.0 |
| Site/user language | en_US |
| Site timezone | UTC |
| HTTPS | Enabled |
| Installation subdirectory | /konstantinos |
| Permalinks | /index.php/%year%/%monthnum%/%day%/%postname%/ |
| WordPress users | 3; owner alone is authorized for tickets initially |
| Registration / multisite | Disabled / false |
| WP_MEMORY_LIMIT | 40M |
| PHP memory / admin maximum | 128M / 256M |
| PHP execution time | 30 seconds |
| WP_CACHE | true |
| DB_CHARSET | utf8; inspect actual table/connection encoding before migrations |
| Debug | WP_DEBUG=false; WP_DEBUG_DISPLAY=true; WP_DEBUG_LOG=false |
| robots.txt | Static file exists |
| Extra .htaccess rules | Present |

Use home_url(), site_url(), rest_url() and page permalinks as appropriate. The control panel displayed k.kourentzes.com while the intended public site is kourentzes.com/konstantinos. Resolve canonical URLs from WordPress configuration at installation; never hardcode either hostname. index.php in permalinks must not break portal links or REST calls.

Inspect InnoDB/transaction support and utf8mb4 capability in the test environment. Preserve Unicode and emoji or report a concrete compatibility issue rather than silently corrupting input.

## Relevant active integrations

- LiteSpeed Cache 7.9.2: exclude form-token responses, private portal, exchange, ticket APIs and exports from page/CDN caching. Exclude sensitive scripts from optimization where needed.
- MonsterInsights 11.3.0: no tracking on ticket form/privacy intake/private portal.
- Complianz 7.5.6: no unnecessary consent gate for essential ticket session cookies; document cookie purposes/lifetimes and verify actual script injection.
- AIOS 5.4.10: verify REST, origin checks, cookies and rate-limit responses are not broken.
- Akismet 5.7.2 and AI Engine 3.8.7: do not send ticket content to either service.
- Yoast SEO 28.7 and Yoast SEO Assistant 0.3.0: exclude private portal from sitemap/indexing and automated content processing.
- LLMS Full TXT Generator 2.1.2 and Media JSON Exporter 1.0.0: verify tickets/notes never enter generated exports.
- Age Gate 3.7.3: verify it does not obstruct public privacy intake or secret exchange.
- Customizable Permalinks 1.2 and WP Masonry & Infinite Scroll 2.4: verify page/REST URLs and standalone portal rendering.
- Social Icons Sticky 1.7.7, Smash Balloon X 2.10.0, hosted badges/masthead and other embeds must not load in the private portal.
- kko Google Login is active; visitor login must remain unnecessary.
- Plugin Check 2.1.0 is available for additional package checks.

Other active plugins reported: All In One Lightbox 1.2.0, Code Block Pro 1.28.0, Google Cloud Vision Image Description (custom), Image Gallery Block 1.4.0, Intagrate Lite 1.4.3, Kids Game Coloring Auto Publisher 1.0.4, Song Voter Widget 1.2.0, Steam Player Summaries and Recent Activity to Post 1.8, Superior e-Pub Viewer 2.0.0, WP Auto Post 0.1.0, WPForms Lite 2.0.3, YouTube Channel to Posts 1.1, Hosted by Aabaco Small Business Badge 1.2, Web Hosting Masthead 1.3.

OptinMonster, UserFeedback, the XML Sitemap Generator and several other Smash Balloon feeds are inactive. Do not assume they will remain inactive forever; inspect actual live responses at deployment.

## Operations

- The plugin registers daily cleanup via WP-Cron automatically. No extra cron plugin is required.
- Server cron availability is unknown, not a coding blocker. Document an optional hosting scheduler trigger and do not disable WP-Cron unless a replacement is working.
- Show last cleanup success, overdue deletions and failures. WP-Cron is request-triggered and can run late.
- Owner maintains no backups. Provider snapshots/backups, region, retention and access logs are unverified. Do not claim backups/logs do not exist. Do not add a backup feature.
- Set WP_DEBUG_DISPLAY=false before public use through an explicit deployment change; the plugin must not rewrite wp-config.php.
- Low memory and 30-second execution require bounded batches and pagination.
- Deploy an installable ZIP through wp-admin; SSH is not required.
- Install initially with public submission disabled, verify with synthetic tickets on live, remove test data, then enable intake.
- A lack of existing ticket users does not remove the need to verify the impact on the rest of the live website.
