-- Add device column to statistics table
ALTER TABLE `statistics` ADD COLUMN device VARCHAR(64) DEFAULT 'x';
-- Add device column to redis_stats table
ALTER TABLE `redis_stats` ADD COLUMN device VARCHAR(64) DEFAULT 'x';
