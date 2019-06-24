<?php

/**
 * Session - user login SQL and permissions/authority handling
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-06-24
 * @package      VsoP
 * @name         Session
 * @since        2019-06-24
 * @version      0.11
 *
 * @source { // SAMPLE USAGE:
 *     $session = new Session();
 *     $user['id'] = $session->doLogin('myUser', 'myPassword');
 * }
 */

//IMPORTANT!: incomplete 
//TODO: permissions/authority table

class Session {
    public $user;

    private function __construct() {
        session_start();
    }

    public function doLogin($user, $password) {
        $sql = "SELECT id FROM users WHERE name = ? AND password = ?"; //TODO: variable table/field names configurable for existing user tables
        $parms = [$user, $password];
        $stmt = $db->prepare($sql); //TODO: configurable method to auth users, $db is likely not global here
        $stmt->execute($parms);
        return $stmt->fetch()['id'];
    }
}
