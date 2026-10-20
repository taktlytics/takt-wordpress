<?php

declare(strict_types=1);

namespace Vskstudio\Takt\WordPress\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Vskstudio\Takt\WordPress\AdminPage;
use Vskstudio\Takt\WordPress\Plugin;
use Vskstudio\Takt\WordPress\Settings;

final class AdminPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
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

    public function test_a_blank_submission_keeps_a_refused_origin_suspended(): void
    {
        Functions\when('get_option')->justReturn(['script_origin' => '', 'refused_urls' => ['script_origin']]);
        Functions\expect('add_settings_error')
            ->once()
            ->with(Plugin::OPTION, 'takt_script_origin', \Mockery::type('string'), 'error');

        $out = AdminPage::sanitize(['domain' => 'example.com', 'script_origin' => '']);

        $this->assertSame(['script_origin'], $out['refused_urls']);
        $this->assertSame('', $out['script_origin']);
    }

    public function test_a_valid_https_origin_lifts_a_previous_refusal(): void
    {
        Functions\when('get_option')->justReturn(['script_origin' => '', 'refused_urls' => ['script_origin']]);
        Functions\expect('add_settings_error')->never();

        $out = AdminPage::sanitize(['domain' => 'example.com', 'script_origin' => 'https://cdn.example.com']);

        $this->assertSame([], $out['refused_urls']);
        $this->assertSame('https://cdn.example.com', $out['script_origin']);
    }

    public function test_refusal_notice_says_the_tracking_is_suspended_for_the_script_origin(): void
    {
        $notice = html_entity_decode(AdminPage::refusedUrlNotice(['script_origin' => 'http://cdn.example.com']), ENT_QUOTES);

        $this->assertStringContainsString('notice-error', $notice);
        $this->assertStringContainsString('Origine du script', $notice);
        $this->assertStringContainsString("Le suivi est suspendu tant qu'elle n'est pas corrigée", $notice);
    }

    public function test_refusal_notice_names_a_stored_http_ingest_origin_and_the_woocommerce_impact(): void
    {
        $notice = AdminPage::refusedUrlNotice(['api_endpoint' => 'http://takt.example.com']);

        $this->assertStringContainsString('notice-error', $notice);
        $this->assertStringContainsString('https requis', $notice);
        $this->assertStringContainsString('WooCommerce', $notice);
    }

    public function test_refusal_notice_is_silent_for_https_or_blank_values(): void
    {
        $this->assertSame('', AdminPage::refusedUrlNotice(['api_endpoint' => 'https://taktlytics.com', 'script_origin' => 'https://cdn.example.com']));
        $this->assertSame('', AdminPage::refusedUrlNotice([]));
    }

    public function test_renderer_shows_the_refusal_notice_to_administrators_outside_the_settings_screen(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_current_screen')->justReturn((object) ['id' => 'dashboard']);
        Functions\when('get_option')->justReturn(['script_origin' => 'http://cdn.example.com']);

        ob_start();
        (new AdminPage())->renderRefusedUrlNotice();
        $output = html_entity_decode((string) ob_get_clean(), ENT_QUOTES);

        $this->assertStringContainsString("Le suivi est suspendu tant qu'elle n'est pas corrigée", $output);
    }

    public function test_renderer_stays_silent_for_non_administrators_and_on_the_settings_screen(): void
    {
        Functions\when('get_option')->justReturn(['api_endpoint' => 'http://takt.example.com']);

        Functions\when('current_user_can')->justReturn(false);
        Functions\when('get_current_screen')->justReturn((object) ['id' => 'dashboard']);
        ob_start();
        (new AdminPage())->renderRefusedUrlNotice();
        $this->assertSame('', (string) ob_get_clean());

        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_current_screen')->justReturn((object) ['id' => 'settings_page_takt-analytics']);
        ob_start();
        (new AdminPage())->renderRefusedUrlNotice();
        $this->assertSame('', (string) ob_get_clean());
    }

    public function test_out_of_sdk_notice_warns_when_routes_are_set_outside_sdk_mode(): void
    {
        Functions\stubEscapeFunctions();
        Functions\stubTranslationFunctions();

        $fromTemplates = AdminPage::outOfSdkNotice(Settings::sanitize(['mode' => 'cdn', 'route_templates' => '1']));
        $fromRedaction = AdminPage::outOfSdkNotice(Settings::sanitize(['mode' => 'inline', 'redact_routes' => '/verify/{token}']));

        $this->assertStringContainsString('notice-error', $fromTemplates);
        $this->assertStringContainsString('Ces réglages sont ignorés hors mode SDK', $fromTemplates);
        $this->assertStringContainsString('notice-error', $fromRedaction);
    }

    public function test_out_of_sdk_notice_is_silent_in_sdk_mode_or_without_routes(): void
    {
        Functions\stubEscapeFunctions();
        Functions\stubTranslationFunctions();

        $this->assertSame('', AdminPage::outOfSdkNotice(Settings::sanitize(['mode' => 'sdk', 'route_templates' => '1', 'redact_routes' => '/verify/{token}'])));
        $this->assertSame('', AdminPage::outOfSdkNotice(Settings::sanitize(['mode' => 'cdn'])));
    }
}
