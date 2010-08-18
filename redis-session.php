<?
namespace RedisSession;

$redisTargetPrefix = "session:php:";
$unpackItems = array();
$redisServer = "redis://127.0.0.1:6379/";


function redis_session_init($unpack = null, $server = null, $prefix = null) {
  global $unpackItems, $redisServer, $redisTargetPrefix;

  if ($unpack !== null) {
    $unpackItems = $unpack;
  }
  
  if ($server !== null) {
    $redisServer = $server;
  }

  if ($prefix !== null) {
    $redisTargetPrefix = $prefix;
  }

  session_set_save_handler('RedisSession\redis_session_open',
			   'RedisSession\redis_session_close',
			   'RedisSession\redis_session_read',
			   'RedisSession\redis_session_write',
			   'RedisSession\redis_session_destroy',
			   'RedisSession\redis_session_gc');
}



function redis_session_read($id) {
  global $redisServer, $redisTargetPrefix;

  $redisConnection = new \Predis\Client($redisServer);
  return base64_decode($redisConnection->get($redisTargetPrefix . $id));
}


function redis_session_write($id, $data) {
  global $unpackItems, $redisServer, $redisTargetPrefix;

  $redisConnection = new \Predis\Client($redisServer);
  $ttl = ini_get("session.gc_maxlifetime");
  
  $redisConnection->pipeline(function ($r) use (&$id, &$data, &$redisTargetPrefix, &$ttl, &$unpackItems) {
      $r->setex($redisTargetPrefix . $id, $ttl,
		base64_encode($data));

      foreach ($unpackItems as $item) {
	$keyname = $redisTargetPrefix . $id . ":" . $item;
	
	if (isset($_SESSION[$item])) {
	  $r->setex($keyname, $ttl, $_SESSION[$item]);
	  
	} else {
	  $r->del($keyname);
	}
      }
    });
}


function redis_session_destroy($id) {
  global $redisServer, $redisTargetPrefix;

  $redisConnection = new \Predis\Client($redisServer);
  $redisConnection->del($redisTargetPrefix . $id);

  $unpacked = $redisConnection->keys($redisTargetPrefix . $id . ":*");

  foreach ($unpacked as $unp) {
    $redisConnection->del($unp);
  }
}


// These functions are all noops for various reasons... opening has no practical meaning in
// terms of non-shared Redis connections, the same for closing. Garbage collection is handled by
// Redis anyway.
function redis_session_open($path, $name) {}
function redis_session_close() {}
function redis_session_gc($age) {}
