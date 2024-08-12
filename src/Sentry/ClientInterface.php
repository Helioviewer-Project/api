<?php declare(strict_types=1);

namespace Helioviewer\Api\Sentry;

/**
 * Sentry client interface 
 * @package Helioviewer\Api\Sentry
 * @author  Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */
interface ClientInterface
{
    /**
    * Captures an exception and sends it to Sentry.
    *
    * @param \Throwable $exception The exception to capture.
    * @return @void
    */
    public function capture(\Throwable $exception): void;

    /**
    * Sends a message to Sentry.
    *
    * @param string $e The message to send.
    * @return @void
    */
    public function message(string $message): void;

    /**
    * Sets the context for the Sentry client.
    *
    * @param string               $name   The name of the context.
    * @param array<string, mixed> $params The parameters in the context.
    * @return @void
    */
    public function setContext(string $name, array $params): void;
}
