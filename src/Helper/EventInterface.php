<?php declare(strict_types=1);

require_once HV_ROOT_DIR . "/../vendor/autoload.php";
include_once HV_ROOT_DIR.'/../scripts/rot_hpc.php';

use HelioviewerEventInterface\Events;

class Helper_EventInterface {
    public static function GetEvents(DateTimeInterface $start, DateTimeInterface $end, string $observationTime, ?array $sources = null): array {
        $applyRotation = function ($hv_event) use ($observationTime) {
            // Apply solar rotation from the event time to the current observation time
            list($hv_event->hv_hpc_x, $hv_event->hv_hpc_y) = rot_hpc($hv_event->hpc_x, $hv_event->hpc_y, $hv_event->start, $observationTime);
            return $hv_event;
        };
        if (is_null($sources)) {
            return Events::GetAll($start, $end, $applyRotation);
        } else {
            return Events::GetFromSource($sources, $start, $end, $applyRotation);
        }
    }
}