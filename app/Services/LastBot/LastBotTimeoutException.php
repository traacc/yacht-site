<?php

declare(strict_types=1);

namespace App\Services\LastBot;

/** LastBot не ответил за отведённое время. */
class LastBotTimeoutException extends LastBotException {}
