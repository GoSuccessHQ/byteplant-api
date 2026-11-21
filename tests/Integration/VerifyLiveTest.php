<?php

declare(strict_types=1);

namespace GoSuccess\Byteplant\Tests\Integration;

use GoSuccess\Byteplant\EmailValidator;
use GoSuccess\Byteplant\Result\Category;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Verifies one address live. Every verification uses one credit, so it only
 * runs with BYTEPLANT_LIVE=1 and a key in BYTEPLANT_API_KEY.
 */
#[CoversNothing]
final class VerifyLiveTest extends TestCase
{
    public function testVerifiesByteplantsOwnSupportAddress(): void
    {
        $key = getenv('BYTEPLANT_API_KEY');

        if (getenv('BYTEPLANT_LIVE') !== '1' || !\is_string($key) || $key === '') {
            self::markTestSkipped('Set BYTEPLANT_LIVE=1 and BYTEPLANT_API_KEY to verify one address live (one credit).');
        }

        $verification = new EmailValidator($key)->verify('support@byteplant.com');

        self::assertNotSame(Category::Invalid, $verification->category());
        self::assertNotSame('', $verification->info);
    }
}
