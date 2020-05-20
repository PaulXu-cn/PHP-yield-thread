<?php

require_once __DIR__ . '/YieldThread.php';
require_once __DIR__ . '/MainYieldTread.php';
require_once __DIR__ . '/InterruptedException.php';

if (!function_exists('array_key_last')) {
    function array_key_last($arr) {
        return key(array_reverse($arr));
    }
} else {
    // your PHP version > 7.3.0
}

if (!function_exists('micro_time')) {
    function micro_time()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }
}


/**
 * Class YieldThreadScheduler
 */
final Class YieldThreadScheduler extends YieldThread
{
    /**
     * 所有可用的线程对象数组
     *
     * @var array[Task] $tasks
     */
    static private $tasks = array();

    /**
     * 准备就绪的线程id些
     *
     * @var array[integer]  $readyTaskIds
     */
    static private $readyTaskIds = array();

    /**
     * @var array $interruptTaskIds
     */
    static private $interruptTaskIds = array();

    /**
     * 调度算法轮盘数组
     *
     * @var array $schedule
     */
    static private $schedule = array();

    /**
     * 当前线程对象
     * @var null|YieldThread  $currentThread
     */
    static protected $currentThread = null;

    /**
     * 当前线程ID
     * @var null|integer    $currentThreadId
     */
    static protected $currentThreadId = null;

    /**
     * 线程执行顺序依赖关系树
     * @var array $dependencyTree
     */
    static private $dependencyTree = array();

    /**
     * 那些被依赖的线程ID
     *
     * @var array
     */
    static private $dependencyIds = array();

    /**
     * 休息请求
     *
     * @var array $sleepRequests
     */
    static private $sleepRequests = array();

    static private $putChannels = array();

    static private $waitChannels = array();

    /**
     * @return null|YieldThread
     */
    public static function getCurrentThread()
    {
        return self::$currentThread;
    }

    /**
     * @return null
     */
    public static function getCurrentThreadId()
    {
        return self::$currentThreadId;
    }

    /**
     * @return int
     */
    public static function getActiveThreadCount()
    {
        return count(self::$readyTaskIds);
    }

    public function run()
    {}

    /**
     * 新增任务
     *
     * @param YieldThread   $task
     * @param integer       $key
     *
     * @return null|integer
     */
    static protected function add($task, $key = null)
    {
        if (null === $key) {
            self::$tasks[] = $task;
            $key = count(self::$tasks) - 1;
        } else {
            self::$tasks[$key] = $task;
        }
        return $key;
    }

    /**
     * 工作完成，移除任务
     *
     * @param $key
     */
    static private function remove($key)
    {
        if (in_array($key, array_keys(self::$tasks))) {
            // 依赖更新
            self::removeDependency($key);
            // 移除活动状态
            self::setTaskNotReady($key);
            // 删除任务
            unset(self::$tasks[$key]);
        }
    }

    /**
     * @param mixed|Generator $func
     */
    static public function setMainThread($func)
    {
        if ($func instanceof Generator) {
            $key = count(self::$tasks);
            self::$tasks[] = new MainYieldTread($func, $key);
        } else {
            // main 函数运行就结束
        }
    }

    /**
     * 任务完成，更新依赖关系
     *
     * @param integer   $id
     */
    static private function removeDependency($id)
    {
        if (isset(self::$dependencyIds[$id])) {
            $key = 0;
            foreach (self::$dependencyTree as $key => &$ids) {
                if (isset($ids[$id])) {
                    unset(self::$dependencyTree[$key][$id]);
                    unset(self::$dependencyIds[$id]);
                }
            }

            if (empty(self::$dependencyTree[$key])) {
                unset(self::$dependencyTree[$key]);
            }
        }
    }

    /**
     * 等待线程完成工作（return）
     *
     * @param YieldThread $thread
     *
     * @return null
     * @throws Exception
     */
    static protected function waitFor($thread)
    {
        $cId = self::$currentThreadId;
        $id = $thread->getId();
        if ($cId == $id) {
            throw new \Exception('You could not call join by yourself');
        }

        if (!in_array($id, array_keys(self::$tasks))) {
            // 如果 等待的线程已完成了，就别添加了
            return false;
        }

        if (isset(self::$dependencyTree[$cId])) {
            self::$dependencyTree[$cId][$id] = $id;
        } else {
            self::$dependencyTree[$cId] = array();
            self::$dependencyTree[$cId][$id] = $id;
        }
        self::$dependencyIds[$id] = $id;

        return null;
    }

    /**
     * 休眠时间
     *
     * @param YieldThread   $thread
     * @param integer       $seconds
     */
    static public function sleepThread($thread, $seconds)
    {
        self::$sleepRequests[$thread->id] = array(
            'sleep' => $seconds,
            'slept' => 0.0,
        );
    }

    /**
     * @param $key
     * @return bool
     */
    static protected function setTaskNotReady($key)
    {
        if (in_array($key, array_keys(self::$tasks))) {
            foreach (self::$schedule as $i => $value) {
                if ($value == $key) {
                    unset(self::$schedule[$i]);
                }
            }
            unset(self::$readyTaskIds[$key]);
            return true;
        } else {
            return false;
        }
    }

    /**
     * 生成任务调度排版表
     *
     * @return array
     */
    static protected function generateSchedule()
    {
        self::$schedule = array();
        foreach (self::$readyTaskIds as $key => $id) {
            self::$readyTaskIds[$key] = $key;
            // 获取任务优先级
            /**
             * @var YieldThread $taskObj
             */
            $taskObj = self::$tasks[$key];
            $priority = $taskObj->getPriority();
            // 高优先级别线程，在轮盘中占据更多位置
            self::$schedule = array_merge(self::$schedule, array_fill(0, $priority, $key));
        }

        return self::$schedule;
    }

    /**
     * 过滤处可运行的线程
     */
    static protected function filterActiveTreads()
    {
        $activeThreadIds = array();
        self::$readyTaskIds = array();
        /**
         * @var YieldThread $task
         */
        $task = null;
        foreach (self::$tasks as $key => $task) {
            $filtered = false;

            if ( in_array($task->getState(),
                array(YieldThread::STATE_NEW, YieldThread::STATE_DEAD, YieldThread::STATE_BLOCKED)) ) {
                $filtered = $filtered || true;
            }

//            if ($filtered || $task->isInterrupted()) {
//                // 如果被中断了
//                $filtered = $filtered || true;
//            }

            if (!$filtered && isset(self::$sleepRequests[$key])) {
                $sleepInfo = self::$sleepRequests[$key];
                if ($sleepInfo['sleep'] > $sleepInfo['slept']) {
                    // 还没睡够
                    $filtered = $filtered || true;
                }
            } else {
                // 没有休眠
            }

            if (!$filtered && isset(self::$dependencyTree[$key])) {
                if (!empty(self::$dependencyTree[$key])) {
                    $filtered = $filtered || true;
                }
            }

            if (!$filtered) {
                $activeThreadIds[$key] = $key;
            }
        }

        // 讲结果赋值给 调度器
        self::$readyTaskIds = $activeThreadIds;
    }

    /**
     * 消耗时间触发
     *
     * @param float $seconds    秒，带小数
     */
    static protected function toggleTimeFlies($seconds)
    {
        foreach (self::$sleepRequests as $tId => &$sleepInfo) {
            $sleepInfo['slept'] += $seconds;
            if ($sleepInfo['slept'] >= $sleepInfo['sleep']) {
                // 如果休眠时间，已足够，则踢出
                unset(self::$sleepRequests[$tId]);
            }
        }
    }

    /**
     * 模拟时钟跳动，生产环境可以去掉
     */
    static private function clock()
    {
        if (defined('DEBUG_YIELD')) {
            DEBUG_YIELD ? usleep(1000 * 100) : null ;     // 1/10 秒
        }
    }

    /**
     * 线程调度方法
     */
    static public function threadLoop()
    {
        $loopStartTime = $loopEndTime = 0.0;

        $keepRun = true;
        /**
         * @var \YieldThread    $task
         */
        $task = null;
        while($keepRun) {
            $loopStartTime = micro_time();

            self::filterActiveTreads();
            self::generateSchedule();

            $c = count(self::$schedule);
            if (0 < $c) {
                // 有任务才运行

                // 随机任务调度
                $randomKey = rand(0, count(self::$schedule) - 1);
                self::$currentThreadId = self::$schedule[$randomKey];
                /**
                 * @var \YieldThread $task
                 */
                $task = self::$tasks[self::$currentThreadId];
                self::$currentThread = $task;

                $state = $task->getState();
                $result = null;
                // 根据状态来判断如何运行
                switch ($state) {
                    case \YieldThread::STATE_NEW:
                        break;
                    case \YieldThread::STATE_READY:
                        $result = $task->start();
//                        $result = $task->sendValue(null);
                        break;
                    case \YieldThread::STATE_BLOCKED:
                        $params = array();
                        $result = $task->sendValue($params);
                        break;
                    case \YieldThread::STATE_RUNNING:
                        $params = array();
                        $result = $task->sendValue($params);
                        break;
                    case \YieldThread::STATE_INTERRUPTED:
                        $ex = new InterruptedException('主动中断了线程 ID: ' . self::$currentThreadId .'.');
                        $result = $task->sendException($ex);
                        $task->isInterrupted(true);
                        break;
                    case \YieldThread::STATE_DEAD:
                        break;
                    default:
                        break;
                }

//                $task->checkState();

                // 判断是否完成任务
                if ($task->isFinish()) {
                    self::remove(self::$currentThreadId);
                }
            }

            // 判断下任务是否都跑完了
            if (count(self::$tasks) <= 0) {
                $keepRun = false;
                break;
            }

            // 休息一下，
            self::clock();

            // 记录一个循环消失时间，告知每个线程
            $loopEndTime = micro_time();
            self::toggleTimeFlies($loopEndTime - $loopStartTime);
        }
    }

}