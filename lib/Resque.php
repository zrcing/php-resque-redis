<?php
require_once dirname(__FILE__) . '/Resque/Event.php';
require_once dirname(__FILE__) . '/Resque/Exception.php';
require_once dirname(__FILE__) . '/Resque/Redis.php';

/**
 * Base Resque class.
 *
 * @package		Resque
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque
{
	const VERSION = '1.2';

	/**
	 * @var Resque_Redis Instance of Resque_Redis that talks to redis.
	 */
	public static $redis = null;
	
	/**
	 * @var int PID of current process. Used to detect changes when forking
	 *  and implement "thread" safety to avoid race conditions.
	 */
    protected static $pid = null;
    
    
    protected static $redisHost = null;
    
    protected static $redisPort = null;
    
    /**
     * @var int ID of Redis database to select.
     */
    protected static $redisDatabase = null;
    
    protected static $redisPassword = null;
	 
    /**
     * Loading php-resque-redis
     * 
     * @param string $redisHost
     * @param number $redisPort
     * @param number $redisDatabase
     * @param string $redisPassword
     */
    public static function loading($redisHost = '127.0.0.1', $redisPort = 3379, $redisDatabase = null, $redisPassword = null)
    {
        self::$redisHost = $redisHost;
        self::$redisPort = $redisPort;
        self::$redisDatabase = $redisDatabase;
        self::$redisPassword = $redisPassword;
    }

	/**
	 * Return an instance of the Resque_Redis class instantiated for Resque.
	 *
	 * @return Resque_Redis Instance of Resque_Redis.
	 */
	public static function redis()
	{
		$pid = getmypid();
		if (self::$pid !== $pid) {
			self::$redis = null;
			self::$pid   = $pid;
		}

		if (is_null(self::$redis)) {
		    self::$redis = new Resque_Redis(self::$redisHost, self::$redisPort);
		    if (! is_null(self::$redisPassword)) {
		        self::$redis->auth(self::$redisPassword);
		    }
		    if (! is_null(self::$redisDatabase)) {
		        self::$redis->select(self::$redisDatabase);
		    }
		}
		return self::$redis;
	}

	/**
	 * Push a job to the end of a specific queue. If the queue does not
	 * exist, then create it as well.
	 *
	 * @param string $queue The name of the queue to add the job to.
	 * @param array $item Job description as an array to be JSON encoded.
	 */
	public static function push($queue, $item)
	{
		self::redis()->sadd('queues', $queue);
		self::redis()->rpush('queue:' . $queue, json_encode($item));
	}

	/**
	 * Pop an item off the end of the specified queue, decode it and
	 * return it.
	 *
	 * @param string $queue The name of the queue to fetch an item from.
	 * @return array Decoded item from the queue.
	 */
	public static function pop($queue)
	{
		$item = self::redis()->lpop('queue:' . $queue);
		if(!$item) {
			return;
		}

		return json_decode($item, true);
	}

	/**
	 * Return the size (number of pending jobs) of the specified queue.
	 *
	 * @param $queue name of the queue to be checked for pending jobs
	 *
	 * @return int The size of the queue.
	 */
	public static function size($queue)
	{
		return self::redis()->llen('queue:' . $queue);
	}

	/**
	 * Create a new job and save it to the specified queue.
	 *
	 * @param string $queue The name of the queue to place the job in.
	 * @param string $class The name of the class that contains the code to execute the job.
	 * @param array $args Any optional arguments that should be passed when the job is executed.
	 * @param boolean $trackStatus Set to true to be able to monitor the status of a job.
	 *
	 * @return string
	 */
	public static function enqueue($queue, $class, $args = null, $trackStatus = false)
	{
		require_once dirname(__FILE__) . '/Resque/Job.php';
		$result = Resque_Job::create($queue, $class, $args, $trackStatus);
		if ($result) {
			Resque_Event::trigger('afterEnqueue', array(
				'class' => $class,
				'args'  => $args,
				'queue' => $queue,
			));
		}

		return $result;
	}

	/**
	 * Reserve and return the next available job in the specified queue.
	 *
	 * @param string $queue Queue to fetch next available job from.
	 * @return Resque_Job Instance of Resque_Job to be processed, false if none or error.
	 */
	public static function reserve($queue)
	{
		require_once dirname(__FILE__) . '/Resque/Job.php';
		return Resque_Job::reserve($queue);
	}

	/**
	 * Get an array of all known queues.
	 *
	 * @return array Array of queues.
	 */
	public static function queues()
	{
		$queues = self::redis()->smembers('queues');
		if(!is_array($queues)) {
			$queues = array();
		}
		return $queues;
	}
}
