<?php

declare(strict_types=1);

namespace NetOs\Balena\Exceptions;

/**
 * The balena API rejected the credentials (401).
 */
class AuthenticationException extends BalenaException {}
