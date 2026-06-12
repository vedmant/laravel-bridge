<?php

namespace Bref\LaravelBridge\Support;

/**
 * @see https://github.com/laravel/vapor-core/blob/master/src/Arr.php
 */
final class MultipartArray
{
    /**
     * Set a multi-part body array value in the given array.
     *
     * @param  array<string|int, mixed>  $array
     * @return array<string|int, mixed>
     */
    public static function setMultiPartArrayValue(array $array, string $name, mixed $value): array
    {
        $segments = explode('[', $name);

        $pointer = &$array;

        foreach ($segments as $key => $segment) {
            // If this is our first time through the loop we will just grab the initial
            // key's part of the array. After this we will start digging deeper into
            // the array as needed until we get to the correct depth in the array.
            if ($key === 0) {
                $pointer = &$pointer[$segment];

                continue;
            }

            // If this segment is malformed, we will just use the key as-is since there
            // is nothing we can do with it from here. We will return the array back
            // to the caller with the value set. We cannot continue looping on it.
            if (static::malformedMultipartSegment($segment)) {
                $array[$name] = $value;

                return $array;
            }

            $segment = substr($segment, 0, -1);

            // If the segment is empty after trimming off the closing bracket, it means
            // we are at the end of the segment and are ready to set the value so we
            // can grab a pointer to the array location and set it after the loop.
            if ($segment === '') {
                $pointer = &$pointer[];
            } else {
                $pointer = &$pointer[$segment];
            }
        }

        $pointer = $value;

        return $array;
    }

    /**
     * Determine if the given multi-part value segment is malformed.
     *
     * This can occur when there are two [[ or no closing bracket.
     */
    protected static function malformedMultipartSegment(string $segment): bool
    {
        return $segment === '' || substr($segment, -1) !== ']';
    }
}
