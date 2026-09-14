<?php

declare(strict_types=1);

namespace WeblyConnect\Sdk\Exception;

use RuntimeException;

/**
 * Base exception for all connect-sdk-core failures. Host applications catch this (or a
 * subclass) at the adapter boundary and translate it into a localized, user-facing
 * message; the SDK itself never emits user-facing copy.
 */
class ConnectSdkException extends RuntimeException
{
}
