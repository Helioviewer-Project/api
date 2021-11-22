<?php

require_once '../src/Config.php';
$config = new Config('../settings/Config.ini');

$redis = new Redis();
$redis->connect(HV_REDIS_HOST,HV_REDIS_PORT);

include_once HV_ROOT_DIR.'/../src/Database/Statistics.php';
$statistics = new Database_Statistics();

$statistics->saveRateLimitExceededFromRedis($redis);

?>
