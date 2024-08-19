<?php declare(strict_types=1);

namespace Helioviewer\Api\Sentry;

/**
 * Sentry implementation
 * This class can be used to communicate Sentry with given client
 * @package Helioviewer\Api\Sentry
 * @author  Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */
class Sentry
{
    // This is a private singleton static variable that holds an instance of this class. 
    public static ?ClientInterface $client = null;

    // This is a private array that stores the contexts to be sent to Sentry.
    public static array $contexts = [];

    /*
     * This constructor initialized Sentry functionality with using given configuration.
     *
     * @param array $config Configuration params to talk with Sentry.
     * ex: [ 
     *      'environment' => HV_APP_ENV ?? 'dev',
     *      'sample_rate' => HV_SENTRY_SAMPLE_RATE ?? 0.1,
     *      'enabled' => HV_SENTRY_ENABLED ?? false,
     *      'dsn' => HV_SENTRY_DSN,
     *      'client' => new VoidClient(),
     *   
     */
    public static function init(array $config): void 
    {
        // if client try to load class with empty config , 
        // use a sample config with dispabled
        if(!array_key_exists('enabled', $config) || !is_bool($config['enabled'])) {
            $config = ['enabled' => false];
        }


        // if sentry not enabled, use a void client
        self::$client = $config['enabled'] ? new Client($config) : new VoidClient($config);

        // if there is an already given client , use that one; 
        if(array_key_exists('client', $config) && $config['client'] instanceOf ClientInterface) {
            self::$client = $config['client'];
        }

        // context refreshed
        self:;$contexts = [];

    }

    /*
     * This function captures the given Exception and sends it to Sentry via client
     *
     * @param \Throwable $exception Exception tobe captured
     * @return @void
     */
    public static function capture(\Throwable $exception): void 
    {
        if (null === self::$client) {
            throw new \RuntimeException("Sentry client should be initialized like; Sentry::init(\$config)");
        }

        self::$client->capture($exception);
    }

    /*
     * This function sends the given message to Sentry via client
     *
     * @param string $message any given message we want to track in Sentry
     * @return @void
     */
    public static function message(string $message): void 
    {
        if (null === self::$client) {
            throw new \RuntimeException("Sentry client should be initialized like; Sentry::init(\$config)");
        }

        self::$client->message($message);
    }

    /*
     * This function upserts internal contexts to be sent to Sentry
     *
     * @param string               $name   The name of the context. | ex : "Movie variables" , "Request Vars"
     * @param array<string, mixed> $params The parameters in the context.| ex : "[ 'action' => 'foo', 'module' => 'bar']
     * @throws \InvalidArgumentException  if all the keys of $params are not string.
     * @return @void
     */
    public static function setContext(string $name, array $params): void 
    {
        if(!self::validateContextParams($params)) {
            throw new \InvalidArgumentException(sprintf("Context:%s should be array<string, mixed>",$name));
        }

        if(!array_key_exists($name, self::$contexts)) {
            self::$contexts[$name] = $params;
        } else {
            self::$contexts[$name] = array_merge(self::$contexts[$name], $params);
        }

        self::$client->setContext($name, self::$contexts[$name]);
    }

    /**
     * Function to check context params array should be all string keyed
     * 
     * @param array $params The parameters of the context.
     * @return bool True if all the keys of $params , is string, false otherwise.
     **/
    public static function validateContextParams(array $params): bool 
    {
        $keys = array_keys($params);
        $all_string = array_reduce($keys, function($cur, $key) {
            return $cur && is_string($key);
        }, true); 

        return $all_string;
    }

}
