# CDash

[![Build Status](https://img.shields.io/circleci/project/Kitware/CDash/master.svg?style=flat-square)](https://circleci.com/gh/Kitware/CDash)
[![Packagist Version](https://img.shields.io/packagist/v/kitware/cdash.svg?style=flat-square)](https://packagist.org/packages/kitware/cdash)
[![Packagist License](https://img.shields.io/packagist/l/kitware/cdash.svg?style=flat-square)](https://packagist.org/packages/kitware/cdash)
[![StyleCI](https://styleci.io/repos/25169249/shield)](https://styleci.io/repos/25169249)

## About CDash

CDash is an open source, web-based software testing server. CDash aggregates, analyzes and displays the results of
software testing processes submitted from clients located around the world. Developers depend on CDash to convey the
state of a software system, and to continually improve its quality. CDash is a part of a larger software process that
integrates Kitware’s CMake, CTest, and CPack tools, as well as other external packages used to design, manage and
maintain large-scale software systems. Good examples of a CDash are the
[CMake quality dashboard](https://open.cdash.org/index.php?project=CMake) and the
[VTK quality dashboard](https://open.cdash.org/index.php?project=VTK).

## Installation

The easiest way to install CDash is with [Docker](https://www.docker.com)'s [docker-compose](https://docs.docker.com/compose/).

```bash
git clone https://github.com/Kitware/CDash
cd CDash
docker-compose up -d
```
More details instructions for Docker builds can be in the [CDash Docker README](docker/docker.md)
### Requirements

- MySQL (5.x+) or PostgreSQL(8.3+)
- PHP 7.1
- Composer
- NPM

#### PHP Required Modules

- bcmath
- php_curl
- gd
- mbstring
- pdo_mysql or pdo_pgsql
- bz2
- xsl

```bash
git clone https://github.com/Kitware/CDash

# install CDash and Laravel dependencies
composer install --no-dev --prefer-dist
npm install

# Generate build files.
npm run production

# Setup default configuration.
cp .env.example .env

# Generate application key.
php artisan key:generate

# Migrate your config settings if you're upgrading an existing CDash instance.
php artisan config:migrate

```
#### Further reading
[CDash Docker README](docker/docker.md)

[Laravel Documentation](https://laravel.com/)

[Old install instructions, prebuilt download links, et al.](http://public.kitware.com/Wiki/CDash:Installation)


## Development

If you're interested in contributing to CDash, please begin by [introducing yourself on our mailing list](http://public.kitware.com/mailman/listinfo/cdash).


## Testing

[See here for information about our testing infrastructure](http://public.kitware.com/Wiki/CDash:Testing).
