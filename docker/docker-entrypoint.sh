#!/bin/bash

export BASH_LIB=/bash-lib
source "$BASH_LIB/cdash.bash"
source "$BASH_LIB/debug.bash"
source "$BASH_LIB/misc.bash"
source "$BASH_LIB/on_exit.bash"
source "$BASH_LIB/tmp_dir.bash"
source "$BASH_LIB/web.bash"

do_configure() {
    local_service_setup
    (
        cdash_session "$CDASH_ROOT_ADMIN_EMAIL" \
                "$CDASH_ROOT_ADMIN_PASS"        \
                "$CDASH_ROOT_ADMIN_NEW_PASS"

        if [ "$?" '!=' '0' ] ; then
            echo "Warning: could not log in as the root admin user:" \
                 "Wrong email or password" >&2
            return 1
        fi
    )
}

do_install() {
    local_service_setup

    # ENSURE ROOT ADMIN USER
    root_pass="$CDASH_ROOT_ADMIN_PASS"
    if [ -n "$CDASH_ROOT_ADMIN_NEW_PASS" ] ; then
        root_pass="$CDASH_ROOT_ADMIN_NEW_PASS"
    fi

    session="$( web_make_session )"
    cdash_install "$session" "$CDASH_ROOT_ADMIN_EMAIL" \
        "$root_pass"
}

usage() {
    echo "Usage: $0 [OPTIONS]... [COMMANDS]..."
    echo "Run various CDash operations."
    echo ""

    echo "OPTIONS"
    echo ""
    echo "  -v, --verbose enable more verbose logging"
    echo "  -h, --help    display this help and exit"
    echo ""

    echo "COMMANDS"
    echo ""

    echo "  install"
    echo "      run CDash's initial install process (install.php)."
    echo ""

    echo "  upgrade"
    echo "      upgrade database schema"
    echo ""

    echo "  configure"
    echo "      update local configuration"
    echo ""


    echo "  start-worker"
    echo "      Start the Laravel asynchronus submission parsing"
    echo ""


    echo "  serve"
    echo "      serve web traffic (default operation)"
    echo ""

    echo "  help"
    echo "      display this help and exit"
    echo ""
}

do_install=0
do_upgrade=0
do_configure=0
do_serve=0

local_service_needed=0

args_provided=0

while [ -n "$*" ] ; do
    args_provided=1

    case "$1" in
        install)
            do_install=1
            local_service_needed=1
            ;;
        upgrade)
            do_upgrade=1
            local_service_needed=1
            ;;
        configure)
            do_configure=1
            local_service_needed=1
            ;;
        start-worker)
            start_worker=1
            ;;
        serve)
            do_serve=1
            ;;
        -v)
            export DEBUG=1
            ;;
        --verbose)
            export DEBUG=1
            ;;
        -h)
            usage
            exit
            ;;
        --help)
            usage
            exit
            ;;
        help)
            usage
            exit
            ;;
    esac
    shift
done

if [ "$args_provided" '=' '0' ] ; then
    do_serve=1
fi

if missing_root_admin_pass ; then
    exit 1
fi

setup_local_config

if [ -z "$CDASH_ROOT_ADMIN_EMAIL" ] ; then
    CDASH_ROOT_ADMIN_EMAIL="root@docker.container"
fi

if [ "$local_service_needed" '=' '1' ] ; then
    local_service_setup
fi

if [ "$do_install" '=' '1' ] ; then
    do_install
fi

if [ "$local_service_needed" '=' '1' ] ; then
    cdash_session "$CDASH_ROOT_ADMIN_EMAIL" \
            "$CDASH_ROOT_ADMIN_PASS"        \
            "$CDASH_ROOT_ADMIN_NEW_PASS"
fi

if [ "$do_upgrade" '=' '1' ] ; then
    cdash_upgrade
fi

if [ "$do_configure" '=' '1' ] ; then
    do_configure
fi

if [ "$local_service_needed" '=' '1' ] ; then
    local_service_teardown
fi

if [ "$start_worker" '=' '1' ] ; then
   exec php artisan queue:work
fi

if [ "$do_serve" '!=' '1' ] ; then
    exit
fi

# serve
exec /usr/sbin/apache2ctl -D FOREGROUND
