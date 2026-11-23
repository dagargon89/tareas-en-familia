<?php

declare(strict_types=1);

namespace App\Commands;

use App\Exceptions\ApiException;
use App\Models\UsuarioModel;
use App\Services\UsuariosService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Alta del padre o la madre por consola (T-S1-01). Desde la app, en modo padre, con E-52 (ADR-009);
 * las dos vías usan las mismas reglas (`UsuariosService`). No existe registro público.
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
        $usuarios = new UsuariosService(db_connect());

        if (! $usuarios->hayLugar()) {
            CLI::error('Ya hay ' . UsuarioModel::MAX_USUARIOS . ' usuarios: el máximo 2 está completo.');

            return EXIT_ERROR;
        }

        $entrada = [
            'nombre'   => $this->dato($params, 'nombre', 'Nombre'),
            'email'    => $this->dato($params, 'email', 'Correo'),
            'password' => $this->dato($params, 'password', 'Contraseña (mínimo 10 caracteres)'),
            'pin'      => $this->dato($params, 'pin', 'PIN de modo padre (4 dígitos)'),
        ];

        try {
            $usuario = $usuarios->crear($entrada);
        } catch (ApiException $e) {
            foreach ($e->getCampos() ?? ['' => $e->getMessage()] as $campo => $mensaje) {
                CLI::error(($campo === 'pin' ? 'PIN: ' : '') . $mensaje);
            }

            return EXIT_ERROR;
        }

        CLI::write("Usuario creado: {$usuario['nombre']} <{$usuario['email']}>.", 'green');

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
