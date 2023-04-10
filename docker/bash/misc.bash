if [ -z "$__bash_lib_misc" ] ; then
__bash_lib_misc=1


source "$BASH_LIB/cdash.bash"
source "$BASH_LIB/on_exit.bash"


__local_config_file="/home/kitware/cdash/.env"

missing_root_admin_pass() {
    if [ -z "$CDASH_ROOT_ADMIN_PASS" ] && [ -z "$DEVELOPMENT_BUILD" ]; then
        echo "error: This container requires the CDASH_ROOT_ADMIN_PASS"
        echo "       environment variable to be defined."
        return
    fi >&2
    return 1
}

__local_service_setup=0
__apache_pid=
local_service_setup() {
    if [ "$__local_service_setup" '=' '1' ] ; then
        return
    fi

    PORT="$(( (RANDOM % 20000) + 10000 ))"
    sed -i 's/^Listen [0-9][0-9]*/Listen '"$PORT"'/g' /etc/apache2/ports.conf
    sed -i 's/^<VirtualHost \*:[0-9][0-9]*>/<VirtualHost \*:'"$PORT"'>/g' \
        /etc/apache2/sites-enabled/cdash-site.conf
    echo "CDASH_FULL_EMAIL_WHEN_ADDING_USER=1" >> "$__local_config_file"

    /usr/sbin/apache2ctl -D FOREGROUND &
    __apache_pid="$!"

    on_exit local_service_teardown
    __local_service_setup=1

    cdash_set_host "http://localhost:$PORT"

    sleep 2
}

setup_local_config() {
    (
        echo "DB_HOST=mysql"
        echo "DB_NAME=cdash"
        echo "DB_TYPE=mysql"
        echo "DB_LOGIN=root"
        echo "DB_PASS="

        if [ '!' -z ${CDASH_CONFIG+x} ] ; then
            # Drop old formatting for PHP values by removing "$" or ";"
            sed 's/[$;]//g' <<< "$CDASH_CONFIG"
        fi

    ) >> "$__local_config_file"
    cd /home/kitware/cdash && php artisan config:migrate && php artisan key:generate && npm run dev
}

local_service_teardown() {
    if [ "$__local_service_setup" '=' '0' ] ; then
        return
    fi

    if [ -n "$__apache_pid" ] ; then
        /usr/sbin/apache2ctl graceful-stop
        wait $__apache_pid
        __apache_pid=
        sleep 2
    fi

    sed -i 's/^Listen [0-9][0-9]*/Listen 80/g' /etc/apache2/ports.conf
    sed -i 's/^<VirtualHost \*:[0-9][0-9]*>/<VirtualHost \*:80>/g' \
        /etc/apache2/sites-enabled/cdash-site.conf
    tmp="$( mktemp )"
    head -n -1 "$__local_config_file" > "$tmp"
    cat "$tmp" > "$__local_config_file"
    rm "$tmp"

    cdash_set_host ""

    __local_service_setup=0
}

__user_prefix="__user"
user_set() {
    email="$1" ; shift
    key="$1" ; shift
    value="$1" ; shift
    email_hash="$( echo "$email" | sha1sum | cut -d\  -f 1 )"
    eval "${__user_prefix}_${email_hash}_${key}=\"${value}\""
}

user_get() {
    email="$1" ; shift
    key="$1" ; shift
    email_hash="$( echo "$email" | sha1sum | cut -d\  -f 1 )"
    eval "echo \"\$${__user_prefix}_${email_hash}_${key}\""
}


fi # __bash_lib_misc
