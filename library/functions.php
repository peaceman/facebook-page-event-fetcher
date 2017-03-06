<?php

/**
 * @param DateTime $dateTime
 * @return string[]
 */
function createDateTimeInfo(DateTime $dateTime) {
    return [
        'full' => $dateTime->format(DateTime::ISO8601),
        'date' => $dateTime->format('Y-m-d'),
        'time' => $dateTime->format('H:i:s'),
    ];
}
