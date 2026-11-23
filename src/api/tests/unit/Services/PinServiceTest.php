<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PinService;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Regla de PIN del doc 04 §3.1: 4 dígitos; se rechazan repetidos (0000…9999) y secuencias.
 *
 * @internal
 */
final class PinServiceTest extends CIUnitTestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function triviales(): iterable
    {
        for ($d = 0; $d <= 9; $d++) {
            yield str_repeat((string) $d, 4) => [str_repeat((string) $d, 4)];
        }

        foreach (['0123', '1234', '2345', '3456', '4567', '5678', '6789', '9876', '8765', '7654', '6543', '5432', '4321', '3210'] as $pin) {
            yield $pin => [$pin];
        }
    }

    #[DataProvider('triviales')]
    public function testRechazaPinTrivial(string $pin): void
    {
        $this->assertTrue(PinService::esTrivial($pin));
        $this->assertNotNull(PinService::errorDePinNuevo($pin));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validos(): iterable
    {
        foreach (['4826', '3917', '0912', '1235', '7890', '1122', '2580'] as $pin) {
            yield $pin => [$pin];
        }
    }

    #[DataProvider('validos')]
    public function testAceptaPinNoTrivial(string $pin): void
    {
        $this->assertFalse(PinService::esTrivial($pin));
        $this->assertNull(PinService::errorDePinNuevo($pin));
    }

    public function testFormato(): void
    {
        foreach ([null, '', '123', '12345', '12a4', 4826, ' 4826'] as $pin) {
            $this->assertNotNull(PinService::errorDePinNuevo($pin), var_export($pin, true));
            $this->assertFalse(PinService::formatoValido($pin));
        }
    }
}
