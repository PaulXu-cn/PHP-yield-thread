<?php

namespace LikeThread;

use LikeThread\YieldThreadScheduler;
use LikeThread\InterruptedException;
use LikeThread\ChannelRegistry;
use LikeThread\YieldChannel;

/**
 * Class YieldThread
 *
 * @author Paul Xu
 */
abstract class YieldThread {

    // 新建
    CONST STATE_NEW = 0;

    // 就绪状态
    CONST STATE_READY = 1;

    // 运行中
    CONST STATE_RUNNING = 3;

    // 被阻塞
    CONST STATE_BLOCKED = 2;

    // 中断
    CONST STATE_INTERRUPTED = 4;

    // 等待管道数据就绪
    CONST STATE_WAIT_CHAN = 5;

    // 结束，死亡
    CONST STATE_DEAD = 6;

    //  线程可以具有的最高优先级。
    CONST MAX_PRIORITY = 4;

    // 线程可以具有的最低优先级。
    CONST MIN_PRIORITY = 1;

    // 分配给线程的默认优先级！
    CONST NORM_PRIORITY = 2;

    /**
     * @var \Generator   $gen
     */
    protected $gen;

    /**
     * 优先级
     *
     * @var int $priority
     */
    protected $priority = 1;

    /**
     * 线程名字
     *
     * @var string $name
     */
    protected $name = '';

    /**
     * 线程ID
     *
     * @var int $id
     */
    protected $id = 0;

    /**
     * 是否是守护线程
     *
     * @var bool $daemon
     */
    protected $daemon = false;

    /**
     * 是否开始
     *
     * @var bool
     */
    protected $started = false;

    /**
     * @var bool
     */
    protected $interrupt = false;

    /**
     * 当前线程状态
     * @var int $state
     */
    protected $state = 0;

    protected $return;

    public function __construct()
    {
        $this->priority = self::NORM_PRIORITY;
        $this->state = self::STATE_NEW;
        $this->id = YieldThreadScheduler::add($this);
    }

    /**
     * 更新状态
     */
    protected function checkState()
    {
        if (!$this->isFinish()) {
            if ($this->interrupt) {
                $this->state = self::STATE_BLOCKED;
            } elseif ($this->started) {
                $this->state = self::STATE_RUNNING;
            } else {
                $this->state = self::STATE_READY;
            }
        } else {
            $this->state = self::STATE_DEAD;
        }
    }
    /**
     * 用户自定义
     * @return mixed
     */
    abstract public function run();

    /**
     * @return \Generator|mixed
     */
    public function start()
    {
        $this->gen = static::run();

        if ($this->gen instanceof \Generator) {
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

    /**
     * 继续
     */
    public function comeOn()
    {
        $this->gen->next();
    }

    public function sendValue($value = null)
    {
        return $this->gen->send($value);
    }

    /**
     * @param \Exception|\InterruptedException $ex
     * @return mixed
     */
    public function sendException($ex)
    {
        return $this->gen->throw($ex);
    }

    /**
     * @throws \Exception   msg:Cannot get return value of a generator that hasn't returned
     * @return mixed|null
     */
    public function getReturn()
    {
        if (null === $this->return) {
            $this->return = $this->gen->getReturn();
        }

        return $this->return;
    }

    /**
     * @param mixed $value
     */
    public function putChan($chanName, $value = null)
    {

    }

    public function waitChan($chanName)
    {

    }

    /**
     * @return null
     * @throws \Exception
     */
    public function join()
    {
        return YieldThreadScheduler::waitFor($this);
    }

    /**
     * @return bool
     */
    public function isFinish()
    {
        if ($this->gen instanceof \Generator) {
            try {
                $this->getReturn();
                return true;
            } catch (\Exception $e) {
                return false;
            }
        } else {
            return true;
        }
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     */
    public function setPriority(int $priority)
    {
        $this->priority = $priority;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * 测试线程是否处于活动状态。
     * @return bool
     */
    public function isAlive()
    {
        return $this->gen->valid();
    }

    /**
     * @return int
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param integer   $state
     */
    protected function setState($state)
    {
        $this->state = $state;
    }

    public function getThreadGroup()
    {
    }

    /**
     * 自己休眠
     *
     * @param $seconds
     */
    public function sleep($seconds)
    {
        YieldThreadScheduler::sleepThread($this, $seconds);
    }

    /**
     * 中断线程。
     */
    public function interrupt()
    {
        $this->state = self::STATE_INTERRUPTED;
        $this->interrupt = true;
        return true;
        return ($this->interrupt = YieldThreadScheduler::interruptThread($this));
    }

    /**
     * 获取当前中断状态，以及是否清理
     *
     * @param null $clearInterrupted
     * @return bool
     */
    public function isInterrupted($clearInterrupted = null)
    {
        if (null === $clearInterrupted) {
            return $this->interrupt;
        }
        if (true == $clearInterrupted) {
            $this->interrupt = false;
            $this->state = self::STATE_READY;
        }
        return $this->interrupt;
    }



    public function isDaemon()
    {
        return boolval($this->daemon);
    }

    /**
     * @param $is
     * @throws \Exception
     */
    public function setDaemon($is)
    {
        $this->daemon = $is;
        if (function_exists('pcntl_fork')) {

        } else {
            throw new \Exception('pcntl_fork function not supported !');
        }
    }



    public function __toString()
    {
    }

    /**
     * 当前线程休眠
     * @param $seconds
     */
//    static public function sleep($seconds)
//    {
//        YieldThreadScheduler::sleepThread(self::currentThread()->getId(), $seconds);
//    }

    static public function interrupted()
    {
        /**
         * @var YieldThread $thread;
         */
        $thread = YieldThreadScheduler::getCurrentThread();
        return $thread->isInterrupted(true);
    }

    /**
     * @param YieldThread   $thread
     * @return  bool
     * @throws \Exception
     */
    static public function wait($thread)
    {
        return YieldThreadScheduler::waitFor($thread);
    }

    /**
     * 返回对当前正在执行的线程对象的引用。
     *
     * @return YieldThread|null
     */
    static public function currentThread()
    {
        return YieldThreadScheduler::getCurrentThread();
    }

    /**
     * PHP 自带，就不实现了
     */
//    static public function Yield(){}

    /**
     * 返回当前线程的线程组中活动线程的数目。
     */
    static public function activeCount()
    {
        return YieldThreadScheduler::getActiveThreadCount();
    }

    /**
     * 将当前线程的线程组及其子组中的每一个活动线程复制到指定的数组中。
     */
    static public function enumerate(array $threads)
    {

    }

    /**
     * 当且仅当当前线程在指定的对象上保持监视器锁时，才返回 true。
     *
     * @param \stdClass  $obj
     */
    static public function holdsLock(\stdClass   $obj)
    {

    }





    /**
     * @param YieldChannel  $channel
     * @param mixed         $data
     *
     * @throws \Exception
     */
    public function inChan($channel, $data)
    {
        YieldThreadScheduler::putChannel($channel->getName(), $data);
    }

    /**
     * @param YieldChannel $channel
     * @param null|mixed   $len
     *
     * @throws \Exception
     * @return array|mixed
     */
    public function outChan($channel, $len = null)
    {
        $this->state = self::STATE_WAIT_CHAN;
        return YieldThreadScheduler::pullChannel($channel->getName(), $len);
    }

}
