<?php

declare(strict_types=1);

namespace GoSuccess\Byteplant\Result;

use GoSuccess\Byteplant\Http\Response;

/**
 * The answer to the verification of one address.
 *
 * `status` is the code as Byteplant sent it, also one this package does not know
 * (then `resultCode` is null, and the category still follows the hundreds digit).
 */
final readonly class Verification
{
    /**
     * @param int             $status             Byteplant's result code.
     * @param ResultCode|null $resultCode         The documented code, or null for an unknown one.
     * @param string          $info               Short status description (`info`).
     * @param string          $details            Full status description (`details`).
     * @param bool|null       $freemail           Whether the address belongs to a freemail
     *                                            provider; null when the field is missing.
     * @param int|null        $rateLimitRemaining Requests left before the rate limit is
     *                                            reached (`ratelimit_remain`).
     * @param int|null        $rateLimitSeconds   Seconds left in the current rate limit
     *                                            interval (`ratelimit_seconds`).
     * @param Response        $response           Byteplant's answer unchanged.
     */
    public function __construct(
        public int $status,
        public ?ResultCode $resultCode,
        public string $info,
        public string $details,
        public ?bool $freemail,
        public ?int $rateLimitRemaining,
        public ?int $rateLimitSeconds,
        public Response $response,
    ) {}

    /**
     * Valid, suspect, invalid or indeterminate, by the hundreds digit of the status.
     */
    public function category(): Category
    {
        return Category::fromStatus($this->status);
    }

    /**
     * Whether the address accepts mail (2xx: valid, also with a catch-all policy).
     */
    public function isValid(): bool
    {
        return $this->category() === Category::Valid;
    }

    /**
     * Whether the address may work but is risky or could not be confirmed (3xx).
     */
    public function isSuspect(): bool
    {
        return $this->category() === Category::Suspect;
    }

    /**
     * Whether the address does not work (4xx).
     */
    public function isInvalid(): bool
    {
        return $this->category() === Category::Invalid;
    }

    /**
     * Whether Byteplant marks the result as worth retrying later; false for an
     * unknown code.
     */
    public function isRetryable(): bool
    {
        return $this->resultCode?->isRetryable() ?? false;
    }
}
