if [ -z "$__bash_lib_cdash" ] ; then
__bash_lib_cdash=1

source "$BASH_LIB/debug.bash"
source "$BASH_LIB/on_exit.bash"

cdash_upgrade() {
    cd /home/kitware/cdash && php artisan migrate --force
}

cdash_install() {
    cd /home/kitware/cdash && php artisan config:migrate

    cdash_upgrade

    local admin_email
    local admin_pass

    admin_email="$1" ; shift
    admin_pass="$1" ; shift

    php artisan user:save --email=$admin_email --password=$admin_pass --firstname=admin --lastname=user --institution=CDash --admin=1

    return $?
}

fi # __bash_lib_cdash
