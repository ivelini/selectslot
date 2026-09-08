<?php

namespace Tests\Unit\Support;

use App\Support\Phone;
use Tests\TestCase;

class PhoneTest extends TestCase
{
    public function test_normalize_accepts_common_input_formats(): void
    {
        $this->assertSame('79001234567', Phone::normalize('+7 (900) 123-45-67'));
        $this->assertSame('79001234567', Phone::normalize('8 900 123-45-67'));
        $this->assertSame('79001234567', Phone::normalize('89001234567'));
        $this->assertSame('79000000000', Phone::normalize('+7 900 000-00-00'));
    }

    public function test_normalize_rejects_invalid_phones(): void
    {
        $this->assertNull(Phone::normalize('123'));
        $this->assertNull(Phone::normalize('9001234567'));
        $this->assertNull(Phone::normalize('+7 (900) 123-45-6'));
        $this->assertNull(Phone::normalize('abc'));
        $this->assertNull(Phone::normalize(''));
        $this->assertNull(Phone::normalize(null));
    }
}
