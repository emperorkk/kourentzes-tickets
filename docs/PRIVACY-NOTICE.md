# Ticket privacy notice — release draft

This is the English content baseline for the ticket pages, not a statement that deployment checks have been completed. Before publication, verify hosting location, processor details, transfers, server logs/backups, essential cookie names/lifetimes and the applicable legal-basis assessment. Keep this notice specific to ticketing; it does not replace the website-wide cookie/privacy policy.

## Controller and contact

Konstantinos Kourentzes is responsible for the ticket service. For privacy questions, contact konstantinos@kourentzes.com or use the public Privacy / personal data request form. The plugin itself does not send email.

## What we collect and why

We process the subject and messages you choose to submit, request category, optional alias and optional technical environment details. We also maintain the ticket reference, status, timestamps, access-key hashes, essential session/security records and minimal staff action records.

We use this information only to receive, manage and respond to requests, handle privacy rights and protect the service against abuse. Email and visitor accounts are not required. Do not submit passwords, access tokens, databases or unnecessary information about other people.

Raw IP addresses are not stored in the plugin's ticket records. For temporary abuse prevention, the plugin uses keyed, rotating hashes derived from network addresses and a browser token. These are temporary security data, not anonymous identity guarantees.

## Legal basis

Proposed basis for ordinary request handling and proportionate security controls: legitimate interests in operating a secure communication service, subject to the controller's documented assessment. Handling statutory privacy requests relies on applicable legal obligations. Confirm the final basis against actual operation before publication; do not substitute a blanket consent checkbox.

## Access and sharing

Within the ticket application, only the designated owner account is initially authorized to manage requests. Hosting providers and persons with infrastructure administration access may have technical access as necessary to operate the site.

We do not sell ticket information, use it for advertising or send ticket content to external AI, analytics or anti-spam services through this plugin. No public ticket directory is provided. Any legally required disclosure must be assessed separately.

Provider identity/location and any international-transfer information must be completed from verified hosting arrangements before publication. Do not promise that no third party can access hosted data.

## Retention

Closed tickets, messages, notes and associated access/session/audit records are scheduled for deletion six calendar months after closure, unless a documented necessary exception requires temporary retention and review. Open tickets are reviewed after 90 days of inactivity; this is not automatic deletion or closure.

Guest sessions last no more than 12 hours. Temporary abuse records expire within 24 hours. The final technical documentation must list other short-lived form/idempotency cookie and record lifetimes.

Deletion runs through WordPress scheduled tasks, whose execution may be delayed if the site receives no requests. The service monitors overdue cleanup.

The owner does not maintain backups. Hosting-provider backups, server logs and their retention remain to be verified; do not publish a claim that they do not exist or that the plugin deletes every backup.

## Essential access and cookies

Your private link is an access key: anyone holding it may access the associated conversation. Keep it safe. Losing it cannot be remedied using only the ticket reference. Essential cookies support secure ticket sessions and abuse prevention; the ticket pages do not use advertising or analytics tracking.

There are no automatic reply notifications. Return using your saved link to check for a response.

## Your rights

Where applicable, you may request access, rectification, erasure, restriction, portability or object to processing. Use the privacy form or the contact above. We may request proportionate additional verification when necessary; possession of a ticket link does not establish ownership of another service's account.

You may complain to the Hellenic Data Protection Authority: https://www.dpa.gr/.

Submitting a technical request does not establish a support contract or guarantee a fix. The ticket service does not automatically receive conversations from the Soft1 Code assistant.

## Publication checks

Resolve the provider-specific statements and legal-basis assessment above into concise factual text before publishing the notice. No further product decisions are needed to start development. Do not publish this draft with internal instructions or unresolved claims as if it were final.


## Implemented essential cookies (verify on deployment)

`kt_browser` is a signed random browser token for form binding and local abuse quotas, with a maximum 24-hour expiry. Each authorized ticket uses a separate `kt_s_<publicId>` cookie for a ticket-scoped server session, with a maximum 12-hour expiry. Both are host-only, scoped to the WordPress home path, HttpOnly and SameSite=Strict; Secure is required in production. Logging out ends that ticket session. No ticket access-key history is kept in localStorage. The optional `ktickets.appearance` preference stores only the visitor’s light/dark/automatic choice. A configured central site theme preference may also be read; ticket credentials and content are never stored there. Temporary pending-submission key material exists in page memory for at most 15 minutes, and disappears on navigation/reload. Saved access files are created only at the visitor's explicit request. Confirm these properties in actual live responses before publishing this notice.
