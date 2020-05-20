<?php

/**
 * Class MainYieldTread
 */
class MainYieldTread extends YieldThread
{
    /**
     * @var Generator   $mainGen
     */
    protected $mainGen;

    /**
     * MainYieldTread constructor.
     *
     * @param $gen
     * @param $key
     */
    public function __construct($gen, $key)
    {
        $this->setPriority(YieldThread::NORM_PRIORITY);
        $this->setState(YieldThread::STATE_READY);
        $this->mainGen = $gen;
        $this->id = $key;
//        parent::__construct();
    }

    public function run()
    {}

    public function start()
    {
        $this->gen = $this->mainGen;

        if ($this->gen instanceof Generator) {
            $this->state = self::STATE_RUNNING;
            $this->started = true;
            return $this->gen->current();
//            return $this->gen;
        } else {
            // 非generator，运行就结束
            $this->state = self::STATE_DEAD;
            $this->started = true;
            return $this->gen;
        }
    }

}
