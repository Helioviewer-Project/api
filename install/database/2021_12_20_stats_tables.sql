CREATE TABLE `movies_jpx` (
      `id`                INT unsigned NOT NULL auto_increment,
      `timestamp`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `reqStartDate`      datetime NOT NULL,
      `reqEndDate`        datetime NOT NULL,
      `sourceId`          INT unsigned,
       PRIMARY KEY (`id`),
       KEY `sourceId` (`sourceId`)
    ) DEFAULT CHARSET=utf8;

CREATE TABLE `redis_stats` (
        `datetime`       datetime NOT NULL,
        `action`         varchar(32) NOT NULL,
        `count`          int unsigned NOT NULL,
        PRIMARY KEY (`datetime`, `action`)
    ) DEFAULT CHARSET=utf8;

CREATE TABLE `rate_limit_exceeded` (
        `datetime`    datetime NOT NULL,
        `identifier`  varchar(39) NOT NULL,
        `count`       int unsigned NOT NULL,
        PRIMARY KEY (`datetime`, `identifier`)
    ) DEFAULT CHARSET=utf8;
