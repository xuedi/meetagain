<?php declare(strict_types=1);

namespace Tests\Unit\Metrics;

use RuntimeException;

final class UdpListener
{
    /** @var resource */
    private $socket;

    public function __construct()
    {
        $socket = stream_socket_server('udp://127.0.0.1:0', $errorCode, $errorMessage, STREAM_SERVER_BIND);
        if ($socket === false) {
            throw new RuntimeException($errorMessage);
        }
        $this->socket = $socket;
    }

    public function dsn(string $query = 'app=test'): string
    {
        return sprintf('udp://%s?%s', stream_socket_get_name($this->socket, false), $query);
    }

    /**
     * @return list<string>
     */
    public function receive(): array
    {
        $datagrams = [];
        while (true) {
            $read = [$this->socket];
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 0, 200_000) !== 1) {
                return $datagrams;
            }
            $datagrams[] = (string) stream_socket_recvfrom($this->socket, 65_535);
        }
    }
}
