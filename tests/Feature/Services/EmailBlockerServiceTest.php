<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\PolydockBannedPattern;
use App\Services\EmailBlockerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * checkEmail() is the registration gate — a regression here silently lets
 * banned or disposable addresses through.
 */
class EmailBlockerServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $domainsFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->domainsFile = storage_path('app/disposable_domains.json');
        @unlink($this->domainsFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->domainsFile);
        parent::tearDown();
    }

    private function service(): EmailBlockerService
    {
        // Fresh instance per call: the service memoizes the domains file.
        return new EmailBlockerService;
    }

    public function test_invalid_email_format_is_blocked(): void
    {
        $result = $this->service()->checkEmail('not-an-email');

        $this->assertTrue($result->isBlocked());
        $this->assertSame('Invalid email format', $result->getReason());
    }

    public function test_exact_banned_pattern_blocks_with_its_reason(): void
    {
        PolydockBannedPattern::create(['pattern' => 'spammer@example.com', 'reason' => 'Known abuser']);

        $result = $this->service()->checkEmail('Spammer@Example.com');

        $this->assertTrue($result->isBlocked());
        $this->assertSame('Known abuser', $result->getReason());
    }

    public function test_wildcard_banned_pattern_blocks_the_whole_domain(): void
    {
        PolydockBannedPattern::create(['pattern' => '*@spam-domain.com', 'reason' => null]);

        $result = $this->service()->checkEmail('anyone@spam-domain.com');

        $this->assertTrue($result->isBlocked());
        $this->assertSame('Manually banned', $result->getReason());

        // The wildcard must not bleed into other domains.
        $this->assertFalse($this->service()->checkEmail('anyone@not-spam-domain.org')->isBlocked());
    }

    public function test_disposable_domain_is_blocked(): void
    {
        file_put_contents($this->domainsFile, json_encode(['tempmail.example']));

        $result = $this->service()->checkEmail('user@tempmail.example');

        $this->assertTrue($result->isBlocked());
        $this->assertSame('Disposable email address', $result->getReason());
    }

    public function test_subdomains_of_a_disposable_domain_are_blocked(): void
    {
        file_put_contents($this->domainsFile, json_encode(['tempmail.example']));

        $result = $this->service()->checkEmail('user@mx1.mail.tempmail.example');

        $this->assertTrue($result->isBlocked());
        $this->assertSame('Disposable email address', $result->getReason());
    }

    public function test_clean_email_passes(): void
    {
        file_put_contents($this->domainsFile, json_encode(['tempmail.example']));
        PolydockBannedPattern::create(['pattern' => 'spammer@example.com', 'reason' => 'Known abuser']);

        $result = $this->service()->checkEmail('legit.user@company.example');

        $this->assertFalse($result->isBlocked());
        $this->assertNull($result->getReason());
    }
}
