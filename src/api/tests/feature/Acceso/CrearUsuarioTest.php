<?php

declare(strict_types=1);

namespace Tests\Feature\Acceso;

use App\Libraries\Hash;
use CodeIgniter\Test\StreamFilterTrait;
use Tests\Support\ApiTestCase;

/**
 * `php spark usuarios:crear` (T-S1-01): alta del padre por consola.
 *
 * @internal
 */
final class CrearUsuarioTest extends ApiTestCase
{
    use StreamFilterTrait;

    private function salida(): string
    {
        return $this->getStreamFilterBuffer();
    }

    public function testCreaUnUsuarioConHashes(): void
    {
        command('usuarios:crear --nombre "Mamá" --email Mama@Tareas.Local --password ContrasenaLarga1 --pin 7351');

        $usuario = $this->db->table('usuarios')->where('email', 'mama@tareas.local')->get()->getRowArray();
        $this->assertNotNull($usuario, $this->salida());
        $this->assertSame('Mamá', $usuario['nombre']);
        $this->assertTrue(Hash::verificar('ContrasenaLarga1', $usuario['password_hash']));
        $this->assertTrue(Hash::verificar('7351', $usuario['pin_hash']));
        $this->assertMatchesRegularExpression('/^(\$argon2id\$|\$2y\$)/', $usuario['pin_hash']);
        $this->assertStringContainsString('creado', $this->salida());
    }

    public function testRechazaDatosInvalidos(): void
    {
        command('usuarios:crear --nombre "X" --email no-es-correo --password corta --pin 1234');

        $this->assertSame(1, $this->db->table('usuarios')->countAllResults());
        $salida = $this->salida();
        $this->assertStringContainsString('correo', $salida);
        $this->assertStringContainsString('10 caracteres', $salida);
        $this->assertStringContainsString('PIN', $salida);
    }

    public function testRechazaCorreoDuplicadoYMasDeDosPadres(): void
    {
        command('usuarios:crear --nombre "Otro" --email demo@tareas.local --password ContrasenaLarga1 --pin 7351');
        $this->assertSame(1, $this->db->table('usuarios')->countAllResults());
        $this->assertStringContainsString('ya existe', $this->salida());

        command('usuarios:crear --nombre "Mamá" --email mama@tareas.local --password ContrasenaLarga1 --pin 7351');
        command('usuarios:crear --nombre "Tercero" --email tercero@tareas.local --password ContrasenaLarga1 --pin 7351');
        $this->assertSame(2, $this->db->table('usuarios')->countAllResults());
        $this->assertStringContainsString('máximo 2', $this->salida());
    }
}
