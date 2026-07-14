# Security Policy

## Supported versions

Security fixes are applied to the latest published release in the current major version.

| Version | Supported |
| --- | --- |
| 1.3.x | Yes |
| 1.2.x and earlier | No |

## Reporting a vulnerability

Do not publish credentials, private URLs, customer data, database exports, complete production logs or sensitive calculator configurations in a public issue.

When GitHub private vulnerability reporting is available for this repository, use **Security → Report a vulnerability**.

If private reporting is not available, open a public issue containing only a minimal, non-sensitive summary and request a private communication channel. Do not include exploit details, secrets or production data in that issue.

A useful report should include:

- affected plugin version;
- WordPress and PHP versions;
- affected administrative action, shortcode or AJAX request;
- reproducible steps using synthetic calculator data;
- expected security impact;
- any suggested mitigation.

## Data handled by the plugin

Calc123 may process or store:

- calculator names and formulas;
- variable definitions and select options;
- custom wrapper IDs and CSS classes;
- user-supplied values submitted for calculation;
- temporary nonce-protected AJAX requests.

Reports and test cases must use synthetic values. Remove personal data, credentials, private URLs and unrelated log entries before sharing diagnostics.

Reports will be reviewed as time permits. Confirmed vulnerabilities will be fixed in the current supported release line and documented without exposing unnecessary exploit details.
