<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Perfil;
use App\Exceptions\ApiException;
use App\Models\PerfilModel;
use App\Models\UsuarioModel;
use CodeIgniter\I18n\Time;
use Config\Firebase as FirebaseConfig;
use RuntimeException;

/**
 * Identidad unificada y emisión de Firebase Custom Tokens para el chat familiar (ADR-011).
 *
 * - Si el dispositivo está en modo padre vigente, el actor es `padre:<usuario_id>`.
 * - Si tiene un perfil de hija activo, el actor es `hija:<perfil_id>`.
 * - En producción firma JWT RS256 con la llave privada de la Service Account (`openssl_sign`).
 * - En desarrollo con `firebase.emulador = true` y sin llave privada, emite un JWT `alg: none`
 *   aceptado de forma nativa por el Firebase Auth Emulator.
 */
final class FirebaseTokenService
{
    public const DURACION_SEGUNDOS = 3600;

    private const AUDIENCE = 'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit';

    private readonly SesionService $sesion;
    private readonly FirebaseConfig $config;

    public function __construct(?SesionService $sesion = null, ?FirebaseConfig $config = null)
    {
        $this->sesion = $sesion ?? new SesionService();
        $this->config = $config ?? config(FirebaseConfig::class);
    }

    /**
     * Actor activo de la sesión actual (modo padre tiene prioridad si está vigente).
     *
     * @return array{actor_id: string, tipo: 'padre'|'hija', id: int, apodo: string, avatar: string, color: string, nivel_ux: string|null}
     */
    public function actorActual(): array
    {
        $usuarioId = $this->sesion->usuarioId();

        if ($usuarioId === null) {
            throw ApiException::sinSesion();
        }

        if ($this->sesion->modoPadreHasta() !== null) {
            $usuario = model(UsuarioModel::class)->find($usuarioId);

            if ($usuario === null) {
                throw ApiException::sinSesion();
            }

            return $this->aActorPadre($usuario);
        }

        $perfilId = $this->sesion->perfilActivoId();

        if ($perfilId === null) {
            throw ApiException::sinPerfil();
        }

        $perfil = model(PerfilModel::class)->activo($perfilId);

        if ($perfil === null) {
            $this->sesion->salirPerfil();

            throw ApiException::sinPerfil();
        }

        return $this->aActorHija($perfil);
    }

    /**
     * E-54 · Actor actual y todos los integrantes activos de la familia (sin correos ni PINs).
     *
     * @return array{actual: array<string, mixed>, participantes: list<array<string, mixed>>}
     */
    public function participantes(): array
    {
        $actual        = $this->actorActual();
        $participantes = [];

        $usuarios = model(UsuarioModel::class)->orderBy('id', 'ASC')->findAll();

        foreach ($usuarios as $usuario) {
            $participantes[] = $this->aActorPadre($usuario);
        }

        foreach (model(PerfilModel::class)->activosOrdenados() as $perfil) {
            /** @var Perfil $perfil */
            $participantes[] = $this->aActorHija($perfil);
        }

        return [
            'actual'        => $actual,
            'participantes' => $participantes,
        ];
    }

    /**
     * E-55 · Emite un Custom Token de Firebase para el actor actual.
     *
     * @return array{
     *   token: string,
     *   expira_en: int,
     *   actor: array<string, mixed>,
     *   participantes: list<array<string, mixed>>,
     *   configuracion: array<string, mixed>
     * }
     */
    public function emitirToken(): array
    {
        if (! $this->config->habilitado) {
            throw new ApiException(503, 'CHAT_NO_DISPONIBLE', 'El chat familiar no está activado ahorita.');
        }

        $datos = $this->participantes();
        $actor = $datos['actual'];

        $ahora = Time::now()->getTimestamp();
        $exp   = $ahora + self::DURACION_SEGUNDOS;

        $usaEmulador = $this->config->emulador && ENVIRONMENT !== 'production';
        $privateKey  = trim(str_replace('\n', "\n", $this->config->privateKey));
        $clientEmail = trim($this->config->clientEmail);

        $claims = [
            'familia' => true,
            'rol'     => $actor['tipo'],
            'apodo'   => $actor['apodo'],
        ];

        if ($privateKey !== '' && $clientEmail !== '') {
            $token = $this->firmarRs256($clientEmail, $privateKey, $actor['actor_id'], $claims, $ahora, $exp);
        } elseif ($usaEmulador) {
            $token = $this->firmarEmulador($actor['actor_id'], $claims, $ahora, $exp);
        } else {
            throw new ApiException(503, 'CHAT_NO_CONFIGURADO', 'Falta configurar Firebase para activar el chat familiar.');
        }

        return [
            'token'         => $token,
            'expira_en'     => self::DURACION_SEGUNDOS,
            'actor'         => $actor,
            'participantes' => $datos['participantes'],
            'configuracion' => [
                'projectId'             => $this->config->projectId,
                'apiKey'                => $this->config->apiKey,
                'emulador'              => $usaEmulador,
                'authEmulatorUrl'       => $usaEmulador ? $this->config->authEmulatorUrl : null,
                'firestoreEmulatorHost' => $usaEmulador ? $this->config->firestoreEmulatorHost : null,
                'firestoreEmulatorPort' => $usaEmulador ? $this->config->firestoreEmulatorPort : null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function firmarRs256(string $clientEmail, string $privateKey, string $uid, array $claims, int $iat, int $exp): string
    {
        $header  = self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = self::base64Url(json_encode([
            'iss'    => $clientEmail,
            'sub'    => $clientEmail,
            'aud'    => self::AUDIENCE,
            'iat'    => $iat,
            'exp'    => $exp,
            'uid'    => $uid,
            'claims' => $claims,
        ], JSON_THROW_ON_ERROR));

        $firma = '';
        $ok    = openssl_sign("{$header}.{$payload}", $firma, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $ok) {
            throw new RuntimeException('No se pudo firmar el token de Firebase con la llave configurada.');
        }

        return "{$header}.{$payload}." . self::base64Url($firma);
    }

    /**
     * Token sin firma (`alg: none`) exclusivamente para Firebase Auth Emulator en desarrollo.
     *
     * @param array<string, mixed> $claims
     */
    private function firmarEmulador(string $uid, array $claims, int $iat, int $exp): string
    {
        $emisor  = 'firebase-adminsdk@' . $this->config->projectId . '.iam.gserviceaccount.com';
        $header  = self::base64Url(json_encode(['alg' => 'none', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = self::base64Url(json_encode([
            'iss'    => $emisor,
            'sub'    => $emisor,
            'aud'    => self::AUDIENCE,
            'iat'    => $iat,
            'exp'    => $exp,
            'uid'    => $uid,
            'claims' => $claims,
        ], JSON_THROW_ON_ERROR));

        return "{$header}.{$payload}.";
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * @param array<string, mixed> $usuario
     *
     * @return array{actor_id: string, tipo: 'padre', id: int, apodo: string, avatar: string, color: string, nivel_ux: null}
     */
    private function aActorPadre(array $usuario): array
    {
        $id = (int) $usuario['id'];

        return [
            'actor_id' => 'padre:' . $id,
            'tipo'     => 'padre',
            'id'       => $id,
            'apodo'    => (string) $usuario['nombre'],
            'avatar'   => 'corona',
            'color'    => 'indigo',
            'nivel_ux' => null,
        ];
    }

    /**
     * @return array{actor_id: string, tipo: 'hija', id: int, apodo: string, avatar: string, color: string, nivel_ux: string}
     */
    private function aActorHija(Perfil $perfil): array
    {
        return [
            'actor_id' => 'hija:' . $perfil->id,
            'tipo'     => 'hija',
            'id'       => $perfil->id,
            'apodo'    => $perfil->apodo,
            'avatar'   => $perfil->avatar,
            'color'    => $perfil->color,
            'nivel_ux' => $perfil->nivel_ux,
        ];
    }
}
