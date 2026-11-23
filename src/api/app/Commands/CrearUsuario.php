<?php

declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Hash;
use App\Models\UsuarioModel;
use App\Services\PinService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Alta del padre o la madre por consola (T-S1-01). No existe endpoint de registro.
 *
 * Uso: `php spark usuarios:crear` (pregunta los datos) o con opciones
 * `--nombre --email --password --pin`.
 */
class CrearUsuario extends BaseCommand
{
    protected $group       = 'Tareas';
    protected $name        = 'usuarios:crear';
    protected $description = 'Crea un usuario padre/madre (máximo 2) con contraseña y PIN de modo padre.';
    protected $usage       = 'usuarios:crear [--nombre <nombre>] [--email <correo>] [--password <contraseña>] [--pin <PIN>]';

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--nombre'   => 'Nombre visible (máx. 60).',
        '--email'    => 'Correo de acceso.',
        '--password' => 'Contraseña (mínimo 10 caracteres).',
        '--pin'      => 'PIN de modo padre (4 dígitos, no trivial).',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        $usuarios = model(UsuarioModel::class);

        if ($usuarios->countAllResults() >= UsuarioModel::MAX_USUARIOS) {
            CLI::error('Ya hay ' . UsuarioModel::MAX_USUARIOS . ' usuarios: el máximo 2 está completo.');

            return EXIT_ERROR;
        }

        $nombre   = trim($this->dato($params, 'nombre', 'Nombre'));
        $email    = UsuarioModel::normalizarEmail($this->dato($params, 'email', 'Correo'));
        $password = $this->dato($params, 'password', 'Contraseña (mínimo 10 caracteres)');
        $pin      = $this->dato($params, 'pin', 'PIN de modo padre (4 dígitos)');

        $errores = [];

        if ($nombre === '' || mb_strlen($nombre) > 60) {
            $errores[] = 'El nombre es obligatorio (máximo 60 caracteres).';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 120) {
            $errores[] = 'El correo no es válido.';
        } elseif ($usuarios->porEmail($email) !== null) {
            $errores[] = "Ese correo ya existe: {$email}.";
        }

        if (mb_strlen($password) < 10) {
            $errores[] = 'La contraseña debe tener al menos 10 caracteres.';
        }

        $errorPin = PinService::errorDePinNuevo($pin);

        if ($errorPin !== null) {
            $errores[] = 'PIN: ' . $errorPin;
        }

        if ($errores !== []) {
            foreach ($errores as $error) {
                CLI::error($error);
            }

            return EXIT_ERROR;
        }

        $usuarios->insert([
            'nombre'        => $nombre,
            'email'         => $email,
            'password_hash' => Hash::crear($password),
            'pin_hash'      => Hash::crear($pin),
        ]);

        CLI::write("Usuario creado: {$nombre} <{$email}>.", 'green');

        return EXIT_SUCCESS;
    }

    /**
     * @param array<int|string, string|null> $params
     */
    private function dato(array $params, string $opcion, string $pregunta): string
    {
        $valor = $params[$opcion] ?? CLI::getOption($opcion);

        if (! is_string($valor) || $valor === '') {
            $valor = CLI::prompt($pregunta, null, 'required');
        }

        return (string) $valor;
    }
}
