<?php


namespace Soen\Delay;


use Soen\Delay\Event\DelayExecute;
use Swoole\Coroutine;
use Swoole\Timer;

class Polling
{
    public $duration;
    public $driver;
    public $dispatch;

    function __construct(int $duration)
    {
        $this->duration = $duration;
        $this->driver = \App::redis();
        $this->dispatch = \App::event();
    }

    public function run () {
        $i = 0;
        Timer::tick($this->duration, function ()use(&$i){
            $this->handle($i);
        });
    }

    public function handle (&$i) {
        try{
            $jobIds = $this->getOverdueJobIds();
            $topics = [];
            if(!empty($jobIds)){
                foreach ($jobIds as &$id) {
                    $jobDetail = $this->getJobDetail($id);
                    if (!$jobDetail) {
                        continue;
                    }
                    $topics[$jobDetail->topic][] = $jobDetail->id;
                }
                foreach ($topics as $topic  =>  $jobIds) {
                    $this->moveJobToReadyQueue($topic, $jobIds);
                }
                $i++;
                $this->dispatch->dispatch(new DelayExecute('this is job'));
//            echo "执行了{$i}次扫描";
            }
        }catch (\Throwable $exception){
            $message = $exception->getMessage();
            \App::log()->error($message);
            throw new \RuntimeException($message);
        }

    }

    /**
     * @param $id
     * @return Job
     */
    public function getJobDetail ($id) {
        $all = $this->driver->hGetAll(Config::PREFIX_JOB_POOL . $id);
        if (!$all || empty($all)) {
            return false;
        }
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
     * @param array $ids
     */
    public function moveJobToReadyQueue ($topic, $ids) {
        $this->driver->multi();
        call_user_func_array([$this->driver, 'lPush'], array_merge([$topic], $ids));
//        $this->driver->lPush($topic, ...$ids);
        call_user_func_array([$this->driver, 'zRem'], array_merge([Config::JOB_BUCKETS], $ids));
//        $this->driver->zRem(Config::JOB_BUCKETS, ...$ids);
        call_user_func_array([$this->driver, 'set'], ['teshu', 'hahahaha']);
//        $this->driver->set('teshu', 'hahahaha');
        $this->driver->exec();
    }

}

