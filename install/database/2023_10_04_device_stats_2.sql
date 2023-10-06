-- The purpose of this change is to remove the key on datetime, action in order to support counting devices per action.
-- Before executing this, disable any jobs executing `save_statistics_from_redis.php` so that no writes come to the table.
ALTER TABLE redis_stats
DROP PRIMARY KEY,
ADD COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
ADD INDEX dates (datetime),
ADD INDEX devices (device, action, datetime),
ADD INDEX actions (action, datetime);