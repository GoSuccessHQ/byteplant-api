<?php

declare(strict_types=1);

namespace GoSuccess\Byteplant\Exception;

use GoSuccess\Byteplant\Http\Response;
use RuntimeException;
use Throwable;

/**
 * A response body could not be read as Byteplant's answer: not JSON (e.g. a
 * proxy's HTML page), or without a numeric `status`.
 */
final class SerializationException extends RuntimeException implements ByteplantException
{
    /**
     * @param Response|null $response The answer that could not be read, unchanged.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?Response $response = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
