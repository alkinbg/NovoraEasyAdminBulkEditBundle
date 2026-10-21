# Security Policy

## Supported versions

Security fixes are targeted at the latest published 1.x version. Earlier releases are not guaranteed to receive backports.

| Version | Security support |
| --- | --- |
| Latest 1.x release | Supported |
| Earlier 1.x and prereleases | Not guaranteed |

## Reporting a vulnerability

**Do not open a public issue or pull request containing exploit details.** Prefer [GitHub's private vulnerability reporting](https://github.com/alkinbg/NovoraEasyAdminBulkEditBundle/security/advisories/new) **if enabled**. Otherwise email **alkinbg@gmail.com** with the subject `SECURITY: NovoraEasyAdminBulkEditBundle`.

Please include the affected release, PHP/Symfony/EasyAdmin/Doctrine versions, reproducible steps, expected and actual impact, and a proposed fix if available. Avoid sending real credentials, production data or live exploit traffic.

The maintainer will try to acknowledge reports within 7 days and provide an initial assessment, but these are goals rather than guaranteed SLAs. Valid reports will be coordinated privately until a fix or mitigation can be prepared. Please allow time for remediation before disclosure. Where appropriate, a security advisory and patch release will be published.

## Security model and limitations

The integration must authorize both the EasyAdmin action and **each selected entity**. Only server-declared fields and typed handlers are supported. Batch selection and review are stored in the server-side session, and confirmation forms require CSRF protection.

This library does **not** guarantee exactly-once processing across concurrent requests or multiple application nodes, does not automatically prevent lost updates, and cannot roll back external side effects. See [README.md](README.md) and [docs/integration.md](docs/integration.md) before production deployment.
