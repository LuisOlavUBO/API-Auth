<?php

namespace Tests\Unit;

use App\Http\TrustedProxies;
use PHPUnit\Framework\TestCase;

class TrustedProxiesTest extends TestCase
{
    public function test_empty_trusts_nobody(): void
    {
        $this->assertSame([], TrustedProxies::parse(null));
        $this->assertSame([], TrustedProxies::parse(''));
    }

    public function test_an_asterisk_trusts_every_proxy(): void
    {
        $this->assertSame('*', TrustedProxies::parse('*'));
        $this->assertSame('*', TrustedProxies::parse(' * '));
    }

    public function test_a_list_is_split_by_commas(): void
    {
        $this->assertSame(['10.0.0.1', '172.18.0.0/16'], TrustedProxies::parse('10.0.0.1, 172.18.0.0/16,'));
    }
}
