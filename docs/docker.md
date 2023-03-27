Unfamiliar with Docker?  [Start here](https://docs.docker.com/get-started/).

## How-to Guide ##

### Install CDash

#### Quick Start
If you haven't done so already, begin by cloning the CDash repository. Then start the 
`docker-compose` process to accept default values and start using the website

```bash
git clone https://github.com/Kitware/CDash
cd CDash
docker-compose pull
docker-compose up -d cdash
```

#### Customizing the CDash instance
In the root of your CDash clone, edit `docker-compose.yml`.

The `CDASH_CONFIG` section is where you specify settings that will be stored in your `config.local.php`
file.

Once you're happy with your changes to this file, run:

```
docker-compose up -d cdash
```

This tells [Docker Compose](https://docs.docker.com/compose/) to build and run services for the CDash web server and its MySQL database. This command downloads [a prebuilt image from DockerHub](https://hub.docker.com/r/kitware/cdash/).  If you prefer to build your own Docker image for CDash, pass the `--build` option to `docker-compose`.

This initial `docker-compose` command does not run the CDash's install script by default.  To achieve that, run:

```
docker-compose run --rm cdash install configure
```

This executes a one-shot container that runs the install procedure and sets up the predefined users from your `docker-compose.yml` file.

Once this command complete, browse to localhost:8080.  You should see a freshly installed copy of CDash with the latest database schema.

#### Change the config and redeploy

Edit `docker-compose.yml` and run

```
docker-compose run --rm cdash configure
```

### Using asynchronous submission parsing

The docker-compose created system is now configured to utilize CDash's
asynchronous parsing of submissions.  This introduces a new
service to start **after** the `install` process for CDash has been executed.
Without the tables in the database, the `worker` service will print several error
messages and may exit before the system is set up properly.

This requires an additional run of docker-compose specifically for the
`worker` service:

```bash
docker-compose up -d worker
```

# Synchronous parsing

To revert back to the traditional submission parsing, update the two
instances of `QUEUE_CONNECTION` in the `docker-compose.production.yaml`
file to be `sync` instead of `database`.  Then, run the

```bash
docker-compose up -d cdash
```

command again to make CDash reload it's environment.

### Pull in changes from upstream CDash (upgrade)

If you're using prebuilt images from DockerHub, run the following commands:

```
docker-compose pull cdash
docker-compose up -d
```

If you prefer to build your own images locally, run:
```
docker-compose up -d --no-deps --build common cdash
````

## Container Variables

### `CDASH_CONFIG`

The contents, verbatim, to be included in the local CDash configuration file
(`/var/www/cdash/.env`)

When running the container on the command line, consider writing the contents to
a local file:

```bash
$EDITOR local-configuration.php
...
docker run \
    -e CDASH_CONFIG="$( cat local-configuration.php )" \
    ... \
    kitware/cdash
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
procedure.  The `CDASH_ROOT_ADMIN_PASS` variable is the only one that is
strictly required.  The default root admin email is `root@docker.container`.

The initial "root" administrator user is managed by the container.  The
container uses this user account to log in and provision the service.

### `CDASH_ROOT_ADMIN_NEW_PASS`

Set this variable to change the password for the root administrator account.  If
set, the container will attempt to use this password when logging in as the root
account.  If the login is unsuccessful, it will try logging in using the
(presumably former) password set in `CDASH_ROOT_ADMIN_PASS`.  If this second
attempt is successful, it will update the root account so that its password is
reset to the value of `CDASH_ROOT_ADMIN_NEW_PASS`.
