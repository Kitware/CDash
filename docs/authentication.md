# CDash Authentication

This page describes the various authentication workflows supported by CDash.

## Email & Password
By default, CDash authenticates users against an internal database table.
The following `.env` variables can be used to customize the email/password user login experience.

| Variable      | Description   | Default |
| ------------- |-------------  | --------|
| LOCKOUT_ATTEMPTS | Lock user account after N failed login attempts | 5 |
| LOCKOUT_LENGTH | How long to lock an account for? (in minutes) | 1 |
| MINIMUM_PASSWORD_LENGTH | Minimum password length | 5 |
| MINIMUM_PASSWORD_COMPLEXITY | How many types of characters (uppercase, lowercase, numbers, symbols) must be present in a password | 1 |
| PASSWORD_COMPLEXITY_COUNT | How many characters of a given type must be present in a password for it to count towards the minimum complexity | 1 |
| PASSWORD_EXPIRATION | How long a password should last for (in days). Password rotation is disabled when this is set to 0. | 0 |
| REGISTRATION_EMAIL_VERIFY | Should CDash only register verified email addresses? | true |
| USERNAME_PASSWORD_AUTHENTICATION_ENABLED | Whether or not email+password authentication is enabled | true |
| USER_REGISTRATION_FORM_ENABLED | Whether or not new CDash users can register email+password accounts | true |

## Customizing the login page
You can add your own custom content to the login page by writing a
[Blade template file](https://laravel.com/docs/9.x/blade) named `login.blade.php` in `resources/views/local/`.

## LDAP

Here is a sample `.env` configuration that allows CDash to authenticate against an LDAP server running on localhost for the `example.org` domain.

```
LDAP_USERNAME=cn=admin,dc=example,dc=org
LDAP_PASSWORD=password
CDASH_AUTHENTICATION_PROVIDER=ldap
LDAP_PROVIDER=openldap
LDAP_HOSTS=ldap
LDAP_BASE_DN="dc=example,dc=org"
LDAP_LOGGING=true
LDAP_LOCATE_USERS_BY=mail
```

Here's a description of the `.env` variables involved in the LDAP authentication process.
| Variable | Description | Default |
| -------- |------------ | ------- |
| CDASH_AUTHENTICATION_PROVIDER | Set this to `ldap` to enable CDash's LDAP authentication support. | users |
| LDAP_BASE_DN | The base distinguished name you'd like to perform query operations on. | dc=local,dc=com |
| LDAP_BIND_USERS_BY | The LDAP users attribute used for authentication | distinguishedname |
| LDAP_FILTERS_ON | Additional LDAP query filters to restrict authorized user list. For example, to restrict users to a specific Active Directory group: `cn=myRescrictedGroup,dc=example,dc=com` | false |
| LDAP_HOSTS | The IP address or host name of your LDAP server. | 127.0.0.1 |
| LDAP_LOCATE_USERS_BY | The LDAP users attribute used to locate your users. | mail |
| LDAP_LOGGING | Whether or not to log LDAP activities. Useful for debugging. | true |
| LDAP_USERNAME | Username for account that can query and run operations on your LDAP server(s). | '' |
| LDAP_PASSWORD | Password for account that can query and run operations on your LDAP server(s). | '' |
| LDAP_PROVIDER | The type of LDAP server you are connecting to. Valid values are openldap, activedirectory, and freeipa. | openldap |
| LOGIN_FIELD | The label on the "user" field for the Login form ("Email" by default).  Change this if you're authenticating against something other than an email address in LDAP. | Email |

## OAuth2

CDash currently supports OAuth2 login for GitHub, GitLab, and Google accounts.  As of CDash 3.3, CDash uses the [Socialite](https://laravel.com/docs/11.x/socialite) plugin to provide this functionality.

The CDash instance will automatically populate the callback URI for Socialite's providers.  It will take the form of `<cdash_URL>/auth/<provider>/callback`.  The previous OAuth framework enforced a different structure for the callback with the format of  `/oauth/callback/<provider>`.  Both instances of the callback will be properly handled in CDash 3.3 and later.

###### GitHub

To begin, you will need to
[create a GitHub OAuth2 app](https://docs.github.com/en/apps/oauth-apps/building-oauth-apps/creating-an-oauth-app) for your CDash instance. Make note of the Client ID and Client Secret created for you by GitHub. These will be used in the `.env` variables described below.

| Variable | Description | Default |
| -------- |------------ | ------- |
| GITHUB_ENABLE | Whether or not to use GitHub as an OAuth2 provider. | false |
| GITHUB_CLIENT_ID | The Client ID assigned to your GitHub OAuth2 app. | '' |
| GITHUB_CLIENT_SECRET | The Client Secret created for your GitHub OAuth2 app. | '' |
| GITHUB_AUTO_REGISTER_NEW_USERS | Whether to automatically register a new user or provide them the Registration form | false

###### GitLab

First [configure GitLab as an OAuth2 authentication identity provider](https://docs.gitlab.com/ee/integration/oauth_provider.html). Then set the following variables in your `.env` file.

| Variable | Description | Default |
| -------- |------------ | ------- |
| GITLAB_ENABLE | Whether or not to use GitLab as an OAuth2 provider. | false |
| GITLAB_CLIENT_ID | The OAuth 2 Client ID from the Application ID field. | '' |
| GITLAB_CLIENT_SECRET | The OAuth 2 Client Secret from the Secret field. | '' |
| GITLAB_DOMAIN | The GitLab server to authenticate against. | https://gitlab.com |
| GITLAB_AUTO_REGISTER_NEW_USERS | Whether to automatically register a new user or provide them the Registration form | false
###### Google

Begin by [creating OAuth2 credentials for your Google project](https://developers.google.com/identity/protocols/oauth2/web-server#prerequisites). Then fill out the following `.env` variables:

| Variable | Description | Default |
| -------- |------------ | ------- |
| GOOGLE_ENABLE | Whether or not to use Google as an OAuth2 provider. | false |
| GOOGLE_CLIENT_ID | The client ID from your Google OAuth2 credentials. | '' |
| GOOGLE_CLIENT_SECRET | The client secret from your Google OAuth2 credentials. | '' |
| GOOGLE_AUTO_REGISTER_NEW_USERS | Whether to automatically register a new user or provide them the Registration form | false

###### PingIdentity

Begin by [creating OAuth2 client in your PingIdentity console](https://docs.pingidentity.com/r/en-us/solution-guides/mzt1663945300370). Then fill out the following `.env` variables:

| Variable | Description | Default |
| -------- |------------ | ------- |
| PINGIDENTITY_ENABLE | Whether or not to use PingIdentity as an OAuth2 provider. | false |
| PINGIDENTITY_CLIENT_ID | The client ID from your PingIdentity OAuth2 credentials. | '' |
| PINGIDENTITY_CLIENT_SECRET | The client secret from your PingIdentity OAuth2 credentials. | '' |
| PINGIDENTITY_DOMAIN | The PingIdentity server to authenticate against. | https://auth.pingone.com |
| PINGIDENTITY_AUTH_ENDPOINT |  The URL fragment to the endpoint to ask for Authorization | '/as/authorization.oauth2' |
| PINGIDENTITY_TOKEN_ENDPOINT | The URL fragment to the endpoint to ask for the Token | '/as/token.oauth2' |
| PINGIDENTITY_USER_ENDPOINT | The URL fragment to the endpoint to ask for the user's information with the token | '/idp/userinfo.openid' |
| PINGIDENTITY_AUTO_REGISTER_NEW_USERS | Whether to automatically register a new user or provide them the Registration form | false

## SAML2

To configure CDash to authenticate against a SAML2 identity provider, you need to call `php artisan saml2:create-tenant` from the root of your CDash clone. For more details about the arguments that this Artisan command accepts, please run `php artisan saml2:create-tenant --help` or view the [upstream documentation](https://github.com/24Slides/laravel-saml2/#step-2-create-a-tenant).

Note that CDash currently only supports authentication against a single SAML2 IdP.

Relevant `.env` variables for CDash SAML2 authentication:
| Variable | Description | Default |
| -------- |------------ | ------- |
| SAML2_ENABLED | Whether or not to use SAML2 authentication. | false |
| SAML2_LOGIN_TEXT | What text to display in the SAML2 login button. | SAML2 |
| SAML2_AUTO_REGISTER_NEW_USERS | Whether or not to automatically register new users upon first login. | false |
