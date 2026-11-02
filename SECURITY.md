# Security Policy

## Reporting a vulnerability

**Please don't report security vulnerabilities in public GitHub issues, discussions, or pull requests.**

Report them privately through **GitHub private vulnerability reporting**:

1. Open the repository's **Security** tab and click **Report a vulnerability**.
2. Fill in the form and submit. Only you and the maintainers can see the report.

If you can't use GitHub private vulnerability reporting (for example, because the repository is not public yet), email `security@wendfold.com` as a fallback. Leave out exploit details until a maintainer replies with a private channel.

Please don't send security reports to the general support address or post them on forums.

A useful report includes:

- the affected Wendfold version (or commit) and which extension: `com_wendfold`, `plg_user_wendfold`, `plg_task_wendfold`, or `pkg_wendfold`
- your Joomla, PHP, and database versions
- clear steps to reproduce, and a proof of concept if it's safe to share
- the impact as you understand it, including whether member, officer, attendance, dues, or other personal data could be exposed or changed

Redact any personal data or credentials you don't need to show the problem.

## Supported versions

Security fixes go into the latest minor release line. Older lines don't get fixes.

| Version | Supported |
| --- | --- |
| Latest `1.x` release | Yes |
| `main` (unreleased) | Fixed before the next release |
| Anything older, or local or snapshot builds | No |

Supported platforms are Joomla 6.x and PHP 8.3 or newer. If an issue only reproduces on an end-of-life Joomla or PHP version, it may be treated as out of scope.

## What to expect

Wendfold is maintained by a small team. These are our targets, not contractual guarantees:

| Step | Target |
| --- | --- |
| Acknowledge your report | Within 5 business days |
| First assessment (confirmed, needs info, or not a vulnerability) | Within 10 business days |
| Fix or mitigation for confirmed high or critical issues | Within 30 days if possible. We'll tell you if it will take longer. |

We'll keep you updated in the private advisory thread, or by email if you reported through security@wendfold.com. Once a fix ships, we publish a GitHub Security Advisory, request a CVE where appropriate, and credit you unless you'd rather stay anonymous.

## Coordinated disclosure

Please give us a reasonable chance to release a fix before you disclose publicly. We normally aim for 90 days from the report or the release of a fix, whichever comes first. We're happy to agree on a different timeline with you.

## Scope

In scope:

- code in this repository: the component, both plugins, the package installer script, and the build and release tooling
- install, update, and uninstall behavior
- access-control, CSRF, input-validation, and output-escaping flaws
- exposure of member or chapter data

Out of scope:

- vulnerabilities in Joomla core, PHP, or third-party hosting. Report those upstream (for Joomla, see <https://developer.joomla.org/security.html>)
- problems that need administrator-level access the attacker already has, unless they cross a documented trust boundary
- missing security headers or similar hardening on <https://wendfold.com>, unless they lead to a concrete exploit
