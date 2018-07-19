#!/bin/bash

declare -a __exit_callbacks
onexit() {
    __exit_callbacks[${#__exit_callbacks[@]}]="$@"
}

do_exit() {
    if [ -z "$exit_code" ] ; then
        exit_code="$1"
        local n
        n=${#__exit_callbacks[@]}
        for ((; n--; )) ; do
            eval "${__exit_callbacks[$n]}"
        done
    fi
}

EXEC() {
    do_exit 0
    exec "$@"
}

trap "do_exit 1; exit $exit_code" INT TERM QUIT
trap "do_exit 0; exit $exit_code" EXIT

onexit 'if [ -n "$tmpdir" -a -d "$tmpdir" ] ; then rm -r "$tmpdir" ; fi'

ensure_tmp() {
    if [ -z "$tmpdir" ] ; then
        tmpdir="$( mktemp -d )"
    fi
}

# poor man's CDash client
mksession() {
    local result
    ensure_tmp
    mkdir -p "$tmpdir/sessions"
    until mkdir "$result" 2> /dev/null ; do
        result="$tmpdir/sessions/$RANDOM"
    done
    echo "$result"
}

ajax() {
    local method
    local session
    local route
    local curl_args
    local arg

    method="$1" ; shift
    session="$1" ; shift
    route="$1" ; shift

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

    local port="$PORT"
    if [ -n "$port" ] ; then
        port=":$port"
    fi

    curl_args="$curl_args 'http://localhost${port}/$route"

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

get() {
    ajax GET "$@"
}

post() {
    ajax POST "$@"
}

user_prefix="__user"
user_set() {
    email="$1" ; shift
    key="$1" ; shift
    value="$1" ; shift
    email_hash="$( echo "$email" | sha1sum | cut -d\  -f 1 )"
    eval "${user_prefix}_${email_hash}_${key}=\"${value}\""
}

user_get() {
    email="$1" ; shift
    key="$1" ; shift
    email_hash="$( echo "$email" | sha1sum | cut -d\  -f 1 )"
    eval "echo \"\$${user_prefix}_${email_hash}_${key}\""
}

DEBUG() {
    if [ -n "$DEBUG" ] ; then
        echo -n "DEBUG:: "
        echo "$@"
    fi
}

if [ -z "$CDASH_ROOT_ADMIN_PASS" ] ; then
    cat << ____EOF
error: This container requires the CDASH_ROOT_ADMIN_PASS
       environment variable to be defined.
____EOF
    exit 1
fi 1>&2

local_config_file="/var/www/cdash/config/config.local.php"

(
    echo '<?php'
    if [ '!' -z ${CDASH_CONFIG+x} ] ; then
        echo "$CDASH_CONFIG"
    fi
) > "$local_config_file"

PORT="$(( (RANDOM % 20000) + 10000 ))"
sed -i 's/^Listen [0-9][0-9]*/Listen '"$PORT"'/g' /etc/apache2/ports.conf
sed -i 's/^<VirtualHost \*:[0-9][0-9]*>/<VirtualHost \*:'"$PORT"'>/g' \
    /etc/apache2/sites-enabled/000-default.conf
echo "\$CDASH_FULL_EMAIL_WHEN_ADDING_USER = '1';" >> "$local_config_file"

/usr/sbin/apache2ctl -D FOREGROUND &
apache_pid="$!"
onexit '
if [ -n "$apache_pid" ] ; then
    /usr/sbin/apache2ctl graceful-stop
    wait
fi'

sleep 10

# ENSURE ROOT ADMIN USER
final_root_pass="$CDASH_ROOT_ADMIN_PASS"
if [ -n "$CDASH_ROOT_ADMIN_NEW_PASS" ] ; then
    final_root_pass="$CDASH_ROOT_ADMIN_NEW_PASS"
fi

post - install.php admin_email='rootadmin@docker.container' \
                   admin_password="$final_root_pass"        \
                   Submit=Install &> /dev/null

if [ -n "$CDASH_ROOT_ADMIN_NEW_PASS" ] ; then
    root_session="$( mksession )"
    post "$root_session" user.php login='rootadmin@docker.container' \
                                  passwd="$final_root_pass"          \
                                  sent='Login >>'                    \
        | grep 'Wrong email or password'                             \
        | ( read X ; DEBUG "|$X|" ; [ -z "$X" ] )

    if [ "$?" '!=' '0' -a \
         "$CDASH_ROOT_ADMIN_PASS" '!=' "$final_root_pass" ] ; then

        # login failure
        post "$root_session" user.php login='rootadmin@docker.container' \
                                      passwd="$CDASH_ROOT_ADMIN_PASS"    \
                                      sent='Login >>'                    \
            | grep 'Wrong email or password'                             \
            | ( read X ; DEBUG "|$X|" ; [ -z "$X" ] )

        if [ "$?" '=' '0' ] ; then
            post "$root_session" editUser.php      \
                oldpasswd="$CDASH_ROOT_ADMIN_PASS" \
                passwd="$final_root_pass"          \
                passwd2="$final_root_pass"         \
                updatepassword='Update Password' &> /dev/null
        else
            echo "Warning: could not log in as the root admin user:" \
                 "Wrong email or password" >&2
            root_login_failed=1
        fi
    fi
fi

if [ "$root_login_failed" '!=' '1' ] ; then
    declare -a users_list
    if [ '!' -z ${CDASH_STATIC_USERS+x} ] ; then
        ensure_tmp
        mkfifo "$tmpdir/fifo"
        eval "exec 3<>$tmpdir/fifo"
        unlink "$tmpdir/fifo"

        echo "$CDASH_STATIC_USERS" >&3
        echo EOF >&3

        oldifs="$IFS"
        IFS=$'\n'

        while read -u 3 line ; do
            processed_line="$( echo "$line" |
                     sed $'s/\t\t*/ /g' | sed 's/  */ /g' | sed 's/#.*//g')"

            if [ "$processed_line" '=' 'EOF' ] ; then
                break
            fi

            if [ -z "$( echo "$processed_line" | sed $'s/[ \t]*//g' )" ] ; then
                DEBUG "SKIPPING LINE"
                DEBUG "[$line]"
                continue
            fi

            eval "entry=($processed_line)"
            _disp=
            _email=
            _pass=
            _newpass=

            if [ "${entry[0]}" '=' 'USER'   -o \
                 "${entry[0]}" '=' 'ADMIN'  -o \
                 "${entry[0]}" '=' 'DELETE' ] ; then

                # explicit user entry line
                _disp="${entry[0]}"
                _email="${entry[1]}"
                _pass="${entry[2]}"
                _newpass="${entry[3]}"
                parsed_user=1

            elif [ "${entry[0]}" '!=' "${entry[0]/@*}" ] ; then
                # implicit user entry line
                _disp=USER
                _email="${entry[0]}"
                _pass="${entry[1]}"
                _newpass="${entry[2]}"
                parsed_user=1

            elif [ "${entry[0]}" '=' 'INFO' ] ; then
                # explicit user info line
                first="${entry[1]}"
                last="${entry[2]}"
                institution="${entry[3]}"
                parsed_user=0

            else
                # implicit user info line
                first="${entry[0]}"
                last="${entry[1]}"
                institution="${entry[2]}"
                parsed_user=0
            fi

            if [ -n "$email" ] ; then
                user_set "$email" disp "$disp"
                user_set "$email" pass "$pass"
                user_set "$email" newpass "$newpass"

                if [ "$parsed_user" '=' '0' ] ; then
                    user_set "$email" first "$first"
                    user_set "$email" last "$last"
                    user_set "$email" institution "$institution"
                fi

                if [ "$( user_get "$email" listed )" '!=' 1 ] ; then
                    users_list[${#users_list[@]}]="$email"
                    user_set "$email" listed 1
                fi
            fi

            email="$_email"
            disp="$_disp"
            pass="$_pass"
            newpass="$_newpass"

            if [ -n "$DEBUG" ] ; then
                if [ "$parsed_user" '=' '0' ] ; then
                    DEBUG "PARSED USER INFO"
                    DEBUG "  First Name:  |$first|"
                    DEBUG "  Last Name:   |$last|"
                    DEBUG "  Institution: |$institution|"
                else
                    DEBUG "PARSED USER ENTRY"
                    DEBUG "  email:        |$email|"
                    DEBUG "  disposition:  |$disp|"
                    DEBUG "  password:     |$pass|"
                    DEBUG "  new password: |$newpass|"
                fi
            fi
        done

        if [ -n "$email" ] ; then
            user_set "$email" disp "$disp"
            user_set "$email" pass "$pass"
            user_set "$email" newpass "$newpass"

            if [ "$( user_get "$email" listed )" '!=' 1 ] ; then
                users_list[${#users_list[@]}]="$email"
                user_set "$email" listed 1
            fi
        fi

        exec 3<&-
        IFS="$oldifs"
    fi

    DEBUG "BEGIN DUMP OF USER TABLE"
    if [ -n "$DEBUG" ] ; then
        for (( i=0; i < ${#users_list[@]} ; ++i )) ; do
            email="${users_list[$i]}"

            for tuple in "disp" "pass" "newpass" "first:NONE" \
                         "last:NONE" "institution:NONE" ; do
                fragment="${tuple/:*}"
                tuple="${tuple:$(( ${#fragment} + 1 ))}"
                param="$fragment"
                fragment="${tuple/:*}"
                tuple="${tuple:$(( ${#fragment} + 1 ))}"
                default="$fragment"

                value="$( user_get "$email" "$param" )"
                if [ -z "$value" -a -n "$default" ] ; then
                    value="$default"
                fi
                eval "${param}=\"$value\""
            done
            DEBUG "$i:"
            DEBUG "  $email"
            DEBUG "  disposition: $disp"
            DEBUG "  password: $pass"
            DEBUG "  new pass: $newpass"
            DEBUG "  First Name: $first"
            DEBUG "  Last Name: $last"
            DEBUG "  Institution: $institution"
        done
    fi

    for (( i=0; i < ${#users_list[@]} ; ++i )) ; do
        email="${users_list[$i]}"
        if [ "$email" '=' 'rootadmin@docker.conatiner' ] ; then
            echo 'Warning: refusing to modify the root admin account!' \
                 "Use the CDASH_ROOT_ADMIN_NEW_PASS environment variable" \
                 "to update the root account password." >&2
            continue
        fi

        if [ -z "$root_session" ] ; then
            root_session="$( mksession )"

            # LOGIN AS ROOT ADMIN USER
            post "$root_session" user.php              \
                    login='rootadmin@docker.container' \
                    passwd="$final_root_pass"          \
                    sent='Login >>'                    \
                | grep 'Wrong email or password'       \
                | ( read X ; DEBUG "|$X|" ; [ -z "$X" ] )

            if [ "$?" '!=' '0' ] ; then
                echo "Warning: could not log in as the root admin user:" \
                     "Wrong email or password" >&2
                break
            fi
        fi

        for tuple in "disp" "pass" "newpass" "first:NONE" \
                     "last:NONE" "institution:NONE" ; do
            fragment="${tuple/:*}"
            tuple="${tuple:$(( ${#fragment} + 1 ))}"
            param="$fragment"
            fragment="${tuple/:*}"
            tuple="${tuple:$(( ${#fragment} + 1 ))}"
            default="$fragment"

            value="$( user_get "$email" "$param" )"
            if [ -z "$value" -a -n "$default" ] ; then
                value="$default"
            fi
            eval "${param}=\"$value\""
        done

        ids=($(                                                    \
            get "$root_session" ajax/findusers.php search="$email" \
                | grep '<input'                                    \
                | grep 'name="userid"'                             \
                | grep 'type="hidden"'                             \
                | sed 's/..*value="\([0-9][0-9]*\)"..*/\1/g'))

        user_id="${ids[0]}"

        if [ "$disp" '=' 'DELETE' ] ; then
            # REMOVE USER
            DEBUG "REMOVING USER: $email"
            if [ -n "$user_id" ] ; then
                post "$root_session" manageUsers.php          \
                                     userid="$user_id"        \
                                     removeuser="remove user" &> /dev/null
            else
                echo "Warning: could not remove user" \
                     "$email: user not found" >&2
            fi

            continue
        fi

        final_pass="$pass"
        if [ -n "$newpass" ] ; then
            final_pass="$newpass"
        fi

        login_pass="$pass"

        if [ -z "$user_id" ] ; then
            login_pass="$final_pass"

            # CREATE USER
            DEBUG "CREATING USER: $email"
            post "$root_session" manageUsers.php             \
                                  fname="$first"             \
                                  lname="$last"              \
                                  email="$email"             \
                                  passwd="$final_pass"       \
                                  passwd2="$final_pass"      \
                                  institution="$institution" \
                                  adduser='Add user >>' &> /dev/null

            ids=($(                                                    \
                get "$root_session" ajax/findusers.php search="$email" \
                    | grep '<input'                                    \
                    | grep 'name="userid"'                             \
                    | grep 'type="hidden"'                             \
                    | sed 's/..*value="\([0-9][0-9]*\)"..*/\1/g'))

            user_id="${ids[0]}"

            if [ -z "$user_id" ] ; then
                echo "Warning: unable to create user account" \
                     "$email: unknown error" >&2
                continue
            fi
         fi

         if [ "$disp" '=' 'ADMIN' ] ; then
             DEBUG "PROMOTING USER: $email"
             post "$root_session" manageUsers.php        \
                                  userid="$user_id"      \
                                  makeadmin="make admin" &> /dev/null
         fi

         if [ "$disp" '=' 'USER' ] ; then
             DEBUG "DEMOTING USER: $email"
             post "$root_session" manageUsers.php                   \
                                  userid="$user_id"                 \
                                  makenormaluser="make normal user" \
                                  &> /dev/null
         fi

        user_session="$( mksession )"

        # LOGIN AS NORMAL USER
        login_success=0
        DEBUG "LOGGING IN AS USER: $email"
        post "$user_session" user.php login="$email"        \
                                      passwd="$login_pass"  \
                                      sent='Login >>'       \
            | grep 'Wrong email or password'                \
            | ( read X ; DEBUG "|$X|" ; [ -z "$X" ] )

        if [ "$?" '=' '0' ] ; then # login success
            login_success=1
        elif [ "$login_pass" '!=' "$newpass" ] ; then # login failure
            login_pass="$newpass"
            DEBUG "LOGGING IN (FALLBACK) AS USER: $email"
            post "$user_session" user.php login="$email"        \
                                          passwd="$login_pass"  \
                                          sent='Login >>'       \
                | grep 'Wrong email or password'                \
                | ( read X ; DEBUG "|$X|" ; [ -z "$X" ] )

            if [ "$?" '=' '0' ] ; then
                login_success=1
            fi
        fi

        if [ "$login_success" '=' '0' ] ; then
            echo "Warning: could not log in as user" \
                 "$email: Wrong email or password" >&2
            continue
        fi

        DEBUG "UPDATING USER PROFILE: $email"
        post "$user_session" editUser.php fname="$first"                 \
                                          lname="$last"                  \
                                          email="$email"                 \
                                          institution="$institution"     \
                                          updateprofile='Update Profile' \
                                          &> /dev/null

        if [ -n "$newpass" -a "$login_pass" '!=' "$newpass" ] ; then
            # update user's password
            DEBUG "UPDATING USER PASSWORD: $email"
            post "$user_session" editUser.php    \
                oldpasswd="$login_pass"          \
                passwd="$newpass"                \
                passwd2="$newpass"               \
                updatepassword='Update Password' &> /dev/null
        fi

        get "$user_session" user.php logout=1 &> /dev/null
    done

    get "$root_session" user.php logout=1 &> /dev/null
fi

/usr/sbin/apache2ctl graceful-stop
unset apache_pid
wait
sleep 10

sed -i 's/^Listen [0-9][0-9]*/Listen 80/g' /etc/apache2/ports.conf
sed -i 's/^<VirtualHost \*:[0-9][0-9]*>/<VirtualHost \*:80>/g' \
    /etc/apache2/sites-enabled/000-default.conf
tmp="$( mktemp )"
head -n -1 "$local_config_file" > "$tmp"
cat "$tmp" > "$local_config_file"
rm "$tmp"

EXEC /usr/sbin/apache2ctl -D FOREGROUND
