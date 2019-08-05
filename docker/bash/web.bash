if [ -z "$__bash_lib_web" ] ; then
__bash_lib_web=1


source "$BASH_LIB/tmp_dir.bash"


__sessions_dir=
__sessions_counter=0

# poor man's web client (using curl)
web_make_session() {
    local result
    tmp_dir __sessions_dir
    mkdir -p "$__sessions_dir/sessions"
    until mkdir "$result" 2> /dev/null ; do
        result="$__sessions_dir/sessions/$__sessions_counter"
        __sessions_counter="$(( __sessions_counter + 1 ))"
    done
    echo "$result"
}

web_ajax() {
    local method
    local session
    local url
    local curl_args
    local arg

    method="$1" ; shift
    session="$1" ; shift
    url="$1" ; shift

    if [ "$method" '=' 'POST' ] ; then
        for arg in "$@" ; do
            curl_args="$curl_args --form '$arg'"
        done
    fi

    local oldcookies
    local newcookies

    oldcookies="$session/cookies.txt"
    newcookies="$session/cookies.tmp"

    if [ "$session" '!=' '-' ] ; then
        if [ -f "$oldcookies" ] ; then
            curl_args="$curl_args --cookie '$oldcookies'"
        fi
        curl_args="$curl_args --cookie-jar '$newcookies'"
    fi

    curl_args="$curl_args '${url}"

    if [ "$method" '=' 'GET' ] ; then
        arg="$1" ; shift
        if [ -n "$arg" ] ; then
            curl_args="${curl_args}?$arg"
        fi

        for arg in "$@" ; do
            curl_args="${curl_args}&$arg"
        done
    fi

    curl_args="${curl_args}'"

    eval "curl $curl_args" 2>&-

    if [ "$session" '!=' '-' ] ; then
        if [ -f "$newcookies" ] ; then
            mv "$newcookies" "$oldcookies"
        fi
    fi

    sleep 0.2
}

web_get() {
    web_ajax GET "$@"
}

web_post() {
    web_ajax POST "$@"
}


fi # __bash_lib_web
