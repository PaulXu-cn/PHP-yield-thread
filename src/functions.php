<?php

namespace LikeThread;

if (!function_exists('\LikeThread\array_key_last')) {
    function array_key_last($arr) {
        return key(array_reverse($arr));
    }
} else {
    // your PHP version > 7.3.0
}

if (!function_exists('\LikeThread\micro_time')) {
    function micro_time()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
}

if (!function_exists('\LikeThread\makeChan')) {
    /**
     * @param $channelName
     *
     * @return YieldChannel|void
     * @throws \Exception
     */
    function makeChan($channelName)
    {
        return YieldThreadScheduler::registerChannel($channelName);
    }
}
