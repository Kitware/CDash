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
namespace CDash\Model;

use CDash\Database;

/** AuthToken class */
class AuthToken
{
    public $Hash;
    public $UserId;
    public $Created;
    public $Expires;
    public $Description;
    private $Filled;
    private $PDO;

    public function __construct()
    {
        $this->Filled = false;
        $this->PDO = Database::getInstance()->getPdo();
    }

    // Generate a new authentication token.
    // The raw token is returned by this function and its hash is saved in
    // $this->Hash.
    public function Generate()
    {
        global $CDASH_TOKEN_DURATION;
        $now = time();
        $token = bin2hex(random_bytes(16));
        $this->Hash = $this->HashToken($token);
        $this->Created = gmdate(FMT_DATETIME, $now);
        if ($CDASH_TOKEN_DURATION === 0) {
            // Special value meaning "this token never expires".
            $this->Expires = '9999-12-31 23:59:59';
        } else {
            $this->Expires = gmdate(FMT_DATETIME, $now + $CDASH_TOKEN_DURATION);
        }
        return $token;
    }

    public static function HashToken($token)
    {
        return hash('sha512', $token);
    }

    /** Return true if this token already exists */
    public function Exists()
    {
        if (!$this->Hash) {
            return false;
        }
        $stmt = $this->PDO->prepare(
            'SELECT COUNT(*) FROM authtoken WHERE hash = ?');
        pdo_execute($stmt, [$this->Hash]);
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
        return false;
    }

    /** Insert a new record in the database or update an existing one. */
    public function Save()
    {
        if (!$this->Hash) {
            add_log('Hash not set', 'AuthToken::Save', LOG_ERR);
            return false;
        }
        $user = new User();
        $user->Id = $this->UserId;
        if (!$user->Exists()) {
            add_log('Invalid UserId', 'AuthToken::Save', LOG_ERR);
            return false;
        }

        if ($this->Exists()) {
            $stmt = $this->PDO->prepare(
                'UPDATE authtoken
                SET userid = :userid, created = :created, expires = :expires,
                    description = :description
                WHERE hash = :hash');
        } else {
            $stmt = $this->PDO->prepare(
                'INSERT INTO authtoken
                (hash, userid, created, expires, description)
                VALUES
                (:hash, :userid, :created, :expires, :description)');
        }
        $stmt->bindParam(':hash', $this->Hash);
        $stmt->bindParam(':userid', $this->UserId);
        $stmt->bindParam(':created', $this->Created);
        $stmt->bindParam(':expires', $this->Expires);
        $stmt->bindParam(':description', $this->Description);
        if (!pdo_execute($stmt)) {
            return false;
        }
        return true;
    }

    /** Delete this record from the database. */
    public function Delete()
    {
        if (!$this->Hash) {
            add_log('Hash not set', 'AuthToken::Delete', LOG_ERR);
            return false;
        }

        if (!$this->UserId) {
            add_log('UserId not set', 'AuthToken::Delete', LOG_ERR);
            return false;
        }

        if (!$this->Exists()) {
            add_log('Token does not exist', 'AuthToken::Delete', LOG_ERR);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'DELETE FROM authtoken WHERE hash = ? AND userid = ?');
        return pdo_execute($stmt, [$this->Hash, $this->UserId]);
    }

    /** Retrieve details for a given token. */
    public function Fill()
    {
        if ($this->Filled) {
            return true;
        }
        if (!$this->Hash) {
            add_log('Hash not set', 'AuthToken::Fill', LOG_ERR);
            return false;
        }

        $stmt = $this->PDO->prepare(
            'SELECT * FROM authtoken WHERE hash = ?');
        if (!pdo_execute($stmt, [$this->Hash])) {
            return false;
        }

        $row = $stmt->fetch();
        $this->UserId = $row['userid'];
        $this->Created = $row['created'];
        $this->Expires = $row['expires'];
        $this->Description = $row['description'];
        $this->Filled = true;
        return true;
    }

    /** Return true if this token has expired */
    public function Expired()
    {
        if (!$this->Fill()) {
            return false;
        }
        if (strtotime($this->Expires) < time()) {
            return true;
        }
        return false;
    }

    /** Get JSON representations for all the authentication tokens
     * for a given user.
     **/
    public static function getTokensForUser($userid)
    {
        if (!$userid) {
            return;
        }
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            'SELECT hash FROM authtoken WHERE userid = ? ORDER BY expires ASC');
        pdo_execute($stmt, [$userid]);
        $tokens = [];
        while ($row = $stmt->fetch()) {
            $token = new AuthToken();
            $token->Hash = $row['hash'];
            $tokens[] = $token->marshal();
        }
        return $tokens;
    }

    // Get JSON representation of this object.
    public function marshal()
    {
        $marshaledAuthToken = [];

        if (!$this->Fill()) {
            return $marshaledAuthToken;
        }

        $marshaledAuthToken['hash'] = $this->Hash;
        $marshaledAuthToken['description'] = $this->Description;

        $user = new User();
        $user->Id = $this->UserId;
        $marshaledAuthToken['user'] = $user->GetName();

        $created = strtotime($this->Created . ' UTC');
        $marshaledAuthToken['created'] = date(FMT_DATETIMEDISPLAY, $created);
        $expires = strtotime($this->Expires . ' UTC');
        $marshaledAuthToken['expires'] = date(FMT_DATETIMEDISPLAY, $expires);

        return $marshaledAuthToken;
    }

    /**
     * Get Authorization header.
     * Adapted from http://stackoverflow.com/a/40582472
     **/
    private static function getAuthorizationHeader()
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } else {
            $requestHeaders = getallheaders();
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    /**
     * Get access token from header.
     **/
    private static function getBearerToken()
    {
        $headers = AuthToken::getAuthorizationHeader();
        if (!empty($headers)) {
            $matches = [];
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    // Check for the presence of a bearer token in the request.
    // If one is found, return the corresponding userid.
    // Otherwise return null.
    // If the specified token has expired it will be deleted.
    public function getUserIdFromRequest()
    {
        $token = AuthToken::getBearerToken();
        if (!$token) {
            return null;
        }

        $this->Hash = $this->HashToken($token);

        if (!$this->Exists()) {
            return null;
        }
        if ($this->Expired()) {
            $this->Delete();
            return null;
        }

        return $this->UserId;
    }
}
