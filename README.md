# LJNDI HRMS OAuth Client

Official PHP SDK for **Continue with LJNDI HRMS**.

The package integrates Laravel and other PHP applications with the LJNDI HRMS identity service using OAuth 2.1 Authorization Code Flow with PKCE.

## Features

- OAuth authorization URLs with PKCE S256
- secure state generation
- authorization-code exchange
- rotating refresh-token support
- employee profile retrieval
- token revocation
- OAuth discovery
- confidential server applications and public PKCE clients

## Requirements

- PHP 8.1 or later
- Guzzle 7.8 or later
- an OAuth application created by an LJNDI HRMS administrator

## Identity service

The production issuer is:

```text
https://auth.lerionjakenwauda.com
```

Discovery document:

```text
https://auth.lerionjakenwauda.com/.well-known/oauth-authorization-server
```

## Installation

After the package is registered on Packagist:

```bash
composer require ljndi/hrms-oauth-client
```

Before Packagist registration, install directly from GitHub:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/lerionjakenwauda/ljndi-hrms-oauth-client.git"
        }
    ]
}
```

```bash
composer require ljndi/hrms-oauth-client:dev-main
```

## Create application credentials

Credentials are not created inside this SDK. An HRMS administrator creates them from:

```text
HRMS Admin → Settings → Identity & API
```

Production admin URL:

```text
https://hrms.lerionjakenwauda.com/admin/settings/identity
```

For each application, the administrator configures:

- application name and description
- exact redirect URI or URIs
- allowed scopes
- confidential or public client type
- first-party or third-party status

The HRMS then generates a unique client ID. Confidential server-side applications also receive a client secret, which is displayed once.

### Confidential clients

Use a client secret for applications that can keep secrets on a trusted server, such as Laravel, Symfony or server-rendered WordPress applications.

### Public clients

Mobile applications, desktop applications and browser-only SPAs must not contain a client secret. Register them as public clients and use PKCE.

## Environment variables

```env
LJNDI_HRMS_ISSUER=https://auth.lerionjakenwauda.com
LJNDI_HRMS_CLIENT_ID=ljndi_client_xxxxxxxxx
LJNDI_HRMS_CLIENT_SECRET=ljndi_secret_xxxxxxxxx
LJNDI_HRMS_REDIRECT_URI=https://your-app.example.com/auth/ljndi/callback
```

For a public client, omit `LJNDI_HRMS_CLIENT_SECRET`.

## Create the client

```php
<?php

use Ljndi\HrmsOAuth\HrmsOAuthClient;

$client = new HrmsOAuthClient(
    issuer: $_ENV['LJNDI_HRMS_ISSUER'],
    clientId: $_ENV['LJNDI_HRMS_CLIENT_ID'],
    redirectUri: $_ENV['LJNDI_HRMS_REDIRECT_URI'],
    clientSecret: $_ENV['LJNDI_HRMS_CLIENT_SECRET'] ?? null,
);
```

## Laravel example

### Configuration

```php
// config/services.php

return [
    'ljndi_hrms' => [
        'issuer' => env('LJNDI_HRMS_ISSUER', 'https://auth.lerionjakenwauda.com'),
        'client_id' => env('LJNDI_HRMS_CLIENT_ID'),
        'client_secret' => env('LJNDI_HRMS_CLIENT_SECRET'),
        'redirect_uri' => env('LJNDI_HRMS_REDIRECT_URI'),
    ],
];
```

### Start sign-in

```php
use Illuminate\Http\RedirectResponse;
use Ljndi\HrmsOAuth\HrmsOAuthClient;

public function redirect(): RedirectResponse
{
    $client = $this->hrmsClient();
    $authorization = $client->authorizationRequest();

    session([
        'ljndi_oauth_state' => $authorization['state'],
        'ljndi_oauth_code_verifier' => $authorization['code_verifier'],
    ]);

    return redirect()->away($authorization['url']);
}
```

### Handle callback

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

public function callback(Request $request)
{
    $request->validate([
        'code' => ['required', 'string'],
        'state' => ['required', 'string'],
    ]);

    abort_unless(
        hash_equals(
            (string) session()->pull('ljndi_oauth_state'),
            (string) $request->string('state')
        ),
        403,
        'OAuth state mismatch.'
    );

    $tokens = $this->hrmsClient()->exchangeCode(
        (string) $request->string('code'),
        (string) session()->pull('ljndi_oauth_code_verifier')
    );

    $employee = $tokens['employee'] ?? $this->hrmsClient()->userinfo($tokens['access_token']);

    // Match or create the local application user here.
    // Store tokens encrypted if your application needs them later.
    $encryptedRefreshToken = isset($tokens['refresh_token'])
        ? Crypt::encryptString($tokens['refresh_token'])
        : null;

    return redirect('/dashboard');
}
```

### Client factory

```php
private function hrmsClient(): HrmsOAuthClient
{
    return new HrmsOAuthClient(
        issuer: config('services.ljndi_hrms.issuer'),
        clientId: config('services.ljndi_hrms.client_id'),
        redirectUri: config('services.ljndi_hrms.redirect_uri'),
        clientSecret: config('services.ljndi_hrms.client_secret'),
    );
}
```

## Refresh tokens

The identity service rotates refresh tokens. Replace the stored refresh token after every successful refresh:

```php
$tokens = $client->refresh($storedRefreshToken);

$newAccessToken = $tokens['access_token'];
$newRefreshToken = $tokens['refresh_token'];
```

Do not continue using the previous refresh token after rotation.

## Retrieve employee identity

```php
$employee = $client->userinfo($accessToken);
```

The response can include the employee's:

- HRMS employee ID
- full name
- work email
- employment status
- joining and exit dates
- active roles
- departments
- permitted profile claims based on granted scopes

## Revoke a token

```php
$client->revoke($refreshToken);
```

## Discovery

```php
$metadata = $client->discovery();
```

## Supported scopes

The currently supported scopes are:

| Scope | Purpose |
|---|---|
| `openid` | Identity subject |
| `profile` | Employee name and profile claims |
| `email` | Work email address |
| `employment:read` | Employment status and dates |
| `roles:read` | Active roles and departments |
| `payroll:summary` | Approved payroll summary where authorized |

Applications should request only the scopes they actually need.

## Security requirements

- Always validate the OAuth `state` value.
- Always use PKCE, including confidential applications.
- Register exact callback URLs; wildcard redirect URLs are not supported.
- Never place a client secret in JavaScript, a mobile binary or a public repository.
- Store access and refresh tokens encrypted at rest.
- Replace refresh tokens after every rotation.
- Use HTTPS in production.
- Revoke credentials immediately when an application is compromised.

## Package ownership and releases

The canonical implementation is maintained inside the private LJNDI HRMS repository. This public repository is the distributable SDK mirror used by Composer and Packagist.

Version tags in this repository are the versions Composer installs. See [Packagist publishing instructions](docs/PACKAGIST.md).

## Support

Use the GitHub issue tracker for SDK bugs and documentation problems. Questions involving employee accounts, OAuth application approval or credential revocation must be handled by an authorized HRMS administrator.
