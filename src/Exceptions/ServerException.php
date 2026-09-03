<?php

declare(strict_types=1);

namespace NetOs\Balena\Exceptions;

/**
 * The balena API failed to handle the request (5xx).
 */
class ServerException extends BalenaException {}
