<?php namespace LGTMBot\Test;

class MockLogger
{
    public $logData;

    public function __construct()
    {
        $this->logData = [
            'info' => [],
            'debug' => []
        ];
    }

    public function info($message)
    {
        $this->logData['info'][] = $message;
    }

    public function debug($message)
    {
        $this->logData['debug'][] = $message;
    }
}