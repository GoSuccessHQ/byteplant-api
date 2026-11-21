<?php

declare(strict_types=1);

namespace GoSuccess\Byteplant\Tests\Unit;

use GoSuccess\Byteplant\ClientOptions;
use GoSuccess\Byteplant\EmailValidator;
use GoSuccess\Byteplant\Exception\ApiException;
use GoSuccess\Byteplant\Exception\AuthenticationException;
use GoSuccess\Byteplant\Exception\RateLimitException;
use GoSuccess\Byteplant\Exception\SerializationException;
use GoSuccess\Byteplant\Exception\ServerException;
use GoSuccess\Byteplant\Exception\TransportException;
use GoSuccess\Byteplant\Http\Method;
use GoSuccess\Byteplant\Http\Response;
use GoSuccess\Byteplant\Result\Category;
use GoSuccess\Byteplant\Result\ResultCode;
use GoSuccess\Byteplant\Result\Verification;
use GoSuccess\Byteplant\Tests\Support\ExceptionTrace;
use GoSuccess\Byteplant\Tests\Support\LocalServer;
use GoSuccess\Byteplant\Tests\Support\MockHttpClient;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SensitiveParameterValue;

/**
 * The verification against answers built from the fields Byteplant documents
 * (status, info, details, freemail, ratelimit_remain, ratelimit_seconds).
 */
#[CoversClass(EmailValidator::class)]
#[CoversClass(Verification::class)]
final class EmailValidatorTest extends TestCase
{
    private const string KEY = 'bp-test-key-0123456789';

    public function testSendsAFormPostWithKeyAndAddressInTheBody(): void
    {
        $http = new MockHttpClient(self::fixture('valid'));

        new EmailValidator(self::KEY, httpClient: $http)->verify('jane.doe+news@example.com');

        self::assertSame(1, $http->callCount());
        $request = $http->requests[0];
        self::assertSame(Method::Post, $request->method);
        self::assertSame('https://api.email-validator.net/api/verify', $request->uri);
        self::assertSame('application/x-www-form-urlencoded', $request->headers['Content-Type']);
        self::assertSame('application/json', $request->headers['Accept']);
        self::assertSame(ClientOptions::DEFAULT_USER_AGENT, $request->headers['User-Agent']);

        parse_str((string) $request->body, $fields);
        self::assertSame(['EmailAddress' => 'jane.doe+news@example.com', 'APIKey' => self::KEY], $fields);
        self::assertStringNotContainsString(self::KEY, $request->uri);
    }

    public function testSendsTheValidationTimeoutWhenSet(): void
    {
        $http = new MockHttpClient(self::fixture('valid'));

        new EmailValidator(self::KEY, new ClientOptions(validationTimeout: 5, timeout: 8.0), $http)->verify('a@example.com');

        parse_str((string) $http->requests[0]->body, $fields);
        self::assertSame('5', $fields['Timeout'] ?? null);
        self::assertSame(8.0, $http->requests[0]->timeout);
    }

    public function testReadsAValidAddress(): void
    {
        $verification = new EmailValidator(self::KEY, httpClient: new MockHttpClient(self::fixture('valid')))->verify('a@example.com');

        self::assertSame(200, $verification->status);
        self::assertSame(ResultCode::ValidAddress, $verification->resultCode);
        self::assertSame('OK - Valid Address', $verification->info);
        self::assertSame('The mail address is valid.', $verification->details);
        self::assertTrue($verification->freemail);
        self::assertSame(99, $verification->rateLimitRemaining);
        self::assertSame(299, $verification->rateLimitSeconds);
        self::assertSame(Category::Valid, $verification->category());
        self::assertTrue($verification->isValid());
        self::assertFalse($verification->isInvalid());
        self::assertFalse($verification->isRetryable());
        self::assertSame(200, $verification->response->statusCode);
    }

    /**
     * @return iterable<string, array{string, ResultCode, Category, bool}>
     */
    public static function results(): iterable
    {
        yield 'catch-all' => ['catch-all', ResultCode::CatchAllActive, Category::Valid, false];
        yield 'disposable' => ['disposable', ResultCode::DisposableAddress, Category::Suspect, false];
        yield 'bad address' => ['bad-address', ResultCode::BadAddress, Category::Invalid, false];
        yield 'mx lookup error' => ['mx-lookup-error', ResultCode::MxLookupError, Category::Invalid, false];
        yield 'validation delayed' => ['validation-delayed', ResultCode::ValidationDelayed, Category::Indeterminate, true];
    }

    #[DataProvider('results')]
    public function testEveryVerdictIsAResultNotAnException(string $fixture, ResultCode $code, Category $category, bool $retryable): void
    {
        $verification = new EmailValidator(self::KEY, httpClient: new MockHttpClient(self::fixture($fixture)))->verify('a@example.com');

        self::assertSame($code, $verification->resultCode);
        self::assertSame($category, $verification->category());
        self::assertSame($retryable, $verification->isRetryable());
        self::assertFalse($verification->freemail);
    }

    public function testAnUnknownCodeKeepsItsStatusAndCategory(): void
    {
        $verification = new EmailValidator(self::KEY, httpClient: new MockHttpClient(new Response(200, '{"status":"499","info":"Something New"}')))->verify('a@example.com');

        self::assertSame(499, $verification->status);
        self::assertNull($verification->resultCode);
        self::assertSame(Category::Invalid, $verification->category());
        self::assertNull($verification->freemail);
        self::assertNull($verification->rateLimitRemaining);
        self::assertSame('', $verification->details);
    }

    public function testAnInvalidKeyOrDepletedBalanceThrows(): void
    {
        try {
            new EmailValidator(self::KEY, httpClient: new MockHttpClient(self::fixture('api-key-invalid')))->verify('a@example.com');
            self::fail('Expected an AuthenticationException.');
        } catch (AuthenticationException $e) {
            self::assertSame(119, $e->status);
            self::assertSame(200, $e->httpStatus);
            self::assertSame('API Key Invalid or Depleted', $e->info);
            self::assertSame(119, $e->getCode());
            self::assertStringNotContainsString(self::KEY, $e->getMessage());
        }
    }

    public function testTheRateLimitThrowsWithTheSecondsLeft(): void
    {
        try {
            new EmailValidator(self::KEY, httpClient: new MockHttpClient(self::fixture('rate-limit-exceeded')))->verify('a@example.com');
            self::fail('Expected a RateLimitException.');
        } catch (RateLimitException $e) {
            self::assertSame(118, $e->status);
            self::assertSame(120, $e->retryAfter);
        }
    }

    public function testHttpErrorsMapToTheirExceptions(): void
    {
        $cases = [
            [new Response(429, ''), RateLimitException::class],
            [new Response(401, '<html>no</html>'), AuthenticationException::class],
            [new Response(403, ''), AuthenticationException::class],
            [new Response(502, '<html>Bad Gateway</html>'), ServerException::class],
            [new Response(404, 'not found'), ApiException::class],
        ];

        foreach ($cases as [$response, $class]) {
            try {
                new EmailValidator(self::KEY, httpClient: new MockHttpClient($response))->verify('a@example.com');
                self::fail("Expected {$class}.");
            } catch (ApiException $e) {
                self::assertSame($class, $e::class);
                self::assertSame($response->statusCode, $e->httpStatus);
                self::assertNull($e->status);
                self::assertSame($response, $e->response);
            }
        }
    }

    public function testAnErrorStatusWithAByteplantCodeKeepsTheCode(): void
    {
        $this->expectException(AuthenticationException::class);

        new EmailValidator(self::KEY, httpClient: new MockHttpClient(new Response(400, '{"status":119,"info":"API Key Invalid or Depleted"}')))->verify('a@example.com');
    }

    public function testAnswersThatAreNotByteplantsJsonThrow(): void
    {
        foreach (['<html>proxy</html>', '[]', '{"info":"no status"}', '{"status":true}', '"200"'] as $body) {
            try {
                new EmailValidator(self::KEY, httpClient: new MockHttpClient(new Response(200, $body)))->verify('a@example.com');
                self::fail("Expected a SerializationException for {$body}.");
            } catch (SerializationException $e) {
                self::assertSame($body, $e->response?->body);
            }
        }
    }

    public function testServerErrorsAndNetworkFailuresAreRetriedOnlyWhenAsked(): void
    {
        $http = new MockHttpClient(new Response(503, ''));

        try {
            new EmailValidator(self::KEY, httpClient: $http)->verify('a@example.com');
            self::fail('Expected a ServerException.');
        } catch (ServerException) {
            self::assertSame(1, $http->callCount());
        }

        $http = new MockHttpClient(new TransportException('down'), new Response(500, ''), self::fixture('valid'));
        $options = new ClientOptions(maxRetries: 2, retryBaseDelay: 0.0);

        self::assertTrue(new EmailValidator(self::KEY, $options, $http)->verify('a@example.com')->isValid());
        self::assertSame(3, $http->callCount());

        $http = new MockHttpClient(new TransportException('down'));
        $this->expectException(TransportException::class);
        new EmailValidator(self::KEY, httpClient: $http)->verify('a@example.com');
    }

    public function testAnEmptyAddressIsRefusedBeforeARequest(): void
    {
        $http = new MockHttpClient();

        try {
            new EmailValidator(self::KEY, httpClient: $http)->verify('  ');
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, $http->callCount());
        }
    }

    public function testAnEmptyKeyIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EmailValidator(' ', httpClient: new MockHttpClient());
    }

    public function testKeyAndAddressStayOutOfTracesAndDebugOutput(): void
    {
        $port = LocalServer::closedPort();
        $validator = new EmailValidator(self::KEY, new ClientOptions(baseUri: "http://127.0.0.1:{$port}", connectTimeout: 1.0));

        $e = ExceptionTrace::capture(static fn(): Verification => $validator->verify('secret.person@example.com'));

        self::assertInstanceOf(TransportException::class, $e);
        $arguments = ExceptionTrace::arguments($e);
        self::assertStringNotContainsString(self::KEY, $arguments);
        self::assertStringNotContainsString('secret.person', $arguments);
        $verify = array_values(array_filter($e->getTrace(), static fn(array $frame): bool => $frame['function'] === 'verify'))[0] ?? [];
        self::assertInstanceOf(SensitiveParameterValue::class, $verify['args'][0] ?? null);
        self::assertStringNotContainsString(self::KEY, print_r($validator, true));

        $http = new MockHttpClient(self::fixture('valid'));
        new EmailValidator(self::KEY, httpClient: $http)->verify('secret.person@example.com');
        $debug = print_r($http->requests[0], true);
        self::assertStringNotContainsString(self::KEY, $debug);
        self::assertStringNotContainsString('secret.person', $debug);
    }

    private static function fixture(string $name, int $status = 200): Response
    {
        return new Response($status, (string) file_get_contents(__DIR__ . "/../Fixtures/{$name}.json"), ['content-type' => 'application/json']);
    }
}
