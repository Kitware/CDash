Unfamiliar with Docker?  [Start here](https://docs.docker.com/get-started/).

## Quick Start (testing installation) ##

The following instructions spin up CDash & MySQL for local experimentation.

1. If you haven't done so already, begin by cloning the CDash repository:

```bash
git clone https://github.com/Kitware/CDash
cd CDash
```

2. Next, use the `docker compose up` command to spin up your new CDash instance:

```bash
docker compose -f docker/docker-compose.yml \
               -f docker/docker-compose.dev.yml \
               -f docker/docker-compose.mysql.yml \
               --env-file .env.dev up -d
```

3. Browse to http://localhost:8080.  You should see a freshly installed copy of CDash with the latest database schema.

## Configuration

### Why so many YAML files?
You may have noticed that CDash's `docker compose` configuration is [split across multiple files](https://docs.docker.com/compose/extends/). The allows us to support various workflows (MySQL vs. Postgres, production vs. development) while minimizing code duplication.

For example, to use Postgres instead of MySQL, pass `-f docker/docker-compose.postgres.yml` instead of `-f docker/docker-compose.mysql.yml` to the `docker compose` commands mentioned in this document.

### Changing the default configuration
You can change the following environment variables in `docker/docker-compose.yml`:
* `CDASH_ROOT_ADMIN_EMAIL`: the email address (username) for the CDash admin user. The default email address is `root@docker.container`.
* `CDASH_ROOT_ADMIN_PASS`: the password for the CDash admin user. The default password is `secret`.

To change the default database password, modify `DB_PASSWORD` in `docker/docker-compose.mysql.yml` or `docker/docker-compose.postgres.yml`.

Once you're happy with your changes, re-run `docker compose up` (with the appropriate`-f` flags) to build and run services for CDash and its database.

### Building from source
If you would prefer to build your own Docker images for CDash, pass the `--build` option to your `docker compose up` command.


## Production Installation

A production installation differs from a testing installation in the following ways:
* Traffic is served over https. For this reason, these instructions assume you don't already have a web server on your host system that's serving traffic on port 443.
* CDash will be serving traffic over an externally-visible URL (not `localhost`).
* CDash's submissions will be parsed _asychronously_. Note that the `cdash_worker` service will emit errors until the database tables are created.

To set up a CDash production instance using docker compose, follow these steps:
* Generate or obtain SSL certificate files. Some possibilities here are [Let's Encrypt](https://letsencrypt.org/) or [self-signed certificates](https://wiki.debian.org/Self-Signed_Certificate). Make sure the resulting files will be readable to the `www-data` user (UID 33) in the CDash container.
* `cp .env.example .env`
* Edit `.env` and modify the following lines:
  - `APP_URL=https://<my-cdash-url>`
  - `SSL_CERTIFICATE_FILE=</path/to/certs/my-cert.pem>`
  - `SSL_CERTIFICATE_KEY_FILE=</path/to/certs/my-cert.key>`
* For postgres only, edit `docker/docker-compose.postgres.yml` and uncomment the `worker` section.
* Run this command to start your CDash containers:
```bash
docker compose --env-file .env \
	   -f docker/docker-compose.yml \
	   -f docker/docker-compose.production.yml \
	   -f docker/docker-compose.mysql.yml \
	    up -d
```


## Pull in changes from upstream CDash (upgrade)

If you're using prebuilt images from DockerHub, run the following command to download the latest image:

```
docker compose -f docker/docker-compose.yml \
               -f docker/docker-compose.production.yml \
               -f docker/docker-compose.mysql.yml \
               pull cdash
```

and then repeat your `docker compose up` command to start your CDash containers.

If you prefer to build your own images locally, you can pass the `--build` option to your `docker compose up` command as shown previously in this document.

## Cleaning up
CDash's docker compose system creates volumes for persistent data. A primary benefit of this setup is that you won't lose the contents of your database if that container stops running.

If you're done experimenting with CDash locally and you would like to remove these volumes, perform the following commands:
```bash
docker volume ls                      # shows what volumes are defined on your system
docker volume rm cdash_storage        # CDash's local storage for submission files
docker volume rm cdash_mysqldata      # for mysql
docker volume rm cdash_postgresqldata # for postgres
```
