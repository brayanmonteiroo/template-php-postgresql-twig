<?php

declare(strict_types=1);

/**
 * Resolve an environment variable from $_ENV, $_SERVER, or getenv().
 * Docker Compose injects process env; PHP only fills $_ENV when variables_order includes E.
 */
function env_value(string $key, ?string $default = null): ?string
{
    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }

    if (array_key_exists($key, $_SERVER) && is_string($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    return $default;
}
