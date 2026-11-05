<?php

namespace App\AI\Exceptions;

use RuntimeException;

/** Transient or provider-side failure: the gateway should try the next provider. */
final class ProviderUnavailable extends RuntimeException {}
