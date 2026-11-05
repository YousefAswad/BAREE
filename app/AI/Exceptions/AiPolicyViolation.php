<?php

namespace App\AI\Exceptions;

use RuntimeException;

/** Not allowed: agent disabled, no access, no credits, invalid inputs. Never fail over on these. */
final class AiPolicyViolation extends RuntimeException {}
