<?php


namespace Soen\Delay;


use Swoole\Timer;

class Polling
{
    public $duration;
    public $redis;
    function __construct(int $duration)
    {
        $this->duration = $duration;
        $this->redis = \App::redis();
    }

    public function run () {
        Timer::tick(1000, function (){
            $this->getOverdueJob();
        });
    }

    public function getOverdueJob () {

    }


}