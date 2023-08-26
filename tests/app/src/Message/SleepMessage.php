<?php

namespace SoureCode\Bundle\Worker\Tests\app\src\Message;

class SleepMessage
{
    private int $seconds;

    public function __construct(int $seconds)
    {
        $this->seconds = $seconds;
    }

    public function getSeconds(): int
    {
        return $this->seconds;
    }

}