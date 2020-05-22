<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../src/functions.php';

define('DEBUG_YIELD', 1);

use LikeThread\YieldThread;
use LikeThread\YieldChannel;

class Task extends LikeThread\YieldThread
{
    protected $max = 1;
    protected $chan;

    public function __construct($max = 1, YieldChannel $channel)
    {
        $this->max = $max;
        $this->chan = $channel;
        parent::__construct();
    }

    /**
     * @return Generator|int
     */
    public function run()
    {
        $arr = range(0, intval($this->max));
        foreach ($arr as $key => $value) {
            echo 'Thread Id: ' . $this->getId() . ', run Task loop: ' . $key . PHP_EOL;
            (yield $value);
            if (5 == $key) {

                echo 'Thread Id: ' . $this->getId() . ', call put data: '  . PHP_EOL;
                yield $this->chan->put('the thread id: ' . $this->getId() . ', hello!');
            }
        }

        return '0';
    }
}

class Task2 extends YieldThread
{
    protected $max = 1;
    protected $chan = null;

    public function __construct($max = 1, YieldChannel $channel)
    {
        $this->max = $max;
        $this->chan = $channel;
        parent::__construct();
    }

    /**
     * @return Generator|int|mixed
     * @throws Exception
     */
    public function run()
    {
        $arr = range(0, intval($this->max));
        foreach ($arr as $key => $value) {
            if (2 == $key) {
                echo 'Thread Id: ' . $this->getId() . ', call this->outChan ' . PHP_EOL;
                $data = (yield $this->chan->pull($this->chan));
                echo 'get Channel data ' . $data . PHP_EOL;
            }
            echo 'Thread Id: ' . $this->getId() . ', run Task loop: ' . $key . PHP_EOL;
            (yield $value);
        }

        return 0;
    }
}

class Demo4
{
    /**
     * @param integer $argc
     * @param array   $argv
     *
     * @return void|Generator
     */
    public static function main($argc, $argv)
    {

        $chan1 = \LikeThread\makeChan('chan1');

        $t2 = new \Task(7, $chan1);
        $t3 = new \Task2(5, $chan1);

        echo 'start task 2' . PHP_EOL;
        $t2->start();
        echo 'start task 3' . PHP_EOL;
        $t3->start();
        echo 'wait task 3' . PHP_EOL;
        yield YieldThread::wait($t2);
        yield YieldThread::wait($t3);
        yield;

        echo 'main run finished, task 2 return: ' . $t2->getReturn() . PHP_EOL;
        return 0;
    }

}
