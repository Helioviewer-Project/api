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
    private static ?Sentry $instance = null;

    // This is a private array that stores the contexts to be sent to Sentry.
    private array $contexts = [];

    /*
     * This constructor initialized Sentry functionality with using given client     
     * 
     * @param \ClientInterface $client Client tobe used to talk with Sentry.
     */
    public function __construct(
        private ClientInterface $client,
    ) {}

    /*
     * This constructor initialized Sentry functionality with using given client.
     * It always return the first initialized instance, 
     * 
     * @param array $config Client tobe used to talk with Sentry.
     * @return Sentry, singleton instance of this class 
     */
    public static function get($config = null): Sentry 
    {
        if (is_null(self::$instance)) {

            // if sentry not enabled, use a void client
            $client = $config['enabled'] ? new Client($config) : new VoidClient($config);

            self::$instance = new Sentry($client);

        }

        return self::$instance;
    }

    /*
     * This function captures the given Exception and sends it to Sentry via client
     *
     * @param \Throwable $exception Exception tobe captured
     * @return @void
     */
    public function capture(\Throwable $exception): void 
    {
        $this->client->capture($exception);
    }

    /*
     * This function sends the given message to Sentry via client
     *
     * @param string $message any given message we want to track in Sentry
     * @return @void
     */
    public function message(string $message): void 
    {
        $this->client->message($message);
    }

    /*
     * This function upserts internal contexts to be sent to Sentry
     *
     * @param string               $name   The name of the context. | ex : "Movie variables" , "Request Vars"
     * @param array<string, mixed> $params The parameters in the context.| ex : "[ 'action' => 'foo', 'module' => 'bar']
     * @throws \InvalidArgumentException  if all the keys of $params are not string.
     * @return @void
     */
    public function setContext(string $name, array $params): void 
    {
        if(!self::validateContextParams($params)) {
            throw new \InvalidArgumentException(sprintf("Context:%s should be array<string, mixed>",$name));
        }

        if(!array_key_exists($name, $this->contexts)) {
            $this->contexts[$name] = $params;
        } else {
            $this->contexts[$name] = array_merge($this->contexts[$name], $params);
        }

        $this->client->setContext($name, $this->contexts[$name]);
    }

    /**
     * Function to check context params array should be all string keyed
     * 
     * @param array $params The parameters of the context.
     * @return bool True if all the keys of $params , is string, false otherwise.
     **/
    private static function validateContextParams(array $params): bool 
    {
        $keys = array_keys($params);
        $all_string = array_reduce($keys, function($cur, $key) {
            return $cur && is_string($key);
        }, true); 

        return $all_string;
    }

    /*
     * This function returns the current context and is primarly implemented for testing purposes
     *
     * @return array<array>  
     */
    public function getContexts(): array 
    {
        return $this->contexts;
    }
}
