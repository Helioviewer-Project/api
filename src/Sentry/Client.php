<?php declare(strict_types=1);

namespace Helioviewer\Api\Sentry;

/**
 * Sentry client 
 * @package Helioviewer\Api\Sentry
 * @author  Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

class Client implements ClientInterface 
{
    /**
    * Create a sentry client.
    *
    * @param array $config The array that holds all configuration.
    * @return @void
    */
    public function __construct(array $config)
    {
        \Sentry\init([
            'dsn' => $config['dsn'],
            'sample_rate' => (float)$config['sample_rate'],
            'environment' => $config['environment'],
        ]);
    }

    /**
    * Captures an exception and sends it to Sentry.
    *
    * @param \Throwable $exception The exception to capture.
    * @return @void
    */
    public function capture(\Throwable $exception): void
    {
        \Sentry\captureException($exception);
    }

    /**
    * Sends a message to Sentry.
    *
    * @param string $message The message to send.
    * @return @void
    */
    public function message(string $message): void
    {
        \Sentry\captureMessage($message);
    }

    /**
    * Sets the context for the Sentry client.
    * Those variables will be sent to Sentry 
    *
    * @param string               $name   The name of the context.
    * @param array<string, mixed> $params The parameters in the context.
    * @return @void
    */
    public function setContext(string $name, array $params): void
    {
        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($name, $params): void {
            $scope->setContext($name, $params);
        });
    }


    /**
    * Sets the tag value for the Sentry client.
    * Those variables will be sent to Sentry 
    *
    * @param string  $tag   The name of the tag.
    * @param string  $value The value of the tag.
    * @return @void
    */
    public function setTag(string $tag, string $value): void
    {
        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($tag, $value): void {
            $scope->setTag($tag, $value);
        });
    }
}

