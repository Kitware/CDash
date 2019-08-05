if [ -z "$__bash_lib_tmp_dir" ] ; then
__bash_lib_tmp_dir=1


source "$BASH_LIB/on_exit.bash"


declare -a __tmp_dirs

__clean_tmp_dirs() {
    local n
    n=${#__tmp_dirs[@]}
    for ((; n--; )) ; do
        rm -rf "${__tmp_dirs[$n]}"
    done
}

tmp_dir() {
    local n
    n=${#__tmp_dirs[@]}

    if [ "$n" '=' '0' ] ; then
        on_exit __clean_tmp_dirs
    fi

    var="$1" ; shift

    if [ -n "$var" -a -n "${!var}" ] ; then
        return
    fi

    tmpdir="$( mktemp -d )"
    __tmp_dirs[${#tmp_dirs[@]}]="$tmpdir"

    if [ -n "$var" ] ; then
        eval "$var=\"$tmpdir\""
    else
        echo "$tmpdir"
    fi
}


fi # __bash_lib_tmp_dir
