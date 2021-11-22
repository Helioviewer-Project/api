<?php

$redis = new Redis();
$redis->connect("127.0.0.1",6379);
$limitKeys = $redis->keys("limit/*");
$count = 0;
$accesses = [];
foreach( $limitKeys as $key ){
    $num = (int)$redis->get($key);
    array_push($accesses, $num);
}
rsort($accesses);
echo "-----------------------------------------\n";
echo count($accesses) . " unique visitors\n";
echo "-----------------------------------------\n";
echo "Top 20 Users : Number of Requests\n";
echo "-----------------------------------------\n";

foreach( $accesses as $rate ){
    echo $rate . "\n";
    $count++;
    if($count >= 20){
        break;
    }
}

echo "-----------------------------------------\n";
