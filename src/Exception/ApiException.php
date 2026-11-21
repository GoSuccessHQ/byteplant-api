<?php

declare(strict_types=1);

namespace GoSuccess\Byteplant\Exception;

use GoSuccess\Byteplant\Http\Response;
use RuntimeException;

/**
 * Byteplant could not verify the address: an API error (`118`, `119`) or an
 * HTTP error status.
 *
 * A result about the address, even a bad one, is never an exception; it comes
 * back as a {@see \GoSuccess\Byteplant\Result\Verification}.
 */
class ApiException extends RuntimeException implements ByteplantException
{
    /**
     * @param int      $httpStatus HTTP status code of the response.
     * @param int|null $status     Byteplant's code (`status`), if the body carried one.
     * @param string   $info       Byteplant's short description (`info`), if any.
     * @param Response $response   The answer unchanged.
     */
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        public readonly ?int $status,
        public readonly string $info,
        public readonly Response $response,
    ) {
        parent::__construct($message, $status ?? $httpStatus);
    }
}
