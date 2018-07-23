## Container Variables

### `CDASH_CONFIG`

The contents, verbatim, to be included in the local CDash configuration file
(`/var/www/cdash/config/config.local.php`), excluding the initial `<?php` line.
When running the container on the command line, consider writing the contents to
a local file:

```bash
$EDITOR local-configuration.php
...
docker run \
    -e CDASH_CONFIG="$( cat local-configuration.php )" \
    ... \
    kitware/cdash-docker
```

Note: When setting this variable in a docker-compose file, take care to ensure
that dollar signs (`$`) are properly escaped.  Otherwise, the resulting contents
of the file may be subject to variable interpolation.

Example:

```YAML

...

  # wrong: this string syntax is subject to interpolation
  # The contents will depend on the CDASH_DB_... variables as they are set at
  # container creation time
  CDASH_CONFIG: |
    $CDASH_DB_HOST = 'mysql';
    $CDASH_DB_NAME = 'cdash';
    $CDASH_DB_TYPE = 'mysql';
    ...

  # correct: use $$ to represent a literal `$`
  CDASH_CONFIG: |
    $$CDASH_DB_HOST = 'mysql';
    $$CDASH_DB_NAME = 'cdash';
    $$CDASH_DB_TYPE = 'mysql';
    ...

...
```

### `CDASH_ROOT_ADMIN_EMAIL` and `CDASH_ROOT_ADMIN_PASS`

The email and password, respectively, for the "root" administrator user, or the
initial administrator user that is created during the CDash `install.php`
procedure.  The `CDASH_ROOT_ADMIN_PASS` variable the only one that is strictly
required.  The default root admin email is `root@docker.container`.

The initial "root" administrator user is managed by the container.  The
container uses this user account to log in and provision the service as well as
set up static users.  This account is meant primarily to automate setup of the
CDash service and not as a regular administrator account.  To set up a
predefined administrator account, see `CDASH_STATIC_USERS`.

### `CDASH_ROOT_ADMIN_NEW_PASS`

Set this variable to change the password for the root administrator account.  If
set, the container will attempt to use this password when logging in as the root
account.  If the login is unsuccessful, it will try logging in using the
(presumably former) password set in `CDASH_ROOT_ADMIN_PASS`.  If this second
attempt is successful, it will update the root account so that its password is
reset to the value of `CDASH_ROOT_ADMIN_NEW_PASS`.

### `CDASH_STATIC_USERS`

A multiline value representing the set of user accounts to prepare as part of
the container's initialization process.  This value may contain comments that
start with `#` and lines with only white space; these parts of the text are
ignored.

Each user account identified in the set is created using the information
provided.  If the account already exists, its details are modified to match the
information provided.  Existing accounts that are not identified in the set are
not modified.

The representation for each user account begins with a line of the following
form:

```
[USER|ADMIN|DELETE] EMAIL PASSWORD [NEW_PASSWORD]
```

Where `EMAIL` is the user's email address, `PASSWORD` is the user's password,
and `NEW_PASSWORD` (if provided) is the user's new password.  If `NEW_PASSWORD`
is provided, the user's password is updated using the same procedure as that
with `CDASH_ROOT_ADMIN_NEW_PASS`.

This entry line may begin with an additional token.  A token of `USER` indicates
that the entry is for a normal (non-admin) account.  A token of `ADMIN`
indicates that the entry is for an administrator account.  A token of `DELETE`
indicates that any account with the given email (if found) should be deleted.
If no such token is provided, `USER` is assumed by default.

An entry may include an additional, optional line.  Such lines must be of the
following form:

```
[INFO] FIRST_NAME [LAST_NAME] [INSTITUTION]
```

Where `FIRST_NAME` is the user's first name, `LAST_NAME` is the user's last
name, and `INSTITUTION` is the name of the institution with which the user is
affiliated.

This second line may begin with an additional token with the value `INFO`, which
may be provided to distinguish this second line from a line representing a new
user account, in case the user's first name contains an `@` character.  For such
unusual cases, include this token so that the name is not mistaken for an email
address.

Note: for `DELETE` entries, all that is needed is the account's email address,
and for either `PASSWORD` or `NEW_PASSWORD` (if provided) to be set to the
password needed to log in as that user.  You may provide additional information
for the account, but it will not be used since the account will be deleted.

Note: for tokens with spaces, wrap them in quotes (`"`).
