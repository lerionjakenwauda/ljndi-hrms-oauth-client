# Security Policy

## Reporting a vulnerability

Do not disclose OAuth, token-handling or authentication vulnerabilities in a public issue.

Report security concerns privately to the LJNDI technical team and include:

- affected package version or commit
- affected method or endpoint
- reproduction steps
- expected and actual behaviour
- potential impact
- suggested remediation, when available

Do not include real employee credentials, client secrets, access tokens or refresh tokens in reports.

## Credential handling

This repository must never contain:

- HRMS employee passwords
- OAuth client secrets
- integration API keys
- access or refresh tokens
- production `.env` files
- database backups or employee records

Confidential client secrets are generated in HRMS Admin and should be stored only in the consuming application's secret environment configuration.

## Supported versions

Until version 1.0.0, only the latest tagged preview release and the current `main` branch receive security fixes.
