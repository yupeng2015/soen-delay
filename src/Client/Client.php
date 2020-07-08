<?php


namespace Soen\Delay\Client;


use Soen\Delay\Config;
use Soen\Delay\Job;

class Client
{
    public $driver;
    public function __construct()
    {
        $this->driver = \App::redis();
    }

    /**
     * @param $id
     * @param $topic
     * @param $body
     * @param $delayTime
     * @param int $readyMaxLifetime
     * @return bool
     */
    public function push ($id, $topic, $body, $delayTime, $readyMaxLifetime = 604800) {
        $key = Config::PREFIX_JOB_POOL . $id;
        $this->driver->multi();
        $this->driver->hMset($key, [
                'id'    => $id,
                'topic' =>  $topic,
                'body'  =>  $body
            ]);
        $this->driver->expire($key, $delayTime + $readyMaxLifetime);
        $this->driver->zAdd(Config::JOB_BUCKETS, time() + $delayTime, $id);
        $result = $this->driver->exec();
        foreach ($result as $status) {
            if (!$status) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $topic
     * @param int $timeout
     * @return bool|Job
     */
    public function bPop ($topic, $timeout = 120) {
        $result = $this->driver->brPop([Config::PREFIX_READY_QUEUE . $topic], $timeout);
        if(empty($result)){
            return false;
        }
        $jobId = array_pop($result);
        $jobDetail= $this->driver->hGetAll(Config::PREFIX_JOB_POOL . $jobId);
        if (!$jobDetail || empty($jobDetail['topic']) || empty($jobDetail['body'])) {
            return false;
        }
        $this->driver->del(Config::PREFIX_JOB_POOL . $id);
        return new Job($jobDetail['id'], $jobDetail['body'], $jobDetail['delay'], $jobDetail['topic'], $jobDetail['ttr']);
    }

    /**
     * @param $id
     * @return bool
     */
    public function remove ($id) {
        $this->driver->multi();
        $this->driver->zRem(Config::JOB_BUCKETS, $id);
        $this->driver->del(Config::PREFIX_JOB_POOL . $id);
        $result = $this->driver->exec();
        foreach ($result as $key=>$val){
            if(!$val){
                return false;
            }
        }
        return true;
    }

}