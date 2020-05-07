<?php

/**
 * Session - user login SQL and permissions/authority handling
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-12-16
 * @package      VsoP
 * @name         Session
 * @since        2019-06-24
 * @version      0.14
 * @license      MIT
 *
 * @source { // SAMPLE USAGE:
 *     $session = new Session();
 *     $user['id'] = $session->doLogin($db, $config, $data, 'myUser', 'myPassword');
 * }
 */

class Session {
    public $user;

    public function __construct(&$config, $db, &$data) {
        $data['loginConfig']['uidField'] = $config['login']['uidField'];
        $data['loginConfig']['userField'] = $config['login']['userField'];
        $data['loginConfig']['requireLogin'] = $config['login']['requireLogin'];
        $data['loginConfig']['defaultUID'] = $config['login']['defaultUID'];
        $data['loginConfig']['defaultUsername'] = $config['login']['defaultUsername'];

        session_start();
        $this->user = &$_SESSION['user'];
        if (!isset($this->user)) $this->goAnon($config);
        else $this->user['authority'] = $this->getUserAuthority($db, $config);
    }

    public function doLogout($config, &$data) {
        $this->goAnon($config);
        $data['status'] = 200;
    }

    public function doLogin($db, &$config, &$data, $user, $password) {
        $data['user'] = &$this->user;
        $conditions = '';
        if (!empty($config['login']['conditions'])) {
            $conditions = implode(' AND ', $config['login']['conditions']);
            $conditions .= " AND";
        }
        $stmt = $db[$config['login']['db']]->prepare("{$config['login']['sql']} WHERE $conditions {$config['login']['userField']} = ?");
        $stmt->execute([$user]);
        unset($config['login']['conditions']);
        unset($config['login']['sql']);

        $usrData = $stmt->fetchAll();

        switch (sizeof($usrData) <=> 1) {
            case 1:
                $this->goAnon($config);
                $data['status'] = 401;
                $data['messages'][] = [ 'type' => 'error', 'message' => 'Ambiguous User!' ];
                break;
            case -1:
                $this->goAnon($config);
                $data['status'] = 401;
                $data['messages'][] = [ 'type' => 'error', 'message' => 'User Not Found!' ];
                break;
            case 0:
                if ($this->verifyPassword($usrData[0], $config['login'], $password)) {
                    $this->user = $usrData[0];
                    if (isset($config['login']['authority'])) {
                        $this->user['authority'] = $this->getUserAuthority($db, $config);
                    }
                    $data['status'] = 200;
                } else {
                    $this->goAnon($config);
                    $data['status'] = 401;
                    $data['messages'][] = [ 'type' => 'error', 'message' => 'Incorrect Password!' ];
                }
                break;
            unset($config['login']);
        }
    }

    public function authCheck($config, &$data, $key, $permission, $errorOnNoAuth = true) {
        $isAuth = (!empty($config['mapping'][$key]['noauth']) || !empty($this->user['authority'][$key][$permission]));
        if (!$isAuth && $errorOnNoAuth) {
            $data['key'] = $key;
            $data['permission'] = $permission;
            $data['status'] = 403;
            if (!empty($this->user['authority'])) $data['messages'][] = [ 'type' => 'error', 'message' => 'Not Authorized!' ];
        }
        return $isAuth;
    }

    private function verifyPassword(&$user, $fields, $password) {
        $result = password_verify($password . $user[$fields['salt']], $user[$fields['passField']]);
        unset($user[$fields['passField']]);
        unset($user[$fields['salt']]);
        return $result;
    }

    private function getUserAuthority($db, $config) {
        $stmt = $db[$config['login']['db']]->prepare($config['login']['authority']['sql']);
        $parms = [];
        foreach ($config['login']['authority']['parms'] as $parm) {
            $parms[] = $this->user[$parm];
        }
        $stmt->execute($parms);
        return $stmt->fetchAll(PDO::FETCH_UNIQUE);
    }

    private function goAnon($config) {
        $this->user = [
            $config['login']['uidField'] => $config['login']['defaultUID'],
            $config['login']['userField'] => $config['login']['defaultUsername'],
            'authority' => []
        ];
    }
}
