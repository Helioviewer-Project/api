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

    /**
    * Sets the tag value for the Sentry client.
    * Those variables will be sent to Sentry 
    *
    * @param string  $tag   The name of the tag.
    * @param string  $value The value of the tag.
    * @return @void
    */
    public function setTag(string $tag, string $value): void;
}
