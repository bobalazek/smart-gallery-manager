<?php

namespace App\Message;

class QueueEntry
{
    public function __construct(array $input)
    {
        $this->input = $input;
    }

    public function getInput(): array
    {
        return $this->input;
    }
}
