<?php

namespace App\Modules\Notification\Exceptions;

use RuntimeException;

class ExpoPushException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }

    public function isDeviceNotRegistered(): bool
    {
        return $this->errorCode === 'DeviceNotRegistered';
    }
}
