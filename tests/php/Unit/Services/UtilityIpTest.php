<?php

namespace FluentEvent\Tests\Unit\Services;

use Brain\Monkey\Functions;
use FluentEvent\App\Utils\Utility;
use FluentEvent\Tests\TestCase;

/**
 * Client-IP resolution for rate limiting. A client that can dictate its address gets a fresh
 * counter on every request, so a forwarded header counts only from Cloudflare or a trusted proxy.
 */
class UtilityIpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('rest_is_ip_address')->alias(function ($ip) {
            return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : false;
        });
    }

    /**
     * @dataProvider cloudflareAddresses
     */
    public function test_it_recognises_addresses_inside_cloudflare_ranges($ip)
    {
        $this->assertTrue(Utility::isCfIp($ip));
    }

    public function cloudflareAddresses()
    {
        return [['173.245.48.1'], ['104.16.0.5'], ['162.158.0.5'], ['172.64.0.5'], ['2606:4700::6810:84e5']];
    }

    /**
     * @dataProvider otherAddresses
     */
    public function test_it_rejects_addresses_outside_cloudflare_ranges($ip)
    {
        $this->assertFalse(Utility::isCfIp($ip));
    }

    public function otherAddresses()
    {
        return [
            'public'                  => ['8.8.8.8'],
            'loopback'                => ['127.0.0.1'],
            'just below 104.16.0.0/13' => ['104.15.255.255'],
            'just above 172.64.0.0/13' => ['172.72.0.0'],
            'empty'                   => [''],
        ];
    }

    public function test_the_cloudflare_header_counts_only_from_a_cloudflare_connection()
    {
        $this->assertSame('8.8.8.8', Utility::resolveClientIp(['REMOTE_ADDR' => '8.8.8.8', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4']));
        $this->assertSame('1.2.3.4', Utility::resolveClientIp(['REMOTE_ADDR' => '162.158.0.5', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4']));
        $this->assertSame('2001:db8::1', Utility::resolveClientIp(['REMOTE_ADDR' => '2606:4700::6810:84e5', 'HTTP_CF_CONNECTING_IP' => '2001:db8::1']));
    }

    public function test_a_cloudflare_header_that_is_not_an_address_is_ignored()
    {
        $this->assertSame('162.158.0.5', Utility::resolveClientIp(['REMOTE_ADDR' => '162.158.0.5', 'HTTP_CF_CONNECTING_IP' => 'not-an-ip']));
    }

    public function test_forwarded_for_is_ignored_with_no_trusted_proxy()
    {
        $this->assertSame('127.0.0.1', Utility::resolveClientIp(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']));
    }

    public function test_forwarded_for_is_ignored_from_an_untrusted_connection()
    {
        $this->assertSame('8.8.8.8', Utility::resolveClientIp(['REMOTE_ADDR' => '8.8.8.8', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4'], ['10.0.0.0/8']));
    }

    public function test_behind_a_trusted_proxy_the_right_most_hop_counts()
    {
        // The left-most value is whatever the client sent; the proxy appends the peer it saw.
        $this->assertSame('203.0.113.9', Utility::resolveClientIp([
            'REMOTE_ADDR'          => '10.1.2.3',
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 203.0.113.9',
        ], ['10.0.0.0/8']));
    }

    public function test_a_trusted_proxy_can_be_a_single_address()
    {
        $this->assertSame('203.0.113.9', Utility::resolveClientIp(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'], ['127.0.0.1']));
    }

    public function test_a_forwarded_value_that_is_not_an_address_falls_back_to_the_connection()
    {
        $this->assertSame('127.0.0.1', Utility::resolveClientIp(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => 'not-an-ip'], ['127.0.0.1']));
    }

    public function test_an_ipv4_mapped_address_is_read_as_ipv4()
    {
        $this->assertSame('203.0.113.9', Utility::resolveClientIp(['REMOTE_ADDR' => '::ffff:203.0.113.9']));
    }

    public function test_a_mapped_cloudflare_connection_still_counts_as_cloudflare()
    {
        $this->assertSame('1.2.3.4', Utility::resolveClientIp([
            'REMOTE_ADDR'           => '::ffff:162.158.0.5',
            'HTTP_CF_CONNECTING_IP' => '1.2.3.4',
        ]));
    }

    public function test_a_plain_ipv6_address_is_left_as_it_is()
    {
        $this->assertSame('2001:db8::5', Utility::resolveClientIp(['REMOTE_ADDR' => '2001:db8::5']));
    }

    public function test_a_port_is_stripped()
    {
        $this->assertSame('203.0.113.9', Utility::resolveClientIp(['REMOTE_ADDR' => '203.0.113.9:54321']));
        $this->assertSame('2001:db8::5', Utility::resolveClientIp(['REMOTE_ADDR' => '[2001:db8::5]:443']));
    }
}
