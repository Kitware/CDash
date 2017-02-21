<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

function setRememberMeCookie($userId)
{
    $cookiename = 'CDash-' . $_SERVER['SERVER_NAME'];
    $time = time() + 60 * 60 * 24 * 30; // 30 days;

    // Create a new password
    $keychars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $length = 32;

    $key = '';
    $max = strlen($keychars) - 1;
    for ($i = 0; $i <= $length; $i++) {
        // random_int is available in PHP 7 and the random_compat PHP 5.x
        // polyfill included in the Composer package.json dependencies.
        $key .= substr($keychars, random_int(0, $max), 1);
    }

    // Update the user key
    if (pdo_query('UPDATE ' . qid('user') . " SET cookiekey='" . $key . "' WHERE id=" . qnum($userId)) !== false) {
        setcookie($cookiename, $userId . $key, $time);
    }
}

/** Database authentication */
function databaseAuthenticate($email, $password, $SessionCachePolicy, $rememberme)
{
    global $loginerror;
    $loginerror = '';

    include dirname(__DIR__) . '/config/config.php';

    $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
    pdo_select_db("$CDASH_DB_NAME", $db);
    $sql = 'SELECT id,password FROM ' . qid('user') . " WHERE email='" . pdo_real_escape_string($email) . "'";
    $result = pdo_query("$sql");

    if (pdo_num_rows($result) == 0) {
        pdo_free_result($result);
        $loginerror = 'Wrong email or password.';
        return false;
    }

    $user_array = pdo_fetch_array($result);
    $pass = $user_array['password'];

    // Check if the account is locked out.
    if (accountIsLocked($user_array['id'])) {
        return false;
    }

    // External authentication
    if ($password === null && isset($CDASH_EXTERNAL_AUTH) && $CDASH_EXTERNAL_AUTH) {
        // create the session array
        $sessionArray = array('login' => $login, 'password' => 'this is not a valid password', 'passwd' => $user_array['password'], 'ID' => session_id(), 'valid' => 1, 'loginid' => $user_array['id']);
        $_SESSION['cdash'] = $sessionArray;
        pdo_free_result($result);
        return true;                               // authentication succeeded
    } elseif (md5($password) == $pass) {
        // Authentication is successful.
        if ($rememberme) {
            setRememberMeCookie($user_array['id']);
        }

        session_name('CDash');
        session_cache_limiter($SessionCachePolicy);
        session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
        @ini_set('session.gc_maxlifetime', $CDASH_COOKIE_EXPIRATION_TIME + 600);
        session_start();

        // create the session array
        if (isset($_SESSION['cdash']['password'])) {
            $password = $_SESSION['cdash']['password'];
        }
        $sessionArray = array('login' => $email, 'passwd' => $pass, 'ID' => session_id(), 'valid' => 1, 'loginid' => $user_array['id']);
        $_SESSION['cdash'] = $sessionArray;

        checkForExpiredPassword();
        clearUnsuccessfulAttempts($user_array['id']);
        return true;
    }

    incrementUnsuccessfulAttempts($user_array['id']);
    $loginerror = 'Wrong email or password.';
    return false;
}

/** LDAP authentication */
function ldapAuthenticate($email, $password, $SessionCachePolicy, $rememberme)
{
    global $loginerror;
    $loginerror = '';

    include dirname(__DIR__) . '/config/config.php';
    include_once 'models/user.php';

    $ldap = ldap_connect($CDASH_LDAP_HOSTNAME);
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, $CDASH_LDAP_PROTOCOL_VERSION);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, $CDASH_LDAP_OPT_REFERRALS);
    // Bind as the LDAP user if authenticated ldap is enabled
    if ($CDASH_LDAP_AUTHENTICATED) {
        ldap_bind($ldap, $CDASH_LDAP_BIND_DN, $CDASH_LDAP_BIND_PASSWORD);
    }

    if (isset($ldap) && $ldap != '') {
        /* search for pid dn */
        $result = ldap_search($ldap, $CDASH_LDAP_BASEDN,
            '(&(mail=' . $email . ')' . $CDASH_LDAP_FILTER . ')', array('dn', 'cn'));
        if ($result != 0) {
            $entries = ldap_get_entries($ldap, $result);
            @$principal = $entries[0]['dn'];
            if (isset($principal)) {
                // bind as this user
                if (@ldap_bind($ldap, $principal, $password) and strlen(trim($password)) != 0) {
                    $sql = 'SELECT id,password FROM ' . qid('user') . " WHERE email='" . pdo_real_escape_string($email) . "'";
                    $result = pdo_query("$sql");

                    // If the user doesn't exist we add it
                    if (pdo_num_rows($result) == 0) {
                        @$givenname = $entries[0]['cn'][0];
                        if (!isset($givenname)) {
                            $loginerror = 'No givenname (cn) set in LDAP, cannot register user into CDash';
                            return false;
                        }
                        $names = explode(' ', $givenname);

                        $User = new User;

                        if (count($names) > 1) {
                            $User->FirstName = $names[0];
                            $User->LastName = $names[1];
                            for ($i = 2; $i < count($names); $i++) {
                                $User->LastName .= ' ' . $names[$i];
                            }
                        } else {
                            $User->LastName = $names[0];
                        }

                        // Add the user in the database
                        $storedPassword = md5($password);
                        $User->Email = $email;
                        $User->Password = $storedPassword;
                        $User->Save();
                        $userid = $User->Id;
                    } else {
                        $user_array = pdo_fetch_array($result);
                        $storedPassword = $user_array['password'];
                        $userid = $user_array['id'];

                        // If the password has changed we update
                        if ($storedPassword != md5($password)) {
                            $User = new User;
                            $User->Id = $userid;
                            $User->SetPassword(md5($password));
                        }
                    }

                    if ($rememberme) {
                        $cookiename = 'CDash-' . $_SERVER['SERVER_NAME'];
                        $time = time() + 60 * 60 * 24 * 30; // 30 days;

                        // Create a new password
                        $keychars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                        $length = 32;

                        $key = '';
                        $max = strlen($keychars) - 1;
                        for ($i = 0; $i <= $length; $i++) {
                            // random_int is available in PHP 7 and the random_compat PHP 5.x
                            // polyfill included in the Composer package.json dependencies.
                            $key .= substr($keychars, random_int(0, $max), 1);
                        }

                        $value = $userid . $key;
                        setcookie($cookiename, $value, $time);

                        // Update the user key
                        pdo_query('UPDATE ' . qid('user') . " SET cookiekey='" . $key . "' WHERE id=" . qnum($userid));
                    }

                    session_name('CDash');
                    session_cache_limiter($SessionCachePolicy);
                    session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
                    @ini_set('session.gc_maxlifetime', $CDASH_COOKIE_EXPIRATION_TIME + 600);
                    session_start();

                    // create the session array
                    if (isset($_SESSION['cdash']['password'])) {
                        $password = $_SESSION['cdash']['password'];
                    }
                    $sessionArray = array('login' => $email, 'passwd' => $storedPassword, 'ID' => session_id(), 'valid' => 1, 'loginid' => $userid);
                    $_SESSION['cdash'] = $sessionArray;
                    return true;
                } else {
                    $loginerror = 'Wrong email or password.';
                    return false;
                }
            } else {
                $loginerror = 'User not found in LDAP';
            }
            ldap_free_result($result);
        } else {
            $loginerror = 'Error occured searching the LDAP';
        }
        ldap_close($ldap);
    } else {
        $loginerror = 'Could not connect to LDAP at ' . $CDASH_LDAP_HOSTNAME;
    }
    return false;
}

/** authentication */
function authenticate($email, $password, $SessionCachePolicy, $rememberme)
{
    if (empty($email)) {
        return 0;
    }
    include dirname(__DIR__) . '/config/config.php';

    if ($CDASH_USE_LDAP) {
        // If the user is '1' we use it to login
        $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
        pdo_select_db("$CDASH_DB_NAME", $db);
        $query = pdo_query('SELECT id FROM ' . qid('user') . " WHERE email='$email'");
        if ($query && pdo_num_rows($query) > 0) {
            $user_array = pdo_fetch_array($query);
            if ($user_array['id'] == 1) {
                return databaseAuthenticate($email, $password, $SessionCachePolicy, $rememberme);
            }
        }
        return ldapAuthenticate($email, $password, $SessionCachePolicy, $rememberme);
    } else {
        return databaseAuthenticate($email, $password, $SessionCachePolicy, $rememberme);
    }
}

/**
 * Authentication function
 * This is called on every page load where common.php is selected, as well as when
 * submitting the login form.
 **/
function auth($SessionCachePolicy = 'private_no_expire')
{
    include dirname(__DIR__) . '/config/config.php';
    $loginid = 1231564132;

    if (isset($CDASH_EXTERNAL_AUTH) && $CDASH_EXTERNAL_AUTH
        && isset($_SERVER['REMOTE_USER'])
    ) {
        $login = $_SERVER['REMOTE_USER'];
        return authenticate($login, null, $SessionCachePolicy, 0); // we don't remember
    }

    if (@$_GET['logout']) {                             // user requested logout
        session_name('CDash');
        session_cache_limiter('nocache');
        @session_start();
        unset($_SESSION['cdash']);
        session_destroy();

        // Remove the cookie if we have one
        $cookienames = array('CDash', str_replace('.', '_', 'CDash-' . $_SERVER['SERVER_NAME'])); // php doesn't like dot in cookie names
        foreach ($cookienames as $cookiename) {
            if (isset($_COOKIE[$cookiename])) {
                $cookievalue = $_COOKIE[$cookiename];
                $cookieuseridkey = substr($cookievalue, 0, strlen($cookievalue) - 33);
                $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
                pdo_select_db("$CDASH_DB_NAME", $db);

                pdo_query('UPDATE ' . qid('user') . " SET cookiekey='' WHERE id=" . qnum($cookieuseridkey));
                setcookie('CDash-' . $_SERVER['SERVER_NAME'], '', time() - 3600);
            }
        }
        echo "<script language=\"javascript\">window.location='index.php'</script>";
        return 0;
    }

    if (isset($_POST['sent'])) {
        // arrive from login form

        @$login = $_POST['login'];
        if ($login != null) {
            $login = htmlspecialchars(pdo_real_escape_string($login));
        }

        @$passwd = $_POST['passwd'];
        if ($passwd != null) {
            $passwd = htmlspecialchars(pdo_real_escape_string($passwd));
        }

        return authenticate($login, $passwd, $SessionCachePolicy, isset($_POST['rememberme']));
    } else {                                         // arrive from session var
        $cookiename = str_replace('.', '_', 'CDash-' . $_SERVER['SERVER_NAME']); // php doesn't like dot in cookie names
        if (isset($_COOKIE[$cookiename])) {
            $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
            pdo_select_db("$CDASH_DB_NAME", $db);

            $cookievalue = $_COOKIE[$cookiename];
            $cookiekey = substr($cookievalue, strlen($cookievalue) - 33);
            if (strlen($cookiekey) < 1) {
                return false;
            }
            $cookieuseridkey = substr($cookievalue, 0, strlen($cookievalue) - 33);
            $sql =
                'SELECT email,password,id FROM ' . qid('user') . "
                WHERE cookiekey='" . pdo_real_escape_string($cookiekey) . "'";
            if (!empty($cookieuseridkey)) {
                $sql .= " AND id='" . pdo_real_escape_string($cookieuseridkey) . "'";
            }
            $result = pdo_query("$sql");
            if (pdo_num_rows($result) == 1) {
                $user_array = pdo_fetch_array($result);
                session_name('CDash');
                session_cache_limiter($SessionCachePolicy);
                session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
                @ini_set('session.gc_maxlifetime', $CDASH_COOKIE_EXPIRATION_TIME + 600);
                session_start();

                $sessionArray = array('login' => $user_array['email'], 'passwd' => $user_array['password'], 'ID' => session_id(), 'valid' => 1, 'loginid' => $user_array['id']);
                $_SESSION['cdash'] = $sessionArray;
                return true;
            }
        }

        // Return early if a session has already been started.
        if (session_status() != PHP_SESSION_NONE) {
            return;
        }

        session_name('CDash');
        session_cache_limiter($SessionCachePolicy);
        session_set_cookie_params($CDASH_COOKIE_EXPIRATION_TIME);
        @ini_set('session.gc_maxlifetime', $CDASH_COOKIE_EXPIRATION_TIME + 600);
        session_start();

        $email = @$_SESSION['cdash']['login'];

        if (!empty($email)) {
            $db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN", "$CDASH_DB_PASS");
            pdo_select_db("$CDASH_DB_NAME", $db);
            $sql = 'SELECT id,password FROM ' . qid('user') . " WHERE email='" . pdo_real_escape_string($email) . "'";
            $result = pdo_query("$sql");

            if (pdo_num_rows($result) == 0) {
                pdo_free_result($result);
                $loginerror = 'Wrong email or password.';
                return false;
            }

            $user_array = pdo_fetch_array($result);
            if ($user_array['password'] == $_SESSION['cdash']['passwd']) {
                return true;
            }
            $loginerror = 'Wrong email or password.';
            return false;
        }
    }
}

/** Login Form function */
function LoginForm($loginerror)
{
    include dirname(__DIR__) . '/config/config.php';
    require_once 'include/pdo.php';
    include_once 'include/common.php';
    include 'include/version.php';

    $xml = begin_XML_for_XSLT();
    $xml .= '<title>Login</title>';
    if (isset($CDASH_NO_REGISTRATION) && $CDASH_NO_REGISTRATION == 1) {
        $xml .= add_XML_value('noregister', '1');
    }
    if (@$_GET['note'] == 'register') {
        $xml .= '<message>Registration Complete. Please login with your email and password.</message>';
    }

    if ($loginerror != '') {
        $xml .= '<message>' . $loginerror . '</message>';
    }

    if ($CDASH_ALLOW_LOGIN_COOKIE) {
        $xml .= '<allowlogincookie>1</allowlogincookie>';
    }

    if ($GOOGLE_CLIENT_ID != '' && $GOOGLE_CLIENT_SECRET != '') {
        $xml .= '<oauth2>';
        $xml .= add_XML_value('client', $GOOGLE_CLIENT_ID);
        // Google OAuth needs to know the base url to redirect back to
        $xml .= add_XML_value('CDASH_BASE_URL', get_server_URI());
        $xml .= '</oauth2>';
    }

    $xml .= '</cdash>';

    if (!isset($NoXSLGenerate)) {
        generate_XSLT($xml, 'login');
    }
}

/** Compute a complexity score for a potential password */
function getPasswordComplexity($password)
{
    global $CDASH_PASSWORD_COMPLEXITY_COUNT;
    $complexity = 0;
    $matches = array();

    // Uppercase letters
    $num_uppercase = preg_match_all('/[A-Z]/', $password, $matches);
    if ($num_uppercase >= $CDASH_PASSWORD_COMPLEXITY_COUNT) {
        $complexity++;
    }

    // Lowercase letters
    $num_lowercase = preg_match_all('/[a-z]/', $password, $matches);
    if ($num_lowercase >= $CDASH_PASSWORD_COMPLEXITY_COUNT) {
        $complexity++;
    }

    // Numbers
    $num_numbers = preg_match_all('/[0-9]/', $password, $matches);
    if ($num_numbers >= $CDASH_PASSWORD_COMPLEXITY_COUNT) {
        $complexity++;
    }

    // Symbols
    $num_symbols = preg_match_all("/\W/", $password, $matches);
    // Underscore is not matched by \W but we consider it a symbol.
    $num_symbols += substr_count($password, '_');
    if ($num_symbols >= $CDASH_PASSWORD_COMPLEXITY_COUNT) {
        $complexity++;
    }
    return $complexity;
}

/** Sets a session variable forcing the redirect if the user needs
 *  to change their password.
 */
function checkForExpiredPassword()
{
    global $CDASH_PASSWORD_EXPIRATION;
    if ($CDASH_PASSWORD_EXPIRATION < 1) {
        return false;
    }

    if (!isset($_SESSION['cdash']) || !array_key_exists('loginid', $_SESSION['cdash'])) {
        return false;
    }

    unset($_SESSION['cdash']['redirect']);
    $uri = get_server_URI(false);
    $uri .= '/editUser.php?reason=expired';

    $userid = $_SESSION['cdash']['loginid'];
    $result = pdo_query("
            SELECT date FROM password
            WHERE userid=$userid ORDER BY date DESC LIMIT 1");
    if (pdo_num_rows($result) < 1) {
        // If no result, then password rotation must have been enabled
        // after this user set their password.  Force them to change it now.
        $_SESSION['cdash']['redirect'] = $uri;
        return true;
    }

    $row = pdo_fetch_array($result);
    $password_created_time = strtotime($row['date']);
    $password_expiration_time =
        strtotime("+$CDASH_PASSWORD_EXPIRATION days", $password_created_time);
    if (time() > $password_expiration_time) {
        $_SESSION['cdash']['redirect'] = $uri;
        return true;
    }
    return false;
}

/** Set the number of unsuccessful login attempts to 0 for a given user.
  * Does nothing unless account lockout functionality is enabled.
  */
function clearUnsuccessfulAttempts($userid)
{
    global $CDASH_LOCKOUT_ATTEMPTS;
    if ($CDASH_LOCKOUT_ATTEMPTS == 0) {
        return;
    }
    $pdo = get_link_identifier()->getPdo();
    createLockoutRow($userid, $pdo);
    $stmt = $pdo->prepare(
        'UPDATE lockout SET failedattempts = 0 WHERE userid=?');
    pdo_execute($stmt, [$userid]);
}

/** Increment the number of unsuccessful login attempts for a given user,
  * marking the account as locked if it exceeds our limit.
  * Does nothing unless account lockout functionality is enabled.
  */
function incrementUnsuccessfulAttempts($userid)
{
    global $CDASH_LOCKOUT_ATTEMPTS, $CDASH_LOCKOUT_LENGTH;
    if ($CDASH_LOCKOUT_ATTEMPTS == 0) {
        return;
    }
    $pdo = get_link_identifier()->getPdo();
    createLockoutRow($userid, $pdo);

    $pdo->beginTransaction();

    // Add one to the current number of failed attempts.
    $stmt = $pdo->prepare('SELECT failedattempts FROM lockout WHERE userid=?');
    if (!pdo_execute($stmt, [$userid])) {
        $pdo->rollBack();
        return;
    }
    $row = $stmt->fetch();
    $failedattempts = $row['failedattempts'] + 1;

    if ($failedattempts >= $CDASH_LOCKOUT_ATTEMPTS) {
        // Lock the account if it exceeds our number of failed attempts.
        $unlocktime = gmdate(FMT_DATETIME, time() + $CDASH_LOCKOUT_LENGTH * 60);
        $stmt = $pdo->prepare(
                'UPDATE lockout SET failedattempts = :failedattempts,
                islocked = 1, unlocktime=:unlocktime
                WHERE userid=:userid');
        $stmt->bindParam(':unlocktime', $unlocktime);
    } else {
        // Otherwise just increment the number of failed attempts.
        $stmt = $pdo->prepare(
                'UPDATE lockout SET failedattempts = :failedattempts
                WHERE userid=:userid');
    }

    $stmt->bindParam(':userid', $userid);
    $stmt->bindParam(':failedattempts', $failedattempts);
    if (!pdo_execute($stmt)) {
        $pdo->rollBack();
        return;
    }
    $pdo->commit();
}

/** Create a row in the lockout table for the given user if one does not
  * exist already.
  */
function createLockoutRow($userid, $pdo)
{
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT userid FROM lockout WHERE userid=?');
    if (!pdo_execute($stmt, [$userid])) {
        $pdo->rollBack();
        return;
    }
    if ($stmt->rowCount() === 0) {
        $stmt = $pdo->prepare('INSERT INTO lockout (userid) VALUES (?)');
        if (!pdo_execute($stmt, [$userid])) {
            $pdo->rollBack();
            return;
        }
    }
    $pdo->commit();
}

/** Returns true and sets loginerror if the user's account is locked out.
  * Unlocks the account if the expiration time has already passed.
  */
function accountIsLocked($userid)
{
    global $CDASH_LOCKOUT_ATTEMPTS;
    if ($CDASH_LOCKOUT_ATTEMPTS == 0) {
        return false;
    }

    global $loginerror;
    $loginerror = '';

    $pdo = get_link_identifier()->getPdo();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT islocked, unlocktime FROM lockout WHERE userid=?');
    if (!pdo_execute($stmt, [$userid])) {
        $pdo->rollBack();
        return false;
    }
    $row = $stmt->fetch();
    if ($row['islocked'] == 1) {
        $now = strtotime(gmdate(FMT_DATETIME));
        $unlocktime = strtotime($row['unlocktime']);
        if ($now > $unlocktime) {
            // Lockout period has expired.
            $stmt = $pdo->prepare(
                    "UPDATE lockout SET failedattempts = 0,
                    islocked = 0, unlocktime = '1980-01-01 00:00:00'
                    WHERE userid=?");
            if (!pdo_execute($stmt, [$userid])) {
                $pdo->rollBack();
                return false;
            }
        } else {
            // Account still locked.
            $num_minutes = round(($unlocktime - $now) / 60, 0) + 1;
            $loginerror = "Your account is locked due to failed login attempts.  Please try again in $num_minutes minutes.";
            $pdo->commit();
            return true;
        }
    }
    $pdo->commit();
    return false;
}
