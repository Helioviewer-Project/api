<?php

declare(strict_types=1);

namespace RateLimit\Exception;

use RateLimit\Rate;
use RuntimeException;

final class LimitExceeded extends RuntimeException
{
    /** @var string */
    protected $identifier;

    /** @var Rate */
    protected $rate;

    public static function for(string $identifier, Rate $rate): self
    {
        $interval = $rate->getInterval();
        $operations = $rate->getOperations();
        $exception = new self("Limit of $operations requests per $interval seconds has been exceeded by identifier: \"$identifier\". \n If you would like us to provision additional resources for your identifier, please email contact@helioviewer.org with a justification for your use case.");
        $exception->identifier = $identifier;
        $exception->rate = $rate;

        return $exception;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getRate(): Rate
    {
        return $this->rate;
    }
}
