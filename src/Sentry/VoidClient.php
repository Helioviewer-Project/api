<?php declare(strict_types=1);

namespace Helioviewer\Api\Sentry;

/**
 * Sentry void client 
 * This is client does not talk to sentry, 
 * We use this class if sentry is not enabled
 *
 * @package Helioviewer\Api\Sentry
 * @author  Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */
class VoidClient implements ClientInterface 
{
    public function __construct(array $config)
    {
    }

    public function capture(\Throwable $exception): void
    {
    }

    public function message(string $message): void
    {
    }

    public function setContext(string $name, array $params): void
    {
    }

    public function setTag(string $tag, string $value): void
    {
    }

}

