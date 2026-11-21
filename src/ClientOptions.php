<?php

declare(strict_types=1);

namespace GoSuccess\Byteplant;

use InvalidArgumentException;

/**
 * Immutable transport, retry and validation settings.
 *
 * The API key is not part of the options: the client receives it separately,
 * so options can be shared and logged safely.
 */
final readonly class ClientOptions
{
    public const string DEFAULT_BASE_URI = 'https://api.email-validator.net';

    public const string DEFAULT_USER_AGENT = 'gosuccess/byteplant-api (+https://github.com/GoSuccessHQ/byteplant-api)';

    /**
     * Seconds Byteplant spends on a verification when no `Timeout` is sent.
     */
    public const int DEFAULT_VALIDATION_TIMEOUT = 10;

    /**
     * The bounds Byteplant documents for the `Timeout` parameter, in seconds.
     */
    public const int MIN_VALIDATION_TIMEOUT = 5;

    public const int MAX_VALIDATION_TIMEOUT = 300;

    /**
     * @param string   $baseUri            Scheme and host of the API, without a path.
     * @param int|null $validationTimeout  How long Byteplant may spend on one verification
     *                                     (the `Timeout` parameter, 5 to 300 seconds); null
     *                                     sends none, and Byteplant uses its default of 10.
     * @param float    $timeout            Maximum duration of a request in seconds; `0.0`
     *                                     means no limit. It must exceed the validation
     *                                     timeout, or the answer arrives after the request
     *                                     was given up.
     * @param float    $connectTimeout     Maximum duration of the connection phase in seconds.
     * @param int      $maxRetries         How often a request is retried after a network
     *                                     failure or a server error (5xx). `0` by default:
     *                                     a repeated verification may be charged again.
     * @param float    $retryBaseDelay     Base delay in seconds of the exponential backoff.
     * @param float    $maxRetryDelay      Upper bound in seconds for a single wait.
     * @param string   $userAgent          Value of the `User-Agent` header.
     */
    public function __construct(
        public string $baseUri = self::DEFAULT_BASE_URI,
        public ?int $validationTimeout = null,
        public float $timeout = 30.0,
        public float $connectTimeout = 10.0,
        public int $maxRetries = 0,
        public float $retryBaseDelay = 1.0,
        public float $maxRetryDelay = 10.0,
        public string $userAgent = self::DEFAULT_USER_AGENT,
    ) {
        if (preg_match('#\Ahttps?://[^/?\#\s]+\z#', $baseUri) !== 1) {
            throw new InvalidArgumentException('baseUri must be http:// or https:// with a host and without a path.');
        }

        if ($validationTimeout !== null && ($validationTimeout < self::MIN_VALIDATION_TIMEOUT || $validationTimeout > self::MAX_VALIDATION_TIMEOUT)) {
            throw new InvalidArgumentException('validationTimeout must be between ' . self::MIN_VALIDATION_TIMEOUT . ' and ' . self::MAX_VALIDATION_TIMEOUT . ' seconds.');
        }

        foreach (['timeout' => $timeout, 'connectTimeout' => $connectTimeout, 'retryBaseDelay' => $retryBaseDelay, 'maxRetryDelay' => $maxRetryDelay] as $name => $value) {
            // NAN and INF would turn into warnings or endless waits once converted for cURL or sleep().
            if (!is_finite($value) || $value < 0.0) {
                throw new InvalidArgumentException("{$name} must be a finite number that is not negative.");
            }
        }

        if ($timeout > 0.0 && $timeout <= (float) $this->effectiveValidationTimeout()) {
            throw new InvalidArgumentException('timeout must exceed the validation timeout, or the answer arrives after the request was given up.');
        }

        if ($maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries must not be negative.');
        }

        if (trim($userAgent) === '') {
            throw new InvalidArgumentException('userAgent must not be empty.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $userAgent) === 1) {
            throw new InvalidArgumentException('userAgent must not contain control characters.');
        }
    }

    /**
     * The seconds Byteplant may spend on a verification: the configured validation
     * timeout, or Byteplant's default.
     */
    public function effectiveValidationTimeout(): int
    {
        return $this->validationTimeout ?? self::DEFAULT_VALIDATION_TIMEOUT;
    }
}
