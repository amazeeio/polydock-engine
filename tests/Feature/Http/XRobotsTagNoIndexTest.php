<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

class XRobotsTagNoIndexTest extends TestCase
{
    public function test_responses_include_noindex_header(): void
    {
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
