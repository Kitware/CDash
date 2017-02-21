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

```bash
git clone https://github.com/Kitware/CDash.git CDash
cd CDash
```

### Prerequisites

CDash needs:
  * A web server (Apache, NGINX, IIS) with PHP and SSL enabled.
  * Access to a MySQL or PostgreSQL database server.

### Linux and OS X

For production:
```bash
git checkout prebuilt
```

For development:

[Install Node.js](https://nodejs.org/en/download/package-manager/).
```bash
curl -sS https://getcomposer.org/installer | php
php composer.phar install
npm install
node_modules/.bin/gulp
```

### Windows

For production:
```cmd
git checkout prebuilt
```

For development:

Download and run [Composer-Setup.exe](https://getcomposer.org/Composer-Setup.exe).

[Install Node.js](https://nodejs.org/en/download).
```cmd
composer install
npm install
node_modules/.bin/gulp
```


## Development

If you're interested in contributing to CDash, please begin by [introducing yourself on our mailing list](http://public.kitware.com/mailman/listinfo/cdash).


## Testing

[See here for information about our testing infrastructure](http://public.kitware.com/Wiki/CDash:Testing).
