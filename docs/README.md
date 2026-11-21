# Documentation

Byteplant documents two endpoints (as of 2026-11-21):

- `GET` or `POST https://api.email-validator.net/api/verify` with
  `EmailAddress`, `APIKey` and the optional `Timeout` (seconds, 5 to 300,
  default 10). The answer is a JSON object with `status` (int), `info`,
  `details`, `freemail`, `ratelimit_remain` and `ratelimit_seconds`.
- `POST https://api.email-validator.net/api/bulk-verify` for lists of up to
  100,000 addresses (not covered by this package).

The client always sends a form-encoded `POST`, so the key and the address never
appear in a URL. API errors are `401` ("Email address missing"), `118` ("Rate
Limit Exceeded") and `119` ("API Key Invalid or Depleted"). Byteplant does not
say which HTTP status accompanies them; the client reads the `status` of any
answer with a JSON body.

Byteplant answers usually within 750 ms and states an availability of 99.9 %.
Results are categorized by the hundreds digit of the code (1 indeterminate,
2 valid, 3 suspect, 4 invalid), as the filter on Byteplant's result page does.

Sources:

- https://www.byteplant.com/email-validator/api.html
- https://www.byteplant.com/email-validator/validation-results.html
