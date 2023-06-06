if [ -z "$__bash_lib_cdash" ] ; then
__bash_lib_cdash=1


source "$BASH_LIB/debug.bash"
source "$BASH_LIB/on_exit.bash"
source "$BASH_LIB/web.bash"


__cdash_session_host=
__cdash_session=
__cdash_password_index=

# poor man's CDash web client
cdash_set_host() {
    __cdash_session_host="$1"
}

cdash_session() {
    local login
    local pass
    local result
    local counter

    login="$1" ; shift

    __cdash_session="$( web_make_session )"

    result=1
    counter=-1

    while [ -n "$*" ] ; do
        counter="$(( counter + 1 ))"
        pass="$1" ; shift

        web_post        "${__cdash_session}"               \
                        "${__cdash_session_host}/user.php" \
                login="$login"                             \
                passwd="$pass"                             \
                sent='Login >>'                            \
            | grep 'Wrong email or password'               \
            | ( read X ; debug "|$X|" ; [ -z "$X" ] )

        if [ "$?" '=' '0' ] ; then
            __cdash_password_index="$counter"
            on_exit __cdash_logout
            return
        fi
    done

    __cdash_password_index="-1"
    return $result
}

cdash_password_index() {
    [ "${__cdash_password_index}" '=' "$1" ]
    return $?
}

__cdash_logout() {
    web_get     "${__cdash_session}"               \
                "${__cdash_session_host}/user.php" \
            logout=1 &> /dev/null
    return $?
}

cdash_upgrade() {
    cd /home/kitware/cdash && php artisan migrate --force
}

cdash_install() {
    cdash_upgrade

    local admin_email
    local admin_pass

    admin_email="$1" ; shift
    admin_pass="$1" ; shift

    cd /home/kitware/cdash && php artisan migrate --force
    php artisan user:save --email=$admin_email --password=$admin_pass --firstname=admin --lastname=user --institution=CDash --admin=1

    return $?
}

fi # __bash_lib_cdash
