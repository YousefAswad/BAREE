<?php

namespace App\Services\Media;

interface ImageGenerator
{
    public function generate(string $prompt, array $options = []): MediaResult;
}
