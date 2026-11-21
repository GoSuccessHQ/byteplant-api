<?php

declare(strict_types=1);

namespace GoSuccess\Byteplant\Result;

/**
 * The result codes Byteplant documents on its result page (as of 2026-11-21),
 * with their status text, description and whether a retry may change them.
 *
 * @see https://www.byteplant.com/email-validator/validation-results.html
 */
enum ResultCode: int
{
    case ValidationDelayed = 114;
    case RateLimitExceeded = 118;
    case ApiKeyInvalidOrDepleted = 119;
    case TaskAccepted = 121;
    case ValidAddress = 200;
    case CatchAllActive = 207;
    case CatchAllTestDelayed = 215;
    case LocalAddress = 302;
    case IpAddressLiteral = 303;
    case DisposableAddress = 305;
    case RoleAddress = 308;
    case ServerUnavailable = 313;
    case AddressUnavailable = 314;
    case DuplicateAddress = 316;
    case ServerReject = 317;
    case BadAddress = 401;
    case DomainNotFullyQualified = 404;
    case MxLookupError = 406;
    case NoReplyAddress = 409;
    case AddressRejected = 410;
    case ServerUnavailablePermanently = 413;
    case AddressUnavailablePermanently = 414;
    case DomainNameMisspelled = 420;

    /**
     * The category, by the hundreds digit of the code.
     */
    public function category(): Category
    {
        return Category::fromStatus($this->value);
    }

    /**
     * Whether Byteplant marks the result as worth retrying ("Retry: yes").
     */
    public function isRetryable(): bool
    {
        return match ($this) {
            self::ValidationDelayed, self::RateLimitExceeded, self::CatchAllTestDelayed, self::ServerUnavailable, self::AddressUnavailable => true,
            default => false,
        };
    }

    /**
     * The status text as Byteplant documents it; the API sends it as `info`.
     */
    public function info(): string
    {
        return match ($this) {
            self::ValidationDelayed => 'Validation Delayed',
            self::RateLimitExceeded => 'Rate Limit Exceeded',
            self::ApiKeyInvalidOrDepleted => 'API Key Invalid or Depleted',
            self::TaskAccepted => 'Task Accepted',
            self::ValidAddress => 'OK - Valid Address',
            self::CatchAllActive => 'OK - Catch-All Active',
            self::CatchAllTestDelayed => 'OK - Catch-All Test Delayed',
            self::LocalAddress => 'Local Address',
            self::IpAddressLiteral => 'IP Address Literal',
            self::DisposableAddress => 'Disposable Address',
            self::RoleAddress => 'Role Address',
            self::ServerUnavailable, self::ServerUnavailablePermanently => 'Server Unavailable',
            self::AddressUnavailable, self::AddressUnavailablePermanently => 'Address Unavailable',
            self::DuplicateAddress => 'Duplicate Address',
            self::ServerReject => 'Server Reject',
            self::BadAddress => 'Bad Address',
            self::DomainNotFullyQualified => 'Domain Not Fully Qualified',
            self::MxLookupError => 'MX Lookup Error',
            self::NoReplyAddress => 'No-Reply Address',
            self::AddressRejected => 'Address Rejected',
            self::DomainNameMisspelled => 'Domain Name Misspelled',
        };
    }

    /**
     * The description as Byteplant documents it.
     */
    public function description(): string
    {
        return match ($this) {
            self::ValidationDelayed => 'SMTP address validation is still in progress (API only)',
            self::RateLimitExceeded => 'The API rate limit for your account has been exceeded (API only)',
            self::ApiKeyInvalidOrDepleted => 'The API key is invalid, or the account balance is depleted (API only)',
            self::TaskAccepted => 'The validation task was accepted',
            self::ValidAddress => 'The mail address is valid',
            self::CatchAllActive => 'The mail server for this domain accepts the address, but it also implements a catch-all policy. For this reason, it is not possible to determine if a mail account with this name actually exists, without sending a message and waiting for a reply',
            self::CatchAllTestDelayed => 'The mail server for this domain accepts the address, the Catch-All test returned a temporary error (API only)',
            self::LocalAddress => 'The mail address lacks the domain qualifier. It may work locally within some organization, but otherwise it is unusable',
            self::IpAddressLiteral => 'The mail address is syntactically correct, but the domain part defines an IP address. This kind of address may work, but is usually only used by spammers, or for testing purposes',
            self::DisposableAddress => 'The mail address is provided by a disposable email address service. Disposable addresses only work for a limited amount of time, or for a limited amount of messages',
            self::RoleAddress => 'The mail address is a role address and typically not associated with a particular person',
            self::ServerUnavailable => 'The mail server for this domain could not be contacted, or did not respond',
            self::AddressUnavailable => 'The mail server for this domain responded with an error condition for this address',
            self::DuplicateAddress => 'The address is a duplicate of an address that has already been processed (batch processing only)',
            self::ServerReject => 'The server refuses to answer to SMTP commands, probably because some very strict anti-spam measures are in effect',
            self::BadAddress => 'The mail address failed to pass basic syntax checks',
            self::DomainNotFullyQualified => 'The mail address is syntactically correct, but the domain part of the mail address is not fully qualified, and the address is not usable',
            self::MxLookupError => 'There is no valid DNS MX record associated with this domain, or one or more MX entries lack an A record. Messages to this domain cannot be delivered',
            self::NoReplyAddress => 'The mail address appears to be a no-reply address, and is not usable as a recipient of email messages',
            self::AddressRejected => 'The mail server for the recipient domain does not accept messages to this address',
            self::ServerUnavailablePermanently => 'The mail server for this domain could not be contacted, or did not accept mail over an extended period of time',
            self::AddressUnavailablePermanently => 'The mailbox is full, or the mail server for this domain responded with an error condition for this address over an extended period of time',
            self::DomainNameMisspelled => 'The domain name is probably misspelled',
        };
    }
}
