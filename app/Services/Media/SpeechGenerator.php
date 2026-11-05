<?php

namespace App\Services\Media;

interface SpeechGenerator
{
    public function synthesize(string $text, array $options = []): MediaResult;
}
