# CDash Configuration

CDash is configured using environment variables.
These are typically set in a file named `.env` that lives in the root of your
CDash tree. In most cases, reasonable default values apply if a variable is not
explicitly set.

If you don't already have a `.env` file, you should start with
one based on the default configuration, and set your application key.

```bash
cd /path/to/CDash
cp .env.example .env
php artisan key:generate
```

In this document, we describe the settings that CDash administrators are most
likely to modify. Note that [.env.example](../.env.example) is the canonical
source of truth for the various environment variables used by CDash.
Please see that file for a full list of configuration options.

## Critical settings

| Variable  | Description | Default |
| --------- | ----------- | ------- |
| APP_URL | The root URL of CDash | https://localhost |
| DB_DATABASE | The name of CDash's database | cdash |
| DB_HOST | The hostname of the database server | localhost |
| DB_CONNECTION | The type of database used by CDash | mysql |
| DB_USERNAME | The database user used by CDash | root |
| DB_PASSWORD | The password of CDash's database user | secret |

It is required that your `.env` file has a `APP_URL` entry, and that the following line
appears somewhere further down in the file:

```
MIX_APP_URL="${APP_URL}"
```

## Email

| Variable  | Description | Default |
| --------- | ----------- | ------- |
| MAIL_MAILER | The mailer that CDash will use to send email | sendmail |
| MAIL_FROM_ADDRESS | The email address that CDash sends mail from | cdash@localhost |
| MAIL_REPLY_ADDRESS | The reply-to email address for CDash | cdash-noreply@localhost |
| MAIL_FROM_NAME | The name associated with emails from CDash | CDash |

### SMTP-specific settings

| Variable  | Description | Default |
| --------- | ----------- | ------- |
| MAIL_HOST | The hostname of the SMTP server used by CDash | smtp.mailgun.org |
| MAIL_PORT | The port used to send email via SMTP | 587 |
| MAIL_USERNAME | The username used to connect to the SMTP server | '' |
| MAIL_PASSWORD | The password of the SMTP user | '' |



## Authentication
By default, CDash stores each user's email address and a hash of their password
in the `user` table. This information is used to authenticate users as they log
into CDash. For this default authentication system, no extra configuration
is required.

For information about CDash other authentication options, please see our
[Authentication guide](authentication.md).

## Submission parsing

For synchronous submission parsing, set `QUEUE_CONNECTION=sync`.

Asynchronous submission parsing is more robust for production CDash instances.
To enable this feature, set `QUEUE_CONNECTION=database`.

Note that async parsing also requires a queue worker to be running.
This can be started manually by running `php artisan queue:work`,
but it is better to make sure this process is always running as a background
service.

See the [Submissions guide](submissions.md) for more details.

## Other settings
| Variable  | Description | Default |
| --------- | ----------- | ------- |
| ACTIVE_PROJECT_DAYS | How long (in days) since the last submission before considering a project inactive and hidden on the /projects page | 7 |
| BACKUP_TIMEFRAME |  How long (in hours) CDash will store parsed input files | 48 |
| DEFAULT_PROJECT | Display a given project by default when one isn't specified | '' |
| LARGE_TEXT_LIMIT | How many bytes of build/test data CDash should accept before truncating away the center (0 for unlimited) | 0 |
| USE_VCS_API | Whether or not CDash should communicate with version control (eg. GitHub) API endpoints | true |
