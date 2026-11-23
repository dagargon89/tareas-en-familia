<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Libraries\ErrorApi;
use CodeIgniter\HTTP\ResponsableInterface;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Error de negocio que se traduce a la respuesta uniforme del doc 05.
 *
 * Los Services la lanzan; CodeIgniter la captura (`ResponsableInterface`)
 * y envía `getResponse()`, pasando por los filtros `required.after`.
 */
class ApiException extends RuntimeException implements ResponsableInterface
{
    /**
     * @param array<string, string>|null $campos
     * @param array<string, mixed>       $extra
     * @param array<string, string>      $headers
     */
    public function __construct(
        private readonly int $estado,
        private readonly string $codigo,
        string $mensaje,
        private readonly ?array $campos = null,
        private readonly array $extra = [],
        private readonly array $headers = [],
    ) {
        parent::__construct($mensaje, $estado);
    }

    /**
     * @param array<string, string> $campos
     */
    public static function validacion(array $campos, string $mensaje = 'Revisa los datos marcados.'): self
    {
        return new self(400, 'VALIDACION', $mensaje, $campos);
    }

    public static function noEncontrado(string $mensaje = 'No encontramos lo que buscas.'): self
    {
        return new self(404, 'NO_ENCONTRADO', $mensaje);
    }

    public static function prohibido(string $mensaje = 'No tienes permiso para esto.'): self
    {
        return new self(403, 'PROHIBIDO', $mensaje);
    }

    public static function estadoInvalido(string $mensaje): self
    {
        return new self(409, 'ESTADO_INVALIDO', $mensaje);
    }

    public static function sinSesion(): self
    {
        return new self(401, 'SIN_SESION', 'Inicia sesión para continuar.');
    }

    public static function credenciales(): self
    {
        return new self(401, 'CREDENCIALES', 'El correo o la contraseña no coinciden.');
    }

    public static function pinIncorrecto(int $intentosRestantes): self
    {
        return new self(401, 'PIN_INCORRECTO', 'Ese PIN no es. Inténtalo otra vez.', null, ['intentos_restantes' => $intentosRestantes]);
    }

    public static function bloqueado(string $bloqueadoHasta): self
    {
        return new self(423, 'BLOQUEADO', 'Vamos a esperar un ratito antes de intentarlo otra vez.', null, ['bloqueado_hasta' => $bloqueadoHasta]);
    }

    public static function demasiadosIntentos(int $segundos): self
    {
        return new self(429, 'DEMASIADOS_INTENTOS', 'Demasiados intentos. Espera un minuto.', null, [], ['Retry-After' => (string) max(1, $segundos)]);
    }

    public static function modoPadreRequerido(): self
    {
        return new self(403, 'MODO_PADRE_REQUERIDO', 'Entra a modo padre para continuar.');
    }

    public static function sinPerfil(): self
    {
        return new self(403, 'SIN_PERFIL', 'Elige tu perfil para continuar.');
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function getEstado(): int
    {
        return $this->estado;
    }

    public function getResponse(): ResponseInterface
    {
        $respuesta = ErrorApi::respuesta($this->estado, $this->codigo, $this->getMessage(), $this->campos, $this->extra);

        foreach ($this->headers as $nombre => $valor) {
            $respuesta->setHeader($nombre, $valor);
        }

        return $respuesta;
    }
}
