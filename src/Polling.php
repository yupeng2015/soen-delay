<?php


namespace Soen\Delay;


use Swoole\Timer;

class Polling
{
    public $duration;
    public $redis;
    const KEY_JOB_POOL = 'delay:job_pool';
    const PREFIX_JOB_BUCKET = 'delay:job_bucket';
    const PREFIX_READY_QUEUE = 'delay:ready_queue';
    function __construct(int $duration)
    {
        $this->duration = $duration;
        $this->redis = \App::redis();
    }

    public function run () {
        Timer::tick(1000, function (){
            $this->getOverdueJobs();
        });
    }

    public function getJob () {

    }

    /**
     * @return array
     */
    public function getOverdueJobs () {
        $jobs = $this->redis->zRangeByScore(self::PREFIX_JOB_BUCKET, 0, time());
        return $jobs;
    }


}