#!/bin/bash

export BASH_LIB=/bash-lib
source "$BASH_LIB/cdash.bash"
source "$BASH_LIB/debug.bash"
source "$BASH_LIB/misc.bash"
source "$BASH_LIB/on_exit.bash"
source "$BASH_LIB/tmp_dir.bash"

do_install() {
    # ENSURE ROOT ADMIN USER
    root_pass="$CDASH_ROOT_ADMIN_PASS"
    cdash_install "$CDASH_ROOT_ADMIN_EMAIL" "$root_pass"
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
do_serve=0

args_provided=0

while [ -n "$*" ] ; do
    args_provided=1

    case "$1" in
        install)
            do_install=1
            ;;
        upgrade)
            do_upgrade=1
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

if [ -z "$CDASH_ROOT_ADMIN_EMAIL" ] ; then
    CDASH_ROOT_ADMIN_EMAIL="root@docker.container"
fi

if [ "$do_install" '=' '1' ] ; then
    do_install
fi

if [ "$do_upgrade" '=' '1' ] ; then
    cdash_upgrade
fi

setup_local_config

if [ "$start_worker" '=' '1' ] ; then
   exec php artisan queue:work
fi

if [ "$do_serve" '!=' '1' ] ; then
    exit
fi

# serve
php artisan schedule:work &
exec /usr/sbin/apache2ctl -D FOREGROUND
