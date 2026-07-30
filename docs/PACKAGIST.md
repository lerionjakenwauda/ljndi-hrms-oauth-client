# Publishing on Packagist

The Composer package name is:

```text
ljndi/hrms-oauth-client
```

The public source repository is:

```text
https://github.com/lerionjakenwauda/ljndi-hrms-oauth-client
```

## First publication

1. Sign in to Packagist using the GitHub account that can access the repository.
2. Choose **Submit**.
3. Enter the repository URL.
4. Confirm the detected package name is `ljndi/hrms-oauth-client`.
5. Submit the package.
6. Enable the GitHub/Packagist update integration when prompted.

Until a stable version tag exists, Composer users can install the development branch:

```bash
composer require ljndi/hrms-oauth-client:dev-main
```

## Stable releases

Packagist obtains package versions from Git tags. Use semantic versioning:

- `v0.1.0` for the first preview release
- `v0.1.1` for backward-compatible fixes
- `v0.2.0` for backward-compatible features during preview
- `v1.0.0` when the public API is considered stable

Recommended first release:

```bash
git checkout main
git pull origin main
composer validate --strict
php -l src/HrmsOAuthClient.php
git tag -a v0.1.0 -m "Initial public OAuth client release"
git push origin v0.1.0
```

After Packagist processes the tag, applications can install:

```bash
composer require ljndi/hrms-oauth-client:^0.1
```

## Updating the public SDK from HRMS

The private HRMS repository remains the canonical source. Before publishing a release:

1. Copy the approved package files from `packages/ljndi/hrms-oauth-client` into this repository.
2. Review the public diff for secrets, internal URLs and private implementation details.
3. Run Composer validation and PHP linting.
4. Update `CHANGELOG.md`.
5. Commit the public SDK changes.
6. Create and push a semantic version tag.

Never copy `.env` files, credentials, production logs, employee data or database exports into this repository.

## Package update automation

Packagist can receive update notifications from GitHub. If automatic updates are unavailable, open the package page on Packagist and request an update manually after pushing a release tag.
