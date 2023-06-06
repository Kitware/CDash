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

setup_local_config() {
    (
        if [ '!' -z ${CDASH_CONFIG+x} ] ; then
            # Drop old formatting for PHP values by removing "$" or ";"
            sed 's/[$;]//g' <<< "$CDASH_CONFIG"
        fi
    ) >> "$__local_config_file"

    # Update the value of APP_URL in the container if necessary.
    if [ -n "$APP_URL" ]; then
        cd /home/kitware/cdash && sed -i "s^APP_URL=https://localhost^APP_URL=${APP_URL}^g" .env
    fi

    cd /home/kitware/cdash && php artisan config:migrate && php artisan key:generate && npm run production
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
