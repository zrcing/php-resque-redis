<?php
/**
 * Extended Redisent class used by Resque for all communication with
 * redis. Essentially adds namespace support to Redisent.
 *
 * @package		Resque/Redis
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_Redis
{
    private $host;
    
    private $port;
   
    private $redis = null;
    
    /**
     * Redis namespace
     * @var string
     */
    private static $defaultNamespace = 'resque:';
	/**
	 * @var array List of all commands in Redis that supply a key as their
	 *	first argument. Used to prefix keys with the Resque namespace.
	 */
	private $keyCommands = array(
		'exists',
		'del',
		'type',
		'keys',
		'expire',
		'ttl',
		'move',
		'set',
		'get',
		'getset',
		'setnx',
		'incr',
		'incrby',
		'decr',
		'decrby',
		'rpush',
		'lpush',
		'llen',
		'lrange',
		'ltrim',
		'lindex',
		'lset',
		'lrem',
		'lpop',
		'rpop',
		'sadd',
		'srem',
		'spop',
		'scard',
		'sismember',
		'smembers',
		'srandmember',
		'zadd',
		'zrem',
		'zrange',
		'zrevrange',
		'zrangebyscore',
		'zcard',
		'zscore',
		'zremrangebyscore',
		'sort'
	);
	// sinterstore
	// sunion
	// sunionstore
	// sdiff
	// sdiffstore
	// sinter
	// smove
	// rename
	// rpoplpush
	// mget
	// msetnx
	// mset
	// renamenx
	
	public function __construct($host = '127.0.0.1', $port = 6379)
	{
	    $this->host = $host;
	    $this->port = $port;
	}
	
	public function __destruct()
	{
	    if (! is_null($this->redis)) {
	        $this->redis->close();
	    }
	    $this->redis = null;
	}
	
	/**
	 * Set Redis namespace (prefix) default: resque
	 * @param string $namespace
	 */
	public static function prefix($namespace)
	{
	    if (strpos($namespace, ':') === false) {
	        $namespace .= ':';
	    }
	    self::$defaultNamespace = $namespace;
	}
	
	public function redis()
	{
	    if (is_null($this->redis)) {
	        $this->redis = new Redis();
	        $this->redis->connect($this->host, $this->port);
	    }
	    return $this->redis;
	}
	
	/**
	 * Magic method to handle all function requests and prefix key based
	 * operations with the {self::$defaultNamespace} key prefix.
	 *
	 * @param string $name The name of the method called.
	 * @param array $args Array of supplied arguments to the method.
	 * @return mixed Return value from Resident::call() based on the command.
	 */
	public function __call($name, $args) 
	{    
		if(in_array($name, $this->keyCommands)) {
		    $args[0] = self::$defaultNamespace . $args[0];
		}
		try {
			switch (count($args)) {
			    case 1:
			        return $this->redis()->$name($args[0]);
			        break;
			    case 2:
			        return $this->redis()->$name($args[0], $args[1]);
			        break;
			    case 3:
			        return $this->redis()->$name($args[0], $args[1], $args[2]);
			        break;
			    default:
			        break;
			}
		}
		catch(RedisException $e) {
			return false;
		}
	}
}
?>