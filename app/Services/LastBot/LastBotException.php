<?php

declare(strict_types=1);

namespace App\Services\LastBot;

use RuntimeException;

/**
 * Сбой обмена с LastBot: сеть, отказ сервера, неожиданный ответ.
 * Для ответов с ошибкой code — HTTP-статус.
 */
class LastBotException extends RuntimeException {}
