<?php

declare(strict_types=1);

namespace App\Queue\Failed;

use Illuminate\Database\QueryException;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Override;

class SafeDatabaseUuidFailedJobProvider extends DatabaseUuidFailedJobProvider
{
    /**
     * Log a failed job into storage, safely ignoring duplicate key exceptions.
     *
     * {@inheritdoc}
     */
    #[Override]
    public function log($connection, $queue, $payload, $exception)
    {
        try {
            return parent::log($connection, $queue, $payload, $exception);
        } catch (QueryException $e) {
            $code = $e->getCode();
            $message = $e->getMessage();

            // Check if the query exception was caused by a duplicate entry / unique key constraint violation:
            // 1. SQLSTATE code 23000 (Integrity constraint violation, lax compared to match both string/integer)
            // 2. MySQL/MariaDB specific duplicate error code '1062' or 'Duplicate entry'
            // 3. SQLite specific unique constraint failure message
            // 4. PostgreSQL / Generic unique constraint failure message
            if ($code == 23000 ||
                str_contains($message, '1062 Duplicate entry') ||
                str_contains($message, 'Duplicate entry') ||
                str_contains($message, 'UNIQUE constraint failed') ||
                str_contains(strtolower($message), 'unique constraint')) {
                $decoded = json_decode($payload, true);

                return $decoded['uuid'] ?? null;
            }

            throw $e;
        }
    }
}
