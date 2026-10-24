# Kourentzes Tickets

Standalone WordPress plugin for private, anonymous ticket conversations without email or visitor accounts.

**Status:** implementation handoff prepared; plugin code is not implemented yet.

## Start here

1. Read [AGENTS.md](AGENTS.md).
2. Implement [the English specification](docs/SPECIFICATION.md).
3. Follow [the implementation plan and acceptance checks](docs/IMPLEMENTATION.md).
4. Use [the confirmed deployment environment](docs/ENVIRONMENT.md).
5. Complete [the privacy notice draft](docs/PRIVACY-NOTICE.md) against actual hosting behavior before public intake.

Owner decisions confirmed on 24 October 2026:
- Name: Kourentzes Tickets; slug and text domain: `kourentzes-tickets`.
- English throughout: frontend, admin, messages, documentation and privacy notice. Strings remain translation-ready; Greek translation is not a first-release deliverable.
- Initial project: Soft1 Code assistant (`soft1-code-assistant`).
- Categories: Technical issue, Suggestion, Privacy / personal data, Other.
- Only Konstantinos Kourentzes manages tickets initially, including privacy requests.
- Controller: Konstantinos Kourentzes; public contact: konstantinos@kourentzes.com.
- Local anti-abuse only; no email, attachments, external AI, SaaS or public ticket directory.
- Closed tickets are deleted after six calendar months, subject to documented required exceptions.
- Owner maintains no backups; provider backups and logs are unverified.
- Live-site installation is the chosen deployment approach. Public intake stays disabled during initial verification.

The implementation must deliver an installable ZIP, migrations, automated security/integration tests, operational documentation and an honest verification report. Do not claim compatibility or deployment success without testing.

No production credentials, real tickets or access secrets belong in this public repository.
