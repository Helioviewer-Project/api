<?php
/**
* Database Information
*/
define("HV_DB_HOST", $_ENV['HV_DB_HOST'] ?? "localhost");
define("HV_DB_NAME", $_ENV['HV_DB_NAME'] ?? "helioviewer");
define("HV_DB_USER", $_ENV['HV_DB_USER'] ?? "helioviewer");
define("HV_DB_PASS", $_ENV['HV_DB_PASS'] ?? "helioviewer");

/**
 * Redis Database Information
 * Used for rate-limiting
 */
define("HV_REDIS_HOST", $_ENV['HV_REDIS_HOST'] ?? "127.0.0.1");
define("HV_REDIS_PORT", $_ENV['HV_REDIS_PORT'] ?? 6379);

/**
 * In order to enable users to submit videos to YouTube, you must register for
 * a developer key which is included with each request. For more information
 * and to request a key, see:
 * http://code.google.com/apis/youtube/overview.html
 */
define("HV_YOUTUBE_DEVELOPER_KEY", $_ENV['HV_YOUTUBE_DEVELOPER_KEY'] ?? "");
define("HV_GOOGLE_OAUTH2_CLIENT_ID", $_ENV['HV_GOOGLE_OAUTH2_CLIENT_ID'] ?? "");
define("HV_GOOGLE_OAUTH2_CLIENT_SECRET", $_ENV['HV_GOOGLE_OAUTH2_CLIENT_SECRET'] ?? "");

/**
 * Password to use when generating unique movie IDs. This can be any random
 * string, e.g. "8sHNa4ju". It is used during hashing to create public
 * video id's that can be used for sharing.
 */
define("HV_MOVIE_ID_PASS", $_ENV['HV_MOVIE_ID_PASS'] ?? "");

/**
 * bit.ly API user and key
 *
 * This is used to shorten Helioviewer.org URLs for easier sharing on
 * Twitter etc. For more information and to register for a free API key, see:
 * http://code.google.com/p/bitly-api/wiki/ApiDocumentation
 */
define("HV_BITLY_USER", $_ENV['HV_BITLY_USER'] ?? "");
define("HV_BITLY_ALLOWED_DOMAIN", $_ENV['HV_BITLY_ALLOWED_DOMAIN'] ?? ""); // string to validate correct domain name when using AJAX
define("HV_BITLY_API_KEY", $_ENV['HV_BITLY_API_KEY'] ?? "");
define("HV_SHORTENER_REDIS_DB", $_ENV['HV_SHORTENER_REDIS_DB'] ?? 10);

/**
 * Proxy Settings
 */
define("HV_PROXY_HOST", $_ENV['HV_PROXY_HOST'] ?? "");
define("HV_PROXY_USER_PASSWORD", $_ENV['HV_PROXY_USER_PASSWORD'] ?? ""); // must be a string in username:password format, leave it empty if proxy do not use authorization

/**
 * Terminal commands that need to be checked for running
 * Example:
 * serialize(array(
 * 	   'terminal command' => 'name of command'
 * ))
 *
 * TODO:
 * PHP 7 support arrays inside constants.
 */
define("TERMINAL_COMMANDS", $_ENV['TERMINAL_COMMANDS'] ?? serialize(array()));
?>
