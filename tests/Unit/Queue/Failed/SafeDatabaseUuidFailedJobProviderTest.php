<?php

declare(strict_types=1);

namespace Tests\Unit\Queue\Failed;

use App\Queue\Failed\SafeDatabaseUuidFailedJobProvider;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SafeDatabaseUuidFailedJobProviderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that logging a failed job for the first time is successful.
     */
    public function test_can_log_failed_job_successfully(): void
    {
        $failer = app('queue.failer');

        $this->assertInstanceOf(SafeDatabaseUuidFailedJobProvider::class, $failer);

        $uuid = (string) Str::uuid();
        $payload = json_encode(['uuid' => $uuid]);
        $exception = new \Exception('First timeout error');

        $result = $failer->log('redis', 'polydock-app-instance-processing-create', $payload, $exception);

        $this->assertSame($uuid, $result);

        // Verify it was actually written to the database
        $this->assertDatabaseHas('failed_jobs', [
            'uuid' => $uuid,
            'queue' => 'polydock-app-instance-processing-create',
            'connection' => 'redis',
        ]);
    }

    /**
     * Test that logging a failed job with a duplicate UUID is caught and ignored gracefully.
     */
    public function test_ignores_duplicate_failed_job_uuid_exception(): void
    {
        $failer = app('queue.failer');

        $this->assertInstanceOf(SafeDatabaseUuidFailedJobProvider::class, $failer);

        $uuid = (string) Str::uuid();
        $payload = json_encode(['uuid' => $uuid]);
        $exception1 = new \Exception('Timeout error attempt 1');
        $exception2 = new \Exception('Timeout error attempt 2');

        // Log the first failure
        $result1 = $failer->log('redis', 'polydock-app-instance-processing-create', $payload, $exception1);
        $this->assertSame($uuid, $result1);

        // Attempting to log the second failure for the exact same UUID should normally crash
        // but our safe provider should catch and return the duplicate UUID.
        $result2 = $failer->log('redis', 'polydock-app-instance-processing-create', $payload, $exception2);
        $this->assertSame($uuid, $result2);

        // Ensure we still have the record in database
        $this->assertDatabaseHas('failed_jobs', [
            'uuid' => $uuid,
        ]);
    }

    /**
     * Test that any other non-duplicate-key QueryException is still rethrown.
     */
    public function test_rethrows_other_database_exceptions(): void
    {
        // Instantiate provider pointing to a non-existent table to force a different database exception
        $failer = new SafeDatabaseUuidFailedJobProvider(
            app('db'),
            'sqlite',
            'invalid_table_name_here_to_trigger_exception'
        );

        $uuid = (string) Str::uuid();
        $payload = json_encode(['uuid' => $uuid]);
        $exception = new \Exception('Timeout error');

        $this->expectException(QueryException::class);

        $failer->log('redis', 'polydock-app-instance-processing-create', $payload, $exception);
    }
}
