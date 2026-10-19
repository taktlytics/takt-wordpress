<?php

declare(strict_types=1);

namespace Vskstudio\Takt\WordPress\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Vskstudio\Takt\WordPress\AdminPage;
use Vskstudio\Takt\WordPress\Plugin;

final class AdminPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_sanitize_keeps_the_stored_key_when_the_field_is_submitted_blank(): void
    {
        Functions\when('get_option')->justReturn(['api_key' => 'kept-secret']);

        $out = AdminPage::sanitize(['domain' => 'example.com', 'api_key' => '']);

        $this->assertSame('kept-secret', $out['api_key']);
    }

    public function test_sanitize_uses_the_submitted_key_when_provided(): void
    {
        Functions\when('get_option')->justReturn(['api_key' => 'old']);

        $out = AdminPage::sanitize(['domain' => 'example.com', 'api_key' => 'new-secret']);

        $this->assertSame('new-secret', $out['api_key']);
    }

    public function test_sanitize_reports_a_plain_http_url_rejected_outside_loopback(): void
    {
        Functions\when('get_option')->justReturn([]);
        Functions\expect('add_settings_error')
            ->once()
            ->with(Plugin::OPTION, 'takt_script_origin', \Mockery::type('string'), 'error');

        $out = AdminPage::sanitize(['script_origin' => 'http://cdn.example.com']);

        $this->assertSame('', $out['script_origin']);
    }

    public function test_sanitize_reports_nothing_for_an_accepted_or_blank_url(): void
    {
        Functions\when('get_option')->justReturn([]);
        Functions\expect('add_settings_error')->never();

        $out = AdminPage::sanitize(['script_origin' => 'https://cdn.example.com', 'api_endpoint' => '']);

        $this->assertSame('https://cdn.example.com', $out['script_origin']);
    }

    public function test_key_storage_notice_warns_when_the_key_lives_in_the_option(): void
    {
        Functions\stubEscapeFunctions();

        $notice = AdminPage::keyStorageNotice(false, 'stored-secret');

        $this->assertStringContainsString('notice-warning', $notice);
        $this->assertStringContainsString('TAKT_API_KEY', $notice);
        $this->assertStringNotContainsString('stored-secret', $notice);
    }

    public function test_key_storage_notice_is_silent_when_the_constant_or_no_key_is_set(): void
    {
        Functions\stubEscapeFunctions();

        $this->assertSame('', AdminPage::keyStorageNotice(true, 'stored-secret'));
        $this->assertSame('', AdminPage::keyStorageNotice(false, ''));
    }
}
