if [ -z "$__bash_lib_debug" ] ; then
__bash_lib_debug=1


debug() {
    if [ -z "$*" ] ; then
        [ -n "$DEBUG" ]
        return $?
    fi

    if debug ; then
        echo -n "DEBUG:: "
        echo "$@"
    fi
}


fi # __bash_lib_debug
