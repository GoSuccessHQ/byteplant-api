<?php

declare(strict_types=1);

/**
 * Verify one address (uses one credit):
 *
 *   BYTEPLANT_API_KEY=… php examples/verify.php someone@example.com
 */

use GoSuccess\Byteplant\ClientOptions;
use GoSuccess\Byteplant\EmailValidator;
use GoSuccess\Byteplant\Exception\AuthenticationException;
use GoSuccess\Byteplant\Exception\ByteplantException;
use GoSuccess\Byteplant\Exception\RateLimitException;

require __DIR__ . '/../vendor/autoload.php';

$key = getenv('BYTEPLANT_API_KEY');
$address = $argv[1] ?? '';

if (!is_string($key) || $key === '' || $address === '') {
    fwrite(\STDERR, "Usage: BYTEPLANT_API_KEY=… php examples/verify.php <address>\n");

    exit(1);
}

$validator = new EmailValidator($key, new ClientOptions(validationTimeout: 5, timeout: 8.0));

try {
    $verification = $validator->verify($address);
} catch (AuthenticationException) {
    fwrite(\STDERR, "The API key is invalid or the balance is depleted.\n");

    exit(1);
} catch (RateLimitException $e) {
    fwrite(\STDERR, 'Rate limit exceeded; try again in ' . ($e->retryAfter ?? 300) . " seconds.\n");

    exit(1);
} catch (ByteplantException $e) {
    fwrite(\STDERR, $e->getMessage() . "\n");

    exit(1);
}

printf(
    "%d %s (%s)%s\n%s\n",
    $verification->status,
    $verification->info,
    $verification->category()->name,
    $verification->freemail === true ? ', freemail' : '',
    $verification->details,
);
