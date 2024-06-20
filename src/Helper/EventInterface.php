<?php declare(strict_types=1);

require_once HV_ROOT_DIR . "/../vendor/autoload.php";
include_once HV_ROOT_DIR.'/../scripts/rot_hpc.php';

use HelioviewerEventInterface\Events;

class Helper_EventInterface {
    public static function GetEvents(DateTimeInterface $start, DateInterval $length, DateTimeInterface $observationTime, ?array $sources = null): array {
        if (is_null($sources)) {
            return Events::GetAll($start, $length, $observationTime);
        } else {
            return Events::GetFromSource($sources, $start, $length, $observationTime);
        }
    }
}
