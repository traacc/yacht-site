<?php

declare(strict_types=1);

namespace App\Services\LastBot;

/**
 * Минимальный синхронный клиент ActionCable (Rails) поверх WebSocket.
 *
 * Нужен ровно для одного: отправить реплику в уже открытый диалог LastBot —
 * REST у них умеет только создать диалог первым сообщением, продолжение идёт
 * исключительно через канал (@see LastBotClient::sendToThread()). Готового
 * WebSocket-клиента в зависимостях нет, поэтому протокол (RFC 6455) реализован
 * здесь в объёме, который нужен для короткой сессии «подключиться → подписаться
 * → perform → дождаться эха → закрыть»: без расширений, без сжатия, фрагменты
 * от сервера собираются, ping получает pong.
 */
class ActionCableClient
{
    private const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    private const OP_CONTINUATION = 0x0;

    private const OP_TEXT = 0x1;

    private const OP_CLOSE = 0x8;

    private const OP_PING = 0x9;

    private const OP_PONG = 0xA;

    /** Потолок одного кадра: канал возвращает сообщения чата, не файлы. */
    private const MAX_FRAME = 8 * 1024 * 1024;

    /** @var resource|null */
    private $socket = null;

    /** Байты, прочитанные из сокета, но ещё не разобранные в кадры. */
    private string $buffer = '';

    public function __construct(
        private readonly int $timeout = 20,
    ) {}

    /**
     * Открывает соединение и дожидается приветствия сервера ({"type":"welcome"}).
     *
     * @param  string  $url  wss://host/cable?token=…
     * @param  string|null  $origin  Rails проверяет Origin по allowed_request_origins
     */
    public function connect(string $url, ?string $origin = null): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'], $parts['scheme'])) {
            throw new LastBotException('Некорректный адрес WebSocket: '.$url);
        }

        $secure = $parts['scheme'] === 'wss';
        $host = $parts['host'];
        $port = $parts['port'] ?? ($secure ? 443 : 80);
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        $context = stream_context_create(['ssl' => [
            'peer_name' => $host,
            'SNI_enabled' => true,
            'verify_peer' => true,
            'verify_peer_name' => true,
        ]]);

        $socket = @stream_socket_client(
            ($secure ? 'ssl://' : 'tcp://').$host.':'.$port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($socket === false) {
            throw new LastBotException("Не удалось подключиться к {$host}:{$port}: {$errstr} ({$errno})");
        }

        // Таймаут на запись: зависший сокет не должен вешать воркер очереди.
        stream_set_timeout($socket, $this->timeout);

        $this->socket = $socket;
        $this->buffer = '';

        $key = base64_encode(random_bytes(16));
        $hostHeader = $host.(isset($parts['port']) ? ':'.$parts['port'] : '');

        $request = "GET {$path} HTTP/1.1\r\n"
            ."Host: {$hostHeader}\r\n"
            ."Upgrade: websocket\r\n"
            ."Connection: Upgrade\r\n"
            ."Sec-WebSocket-Key: {$key}\r\n"
            ."Sec-WebSocket-Version: 13\r\n"
            ."Sec-WebSocket-Protocol: actioncable-v1-json, actioncable-unsupported\r\n"
            .($origin !== null && $origin !== '' ? "Origin: {$origin}\r\n" : '')
            ."\r\n";

        $this->write($request);

        $deadline = microtime(true) + $this->timeout;

        while (! str_contains($this->buffer, "\r\n\r\n")) {
            $this->fill($deadline);
        }

        [$head, $this->buffer] = explode("\r\n\r\n", $this->buffer, 2);

        $lines = explode("\r\n", $head);

        if (! preg_match('~^HTTP/1\.[01] 101~', $lines[0])) {
            throw new LastBotException('Сервер отказал в WebSocket-соединении: '.$lines[0]);
        }

        $expected = base64_encode(sha1($key.self::GUID, true));
        $accept = null;

        foreach (array_slice($lines, 1) as $line) {
            if (stripos($line, 'Sec-WebSocket-Accept:') === 0) {
                $accept = trim(substr($line, strlen('Sec-WebSocket-Accept:')));
            }
        }

        if ($accept !== $expected) {
            throw new LastBotException('Некорректный ответ на WebSocket-рукопожатие.');
        }

        $this->waitFor(static fn (array $frame): bool => ($frame['type'] ?? null) === 'welcome', 'welcome');
    }

    /**
     * Подписка на канал. Идентификатор — JSON параметров канала, по нему же
     * сервер адресует ответы, поэтому возвращаем его для perform()/receive().
     *
     * @param  array<string, mixed>  $params  channel + параметры подписки
     */
    public function subscribe(array $params): string
    {
        $identifier = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $this->send(['command' => 'subscribe', 'identifier' => $identifier]);

        $reply = $this->waitFor(
            static fn (array $frame): bool => ($frame['identifier'] ?? null) === $identifier
                && in_array($frame['type'] ?? null, ['confirm_subscription', 'reject_subscription'], true),
            'confirm_subscription',
        );

        if ($reply['type'] === 'reject_subscription') {
            throw new LastBotException('LastBot отклонил подписку на канал '.($params['channel'] ?? '?').'.');
        }

        return $identifier;
    }

    /**
     * Вызов метода канала — аналог subscription.perform(action, data) в JS.
     *
     * @param  array<string, mixed>  $data
     */
    public function perform(string $identifier, string $action, array $data): void
    {
        $data['action'] = $action;

        $this->send([
            'command' => 'message',
            'identifier' => $identifier,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Ждёт полезное сообщение канала, удовлетворяющее условию.
     * null — не дождались за $seconds.
     *
     * @param  callable(array<string, mixed>): bool  $matches  получает поле message
     * @return array<string, mixed>|null
     */
    public function receive(string $identifier, callable $matches, int $seconds): ?array
    {
        try {
            $frame = $this->waitFor(
                static fn (array $frame): bool => ($frame['identifier'] ?? null) === $identifier
                    && is_array($frame['message'] ?? null)
                    && $matches($frame['message']),
                'message',
                $seconds,
            );
        } catch (LastBotTimeoutException) {
            return null;
        }

        return $frame['message'];
    }

    public function close(): void
    {
        if ($this->socket === null) {
            return;
        }

        try {
            // 1000 — нормальное закрытие; ответный close не ждём.
            $this->writeFrame(self::OP_CLOSE, pack('n', 1000));
        } catch (\Throwable) {
            // Соединение уже могло оборваться — закрываем сокет в любом случае.
        }

        fclose($this->socket);
        $this->socket = null;
        $this->buffer = '';
    }

    public function __destruct()
    {
        $this->close();
    }

    /** @param  array<string, mixed>  $payload */
    private function send(array $payload): void
    {
        $this->writeFrame(
            self::OP_TEXT,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Читает JSON-сообщения ActionCable, пока не встретится нужное.
     * Служебные ping сервера пропускаются; disconnect — ошибка.
     *
     * @param  callable(array<string, mixed>): bool  $matches
     * @return array<string, mixed>
     */
    private function waitFor(callable $matches, string $what, ?int $seconds = null): array
    {
        $deadline = microtime(true) + ($seconds ?? $this->timeout);

        while (true) {
            $text = $this->readMessage($deadline, $what);
            $frame = json_decode($text, true);

            if (! is_array($frame)) {
                continue;
            }

            if (($frame['type'] ?? null) === 'disconnect') {
                throw new LastBotException('LastBot закрыл соединение: '.($frame['reason'] ?? 'без причины').'.');
            }

            if ($matches($frame)) {
                return $frame;
            }
        }
    }

    /** Следующее текстовое сообщение (с учётом фрагментации и управляющих кадров). */
    private function readMessage(float $deadline, string $what): string
    {
        $message = '';

        while (true) {
            [$fin, $opcode, $payload] = $this->readFrame($deadline, $what);

            switch ($opcode) {
                case self::OP_PING:
                    $this->writeFrame(self::OP_PONG, $payload);

                    continue 2;
                case self::OP_PONG:
                    continue 2;
                case self::OP_CLOSE:
                    $code = strlen($payload) >= 2 ? unpack('n', substr($payload, 0, 2))[1] : 0;

                    throw new LastBotException("WebSocket закрыт сервером (код {$code}).");
                case self::OP_TEXT:
                case self::OP_CONTINUATION:
                    $message .= $payload;

                    if ($fin) {
                        return $message;
                    }

                    continue 2;
                default:
                    // Бинарные кадры ActionCable с JSON-протоколом не шлёт.
                    continue 2;
            }
        }
    }

    /** @return array{0: bool, 1: int, 2: string} */
    private function readFrame(float $deadline, string $what): array
    {
        $header = $this->take(2, $deadline, $what);
        $first = ord($header[0]);
        $second = ord($header[1]);

        $fin = ($first & 0x80) !== 0;
        $opcode = $first & 0x0F;
        $masked = ($second & 0x80) !== 0;
        $length = $second & 0x7F;

        if ($length === 126) {
            $length = unpack('n', $this->take(2, $deadline, $what))[1];
        } elseif ($length === 127) {
            $length = unpack('J', $this->take(8, $deadline, $what))[1];
        }

        if ($length > self::MAX_FRAME) {
            throw new LastBotException('Слишком большой WebSocket-кадр: '.$length.' байт.');
        }

        $mask = $masked ? $this->take(4, $deadline, $what) : '';
        $payload = $length > 0 ? $this->take($length, $deadline, $what) : '';

        if ($masked) {
            $payload = $this->applyMask($payload, $mask);
        }

        return [$fin, $opcode, $payload];
    }

    /** Клиентские кадры по RFC обязаны быть замаскированы. */
    private function writeFrame(int $opcode, string $payload): void
    {
        $length = strlen($payload);
        $frame = chr(0x80 | $opcode);

        if ($length < 126) {
            $frame .= chr(0x80 | $length);
        } elseif ($length < 65536) {
            $frame .= chr(0x80 | 126).pack('n', $length);
        } else {
            $frame .= chr(0x80 | 127).pack('J', $length);
        }

        $mask = random_bytes(4);

        $this->write($frame.$mask.$this->applyMask($payload, $mask));
    }

    private function applyMask(string $payload, string $mask): string
    {
        $length = strlen($payload);

        if ($length === 0) {
            return '';
        }

        return $payload ^ substr(str_repeat($mask, intdiv($length, 4) + 1), 0, $length);
    }

    private function take(int $bytes, float $deadline, string $what): string
    {
        while (strlen($this->buffer) < $bytes) {
            $this->fill($deadline, $what);
        }

        $chunk = substr($this->buffer, 0, $bytes);
        $this->buffer = (string) substr($this->buffer, $bytes);

        return $chunk;
    }

    private function fill(float $deadline, string $what = 'handshake'): void
    {
        $socket = $this->socket ?? throw new LastBotException('WebSocket не подключён.');

        $left = $deadline - microtime(true);

        if ($left <= 0) {
            throw new LastBotTimeoutException("Не дождались ответа LastBot ({$what}).");
        }

        // У TLS-потока данные могут лежать в буфере OpenSSL, и stream_select их
        // не видит, поэтому сначала пробуем неблокирующее чтение.
        stream_set_blocking($socket, false);
        $chunk = fread($socket, 65536);
        stream_set_blocking($socket, true);

        if ($chunk !== false && $chunk !== '') {
            $this->buffer .= $chunk;

            return;
        }

        if (feof($socket)) {
            throw new LastBotException('LastBot разорвал WebSocket-соединение.');
        }

        $read = [$socket];
        $write = $except = null;
        $seconds = (int) floor($left);
        $micro = (int) (($left - $seconds) * 1_000_000);

        if (@stream_select($read, $write, $except, $seconds, $micro) === 0) {
            throw new LastBotTimeoutException("Не дождались ответа LastBot ({$what}).");
        }
    }

    private function write(string $data): void
    {
        $socket = $this->socket ?? throw new LastBotException('WebSocket не подключён.');

        while ($data !== '') {
            $written = @fwrite($socket, $data);

            if ($written === false || $written === 0) {
                throw new LastBotException('Не удалось записать в WebSocket.');
            }

            $data = substr($data, $written);
        }
    }
}
