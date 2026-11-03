<?php

namespace Tests\Unit;

use App\Rules\Rut;
use PHPUnit\Framework\TestCase;

class RutTest extends TestCase
{
    protected function errorFor(mixed $value): ?string
    {
        $error = null;

        (new Rut)->validate('rut', $value, function (string $message) use (&$error) {
            $error = $message;
        });

        return $error;
    }

    public function test_valid_ruts_pass(): void
    {
        $this->assertNull($this->errorFor('12345678-5'));
        $this->assertNull($this->errorFor('1234567-4'));
        $this->assertNull($this->errorFor('1000005-K'));
    }

    public function test_a_wrong_check_digit_fails(): void
    {
        $this->assertSame('El :attribute no es un RUT válido.', $this->errorFor('12345678-9'));
    }

    public function test_an_invalid_format_fails(): void
    {
        $this->assertSame('El :attribute no tiene el formato 12345678-9.', $this->errorFor('12.345.678-5'));
        $this->assertSame('El :attribute no tiene el formato 12345678-9.', $this->errorFor('abc'));
    }
}
