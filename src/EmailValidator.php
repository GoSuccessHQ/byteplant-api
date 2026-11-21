<?php

declare(strict_types=1);

namespace GoSuccess\Byteplant;

use GoSuccess\Byteplant\Exception\ApiException;
use GoSuccess\Byteplant\Exception\AuthenticationException;
use GoSuccess\Byteplant\Exception\RateLimitException;
use GoSuccess\Byteplant\Exception\SerializationException;
use GoSuccess\Byteplant\Exception\ServerException;
use GoSuccess\Byteplant\Exception\TransportException;
use GoSuccess\Byteplant\Http\CurlHttpClient;
use GoSuccess\Byteplant\Http\HttpClient;
use GoSuccess\Byteplant\Http\Method;
use GoSuccess\Byteplant\Http\Request;
use GoSuccess\Byteplant\Http\Response;
use GoSuccess\Byteplant\Result\ResultCode;
use GoSuccess\Byteplant\Result\Verification;
use InvalidArgumentException;
use JsonException;
use SensitiveParameter;

/**
 * Client of the real-time verification API of Byteplant's Email-Validator
 * (`POST /api/verify`).
 *
 * The API key and the address travel in the form body of a POST, never in the
 * URL, so they stay out of access logs and error messages. A result about the
 * address comes back as a {@see Verification}, whatever the verdict; only when
 * Byteplant could not verify (`118`, `119`, HTTP errors, network failures) does
 * the client throw.
 */
final class EmailValidator
{
    /**
     * Path of the real-time verification.
     */
    public const string VERIFY_PATH = '/api/verify';

    private readonly HttpClient $httpClient;

    /**
     * @param string          $apiKey     The API key of the Byteplant account.
     * @param HttpClient|null $httpClient A custom transport; defaults to {@see CurlHttpClient}.
     *
     * @throws InvalidArgumentException for an empty API key
     */
    public function __construct(
        #[SensitiveParameter]
        private readonly string $apiKey,
        private readonly ClientOptions $options = new ClientOptions(),
        ?HttpClient $httpClient = null,
    ) {
        if (trim($apiKey) === '') {
            throw new InvalidArgumentException('The API key must not be empty.');
        }

        $this->httpClient = $httpClient ?? new CurlHttpClient($options->timeout, $options->connectTimeout);
    }

    /**
     * Verify one address in real time.
     *
     * @param string $emailAddress The address as entered; Byteplant checks its syntax.
     *
     * @throws InvalidArgumentException for an empty address, before any request
     * @throws AuthenticationException  for an invalid key or a depleted balance (`119`)
     * @throws RateLimitException       when the rate limit is exceeded (`118`)
     * @throws ServerException          for a server error (5xx) after the retries
     * @throws ApiException             for any other HTTP error status
     * @throws SerializationException   when the answer is not Byteplant's JSON
     * @throws TransportException       for a network failure after the retries
     */
    public function verify(#[SensitiveParameter] string $emailAddress): Verification
    {
        // Byteplant would answer an empty address with 401 "Email address missing",
        // the same code as "Bad Address".
        if (trim($emailAddress) === '') {
            throw new InvalidArgumentException('The email address must not be empty.');
        }

        $fields = ['EmailAddress' => $emailAddress, 'APIKey' => $this->apiKey];

        if ($this->options->validationTimeout !== null) {
            $fields['Timeout'] = (string) $this->options->validationTimeout;
        }

        $response = $this->send(new Request(
            method: Method::Post,
            uri: $this->options->baseUri . self::VERIFY_PATH,
            headers: [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => $this->options->userAgent,
            ],
            body: http_build_query($fields, '', '&'),
            timeout: $this->options->timeout,
            connectTimeout: $this->options->connectTimeout,
        ));

        return $this->verification($response);
    }

    /**
     * Hide the API key from var_dump() and print_r().
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['apiKey' => '********', 'options' => $this->options];
    }

    /**
     * Read the answer: a result, or the exception for an API error.
     *
     * @throws ApiException|SerializationException
     */
    private function verification(Response $response): Verification
    {
        $data = $this->decode($response);
        $status = self::integer($data['status'] ?? null);
        $info = \is_string($data['info'] ?? null) ? $data['info'] : '';

        if (!$response->isSuccessful) {
            throw $this->failure($response, $status, $info, $data);
        }

        if ($status === null) {
            throw new SerializationException("The answer (HTTP {$response->statusCode}) has no numeric status.", response: $response);
        }

        if ($status === ResultCode::RateLimitExceeded->value || $status === ResultCode::ApiKeyInvalidOrDepleted->value) {
            throw $this->failure($response, $status, $info, $data);
        }

        return new Verification(
            status: $status,
            resultCode: ResultCode::tryFrom($status),
            info: $info,
            details: \is_string($data['details'] ?? null) ? $data['details'] : '',
            freemail: \is_bool($data['freemail'] ?? null) ? $data['freemail'] : null,
            rateLimitRemaining: self::integer($data['ratelimit_remain'] ?? null),
            rateLimitSeconds: self::integer($data['ratelimit_seconds'] ?? null),
            response: $response,
        );
    }

    /**
     * The exception for an API error or an HTTP error status.
     *
     * @param array<array-key, mixed> $data
     */
    private function failure(Response $response, ?int $status, string $info, array $data): ApiException
    {
        $message = 'Byteplant could not verify the address: HTTP ' . $response->statusCode
            . ($status === null ? '' : ", status {$status}")
            . ($info === '' ? '' : " ({$info})");
        $arguments = [$message, $response->statusCode, $status, $info, $response];

        return match (true) {
            $status === ResultCode::RateLimitExceeded->value, $response->statusCode === 429 => new RateLimitException(...$arguments, retryAfter: self::integer($data['ratelimit_seconds'] ?? null)),
            $status === ResultCode::ApiKeyInvalidOrDepleted->value, $response->statusCode === 401, $response->statusCode === 403 => new AuthenticationException(...$arguments),
            $response->statusCode >= 500 => new ServerException(...$arguments),
            default => new ApiException(...$arguments),
        };
    }

    /**
     * The decoded JSON object; an error status without JSON is still an error, not
     * a serialization problem.
     *
     * @return array<array-key, mixed>
     *
     * @throws SerializationException
     */
    private function decode(Response $response): array
    {
        try {
            $data = json_decode($response->body, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }

        if (\is_array($data)) {
            return $data;
        }

        if (!$response->isSuccessful) {
            return [];
        }

        throw new SerializationException("The answer (HTTP {$response->statusCode}) is not a JSON object.", response: $response);
    }

    /**
     * Send with retries after network failures and server errors (5xx); the last
     * response is returned whatever its status.
     *
     * @throws TransportException
     */
    private function send(#[SensitiveParameter] Request $request): Response
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->httpClient->send($request);
            } catch (TransportException $exception) {
                if ($attempt >= $this->options->maxRetries) {
                    throw $exception;
                }

                $this->wait($attempt);

                continue;
            }

            if ($response->statusCode < 500 || $attempt >= $this->options->maxRetries) {
                return $response;
            }

            $this->wait($attempt);
        }
    }

    private function wait(int $attempt): void
    {
        $delay = min($this->options->maxRetryDelay, $this->options->retryBaseDelay * (2 ** $attempt));

        if ($delay > 0.0) {
            usleep((int) ($delay * 1_000_000));
        }
    }

    /**
     * An integer, also from a numeric string (the bulk API sends `status` as a
     * string); null otherwise.
     */
    private static function integer(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && preg_match('/\A-?\d{1,9}\z/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
}
