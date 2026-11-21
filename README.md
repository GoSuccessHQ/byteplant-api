# Byteplant Email-Validator API for PHP

A modern, strongly-typed, dependency-free PHP client for the real-time
verification API of [Byteplant's Email-Validator](https://www.byteplant.com/email-validator/).

## Features

- **Real-time verification** of one address (`POST /api/verify`) with
  Byteplant's result code, status text, description, freemail flag and rate
  limit counters.
- **All documented result codes** as an enum (`ResultCode`), each with its
  category (valid, suspect, invalid, indeterminate), retry mark, status text and
  description.
- **Results, not exceptions**: every verdict about the address, even "Bad
  Address", comes back as a `Verification`. Only when Byteplant could not verify
  (invalid key, depleted balance, rate limit, HTTP or network errors) does the
  client throw.
- **Secrets stay out of URLs and logs**: key and address travel in the form
  body of a POST, never in the URL, and never in exception messages, debug
  output or the arguments of stack traces (`#[SensitiveParameter]`).
- **No dependencies**: only `ext-curl` and `ext-json`; bring your own transport
  if you like.

## Requirements

- PHP 8.4 or newer
- `ext-curl`, `ext-json`

## Installation

```bash
composer require gosuccess/byteplant-api
```

## Verify an address

```php
use GoSuccess\Byteplant\ClientOptions;
use GoSuccess\Byteplant\EmailValidator;
use GoSuccess\Byteplant\Result\Category;
use GoSuccess\Byteplant\Result\ResultCode;

$validator = new EmailValidator('your API key', new ClientOptions(validationTimeout: 5, timeout: 8.0));

$verification = $validator->verify('someone@example.com');
$verification->status;             // 200, as Byteplant sent it
$verification->resultCode;         // ResultCode::ValidAddress, or null for a code this package does not know
$verification->category();         // Category::Valid (2xx), Suspect (3xx), Invalid (4xx) or Indeterminate (1xx)
$verification->isValid();          // 2xx, also "OK - Catch-All Active"
$verification->isRetryable();      // e.g. 114 "Validation Delayed", 313 "Server Unavailable"
$verification->info;               // "OK - Valid Address"
$verification->details;            // the full description
$verification->freemail;           // ?bool
$verification->rateLimitRemaining; // ?int, requests left in the current interval
$verification->rateLimitSeconds;   // ?int, seconds left in the current interval
$verification->response;           // Http\Response, Byteplant's answer unchanged
```

Every verification uses one credit of the account. `validationTimeout` is the
`Timeout` parameter: how long Byteplant may spend on one address, 5 to 300
seconds (Byteplant's default is 10). The request timeout must exceed it.

## Result codes

The categories follow the hundreds digit, as Byteplant's
[result page](https://www.byteplant.com/email-validator/validation-results.html)
groups them. Byteplant's own code examples treat 2xx as valid and everything in
3xx and 4xx as bad; whether a suspect address (disposable, role address, server
unavailable) is acceptable is up to the caller.

| Code | Status | Retry | Category |
|---|---|---|---|
| 114 | Validation Delayed | yes | indeterminate |
| 118 | Rate Limit Exceeded | yes | indeterminate (thrown as `RateLimitException`) |
| 119 | API Key Invalid or Depleted | no | indeterminate (thrown as `AuthenticationException`) |
| 121 | Task Accepted | no | indeterminate (bulk API only) |
| 200 | OK - Valid Address | no | valid |
| 207 | OK - Catch-All Active | no | valid |
| 215 | OK - Catch-All Test Delayed | yes | valid |
| 302 | Local Address | no | suspect |
| 303 | IP Address Literal | no | suspect |
| 305 | Disposable Address | no | suspect |
| 308 | Role Address | no | suspect |
| 313 | Server Unavailable | yes | suspect |
| 314 | Address Unavailable | yes | suspect |
| 316 | Duplicate Address | no | suspect (bulk API only) |
| 317 | Server Reject | no | suspect |
| 401 | Bad Address | no | invalid |
| 404 | Domain Not Fully Qualified | no | invalid |
| 406 | MX Lookup Error | no | invalid |
| 409 | No-Reply Address | no | invalid |
| 410 | Address Rejected | no | invalid |
| 413 | Server Unavailable | no | invalid |
| 414 | Address Unavailable | no | invalid |
| 420 | Domain Name Misspelled | no | invalid |

`401` also means "Email address missing"; the client refuses an empty address
before a request, so a `401` it returns is always "Bad Address".

## Error handling

Every exception implements `GoSuccess\Byteplant\Exception\ByteplantException`.

| Exception | When |
|---|---|
| `AuthenticationException` | `119`: the key is invalid or the balance is depleted (Byteplant does not tell them apart); also HTTP 401 and 403 |
| `RateLimitException` | `118` or HTTP 429; `retryAfter` holds `ratelimit_seconds` when sent. The default limit is 100 requests in 300 seconds |
| `ServerException` | HTTP 5xx after the retries |
| `ApiException` | any other HTTP error status; the base class of the three above, with `httpStatus`, `status`, `info` and `response` |
| `SerializationException` | a successful answer that is not a JSON object with a numeric `status` (e.g. a proxy's HTML page) |
| `TransportException` | network failure after the retries |
| `InvalidArgumentException` | an empty key or address, or invalid `ClientOptions` |

## Configuration and custom transport

`ClientOptions` sets the base URI, the validation timeout, request and connect
timeouts (30 s, 10 s), retries, backoff and user agent. Retries are off by
default: a repeated verification may be charged again. A custom transport
implements `GoSuccess\Byteplant\Http\HttpClient`.

## Not covered

- The bulk API (`/api/bulk-verify`) and its tasks.
- The balance of the account: Byteplant documents no endpoint for it; a
  depleted balance shows as `119`.

## Documentation and examples

- [docs/README.md](docs/README.md)
- [examples/verify.php](examples/verify.php)
- Byteplant's [API documentation](https://www.byteplant.com/email-validator/api.html)
  and [result codes](https://www.byteplant.com/email-validator/validation-results.html)

## Development

```bash
composer check                                       # code style, static analysis, unit tests
BYTEPLANT_LIVE=1 BYTEPLANT_API_KEY=… composer test:integration   # one live verification, one credit
```

The unit tests run against answers built from the fields Byteplant documents
(`status`, `info`, `details`, `freemail`, `ratelimit_remain`,
`ratelimit_seconds`); they were not recorded from the live API.

## License

MIT
