<?php


namespace Soen\Delay;


use Swoole\Timer;

class Polling
{
    public $duration;
    public $driver;

    function __construct(int $duration)
    {
        $this->duration = $duration;
        $this->driver = \App::redis();
    }

    public function run () {
        $i = 1;
        Timer::tick($this->duration, function (){
            $this->handle($i);
        });
    }

    public function handle (&$i) {
        $jobIds = $this->getOverdueJobIds();
        $topics = [];
        foreach ($jobIds as &$id) {
            $jobDetail = $this->getJobDetail($id);
            $topics[$jobDetail['topic']][] = $jobDetail['id'];
        }
        foreach ($topics as $topic  =>  $jobIds) {
            $this->moveJobToReadyQueue($topic, $jobIds);
        }
        $i++;
        echo "执行了{$i}次扫描";
    }

    /**
     * @param $id
     * @return Job
     */
    public function getJobDetail ($id):Job {
        $all = $this->driver->hGetAll(Config::PREFIX_JOB_POOL . $id);
        $jobDetail = new Job($all['id'], $all['body'], $all['delay'], $all['topic'], $all['ttr']);
        return $jobDetail;
    }

    /**
     * @return array
     */
    public function getOverdueJobIds () {
        $jobs = $this->driver->zRangeByScore(Config::JOB_BUCKETS, 0, time());
        return $jobs;
    }

    /**
     * @param $topic
     * @param mixed ...$ids
     */
    public function moveJobToReadyQueue ($topic, ...$ids) {
        $this->driver->multi();
        $this->driver->lPush($topic, ...$ids);
        $this->driver->zRem(Config::JOB_BUCKETS, ...$ids);
        $this->driver->exec();
    }

}

