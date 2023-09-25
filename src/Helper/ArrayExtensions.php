<?php declare(strict_types=1);
/**
 * This file contains helper functions for doing things with arrays
 */

/**
 * Adds the given value to the value pointed to by the given key
 * essentially $arr[$key] += $value
 * With the extra feature that if $key doesn't exist, then this will create it.
 * @param array $arr Array to use
 * @param mixed $key Key to increment
 */
function array_add(array &$arr, mixed $key, mixed $value) {
    // If the key doesn't exist, create it.
    if (!array_key_exists($key, $arr)) {
        $arr[$key] = 0;
    }
    $arr[$key] += $value;
}