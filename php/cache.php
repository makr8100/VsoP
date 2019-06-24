<?php

/**
 * Cache - shmop wrapper to manage cache stores
 *
 * @author       Mark Gullings <makr8100@gmail.com>
 * @copyright    2019-06-24
 * @package      VsoP
 * @name         Cache
 * @since        2019-06-24
 * @version      0.11
 *
 * @source { // SAMPLE USAGE:
 *     $cache = new Cache();
 *     $data = $cache->get('MyTableData');
 *     if (empty($data)) {
 *         $sql = "SELECT * FROM MyTable";
 *         $stmt = $db->prepare($sql);
 *         $stmt->execute();
 *         $data = $stmt->fetchAll();
 *         $cache->save($data, 'MyTableData', 20); // data to store, name of cache, store time in seconds
 *     }
 * }
 */

class Cache {

    /**
    * @param mixed $data // item to be stored
    * @param string $name // name of item
    * @param integer $timeout // seconds data will be valid before refresh needed
    * @return integer // size of data written on success, false on failure
    */
    public function save($data, $name, $timeout) {
        // delete cache
        $id = shmop_open($this->getID($name), "a", 0, 0);
        shmop_delete($id);
        shmop_close($id);

        // get id for name of cache
        $id = shmop_open($this->getID($name), "c", 0644, strlen(serialize($data)));

        // return int for data size or boolean false for fail
        if ($id) {
            $this->setTimeout($name, $timeout);
            return shmop_write($id, serialize($data), 0);
        }
        else return false;
    }

    /**
    * @param string $name // name of item to retrieve
    * @return mixed // stored data or false if non-existent/expired
    */
    public function get($name) {
        if (!$this->checkTimeout($name)) {
            $id = shmop_open($this->getID($name), "a", 0, 0);

            if ($id) $data = unserialize(shmop_read($id, 0, shmop_size($id)));
            else return false;          // failed to load data

            if ($data) {                // array retrieved
                return $data;
            }
            else return false;          // failed to load data
        }
        else return false;              // data was expired
    }

    /**
    * @param string $name // name of item to get ID for
    * @return integer // ID for use with native PHP shmop functions
    */
    private function getID($name) {
        global $config;
        return $config['cache'][$name];
    }

    /**
    * @param string $name // name of item to set expiration
    * @param integer $int // timeout in seconds to expire cache
    */
    public function setTimeout($name, $int) {
        $timeout = new DateTime(date('Y-m-d H:i:s'));
        date_add($timeout, date_interval_create_from_date_string("$int seconds"));
        $timeout = date_format($timeout, 'YmdHis');

        $id = shmop_open(9999, "a", 0, 0);
        if ($id) $tl = unserialize(shmop_read($id, 0, shmop_size($id)));
        else $tl = [];
        shmop_delete($id);
        shmop_close($id);

        $tl[$name] = $timeout;
        $id = shmop_open(9999, "c", 0644, strlen(serialize($tl)));
        shmop_write($id, serialize($tl), 0);
    }

    /**
    * @param string $name // name of item to check expiration
    * @return boolean // true on old cache, false on current cache
    */
    public function checkTimeout($name) {
        $now = new DateTime(date('Y-m-d H:i:s'));
        $now = date_format($now, 'YmdHis');

        $id = shmop_open(9999, "a", 0, 0);
        if ($id) $tl = unserialize(shmop_read($id, 0, shmop_size($id)));
        else return true;
        shmop_close($id);

        $timeout = $tl[$name];
        return (intval($now)>intval($timeout));
    }
}
