<?php

declare(strict_types=1);

namespace NetOs\Balena\Exceptions;

/**
 * The token is valid but lacks permission for this resource (403).
 */
class AuthorizationException extends BalenaException {}
