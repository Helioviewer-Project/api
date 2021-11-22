<?php
echo "Loading config\n";
require_once '../src/Config.php';
$config = new Config('../settings/Config.ini');

echo "Loading Redis\n";
$redis = new Redis();
echo "Connecting to Redis\n";
$redis->connect(HV_REDIS_HOST,HV_REDIS_PORT);

echo "Loading HV Database\n";
include_once HV_ROOT_DIR.'/../src/Database/Statistics.php';
$statistics = new Database_Statistics();

echo "Saving from redis to sql\n";
$statistics->saveStatisticsFromRedis($redis);

echo "Finished!";
?>
