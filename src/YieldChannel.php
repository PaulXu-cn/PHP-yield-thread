<?php

namespace LikeThread;

use \SplQueue;
use LikeThread\YieldThreadScheduler;

/**
 * Class YieldChannel
 *
 * @author Paul Xu
 * @package LikeThread
 */
class YieldChannel
{
    protected $name;
    protected $arr;

    /**
     * YieldChannel constructor.
     */
    public function __construct($name)
    {
        $this->name = $name;
        $this->arr = new SplQueue();
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param $data
     * @throws \Exception
     */
    public function put($data)
    {
        YieldThreadScheduler::putChannel($this->getName(), $data);
    }

    /**
     * @param null $len
     * @throws \Exception
     */
    public function pull($len = null)
    {
        YieldThreadScheduler::pullChannel($this->getName(), $len);
    }

    /**
     * @param $data
     */
    public function in($data)
    {
        $this->arr->enqueue($data);
    }

    /**
     * @param null|integer  $len
     *
     * @return array|mixed
     */
    public function out($len = null)
    {
        if (null === $len) {
            return $this->arr->dequeue();
        } else {
            $result = array();
            for ($i = 0; $i < $len; $i ++) {
                $re = $this->arr->dequeue();
                if (empty($re)) {
                    break;
                }
                $result[] = $re;
            }
            return $result;
        }
    }

}
