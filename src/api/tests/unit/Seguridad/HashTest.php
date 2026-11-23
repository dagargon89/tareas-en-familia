<?php

declare(strict_types=1);

namespace Tests\Unit\Seguridad;

use App\Libraries\Hash;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * CP-SEG-06 · Contraseña y PIN se guardan con Argon2id (o BCRYPT costo 12
 * si el PHP del hosting no tiene Argon2), doc 04 §3.1.
 *
 * @internal
 */
final class HashTest extends CIUnitTestCase
{
    public function testHashDeContrasenaYPinEsArgon2idOBcrypt(): void
    {
        foreach (['Contrasena-de-prueba-123', '4826'] as $secreto) {
            $hash = Hash::crear($secreto);

            $this->assertMatchesRegularExpression('/^(\$argon2id\$|\$2y\$)/', $hash);
            $this->assertTrue(Hash::verificar($secreto, $hash));
            $this->assertFalse(Hash::verificar($secreto . 'x', $hash));
        }
    }

    public function testConArgon2DisponibleUsaArgon2id(): void
    {
        if (! defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Este PHP no tiene Argon2id; aplica el respaldo BCRYPT.');
        }

        $this->assertStringStartsWith('$argon2id$', Hash::crear('4826'));
    }

    public function testRespaldoBcryptCosto12(): void
    {
        [$algoritmo, $opciones] = Hash::configuracion(false);

        $this->assertSame(PASSWORD_BCRYPT, $algoritmo);
        $this->assertSame(['cost' => 12], $opciones);

        $hash = password_hash('4826', $algoritmo, $opciones);
        $this->assertStringStartsWith('$2y$12$', $hash);
        $this->assertTrue(Hash::verificar('4826', $hash));
    }

    public function testNecesitaRehashSiElHashNoEsDelAlgoritmoVigente(): void
    {
        $viejo = password_hash('4826', PASSWORD_BCRYPT, ['cost' => 10]);

        $this->assertTrue(Hash::necesitaRehash($viejo));
        $this->assertFalse(Hash::necesitaRehash(Hash::crear('4826')));
    }
}
