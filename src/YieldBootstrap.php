<?php
/**
 * Yield Thread Bootstrap
 *
 * @author Paul Xu
 */
namespace LikeThread;

$path = realpath($argv[1]);

require_once $path;

$info = pathinfo($argv[1]);

$mainGen = ($info['filename'])::main($argc, $argv);

YieldThreadScheduler::setMainThread($mainGen);

YieldThreadScheduler::threadLoop();