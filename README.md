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

### Linux and OS X

```bash
git clone https://github.com/Kitware/CDash.git CDash
cd CDash
curl -sS https://getcomposer.org/installer | php
```

For development:
```bash
php composer.phar install
```

For production:
```bash
php composer.phar install --no-dev --optimize-autoloader
```

### Windows

Download and run [Composer-Setup.exe](https://getcomposer.org/Composer-Setup.exe).

```cmd
git clone https://github.com/Kitware/CDash.git CDash
cd CDash
```

For development:
```cmd
composer install
```

For production:
```cmd
composer install --no-dev --optimize-autoloader
```

## Development

TODO

## Testing

TODO
