<?php

namespace App\Services\Files;

use RuntimeException;

/** clamd INSTREAM protocol over TCP. Fails closed: any scanner error rejects the file. */
final class ClamAvScanner implements Scanner
{
    public function __construct(private string $host = '127.0.0.1', private int $port = 3310, private int $timeout = 30) {}

    public function scan(string $contents): array
    {
        $socket = @stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $error, $this->timeout);
        if ($socket === false) {
            throw new RuntimeException("ClamAV unavailable: {$error}");
        }

        stream_set_timeout($socket, $this->timeout);
        fwrite($socket, "zINSTREAM\0");
        foreach (str_split($contents, 8192) as $chunk) {
            fwrite($socket, pack('N', strlen($chunk)).$chunk);
        }
        fwrite($socket, pack('N', 0));
        $reply = trim((string) stream_get_contents($socket), "\0\n ");
        fclose($socket);

        if (str_ends_with($reply, 'OK')) {
            return ['clean' => true, 'signature' => null];
        }
        if (preg_match('/:\s(.+)\sFOUND$/', $reply, $m)) {
            return ['clean' => false, 'signature' => $m[1]];
        }

        throw new RuntimeException('Unexpected ClamAV reply.');
    }
}
