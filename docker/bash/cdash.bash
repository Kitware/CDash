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

cdash_change_password() {
    local old_pass
    local new_pass

    old_pass="$1" ; shift
    new_pass="$1"

    web_post    "${__cdash_session}"                   \
                "${__cdash_session_host}/editUser.php" \
        oldpasswd="$old_pass"                          \
        passwd="$new_pass"                             \
        passwd2="$new_pass"                            \
        updatepassword='Update Password' &> /dev/null

    return $?
}

cdash_update_profile() {
    local first_name
    local last_name
    local email
    local institution

    first_name="$1" ; shift
    last_name="$1" ; shift
    email="$1" ; shift
    institution="$1"

    web_post    "${__cdash_session}"                   \
                "${__cdash_session_host}/editUser.php" \
        fname="$first_name"                            \
        lname="$last_name"                             \
        email="$email"                                 \
        institution="$institution"                     \
        updateprofile='Update Profile' &> /dev/null

    return $?
}

cdash_remove_user() {
    local user_id

    user_id="$1" ; shift

    web_post    "${__cdash_session}"                      \
                "${__cdash_session_host}/manageUsers.php" \
        userid="$user_id"                                 \
        removeuser="remove user" &> /dev/null
}

cdash_find_user() {
    local email

    email="$1" ; shift

    ids=($(                                                      \
        web_get     "${__cdash_session}"                         \
                    "${__cdash_session_host}/ajax/findusers.php" \
            search="$email"                                      \
        | grep '<input'                                          \
        | grep 'name="userid"'                                   \
        | grep 'type="hidden"'                                   \
        | sed 's/..*value="\([0-9][0-9]*\)"..*/\1/g'))

    echo "${ids[0]}"
    [ -n "${ids[0]}" ]
    return $?
}

cdash_add_user() {
    local first_name
    local last_name
    local email
    local pass
    local institution
    local ids

    first_name="$1" ; shift
    last_name="$1" ; shift
    email="$1" ; shift
    pass="$1" ; shift
    institution="$1"

    web_post    "${__cdash_session}"                      \
                "${__cdash_session_host}/manageUsers.php" \
        fname="$first_name"                               \
        lname="$last_name"                                \
        email="$email"                                    \
        passwd="$pass"                                    \
        passwd2="$pass"                                   \
        institution="$institution"                        \
        adduser='Add user >>' &> /dev/null

    cdash_find_user "$email"
    return $?
}

cdash_promote_user() {
    local user_id

    user_id="$1" ; shift

    web_post    "${__cdash_session}"                      \
                "${__cdash_session_host}/manageUsers.php" \
        userid="$user_id"                                 \
        makeadmin="make admin" &> /dev/null

    return $?
}

cdash_demote_user() {
    local user_id

    user_id="$1" ; shift

    web_post    "${__cdash_session}"                      \
                "${__cdash_session_host}/manageUsers.php" \
        userid="$user_id"                                 \
        makenormaluser="make normal user" &> /dev/null

    return $?
}

cdash_install() {
    local session
    local admin_email
    local admin_pass

    session="$1" ; shift
    admin_email="$1" ; shift
    admin_pass="$1" ; shift

    web_post    "${session}"                          \
                "${__cdash_session_host}/install.php" \
        admin_email="$admin_email"                    \
        admin_password="$admin_pass"                  \
        Submit=Install &> /dev/null

    return $?
}

cdash_upgrade() {
    web_post    "${__cdash_session}"                  \
                "${__cdash_session_host}/upgrade.php" \
        Upgrade="Upgrade CDash" &> /dev/null

    return $?
}

cdash_fix_build_groups() {
    web_post    "${__cdash_session}"                  \
                "${__cdash_session_host}/upgrade.php" \
        FixBuildBasedOnRule="Fix build groups" &> /dev/null

    return $?
}

cdash_check_builds_for_wrong_date() {
    web_post    "${__cdash_session}"                  \
                "${__cdash_session_host}/upgrade.php" \
        CheckBuildsWrongDate="Check builds" &> /dev/null

    return $?
}

cdash_delete_builds_with_wrong_date() {
    web_post    "${__cdash_session}"                  \
                "${__cdash_session_host}/upgrade.php" \
        DeleteBuildsWrongDate="Delete builds" &> /dev/null

    return $?
}

cdash_compute_test_timing() {
    local num_days

    num_days="$1" ; shift

    if [ -z "$num_days" ] ; then
        num_days=4
    fi

    web_post    "${__cdash_session}"                  \
                "${__cdash_session_host}/upgrade.php" \
        TestTimingDays="$num_days"                    \
        ComputeTestTiming="Compute test timing" &> /dev/null

    return $?
}

cdash_update_statistics() {
    local num_days

    num_days="$1" ; shift

    if [ -z "$num_days" ] ; then
        num_days=4
    fi

    web_post    "${__cdash_session}"                  \
                "${__cdash_session_host}/upgrade.php" \
        UpdateStatisticsDays="$num_days"              \
        ComputeUpdateStatistics="Compute update statistics" &> /dev/null

    return $?
}

cdash_compress_test_output() {
    web_post    "${__cdash_session}"                  \
                "${__cdash_session_host}/upgrade.php" \
        CompressTestOutput="Compress test output" &> /dev/null

    return $?
}

cdash_cleanup_database() {
    web_post    "${__cdash_session}"                  \
                "${__cdash_session_host}/upgrade.php" \
        Cleanup="Cleanup database" &> /dev/null

    return $?
}


fi # __bash_lib_cdash
