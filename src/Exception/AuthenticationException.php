<?php

declare(strict_types=1);

namespace GoSuccess\Byteplant\Exception;

/**
 * The API key is invalid or the account balance is depleted (`119`), or the
 * request was refused with HTTP 401 or 403. Byteplant does not tell the two
 * causes of `119` apart.
 */
final class AuthenticationException extends ApiException {}
