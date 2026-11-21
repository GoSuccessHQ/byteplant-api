<?php

declare(strict_types=1);

namespace GoSuccess\Byteplant\Tests\Unit\Result;

use GoSuccess\Byteplant\Result\Category;
use GoSuccess\Byteplant\Result\ResultCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The codes, categories and retry marks as Byteplant's result page lists them.
 */
#[CoversClass(ResultCode::class)]
#[CoversClass(Category::class)]
final class ResultCodeTest extends TestCase
{
    public function testKnowsTheTwentyThreeDocumentedCodes(): void
    {
        self::assertSame(
            [114, 118, 119, 121, 200, 207, 215, 302, 303, 305, 308, 313, 314, 316, 317, 401, 404, 406, 409, 410, 413, 414, 420],
            array_map(static fn(ResultCode $code): int => $code->value, ResultCode::cases()),
        );
    }

    public function testTheCategoryFollowsTheHundredsDigit(): void
    {
        foreach (ResultCode::cases() as $code) {
            self::assertSame(Category::from(intdiv($code->value, 100)), $code->category(), $code->name);
            self::assertNotSame('', $code->info());
            self::assertNotSame('', $code->description());
        }

        self::assertSame(Category::Indeterminate, Category::fromStatus(0));
        self::assertSame(Category::Indeterminate, Category::fromStatus(599));
        self::assertSame(Category::Indeterminate, Category::fromStatus(-200));
    }

    public function testOnlyTheDocumentedCodesAreRetryable(): void
    {
        $retryable = array_values(array_filter(ResultCode::cases(), static fn(ResultCode $code): bool => $code->isRetryable()));

        self::assertSame([ResultCode::ValidationDelayed, ResultCode::RateLimitExceeded, ResultCode::CatchAllTestDelayed, ResultCode::ServerUnavailable, ResultCode::AddressUnavailable], $retryable);
    }

    public function testTheTwoUnavailableCodesShareTheirText(): void
    {
        self::assertSame('Server Unavailable', ResultCode::ServerUnavailablePermanently->info());
        self::assertSame('Address Unavailable', ResultCode::AddressUnavailablePermanently->info());
        self::assertNotSame(ResultCode::ServerUnavailable->description(), ResultCode::ServerUnavailablePermanently->description());
    }
}
