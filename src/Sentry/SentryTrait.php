<?php declare(strict_types=1);

namespace Helioviewer\Api\Sentry;

/**
 * Trait SentryTrait
 * This trait provides methods for capturing exceptions and messages with Sentry.
 * @package Helioviewer\Api\Sentry
 * @author  Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */
trait SentryTrait
{
    /**
    * Captures an exception with Sentry.
    * 
    * @param \Throwable $exception The exception to capture.
    * @return @void
    */
    public function sentryCapture(\Throwable $exception): void 
    {
        Sentry::get()->capture($exception);
    }

    /**
    * Sends a message to Sentry.
    * 
    * @param string $message The message to send.
    * @return @void
    */
    public function sentryMessage(string $message): void 
    {
        Sentry::get()->message($message);
    } 

    /**
    * Sets a context for the current request.
    * This context varialbes 
    * 
    * @param string               $name   The name of the context.
    * @param array<string, mixed> $params The parameters in the context.
    * @return @void
    */
    public function setContext(string $name, array $params): void 
    {
        Sentry::get()->setContext($name, $this->contexts[$name]);
    }
}
