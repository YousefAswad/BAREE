<?php

namespace App\AI\Support;

/** Incremental Server-Sent Events parser: feed raw chunks, get complete `data:` payloads back. */
final class SseParser
{
    private string $buffer = '';

    /** @return string[] */
    public function feed(string $chunk): array
    {
        $this->buffer .= str_replace("\r\n", "\n", $chunk);
        $payloads = [];

        while (($pos = strpos($this->buffer, "\n\n")) !== false) {
            $event = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + 2);

            $data = [];
            foreach (explode("\n", $event) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $data[] = ltrim(substr($line, 5));
                }
            }
            if ($data !== []) {
                $payloads[] = implode("\n", $data);
            }
        }

        return $payloads;
    }

    /** Call after the stream ends to flush a final event without a trailing blank line. */
    public function flush(): array
    {
        $rest = trim($this->buffer);
        $this->buffer = '';

        return $rest === '' ? [] : $this->feed($rest."\n\n");
    }
}
