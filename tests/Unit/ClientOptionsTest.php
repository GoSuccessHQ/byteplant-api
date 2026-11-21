<?php

declare(strict_types=1);

namespace GoSuccess\Byteplant\Tests\Unit;

use GoSuccess\Byteplant\ClientOptions;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClientOptions::class)]
final class ClientOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $options = new ClientOptions();

        self::assertSame('https://api.email-validator.net', $options->baseUri);
        self::assertNull($options->validationTimeout);
        self::assertSame(10, $options->effectiveValidationTimeout());
        self::assertSame(30.0, $options->timeout);
        self::assertSame(0, $options->maxRetries);
    }

    public function testAcceptsTheDocumentedBoundsOfTheValidationTimeout(): void
    {
        self::assertSame(5, new ClientOptions(validationTimeout: 5)->validationTimeout);
        self::assertSame(300, new ClientOptions(validationTimeout: 300, timeout: 310.0)->validationTimeout);
        self::assertSame(300, new ClientOptions(validationTimeout: 300, timeout: 0.0)->effectiveValidationTimeout());
    }

    /**
     * @return iterable<string, array{callable(): ClientOptions}>
     */
    public static function invalid(): iterable
    {
        yield 'validation timeout below 5' => [static fn(): ClientOptions => new ClientOptions(validationTimeout: 4)];
        yield 'validation timeout above 300' => [static fn(): ClientOptions => new ClientOptions(validationTimeout: 301, timeout: 400.0)];
        yield 'timeout not above the validation timeout' => [static fn(): ClientOptions => new ClientOptions(validationTimeout: 20, timeout: 20.0)];
        yield 'timeout not above the default validation timeout' => [static fn(): ClientOptions => new ClientOptions(timeout: 10.0)];
        yield 'negative connect timeout' => [static fn(): ClientOptions => new ClientOptions(connectTimeout: -1.0)];
        yield 'infinite delay' => [static fn(): ClientOptions => new ClientOptions(maxRetryDelay: \INF)];
        yield 'negative retries' => [static fn(): ClientOptions => new ClientOptions(maxRetries: -1)];
        yield 'base uri with path' => [static fn(): ClientOptions => new ClientOptions(baseUri: 'https://api.email-validator.net/api')];
        yield 'base uri without scheme' => [static fn(): ClientOptions => new ClientOptions(baseUri: 'api.email-validator.net')];
        yield 'file base uri' => [static fn(): ClientOptions => new ClientOptions(baseUri: 'file:///etc')];
        yield 'empty user agent' => [static fn(): ClientOptions => new ClientOptions(userAgent: ' ')];
        yield 'user agent with a line break' => [static fn(): ClientOptions => new ClientOptions(userAgent: "a\r\nX-Evil: 1")];
    }

    /**
     * @param callable(): ClientOptions $create
     */
    #[DataProvider('invalid')]
    public function testRefusesInvalidValues(callable $create): void
    {
        $this->expectException(InvalidArgumentException::class);

        $create();
    }
}
