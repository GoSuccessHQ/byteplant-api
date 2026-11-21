<?php

declare(strict_types=1);

namespace GoSuccess\Byteplant\Exception;

use GoSuccess\Byteplant\Http\Response;

/**
 * The rate limit of the account is exceeded (`118`, or HTTP 429). Byteplant
 * recommends waiting five minutes; the default limit is 100 requests in 300
 * seconds.
 */
final class RateLimitException extends ApiException
{
    /**
     * @param int|null $retryAfter Seconds until the current rate limit interval ends
     *                             (`ratelimit_seconds`), if Byteplant sent it.
     */
    public function __construct(
        string $message,
        int $httpStatus,
        ?int $status,
        string $info,
        Response $response,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $httpStatus, $status, $info, $response);
    }
}
