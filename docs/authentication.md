# CDash Authentication

This page describes the various authentication workflows supported by CDash.

## Email & Password
By default, CDash authenticates users against an internal database table.
You can disable CDash's standard email + password authentication by setting `USERNAME_PASSWORD_AUTHENTICATION_ENABLED=false
` in your `.env` file.

## Customizing the login page
You can add your own custom content to the login page by writing a
[Blade template file](https://laravel.com/docs/9.x/blade) named `login.blade.php` in `resources/views/local/`.

## LDAP

Here is a sample `.env` configuration that allows CDash to authenticate against an LDAP server running on localhost for the `example.com` domain.

```
CDASH_AUTHENTICATION_PROVIDER=ldap
LDAP_PROVIDER=openldap
LDAP_HOSTS=localhost
LDAP_BASE_DN="dc=example,dc=com"
LDAP_USERNAME="cn=admin,dc=example,dc=com"
LDAP_PASSWORD=<your LDAP admin pass>
LDAP_BIND_USERS_BY=dn
LDAP_LOGGING=true
```

Here's a description of the `.env` variables involved in the LDAP authentication process.
| Variable      | Description   |
| ------------- |-------------  |
| `CDASH_AUTHENTICATION_PROVIDER` | Set this to `ldap` to enable CDash's LDAP authentication support. |
| `LDAP_BASE_DN` | The base distinguished name you'd like to perform query operations on. |
| `LDAP_BIND_USERS_BY` | The LDAP users attribute used for authentication (`distinguishedname` by default). |
| `LDAP_EMAIL_ATTRIBUTE` | The LDAP users attribute containing their email address (`mail` by default). |
| `LDAP_FILTERS_ON` | Additional LDAP query filters to restrict authorized user list. For example, to restrict users to a specific Active Directory group: `(memberOf=cn=myRescrictedGroup,cn=Users,dc=example,dc=com)`
| `LDAP_GUID` | The LDAP attribute name that contains your users object GUID. |
| `LDAP_HOSTS` | A space-separated list of LDAP servers (IP addresses or host names). |
| `LDAP_LOCATE_USERS_BY` | The LDAP users attribute used to locate your users. (`mail` by default). |
| `LDAP_LOGGING` | Whether or not to log LDAP activities. Useful for debugging. |
| `LDAP_PASSWORD` | Password for account that can query and run operations on your LDAP server(s). |
| `LDAP_PROVIDER` | The type of LDAP server you are connecting to. Valid values are activedirectory, openldap, and freeipa. |
| `LDAP_USERNAME` | Username for account that can query and run operations on your LDAP server(s). |

## OAuth2

CDash currently supports OAuth2 login for GitHub, GitLab, and Google accounts.

###### GitHub

To begin, you will need to
[create a GitHub OAuth2 app](https://docs.github.com/en/apps/oauth-apps/building-oauth-apps/creating-an-oauth-app) for your CDash instance. Make note of the Client ID and Client Secret created for you by GitHub. These will be used in the `.env` variables described below.

| Variable      | Description   |
| ------------- |-------------  |
| `GITHUB_ENABLE` | Whether or not to use GitHub as an OAuth2 provider (defaults to false). |
| `GITHUB_CLIENT_ID` | The Client ID assigned to your GitHub OAuth2 app. |
| `GITHUB_CLIENT_SECRET` | The Client Secret created for your GitHub OAuth2 app. |

###### GitLab

First [configure GitLab as an OAuth2 authentication identity provider](https://docs.gitlab.com/ee/integration/oauth_provider.html). Then set the following variables in your `.env` file.

| Variable      | Description   |
| ------------- |-------------  |
| `GITLAB_ENABLE` | Whether or not to use GitLab as an OAuth2 provider (defaults to false). |
| `GITLAB_CLIENT_ID` | The OAuth 2 Client ID from the Application ID field. |
| `GITLAB_CLIENT_SECRET` | The OAuth 2 Client Secret from the Secret field. |
| `GITLAB_DOMAIN` | The GitLab server to authenticate against. Defaults to gitlab.com. |

###### Google

Begin by [creating OAuth2 credentials for your Google project](https://developers.google.com/identity/protocols/oauth2/web-server#prerequisites). Then fill out the following `.env` variables:

| Variable      | Description   |
| ------------- |-------------  |
| `GOOGLE_ENABLE` | Whether or not to use Google as an OAuth2 provider (defaults to false). |
| `GOOGLE_CLIENT_ID` | The client ID from your Google OAuth2 credentials. |
| `GOOGLE_CLIENT_SECRET` | The client secret from your Google OAuth2 credentials. |

## SAML2

To configure CDash to authenticate against a SAML2 identity provider, you need to call `php artisan saml2:create-tenant` from the root of your CDash clone. For more details about the arguments that this Artisan command accepts, please run `php artisan saml2:create-tenant --help` or view the [upstream documentation](https://github.com/24Slides/laravel-saml2/#step-2-create-a-tenant).

Note that CDash currently only supports authentication against a single SAML2 IdP.

Relevant `.env` variables for CDash SAML2 authentication:
| Variable      | Description   |
| ------------- |-------------  |
| `SAML2_ENABLED` | Whether or not to use SAML2 authentication. Defaults to false. |
| `SAML2_LOGIN_TEXT` | What text to display in the SAML2 login button. Defaults to "SAML2". |
| `SAML2_AUTO_REGISTER_NEW_USERS` | Whether or not to automatically register new users upon first login. Defaults to false.
