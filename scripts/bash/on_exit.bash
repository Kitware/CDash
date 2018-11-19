if [ -z "$__bash_lib_on_exit" ] ; then
__bash_lib_on_exit=1


declare -a __exit_callbacks

on_exit() {
    __exit_callbacks[${#__exit_callbacks[@]}]="$@"
}

__exit_wrapper() {
    if [ -z "$exit_code" ] ; then
        exit_code="$1"
        if [ -z "$exit_code" ] ; then
            exit_code=0
        fi
        local n
        n=${#__exit_callbacks[@]}
        for ((; n--; )) ; do
            eval "${__exit_callbacks[$n]}"
        done
    fi
}

__exec_wrapper() {
    __exit_wrapper 0
    \exec "$@"
}

trap "__exit_wrapper 1; \\exit $exit_code" INT TERM QUIT
trap "__exit_wrapper 0; \\exit $exit_code" EXIT

alias exit='__exit_wrapper ; exit'
alias exec=__exec_wrapper


fi # __bash_lib_on_exit
