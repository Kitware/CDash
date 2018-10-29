#!/bin/bash

export BASH_LIB=/bash-lib
source "$BASH_LIB/cdash.bash"
source "$BASH_LIB/debug.bash"
source "$BASH_LIB/misc.bash"
source "$BASH_LIB/on_exit.bash"
source "$BASH_LIB/tmp_dir.bash"
source "$BASH_LIB/web.bash"

do_configure() {
    local_service_setup
    (
        cdash_session "$CDASH_ROOT_ADMIN_EMAIL" \
                "$CDASH_ROOT_ADMIN_PASS"        \
                "$CDASH_ROOT_ADMIN_NEW_PASS"

        if [ "$?" '!=' '0' ] ; then
            echo "Warning: could not log in as the root admin user:" \
                 "Wrong email or password" >&2
            return 1
        fi

        if cdash_password_index 1 ; then
            cdash_change_password            \
                "$CDASH_ROOT_ADMIN_PASS"     \
                "$CDASH_ROOT_ADMIN_NEW_PASS"
        fi

        declare -a users_list
        if [ '!' -z ${CDASH_STATIC_USERS+x} ] ; then
            tmp_dir tmpdir
            mkfifo "$tmpdir/fifo"
            eval "exec 3<>$tmpdir/fifo"
            unlink "$tmpdir/fifo"

            echo "$CDASH_STATIC_USERS" >&3
            echo EOF >&3

            oldifs="$IFS"
            IFS=$'\n'

            while read -u 3 line ; do
                processed_line="$( echo "$line" |
                         sed $'s/\t\t*/ /g' |
                         sed 's/  */ /g' | sed 's/#.*//g')"

                if [ "$processed_line" '=' 'EOF' ] ; then
                    break
                fi

                if [ -z "$( echo "$processed_line" | sed $'s/[ \t]*//g' )" ]
                then
                    debug "SKIPPING LINE"
                    debug "[$line]"
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

                if debug ; then
                    if [ "$parsed_user" '=' '0' ] ; then
                        debug "PARSED USER INFO"
                        debug "  First Name:  |$first|"
                        debug "  Last Name:   |$last|"
                        debug "  Institution: |$institution|"
                    else
                        debug "PARSED USER ENTRY"
                        debug "  email:        |$email|"
                        debug "  disposition:  |$disp|"
                        debug "  password:     |$pass|"
                        debug "  new password: |$newpass|"
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

        debug "BEGIN DUMP OF USER TABLE"
        if debug ; then
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
                debug "$i:"
                debug "  $email"
                debug "  disposition: $disp"
                debug "  password: $pass"
                debug "  new pass: $newpass"
                debug "  First Name: $first"
                debug "  Last Name: $last"
                debug "  Institution: $institution"
            done
        fi

        for (( i=0; i < ${#users_list[@]} ; ++i )) ; do
            email="${users_list[$i]}"
            if [ "$email" '=' 'root' ] ; then
                echo 'Warning: refusing to modify the root admin account!' \
                     "Use the CDASH_ROOT_ADMIN_NEW_PASS environment variable" \
                     "to update the root account password." >&2
                continue
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

            user_id="$( cdash_find_user "$email" )"

            if [ "$disp" '=' 'DELETE' ] ; then
                # REMOVE USER
                debug "REMOVING USER: $email"
                if [ -n "$user_id" ] ; then
                    cdash_remove_user "$user_id"
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
                debug "CREATING USER: $email"
                user_id="$(                       \
                    cdash_add_user                \
                        "$first" "$last" "$email" \
                        "$final_pass" "$institution" )"

                if [ -z "$user_id" ] ; then
                    echo "Warning: unable to create user account" \
                         "$email: unknown error" >&2
                    continue
                fi
            fi

            if [ "$disp" '=' 'ADMIN' ] ; then
                debug "PROMOTING USER: $email"
                cdash_promote_user "$user_id"
            fi

            if [ "$disp" '=' 'USER' ] ; then
                debug "DEMOTING USER: $email"
                cdash_demote_user "$user_id"
            fi

            (
                # LOGIN AS NORMAL USER
                debug "LOGGING IN AS USER: $email"
                cdash_session "$email" "$login_pass" "$newpass"
                login_success=$?

                if [ "$login_success" '!=' '0' ] ; then
                    echo "Warning: could not log in as user" \
                         "$email: Wrong email or password" >&2
                    continue
                fi

                debug "UPDATING USER PROFILE: $email"
                cdash_update_profile "$first" "$last" "$email" "$institution"

                if cdash_password_index 1 ; then
                    # update user's password
                    debug "UPDATING USER PASSWORD: $email"
                    cdash_change_password "$login_pass" "$newpass"
                fi
            )
        done
    )
}

do_install() {
    local_service_setup

    # ENSURE ROOT ADMIN USER
    root_pass="$CDASH_ROOT_ADMIN_PASS"
    if [ -n "$CDASH_ROOT_ADMIN_NEW_PASS" ] ; then
        root_pass="$CDASH_ROOT_ADMIN_NEW_PASS"
    fi

    session="$( web_make_session )"
    cdash_install "$session" "$CDASH_ROOT_ADMIN_EMAIL" \
        "$root_pass"
}

do_install=0
do_upgrade=0
do_configure=0
do_fix_build_groups=0
do_check_builds_for_wrong_date=0
do_delete_builds_with_wrong_date=0
do_compute_timing=0
do_update_stats=0
do_compress=0
do_cleanup=0
do_serve=0

timing_days=4
stat_days=4

local_service_needed=0

args_provided=0

while [ -n "$*" ] ; do
    args_provided=1

    case "$1" in
        install)
            do_install=1
            local_service_needed=1
            ;;
        upgrade)
            do_upgrade=1
            local_service_needed=1
            ;;
        configure)
            do_configure=1
            local_service_needed=1
            ;;
        fix-build-groups)
            do_fix_build_groups=1
            local_service_needed=1
            ;;
        check-builds-for-wrong-date)
            do_check_builds_for_wrong_date=1
            local_service_needed=1
            ;;
        delete-builds-with-wrong-date)
            do_delete_builds_with_wrong_date=1
            local_service_needed=1
            ;;
        compute-timing*)
            frag="$1"
            frag="${frag:14}"
            if [ -z "$frag" ] ||
               [ "${frag::1}" '=' ':' -a -n "${frag:2}" ]
            then
                timing_days=4
                if [ -n "$frag" ] ; then
                    timing_days="${frag:2}"
                fi

                do_compute_timing=1
                local_service_needed=1
            fi
            ;;
        update-stats*)
            frag="$1"
            frag="${frag:12}"
            if [ -z "$frag" ] ||
               [ "${frag::1}" '=' ':' -a -n "${frag:2}" ]
            then
                stat_days=4
                if [ -n "$frag" ] ; then
                    stat_days="${frag:2}"
                fi

                do_update_stats=1
                local_service_needed=1
            fi
            ;;
        compress)
            do_compress=1
            local_service_needed=1
            ;;
        cleanup)
            do_cleanup=1
            local_service_needed=1
            ;;
        serve)
            do_serve=1
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

setup_local_config

if [ -z "$CDASH_ROOT_ADMIN_EMAIL" ] ; then
    CDASH_ROOT_ADMIN_EMAIL="root@docker.container"
fi

if [ "$local_service_needed" '=' '1' ] ; then
    local_service_setup
fi

if [ "$do_install" '=' '1' ] ; then
    do_install
fi

if [ "$local_service_needed" '=' '1' ] ; then
    cdash_session "$CDASH_ROOT_ADMIN_EMAIL" \
            "$CDASH_ROOT_ADMIN_PASS"        \
            "$CDASH_ROOT_ADMIN_NEW_PASS"
fi

if [ "$do_upgrade" '=' '1' ] ; then
    cdash_upgrade
fi

if [ "$do_configure" '=' '1' ] ; then
    do_configure
fi

if [ "$do_fix_build_groups" '=' '1' ] ; then
    cdash_fix_build_groups
fi

if [ "$do_check_builds_for_wrong_date" '=' '1' ] ; then
    cdash_check_builds_with_wrong_date
fi

if [ "$do_delete_builds_with_wrong_date" '=' '1' ] ; then
    cdash_delete_builds_with_wrong_date
fi

if [ "$do_compute_timing" '=' '1' ] ; then
    cdash_compute_test_timing "$timing_days"
fi

if [ "$do_update_stats" '=' '1' ] ; then
    cdash_update_statistics "$stat_days"
fi

if [ "$do_compress" '=' '1' ] ; then
    cdash_compress_test_output
fi

if [ "$do_cleanup" '=' '1' ] ; then
    cdash_cleanup_database
fi

if [ "$local_service_needed" '=' '1' ] ; then
    local_service_teardown
fi

if [ "$do_serve" '!=' '1' ] ; then
    exit
fi

# serve
exec /usr/sbin/apache2ctl -D FOREGROUND
