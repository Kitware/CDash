# Installation

If you'd like to install CDash in a [Docker](https://www.docker.com) container, please see our
[Docker installation guide](docs/docker.md).

## Prerequisite software

Before installing CDash, you will need:

- A web server: [Apache](https://httpd.apache.org) or [NGINX](https://www.nginx.com)
- A database: [MySQL v5.x+](https://www.mysql.com) or [PostgreSQL v9.2+](https://www.postgresql.org)
- [PHP 7.2 - 7.4](https://www.php.net)
- [Composer](https://getcomposer.org) (to install PHP dependencies)
- [npm](https://www.npmjs.com/) (to install Javascript dependencies)

## PHP modules

CDash needs the following PHP modules installed and enabled.

- bcmath
- php_curl
- gd
- json
- mbstring
- pdo_mysql or pdo_pgsql
- bz2
- xsl

## Web server configuration

CDash is built on top of the [Laravel framework](https://laravel.com).

Laravel's routing system requires your web server to have the `mod_rewrite` module enabled.

It also requires your web server to honor .htaccess files `(AllowOverride All)`.

See [Laravel documentation](https://laravel.com/docs/6.x/installation#pretty-urls) for more information.

## Download CDash

If you haven't already done so, [download CDash from GitHub](https://github.com/Kitware/CDash/releases) or clone it using git.

```bash
git clone https://github.com/Kitware/CDash
```

## Expose CDash to the web

Only CDash's `public` subdirectory  should be served to the web.

The easiest way to achieve this is to create a symbolic link in your DocumentRoot
(typically `/var/www`) that points to `/path/to/CDash/public`.

## Adjust filesystem permissions

The user that your web server is running under will need write access to the CDash directory.
In the following example, we assume your web server is run by the `www-data` user.

```bash
# Modify CDash directory to belong to the www-data group
chgrp -R www-data /path/to/CDash

# Make the CDash directory writeable by group.
chmod -R g+rw /path/to/CDash
```

## Install/upgrade steps

Perform the follow steps when you initially install CDash and upon each subsequent upgrade.

```bash
# Install PHP and JavaScript dependencies
composer install --no-dev --prefer-dist
npm install

# Generate build files
npm run dev
```

## Install steps: initial installation only

The following steps only need to be completed the first time you setup CDash.

```bash
# Setup default configuration
cp .env.example .env

# Generate application key
php artisan key:generate
```

## Configure CDash

If you are upgrading an existing CDash instance, run the following command to migrate
your config settings into the .env file:

```bash
php artisan config:migrate
```

Otherwise, edit `.env` and set configuration variables as necessary.
In particular, you will want to set the following values:
* The `DB_*` variables indicate how to connect to the database
* `APP_URL` should be set to the root URL of CDash (ie `https://localhost/CDash`)

In most other cases, reasonable default values apply if the variables are not explicitly set.

## Finish CDash installation

Open up your new CDash instance in a web browser and fill out the installation form.

Once that is complete you can create a project and start submitting builds to it.
