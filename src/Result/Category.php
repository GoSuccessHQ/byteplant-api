<?php

declare(strict_types=1);

namespace GoSuccess\Byteplant\Result;

/**
 * The four groups of result codes, as Byteplant's result page filters them: by
 * the hundreds digit of the code.
 */
enum Category: int
{
    /** 1xx: no verdict (still running, rate limit, key, task). */
    case Indeterminate = 1;

    /** 2xx: the address accepts mail. */
    case Valid = 2;

    /** 3xx: the address may work, but is risky or could not be confirmed. */
    case Suspect = 3;

    /** 4xx: the address does not work. */
    case Invalid = 4;

    /**
     * The category of any status, also one this package does not know; a status
     * outside 100–499 is indeterminate.
     */
    public static function fromStatus(int $status): self
    {
        return self::tryFrom(intdiv($status, 100)) ?? self::Indeterminate;
    }
}
