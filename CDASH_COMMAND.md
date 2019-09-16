# CDash Command

The `docker/commands.bash` file is a bash utility that helps create CDash containers for use in either development or production environments. Its commands are those used by the CDash continuous integration ecosystem and its purpose is to provide stability across platforms and environments as well as ease some of the complication realated to building these environments from scratch.

## Contents
1. [Quick start](#quick-start)
2. [Examples](#examples)
2. [Usage](#usage)
    1. [cdash_branch](#cdash_branch)
    2. [cdash_build_image](#cdash_build_image)
    3. [cdash_environment](#cdash_enviroment)
    4. [cdash_run_and_submit_ctest](#cdash_run_and_submit_ctest)
    5. [cdash_run_and_submit_mysql_ctest](#cdash_run_and_submit_mysql_ctest)
    5. [cdash_run_and_submit_pgsql_ctest](#cdash_run_and_submit_pgsql_ctest)
    5. [cdash_start_docker_services](#cdash_start_docker_services)
    6. [cdash_stop_docker_services](#cdash_stop_docker_services)
    7. [cdash_wait_for_ready](#cdash_wait_for_ready)

## Quick start
```
# from CDash root directory

$ source docker/commands.bash
$ cdash_environment mysql # or postgres, or production
$ cdash_build_image
$ cdash_start_docker_services && cdash_wait_for_ready http://localhost/ping 12
$ cdash_run_and_submit_mysql_ctest # or cdash_run_and_submit_postgres_ctest
$ cdash_stop_docker_services
```
## Examples

#### Development/Testing
```text
$ source docker/commands.bash
$ cdash_environment mysql
$ cdash_start_docker_services
$ cdash_test -VV
```

In the example above:
* The first step is to source the `docker/commands.bash` file--this only needs to happen once per shell session.
* Secondly we indicate that we wish to setup MySQL as the database for our development environment.
* Next we start all of the necessary services. In this case, because we've specified mysql as our environment, four container will start whose service names are: `cdash`, `cdash_chrome_1`, `selenium-hub`, and `cdash_mysql_1`.
* Lastly we run our tests to ensure that everything is working correctly.

#### Production
#### Development/Testing
```text
$ source docker/commands.bash
$ cdash_environment production
$ cdash_start_docker_services
```
In the example above, a production ready set of containers is now available. See the [Docker Instructions](docker/docker.md) for further information about how to configure this environment.

## Usage

### cdash_enviroment

```text
NAME
    cdash_environment
SYNOPSIS
    cdash_environment <environment>
DESCRIPTION
    Sets the desired environment to one of three possibilities, production, 
    mysql or postgres.

    The Production environment uses the docker/docker-compose.production.yml
    file to build the environment. It uses a MySQL backend and *does not* build
    or use containers that are used solely for developing and testing CDash, 
    i.e. facilities for CMake, xdebug and Selenium.

    Both mysql and postgres environments will use the 
    docker/docker-compose.mysql.yml and docker/docker-compose.postgres.yml
    respectively. Both of these environment are used for development and 
    testing and therefore include all facilities for doing so (e.g. CMake
    xdebug and Selenium).

    The default environment is production.

    Arguments:
        production  use the docker-compose.production.yml configuration
        mysql       use the docker-compose.mysql.yml configuration
        postgres    use the docker-compose.postgres.yml configuration
```

### cdash_build_image

```text
NAME
    cdash_build_image
SYNOPSIS
    cdash_build_image
DESCRIPTION
    Builds a CDash image whose environment is specified by the
    docker-compose.local.yml file. The docker-compose.local.yml file is merely
    a link that points to one of the three environment files.
SEE ALSO
    cdash_environment
```

### cdash_start_docker_services

```text
NAME
    cdash_start_docker_services
SYNOPSIS
    cdash_start_docker_services
DESCRIPTION
    Starts all of the docker services in the docker-compose.local.yml in
    detached mode. The command also creates or reuses a Docker network called
    cdash_default allowing the containers to communicate with one another.
    
    Services started in the production enviroment are cdash and cdash_mysql_1.

    Services started in the mysql environment are cdash, cdash_mysql_1,
    cdash_chrome_1, and selenium-hub.
    
    Services started in the postgres environment are cdash, cdash_postgres_1,
    cdash_chrome_1, and selenium-hub.
```

### cdash_start_docker_dev_services

```text
NAME
    cdash_start_docker_dev_services
SYNOPSIS
    cdash_start_docker_dev_services
DESCRIPTION
    This has the same behavior as cdash_start_docker_services with the
    that this command will bind mount the src directory so that any changes
    made there will be reflected in application functionality
```

### cdash_stop_docker_services

```text
NAME
    cdash_stop_docker_services
SYNOPSIS
    cdash_stop_docker_services
DESCRIPTION
    Stops all of the docker services started by cdash_start_docker_services.
```

### cdash_wait_for_ready

```text
NAME
    cdash_wait_for_ready
SYNOPSIS
    cdash_wait_for_ready <url> <attempts>
DESCRIPTION
    This command indicates whether or not CDash is ready to accept
    requests. The services started by cdash_start_docker_services may not be
    available immediately and, for instance, with automated testing, you will
    need CDash and its databases to be fully initialized and ready before
    accepting requests. The command synchronously halts further processing
    until all services are ready for requests.

    It's important to note that the url to test is a url accessible from inside
    the Docker container, and that a value of http://localhost/ping may be
    used.
    
    The command requires an attempts argument and will wait 5 seconds between
    each attempt.
    
    Arguments:
        url         The url to use to test readiness
        attempts    The number of attempts to test for readiness
```

### cdash_site

```text
NAME
    cdash_site
SYNOPSIS
    cdash_site
DESCRIPTION
    Returns either the value of the environment variable SITENAME, or the
    result from the command hostname.
SEE ALSO
    hostname
```

### cdash_branch

```text
NAME
    cdash_branch
SYNOPSIS
    cdash_branch
DESCRIPTION
    Returns the name of the current git branch.
SEE ALSO
    git rev-parse --abbrev-ref HEAD
```

### cdash_run_and_submit_ctest

```text
NAME
    cdash_run_and_submit_ctest
SYNOPSIS
    cdash_run_and_submit_ctest <database>
DESCRIPTION
    Runs the CircleCI CTest driver script. This is the CTest script used to 
    build, test and submit results to https://open.cdash.org. Unless you intend
    to send your build's results to the same location there is no reason to use
    this command.
    
    Argument:
        database    the name of the database to be used in the BUILDNAME var
```

### cdash_run_and_submit_mysql_ctest

```text
NAME
    cdash_run_and_submit_mysql_ctest
SYNOPSIS
    cdash_run_and_submit_mysql_ctest
DESCRIPTION
    Short cut for cdash_run_and_submit_mysql_ctest MySQL
SEE ALSO
    cdash_run_and_submit_ctest
```

### cdash_run_and_submit_pgsql_ctest

```text
NAME
    cdash_run_and_submit_pgsql_ctest
SYNOPSIS
    cdash_run_and_submit_pgsql_ctest
DESCRIPTION
    Short cut for cdash_run_and_submit_mysql_ctest PgSQL
SEE ALSO
    cdash_run_and_submit_ctest
```

### cdash_test

```text
NAME
    cdash_test
SYNOPSIS
    cdash_test [ctest-arguments]
DESCRIPTION
    Builds a new cmake directory and calls CTest inside the container with the
    given arguments.
SEE ALSO
    ctest
```
