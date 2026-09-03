<?php

declare(strict_types=1);

namespace NetOs\Balena\Exceptions;

/**
 * The balena API rejected the request payload (400 or 422).
 */
class ValidationException extends BalenaException {}
