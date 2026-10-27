<?php

namespace App\Messaging;

use RuntimeException;

/** RabbitMQ trả message về vì không có queue nào bind routing key (lỗi cấu hình, không phải broker lỗi). */
class UnroutableMessageException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Không có queue nào nhận routing key này (unroutable).');
    }
}
