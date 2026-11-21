<?php

declare(strict_types=1);

namespace GoSuccess\Byteplant\Exception;

/**
 * Byteplant answered with a server error (5xx) after the retries.
 */
final class ServerException extends ApiException {}
