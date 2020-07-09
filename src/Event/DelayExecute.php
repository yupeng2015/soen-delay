<?php


namespace Soen\Delay\Event;


use Soen\Event\EventInterface;

class DelayExecute implements EventInterface
{
    public $job;
    public $test = 'this is test';
    public function __construct($job)
    {
        $this->job = $job;
    }
}