<?php

namespace LikeThread;

use \SplQueue;

class ChannelRegistry
{
    private static $channels = array();

    /**
     * @param $name
     *
     * @return YieldChannel
     * @throws \Exception
     */
    public static function register($name)
    {
        if (isset(self::$channels[$name])) {
            // 有了还要注册，报错
            throw new \Exception('register channel name confilct');
        } else {
            $chan = new YieldChannel($name);
            self::$channels[$name] = $chan;
            return $chan;
        }
    }

    /**
     * @param $name
     */
    public static function unregister($name)
    {
        unset(self::$channels[$name]);
    }

    /**
     * @param string $name
     *
     * @return YieldChannel
     * @throws \Exception
     */
    public static function get($name)
    {
        if (isset(self::$channels[$name])) {
            return self::$channels[$name];
        } else {
            throw new \Exception('try to get a not register channel.');
        }
    }

}
