<?php

/**
 * Base Redis model
 */
class Unsee_Redis
{

    const EXP_HOUR = 3600;

    const EXP_DAY  = 86400;

    const DB       = 0;

    /**
     * @var int Id of previously used database on Master
     */
    static $prevDbMaster = 0;

    /**
     * @var int Id of previously used database on Slave
     */
    static $prevDbSlave = 0;

    /**
     * @var Redis Redis object
     */
    private $redisMaster;

    /**
     * @var Redis Redis object
     */
    private $redisSlave;

    /**
     * @var string Key of Redis hash field
     */
    public $key;

    /**
     * Creates the Redis model
     *
     * @param string $key
     */
    public function __construct($key = null)
    {
        $this->redisMaster = Zend_Registry::get('RedisMaster');
        $this->redisSlave  = Zend_Registry::get('RedisSlave');
        $this->key         = $key;
    }

    /**
     * Support isset() for Redis model object
     *
     * @param string $key
     *
     * @return true
     */
    public function __isset($key)
    {
        $this->selectDb(false);

        return $this->redisSlave->hExists($this->key, $key);
    }

    /**
     * Support unset() for Redis model object
     *
     * @param type $key
     *
     * @return int
     */
    public function __unset($key)
    {
        $this->selectDb();

        return $this->redisMaster->hDel($this->key, $key);
    }

    /**
     * Fetches the content defined by the key
     *
     * @param string $hKey
     *
     * @return string
     */
    public function __get($hKey)
    {
        $this->selectDb(false);

        Unsee_Timer::start(Unsee_Timer::LOG_DB_GET);
        $res = $this->redisSlave->hGet($this->key, $hKey);
        Unsee_Timer::stop(Unsee_Timer::LOG_DB_GET, $hKey);

        return $res;
    }

    /**
     * Sets the value of the hash defined by the key
     *
     * @param string $hKey
     * @param mixed  $value
     *
     * @return bool
     */
    public function __set($hKey, $value)
    {
        $this->selectDb();

        Unsee_Timer::start(Unsee_Timer::LOG_DB_SET);
        $res = $this->redisMaster->hSet($this->key, $hKey, $value);
        Unsee_Timer::stop(Unsee_Timer::LOG_DB_SET, $hKey);

        return $res;
    }

    /**
     * Sets the current database id to operate on
     *
     * @return boolean
     */
    private function selectDb($useMaster = true)
    {
        $type   = $useMaster ? 'Master' : 'Slave';
        $server = 'redis' . $type;

        Unsee_Timer::start(Unsee_Timer::LOG_DB_SELECT);
        $this->$server->select(static::DB);
        Unsee_Timer::stop(Unsee_Timer::LOG_DB_SELECT, static::DB);

        return true;
    }

    /**
     * Returns true if the specified hash exists
     *
     * @param string $key
     *
     * @return bool
     */
    public function exists($key = null)
    {
        if (!$key) {
            $key = $this->key;
        }

        $this->selectDb(false);

        Unsee_Timer::start(Unsee_Timer::LOG_DB_EXISTS);
        $res = $this->redisSlave->hLen($this->key) > 0;
        Unsee_Timer::stop(Unsee_Timer::LOG_DB_EXISTS, $key);

        return $res;
    }

    /**
     * Deletes the Redis hash
     *
     * @return int
     */
    public function delete()
    {
        $this->selectDb();

        Unsee_Timer::start(Unsee_Timer::LOG_DB_DEL);
        $this->redisMaster->delete($this->key);
        Unsee_Timer::stop(Unsee_Timer::LOG_DB_DEL);

        return true;
    }

    /**
     * Returns array representation of the Redis hash
     *
     * @return array
     */
    public function export()
    {

        $this->selectDb(false);

        Unsee_Timer::start(Unsee_Timer::LOG_DB_EXPORT);
        $res = $this->redisSlave->hGetAll($this->key);
        Unsee_Timer::start(Unsee_Timer::LOG_DB_EXPORT, $this->key);

        return $res;
    }

    /**
     * Increments the value of the hash field by the specified number
     *
     * @param string $key
     * @param int    $num
     *
     * @return bool
     */
    public function increment($key, $num = 1)
    {
        $this->selectDb();

        return $this->redisMaster->hIncrBy($this->key, $key, $num);
    }

    public function expireAt($time)
    {
        $this->selectDb();

        Unsee_Timer::start(Unsee_Timer::LOG_DB_EXPIRE);
        $res = $this->redisMaster->expireAt($this->key, $time);
        Unsee_Timer::stop(Unsee_Timer::LOG_DB_EXPIRE, $this->key);

        return $res;
    }

    public function ttl()
    {
        $this->selectDb();

        Unsee_Timer::start(Unsee_Timer::LOG_DB_TTL);
        $res = $this->redisMaster->ttl($this->key);
        Unsee_Timer::stop(Unsee_Timer::LOG_DB_TTL, $this->key);

        return $res;
    }

    public static function keys($keys)
    {
        $redis = Zend_Registry::get('RedisSlave');
        $redis->select(static::DB);
        self::$prevDbSlave = static::DB;

        Unsee_Timer::start(Unsee_Timer::LOG_DB_KEYS);
        $res = $redis->keys($keys);
        Unsee_Timer::stop(Unsee_Timer::LOG_DB_KEYS, $keys);

        return $res;
    }
}
