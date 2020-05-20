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
            try {
                echo 'Thread Id: ' . $this->getId() . ', run Task loop: ' . $key . PHP_EOL;
                (yield $value);
                if (1 == $key) {
                    echo 'Thread Id: ' . $this->getId() . ', call sleep 5s ' . PHP_EOL;
                    $this->sleep(5);
                }
            } catch (\InterruptedException $e) {
                echo 'Yes, I caught an interrupt exception, and status is : ' . (string) YieldThread::interrupted() . PHP_EOL;
            }
        }

        return '0';
    }
}


class Task2 extends YieldThread
{
    protected $max = 1;
    protected $t = null;

    public function __construct($max = 1, YieldThread   $t)
    {
        $this->max = $max;
        $this->t = $t;
        parent::__construct();
    }

    /**
     * @return Generator|int
     */
    public function run()
    {
        $arr = range(0, intval($this->max));
        foreach ($arr as $key => $value) {
            if (2 == $key) {
                echo 'Thread Id: ' . $this->getId() . ', call this->t->join ' . PHP_EOL;
                (yield $this->t->interrupt());
            } elseif (5 == $key) {
//                (yield $this->t->isInterrupted());
            }
            echo 'Thread Id: ' . $this->getId() . ', run Task loop: ' . $key . PHP_EOL;
            (yield $value);
        }

        return 0;
    }
}

class YieldSchedulerDemo2
{
    /**
     * @param integer $argc
     * @param array   $argv
     *
     * @return void|Generator
     */
    public static function main($argc, $argv)
    {

        $t = new \Task(7);
        $t3 = new \Task2(5, $t);
        echo 'start task 1' . PHP_EOL;
        $t->start();
        echo 'start task 3' . PHP_EOL;
        $t3->start();
        echo 'wait task 1' . PHP_EOL;
        yield YieldThread::wait($t);
        yield
        yield;

        echo 'main run finished, task 1 return: ' . $t->getReturn() . PHP_EOL;
        return 0;
    }

}
