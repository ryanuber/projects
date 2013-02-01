<?php
/**
 * This class is a simple wrapper over the top of PECL-Zookeeper. It simplifies
 * talking with zookeeper by doing a few useful things:
 *   - Handling creating or updating nodes with a unified 'set' command
 *   - Handles creating parent znodes when they do not exist during a 'set'
 *   - Handles recursively deleting a parent znode and all of its children
 *   - Provides a function to parameterize and concatenate znode paths,
 *     eliminating the need to manually concatenate and validate zookeeper
 *     paths to znodes.
 *   - Managing a single connection to the Zookeeper server while the class
 *     instance exists.
 *   - Can traverse all znodes below a certain hierarchy and gather data
 *     from each child znode.
 */
class ZookeeperStorage
{
    /**
     * Instance of the connection to the Zookeeper service
     *
     * @object $Zookeeper
     */
    private static $Zookeeper;

    /**
     * Singleton zookeeper storage self instance
     *
     * @object $instance
     */
    public static $instance;

    /**
     * A symbolic pointer to an element inside of the result array
     *
     * @pointer $pointer
     */
    private static $pointer;

    /**
     * A placeholder for query results. Useful during recursion.
     *
     * @var mixed $result
     */
    private static $result;

    /**
     * Constructor function which actually initiates the connection to
     * Zookeeper, rather than requiring the programmer to call some connect
     * method after instantiating.
     *
     * @return void
     */
    public function __construct($host='127.0.0.1', $port=2181)
    {
        if(! self::$instance instanceof \ZookeeperStorage) {
            try {
                self::$Zookeeper = new \Zookeeper($host.':'.$port);
            } catch(\Exception $Error) {
                self::setLastError(
                    'Failed connecting to Zookeeper host '.
                    $host.' on port '.$port
                );
            }

            self::$instance = $this;
        }

        self::$pointer = &self::$result;

        return self::$instance;
    }

    /**
     * Forces the connections to close themselves out if not already done.
     *
     * @return bool
     */
    public function __destruct()
    {
        $this->Zookeeper = null;
    }

    /**
     * Concatenate a simple path to pass in to Zookeeper. This way, we are not constantly
     * writing long lines of concatenation code, but can instead just feed any number of
     * arguments into this method and retrieve the concatenated path.
     *
     * @param string $Path  Repeating argument to concatentate to path
     *
     * @return string
     */
    public static function path_join()
    {
        $args = func_get_args();
        foreach($args as $path) {
            if(isset($result)) {
                $result .= '/'.$path;
            } else {
                $result = substr($path, 0, 1) == '/' ? $path : '/'.$path;
            }
        }
        return $result;
    }

    /**
     * Generate a usable Zookeeper path from a path string and an associative array
     * of key => value parameter pairs to do string replacement with. A common example
     * would be to write a path like '/clients/:id/hostname', and to pass a parameters
     * array like: array(':id' => 23). This would yield a path like '/clients/23/hostname'.
     *
     * This prevents path injection by breaking the parameters at the first occurance of
     * the '/' character. For example, if you pass in a path like '/clients/:id', and
     * parameters like: array(':id' => '23/secret'), the returned path would be
     * '/consumers/23', not 'consumers/23/secret', successfully preventing the query from
     * returning the client's secret.
     *
     * @param string $path  The parameterized path
     * @param array $params  Key => value list of parameters to replace
     *
     * @return string
     */
    private function zkpath($path, array $params=array())
    {
        foreach($params as $k => $v) {
            strpos((string)$v, '/') === FALSE?$r=strlen((string)$v):$r=strpos((string)$v, '/');
            $v = substr((string)$v, 0, $r);
            $path = str_replace($k, $v, $path);
        }
        return rtrim($path, '/');
    }

    /**
     * Fetch a list of keys within a certain path. Provide a mechanism to perform query
     * parameterization, protecting from query injections.
     *
     * @param string $path  The path to search for
     * @param array $params  Path parameters to replace
     *
     * @return mixed
     */
    public function get_list($path, array $params=array())
    {
        $path = self::zkpath($path, $params);

        try {
            $result = self::$Zookeeper->exists($path)?self::$Zookeeper->getChildren($path):array();
        } catch(Exception $Error) {
            self::setLastError('Failed trying to list child nodes of '.$path);
            return FALSE;
        }

        sort($result);
        return $result;
    }

    /**
     * As much as possible, recurse through znodes in Zookeeper starting at $path and
     * collect all data into an associative array. Zookeeper supports atypical data
     * structure where a znode can be a parent and contain data. This method will ignore
     * data set on a znode that is a parent since arrays in most programming languages
     * do not support such a data structure.
     *
     * @param string $path  The path to begin recursion from
     * @param bool $return  Simple internal indicator we use to know when to return
     *
     * @return mixed  Array on success, null on failure.
     */
    public function get_recursive($path='', $return=true)
    {
        foreach(self::get_list($path) as $leaf) {
            if(count(self::get_list(self::path_join($path, $leaf))) > 0) {
                self::$pointer[$leaf] = array();
                $p = &self::$pointer;
                self::$pointer = &self::$pointer[$leaf];
                self::get_recursive(self::path_join($path, $leaf), false);
                self::$pointer = &$p;
            } else {
                self::$pointer[$leaf] = self::get(self::path_join($path, $leaf));
            }
        }
        if ($return) {
            $result = self::$result;
            self::$result = null;
            self::$pointer = &self::$result;
            return $result;
        }
    }

    /**
     * Delete a node, first trying to list children, and recursing to delete them, and
     * their children, unifying calls to delete a single node, or a node with children.
     *
     * @param string $path  The path to search for
     * @param array $params  Path parameters to replace
     *
     * @return bool
     */
    public function delete($path, array $params=array())
    {
        $path = self::zkpath($path, $params);

        if(self::$Zookeeper->exists($path)) {
            foreach(self::get_list($path) as $node) {
                self::delete($path.'/'.$node);
            }

            try {
                self::$Zookeeper->delete($path);
            } catch(\Exception $Error) {
                self::setLastError('Failed while deleting node '.$path);
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * Get the value of a particular (existing) key. Provide a mechanism to perform query
     * parameterization, protecting from query injections.
     *
     * @param string $path  The path to the key
     * @param array $params  Path parameters to replace
     *
     * @return string
     */
    public function get($path, array $params=array())
    {
        $path = self::zkpath($path, $params);

        try {
            $result = self::$Zookeeper->exists($path)?self::$Zookeeper->get($path):null;
        } catch(\Exception $Error) {
            self::setLastError('Failed trying to fetch node '.$path);
            return FALSE;
        }

        return $result;
    }

    /**
     * Set a specific key to some value. Create the key if it does not exist, as well as
     * any parent nodes that do not already exist.
     *
     * @param string $path  The key to set
     * @param string $value  The value of the key
     * @param array $params  Path parameters to replace
     *
     * @return bool
     */
    public function set($path, $value, array $params=array())
    {
        $path = self::zkpath($path, $params);

        if(self::$Zookeeper->exists($path)) {
            try {
                self::$Zookeeper->set($path, $value);
                return TRUE;
            } catch(\Exception $Error) {
                self::setLastError('Failed trying to update existing node '.$path);
                return FALSE;
            }
        } else {
            /** 
             * This will recurse if the parent node does not exist and create it, and its
             * parent node, again and again until we can finally set the key that was
             * originally specified. Non-existent nodes will be created with a null value.
             */
            if(!self::$Zookeeper->exists(dirname($path))) {
                self::set(dirname($path), '');
            }

            try {
                self::$Zookeeper->create(
                    $path,
                    $value,
                    array(array('perms' => \Zookeeper::PERM_ALL, 'scheme' => 'world', 'id' => 'anyone'))
                );
            } catch(\Exception $Error) {
                self::setLastError('Failed tyring to create new node '.$path);
                return FALSE;
            }

            return TRUE;
        }
    }

    /**
     * Sets the object's lastError variable if the passed message is not empty,
     * which would indicate that something went wrong during your query.
     *
     * @param string $Message  Text string containing error message.
     *
     * @return void
     */
    private function setLastError($Message)
    {
        if(!empty($Message)) {
            error_log($Message);
            self::$lastError = $Message;
        }
    }

    /**
     * Fetches the last reported error from the Zookeeper library (if any).
     * Returns the error text if found, or false if there is none set.
     *
     * @return mixed
     */
    public function getLastError()
    {
        return isset(self::$lastError)?self::$lastError:FALSE;
    }
}

/* EOF */
?>
