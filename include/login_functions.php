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

require_once('include/common.php');
require dirname(__DIR__) . '/vendor/autoload.php';

use CDash\Config;
use CDash\Controller\Auth\Session;
use CDash\ServiceContainer;
use CDash\Database;
use CDash\Model\User;

/** @var PDO $pdo */
global $pdo;
$pdo = Database::getInstance()->getPdo();

function setRememberMeCookie($userId)
{
    $cookiename = 'CDash-' . $_SERVER['SERVER_NAME'];
    $time = time() + 60 * 60 * 24 * 30; // 30 days;

    // Create a new password
    require_once('include/common.php');
    $key = generate_password(32);

    // Update the user key
    $user = new User();
    $user->Id = $userId;
    if ($user->SetCookieKey($key)) {
        setcookie($cookiename, $userId . $key, $time);
    }
}

/** Database authentication */
function databaseAuthenticate($email, $password, $SessionCachePolicy, $rememberme)
{
    global $loginerror, $pdo;
    $loginerror = '';

    $config = Config::getInstance();
    $service = ServiceContainer::getInstance();
    /** @var Session $session */
    $session = $service->get(Session::class);
    $user = $service->create(User::class);

    $userid = $user->GetIdFromEmail($email);
    if (!$userid) {
        $loginerror = 'Wrong email or password.';
        return false;
    }

    $user->Id = $userid;
    $user->Fill();

    // Check if the account is locked out.
    if (accountIsLocked($userid)) {
        return false;
    }

    if ($password === null && $config->get('CDASH_EXTERNAL_AUTH')) {
        // External authentication succeeded.
        // Create the session array.
        $session->setSessionVar('cdash', [
            'login' => $email,
            'passwd' => null,
            'ID' => $session->getSessionId(),
            'valid' => 1,
            'loginid' => $userid,
        ]);

        return true;
    } else {
        $success = false;
        if (password_verify($password, $user->Password)) {
            $success = true;
        } elseif (md5($password) == $user->Password) {
            // Re-hash this password using an algorithm that's more secure than md5.
            // Do not attempt this before the database has been upgraded
            // to accommodate the increased length of this field.
            $config = Config::getInstance();
            $db_check = true;
            if ($config->get('CDASH_DB_TYPE') != 'pgsql') {
                $table_name = qid('user');
                $select = $pdo->query("SELECT password FROM $table_name LIMIT 1");
                $meta = $select->getColumnMeta(0);
                if ($meta['len'] < 255) {
                    $db_check = false;
                }
            }
            if ($db_check) {
                $passwordHash = User::PasswordHash($password);
                if ($passwordHash === false) {
                    $loginerror = 'Failed to hash password.  Contact an admin.';
                } else {
                    $user->Password =  $passwordHash;
                    $user->Save();
                }
            }
            $success = true;
        }

        if ($success) {
            // Authentication is successful.
            if ($rememberme) {
                $key = generate_password(32);
                $session->setRememberMeCookie($user, $key);
            }

            $session->start($SessionCachePolicy);
            $session->setSessionVar('cdash', [
                'login' => $email,
                'passwd' => $user->Password,
                'ID' => $session->getSessionId(),
                'valid' => 1,
                'loginid' => $userid,
            ]);

            // TODO: responsibility probably belongs to user model
            checkForExpiredPassword();

            // TODO: responsibility probably belongs to session class
            clearUnsuccessfulAttempts($userid);
            return true;
        }
    }
    // TODO: resposibility probably belongs to session class
    incrementUnsuccessfulAttempts($userid);
    $loginerror = 'Wrong email or password.';
    return false;
}

/** LDAP authentication */
function ldapAuthenticate($email, $password, $SessionCachePolicy, $rememberme)
{
    $config = Config::getInstance();
    global $loginerror;
    $loginerror = '';

    $ldap = ldap_connect($config->get('CDASH_LDAP_HOSTNAME'));
    ldap_set_option(
        $ldap,
        LDAP_OPT_PROTOCOL_VERSION,
        $config->get('CDASH_LDAP_PROTOCOL_VERSION')
    );
    ldap_set_option(
        $ldap,
        LDAP_OPT_REFERRALS,
        $config->get('CDASH_LDAP_OPT_REFERRALS')
    );

    // Bind as the LDAP user if authenticated ldap is enabled
    if ($config->get('CDASH_LDAP_AUTHENTICATED')) {
        ldap_bind(
            $ldap,
            $config->get('CDASH_LDAP_BIND_DN'),
            $config->get('CDASH_LDAP_BIND_PASSWORD')
        );
    }

    if (isset($ldap) && $ldap != '') {
        /* search for pid dn */
        $result = ldap_search($ldap, $config->get('CDASH_LDAP_BASEDN'),
            '(&(mail=' . $email . ')' . $config->get('CDASH_LDAP_FILTER') . ')', array('dn', 'cn'));
        if ($result != 0) {
            $entries = ldap_get_entries($ldap, $result);
            @$principal = $entries[0]['dn'];
            if (isset($principal)) {
                // bind as this user
                if (@ldap_bind($ldap, $principal, $password) and strlen(trim($password)) != 0) {
                    $user = new User();
                    $userid = $user->GetIdFromEmail($email);

                    // If the user doesn't exist we add it
                    if (!$userid) {
                        @$givenname = $entries[0]['cn'][0];
                        if (!isset($givenname)) {
                            $loginerror = 'No givenname (cn) set in LDAP, cannot register user into CDash';
                            return false;
                        }
                        $names = explode(' ', $givenname);

                        if (count($names) > 1) {
                            $user->FirstName = $names[0];
                            $user->LastName = $names[1];
                            for ($i = 2; $i < count($names); $i++) {
                                $user->LastName .= ' ' . $names[$i];
                            }
                        } else {
                            $user->LastName = $names[0];
                        }

                        // Add the user in the database
                        $passwordHash = User::PasswordHash($password);
                        if ($passwordHash === false) {
                            $loginerror = 'Failed to hash password.  Contact an admin.';
                        } else {
                            $user->Email = $email;
                            $user->Password = $passwordHash;
                            $user->Save();
                            $userid = $user->Id;
                        }
                    } else {
                        $user->Id = $userid;
                        $user->Fill();

                        // If the password has changed we update
                        $passwordHash = User::PasswordHash($password);
                        if ($passwordHash === false) {
                            $loginerror = 'Failed to hash password.  Contact an admin.';
                        } elseif ($user->Password != $passwordHash) {
                            $user->Password = $passwordHash;
                            $user->Save();
                        }
                    }

                    $service = ServiceContainer::getInstance();
                    /** @var  Session $session */
                    $session = $service->create(\CDash\Controller\Auth\Session::class);

                    if ($rememberme) {
                        $key = generate_password(32);
                        $session->setRememberMeCookie($user, $key);
                    }

                    $session->start($SessionCachePolicy);
                    $session->setSessionVar('cdash', [
                        'login' => $email,
                        'passwd' => $passwordHash,
                        'ID' => $session->getSessionId(),
                        'valid' => 1,
                        'loginid' => $userid
                    ]);
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
            $error = ldap_error($ldap);
            $loginerror = "Error occured searching the LDAP: $error";
        }
        ldap_close($ldap);
    } else {
        $loginerror = 'Could not connect to LDAP at ' . $config->get('CDASH_LDAP_HOSTNAME');
    }
    return false;
}

function crowdRequestSession($config) {
    $request_session = new Requests_Session(
        $config->get('CDASH_CROWD_URL')
    );

    $request_session->headers['Content-Type'] = 'application/json';
    $request_session->headers['Accept'] = 'application/json';

    // Crowd authentication
    $request_session->options['auth'] = new Requests_Auth_Basic(
        array(
            $config->get('CDASH_CROWD_APP_USERNAME'),
            $config->get('CDASH_CROWD_APP_PASSWORD')
        )
    );

    // Custom Crowd CA
    $crowd_ssl_ca = $config->get('CDASH_CROWD_CA_CERT');
    if ($crowd_ssl_ca) {
        $request_session->options['verify'] = $crowd_ssl_ca;
    }

    return $request_session;
}

function crowdValidationFactors() {
    return array(
        'validationFactors' => array(
            array(
                'name' => 'remote_addr',
                'value' => $_SERVER['REMOTE_ADDR']
            )
        )
    );
}

function isValidCrowdToken($token, $base_uri, $session) {
    $target = $base_uri . '/session';
    $response = $session->post($target.'/'.$token, crowdValidationFactors());

    if ($response->status_code === 200) {
        return true;
    } else if ($response->status_code === 400) {
        error_log('Validation factors incorrect.');
    } else if ($response->status_code === 404) {
        error_log('Unable to validate token, token not found.');
    } else {
        error_log('Unknown error while validating Crowd token.');
    }

    $loginerror = 'Unable to login with Crowd, contact an administrator.';
    return false;
}

function crowdUsernameAuthenticate($username, $password, $base_uri, $session) {
    $target = $base_uri . '/session';
    $payload = array (
        'username' => $username,
        'password' => $password,
        'validation-factors' => crowdValidationFactors(),
    );

    $response = $session->post(
        $target,
        null,
        json_encode($payload)
    );

    if ($response->status_code === 201) {
        $data = json_decode($response->body, true);
        if ($data['token']) {
            return $data['token'];
        } else {
            $loginerror = 'Authentication successful, but unable to get token';
        }
    } else if ($response->status_code === 400) {
        // Authentication details incorrect
        error_log('Invalid username or password for user ' . $username);
        $loginerror = 'Invalid username or password.';
    } else {
        // Don't know...
        error_log('Unknown error authenticating user '
                  . $username . ' with Crowd.');
        $loginerror = 'Unable to login with Crowd.';
    }

    return "";
}

function crowdUsernameFromEmail($email, $base_uri, $session) {
    $target = $base_uri . '/search?entity-type=user&expand=attributes';
    $payload = array (
        'restriction-type' => 'property-search-restriction',
        'property' => array (
            'name' => 'email',
            'type' => 'string'
        ),
        'value' => $email,
        'match-mode' => 'EXACTLY_MATCHES'
    );

    $response = $session->post(
        $target,
        null,
        json_encode($payload)
    );

    if ($response->status_code === 200) {
        $data = json_decode($response->body, true);
        if ($data['users'] && sizeof($data['users']) === 1) {
            $user = $data['users'][0];
            return $user['name'];
        }
    } else if ($response->status_code === 409) {
        error_log('Entity type unknown to Crowd when searching for username.');
    } else {
        error_log('Unknown error occurred looking up username from Crowd.');
    }

    $loginerror = 'Unable to lookup Crowd user using email ' . $email;
    return "";
}

function crowdAuthenticate($email, $password, $SessionCachePolicy, $rememberme) {
    Requests::register_autoloader();
    /** @var Config $config */
    $config = Config::getInstance();
    /** @var Session $session */

    $authenticated = false;

    $crowdtoken = str_replace('.', '_', $config->get('CDASH_CROWD_TOKEN_NAME'));
    $crowd_request = crowdRequestSession($config);
    $base_uri = $config->get('CDASH_CROWD_API_URI');

    if (isset($_COOKIE[$crowdtoken])) {
        $authenticated = isValidCrowdToken(
            $_COOKIE[$crowdtoken],
            $base_uri,
            $crowd_request
        );
    } else if($email && $password) {
        $username = crowdUsernameFromEmail($email, $base_uri, $crowd_request);

        $token = crowdUsernameAuthenticate(
            $username,
            $password,
            $base_uri,
            $crowd_request
        );

        if ($token) {
            $_COOKIE[$crowdtoken] = $token;
            $authenticated = true;
        }
    }

    if ($authenticated) {
        // Lets make sure CDash knows about our user
        $user = new User();
        $userid = $user->GetIdFromEmail($email);
        if (!$userid) {
            // Create new CDash user from info from Crowd
            $passwordHash = User::PasswordHash($password);
            if ($passwordHash === false) {
                $loginerror = 'Failed to hash password.  Contact an admin.';
            } elseif ($user->Password != $passwordHash) {
                $user->Email = $email;
                $user->Password = $passwordHash;
                $user->Save();
                $userid = $user->Id;
            }
        }

        if ($rememberme) {
            $key = generate_password(32);
            $session->setRememberMeCookie($user, $key);
        }

        // Setup our session
        $service = ServiceContainer::getInstance();
        /** @var  Session $session */
        $session = $service->create(\CDash\Controller\Auth\Session::class);

        $session->start($SessionCachePolicy);
        $session->setSessionVar('cdash', [
            'login' => $email,
            'passwd' => $passwordHash,
            'ID' => $session->getSessionId(),
            'valid' => 1,
            'loginid' => $userid
        ]);
    } else {
        $_COOKIE[$crowdtoken] = '';
    }

    return $authenticated;
}

/** authentication */
function authenticate($email, $password, $SessionCachePolicy, $rememberme)
{
    $config = Config::getInstance();
    $service = ServiceContainer::getInstance();
    if (empty($email)) {
        return 0;
    }
    include dirname(__DIR__) . '/config/config.php';
    $default_authenticators = array(
        'databaseAuthenticate',
    );

    $authenticators = array_merge(
        $default_authenticators,
        $config->get('CDASH_AUTHENTICATORS')
    );

    $user = $service->create(User::class);
    $userid = $user->GetIdFromEmail($email);
    if ($userid == 1) {
        return databaseAuthenticate(
            $email,
            $password,
            $SessionCachePolicy,
            $rememberme
        );
    } else {
        $authenticated = false;
        foreach($authenticators as $authenticator) {
            $authenticated = call_user_func_array(
                $authenticator,
                array(
                    $email,
                    $password,
                    $SessionCachePolicy,
                    $rememberme
                )
            );
            // TODO: Authoritative authenticators, etc...
            if ($authenticated) {
                return $authenticated;
            }
        }
        return $authenticated;
    }
}

/**
 * Authentication function
 * This is called on every page load where common.php is selected, as well as when
 * submitting the login form.
 **/
function cdash_auth($SessionCachePolicy = 'private_no_expire')
{
    /** @var Config $config */
    $config = Config::getInstance();

    if ($config->get('CDASH_EXTERNAL_AUTH') && isset($_SERVER['REMOTE_USER'])
    ) {
        $login = $_SERVER['REMOTE_USER'];
        return authenticate($login, null, $SessionCachePolicy, 0); // we don't remember
    }

    if (@$_GET['logout']) {                             // user requested logout
        logout();
        return 0;
    }

    if (isset($_POST['sent'])) {
        // arrive from login form
        @$login = $_POST['login'];
        @$passwd = $_POST['passwd'];
        return authenticate($login, $passwd, $SessionCachePolicy, isset($_POST['rememberme']));
    } else { // arrive from session var
        $server = $config->getServer();
        $cookiename = str_replace('.', '_', "CDash-{$server}"); // php doesn't like dot in cookie names
        $service = ServiceContainer::getInstance();
        /** @var Session $session */
        $session = $service->get(Session::class);

        if (isset($_COOKIE[$cookiename])) {
            $cookievalue = $_COOKIE[$cookiename];
            $cookiekey = substr($cookievalue, strlen($cookievalue) - 32);
            if (strlen($cookiekey) < 1) {
                return false;
            }
            $cookieuseridkey = substr($cookievalue, 0, strlen($cookievalue) - 32);
            // $user = new User();
            /** @var User $userid */
            $user = $service->create(User::class);
            if ($user->FillFromCookie($cookiekey, $cookieuseridkey)) {
                $session->start($SessionCachePolicy);
                $session->setSessionVar('cdash', [
                    'login' => $user->Email,
                    'passwd' => $user->Password,
                    'ID' => $session->getSessionId(),
                    'valid' => 1,
                    'loginid' => $user->Id
                ]);
                return true;
            }
        }

        // Return early if a session has already been started.
        if (session_status() != PHP_SESSION_NONE) {
            return;
        }

        $session->start($SessionCachePolicy);
        $email = $session->getSessionVar('cdash.login');

        if (!empty($email)) {
            /** @var User $userid */
            $user = $service->create(User::class);
            $userid = $user->GetIdFromEmail($email);
            if (!$userid) {
                $loginerror = 'Wrong email or password.';
                return false;
            }

            $user->Id = $userid;
            $user->Fill();

            if ($user->Password == $session->getSessionVar('cdash.passwd')) {
                return true;
            }
            $loginerror = 'Wrong email or password.';
            return false;
        }
    }
}

/** Log out the current user. */
function logout()
{
    $service = ServiceContainer::getInstance();
    /** @var Session $session */
    $session = $service->get(Session::class);
    $session->start(Session::CACHE_NOCACHE);
    $session->destroy();

    // Remove the cookie if we have one
    $cookienames = array('CDash', str_replace('.', '_', 'CDash-' . $_SERVER['SERVER_NAME'])); // php doesn't like dot in cookie names
    foreach ($cookienames as $cookiename) {
        if (isset($_COOKIE[$cookiename])) {
            $cookievalue = $_COOKIE[$cookiename];
            $cookieuseridkey = substr($cookievalue, 0, strlen($cookievalue) - 33);
            // $user = new User();
            /** @var User $user */
            $user = $service->create(User::class);
            $user->Id = $cookieuseridkey;
            $user->SetCookieKey('');
            setcookie('CDash-' . $_SERVER['SERVER_NAME'], '', time() - 3600);
        }
    }
}

/** Login Form function */
function LoginForm($loginerror)
{
    include_once 'include/common.php';

    $config = Config::getInstance();
    $xml = begin_XML_for_XSLT();
    $xml .= '<title>Login</title>';
    if ($config->get('CDASH_NO_REGISTRATION') == 1) {
        $xml .= add_XML_value('noregister', '1');
    }
    if (@$_GET['note'] == 'register') {
        $xml .= '<message>Registration Complete. Please login with your email and password.</message>';
    }

    if ($loginerror != '') {
        $xml .= '<message>' . $loginerror . '</message>';
    }

    if ($config->get('CDASH_ALLOW_LOGIN_COOKIE')) {
        $xml .= '<allowlogincookie>1</allowlogincookie>';
    }

    // OAuth 2.0 support.
    $valid_oauth2_providers = ['GitHub', 'GitLab', 'Google'];
    $enabled_oauth2_providers = [];
    foreach (array_keys($config->get('OAUTH2_PROVIDERS')) as $provider) {
        if (in_array($provider, $valid_oauth2_providers)) {
            $enabled_oauth2_providers[] = $provider;
        }
    }
    if (!empty($enabled_oauth2_providers)) {
        $xml .= '<oauth2>';
        foreach ($enabled_oauth2_providers as $provider) {
            $xml .= '<provider>';
            $xml .= add_XML_value('name', $provider);
            $xml .= add_XML_value('img', "img/${provider}_signin.png");
            $xml .= '</provider>';
        }
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
    $complexity_count = Config::getInstance()->get('CDASH_PASSWORD_COMPLEXITY_COUNT');
    $complexity = 0;
    $matches = array();

    // Uppercase letters
    $num_uppercase = preg_match_all('/[A-Z]/', $password, $matches);
    if ($num_uppercase >= $complexity_count) {
        $complexity++;
    }

    // Lowercase letters
    $num_lowercase = preg_match_all('/[a-z]/', $password, $matches);
    if ($num_lowercase >= $complexity_count) {
        $complexity++;
    }

    // Numbers
    $num_numbers = preg_match_all('/[0-9]/', $password, $matches);
    if ($num_numbers >= $complexity_count) {
        $complexity++;
    }

    // Symbols
    $num_symbols = preg_match_all("/\W/", $password, $matches);
    // Underscore is not matched by \W but we consider it a symbol.
    $num_symbols += substr_count($password, '_');
    if ($num_symbols >= $complexity_count) {
        $complexity++;
    }
    return $complexity;
}

/** Sets a session variable forcing the redirect if the user needs
 *  to change their password.
 */
function checkForExpiredPassword()
{
    global $pdo;
    $expires = Config::getInstance()->get('CDASH_PASSWORD_EXPIRATION');

    if ($expires < 1) {
        return false;
    }

    if (!isset($_SESSION['cdash']) || !array_key_exists('loginid', $_SESSION['cdash'])) {
        return false;
    }

    unset($_SESSION['cdash']['redirect']);
    $uri = get_server_URI(false);
    $uri .= '/editUser.php?reason=expired';

    $userid = $_SESSION['cdash']['loginid'];
    $stmt = $pdo->prepare(
        'SELECT date FROM password WHERE userid = ?
        ORDER BY date DESC LIMIT 1');
    pdo_execute($stmt, [$userid]);
    $row = $stmt->fetch();

    if (!$row) {
        // If no result, then password rotation must have been enabled
        // after this user set their password.  Force them to change it now.
        $_SESSION['cdash']['redirect'] = $uri;
        return true;
    }

    $password_created_time = strtotime($row['date']);
    $password_expiration_time =
        strtotime("+{$expires} days", $password_created_time);
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
    global $pdo;
    if (Config::getInstance()->get('CDASH_LOCKOUT_ATTEMPTS') == 0) {
        return;
    }
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
    global $pdo;
    $config = Config::getInstance();
    if ($config->get('CDASH_LOCKOUT_ATTEMPTS') == 0) {
        return;
    }
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

    if ($failedattempts >= $config->get('CDASH_LOCKOUT_ATTEMPTS')) {
        // Lock the account if it exceeds our number of failed attempts.
        $length = $config->get('CDASH_LOCKOUT_LENGTH');
        $unlocktime = gmdate(FMT_DATETIME, time() + $length * 60);
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
    global $pdo;
    if (Config::getInstance()->get('CDASH_LOCKOUT_ATTEMPTS') == 0) {
        return false;
    }

    global $loginerror;
    $loginerror = '';

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
