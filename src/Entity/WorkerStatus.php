<?php

namespace SoureCode\Bundle\Worker\Entity;

enum WorkerStatus: string
{
    case OFFLINE = 'offline';
    case IDLE = 'idle';
    case PROCESSING = 'processing';
}