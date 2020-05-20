<?php

require_once __DIR__ . '/YieldThread.php';

define('DEBUG_YIELD', 1);


class Task extends YieldThread
{
    protected $max = 1;

    public function __construct($max = 1)
    {
        $this->max = $max;
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
        }

        return '0';
    }
}

class YieldSchedulerDemo1
{
    /**
     * @param integer $argc
     * @param array   $argv
     *
     * @return void|Generator
     */
    public static function main($argc, $argv)
    {

        $t = new \Task(5);
        $t2 = new \Task(10);
        $t->setPriority(YieldThread::MIN_PRIORITY);
        $t2->setPriority(YieldThread::MAX_PRIORITY);

        $t3 = new \Task(8);
        $t4 = new \Task(4);
        echo 'start task 1' . PHP_EOL;
        $t->start();
        echo 'start task 2' . PHP_EOL;
        $t3->start();
        echo 'start task 3' . PHP_EOL;
        $t2->start();
        echo 'start task 4' . PHP_EOL;
        $t4->start();
        echo 'wait task 1,3' . PHP_EOL;
        yield;
        (yield YieldThread::wait($t));
        (yield YieldThread::wait($t3));
        yield;

        echo 'main run finished, task 1 return: ' . $t->getReturn() . PHP_EOL;
        return 0;
    }

}


