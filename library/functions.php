<?php

/**
 * @param DateTime $dateTime
 * @return string[]
 */
setlocale(LC_TIME, "de_DE");
function createDateTimeInfo(DateTime $dateTime) {
    return [
        'full' => $dateTime->format(DateTime::ISO8601),
        'date' => $dateTime->format('d.m.Y'),
        'time' => $dateTime->format('H:i'),
    ];
}
