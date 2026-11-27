<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Services\FirebaseTokenService;
use App\Services\SesionService;
use Config\Firebase as FirebaseConfig;
use Tests\Support\ApiTestCase;

/**
 * Pruebas de integración para Chat Familiar (ADR-011): E-54 y E-55.
 *
 * @internal
 */
final class ChatApiTest extends ApiTestCase
{
    public function testSinSesionDevuelve401(): void
    {
        $this->assertError($this->api('get', 'chat/participantes'), 401, 'SIN_SESION');
        $this->assertError($this->api('post', 'chat/token'), 401, 'SIN_SESION');
    }

    public function testConSesionPeroSinPerfilNiModoPadreDevuelve403(): void
    {
        $this->iniciarSesionDemo();

        $this->assertError($this->api('get', 'chat/participantes'), 403, 'SIN_PERFIL');
        $this->assertError($this->api('post', 'chat/token'), 403, 'SIN_PERFIL');
    }

    public function testHijaObtieneParticipantesYTokenDeEmuladorSinDatosSensibles(): void
    {
        $this->iniciarSesionDemo();
        $this->entrarPerfil('Luna');

        $res = $this->conEstado($this->api('get', 'chat/participantes'), 200);
        $c   = $this->cuerpo($res);

        $this->assertSame('hija', $c['actual']['tipo']);
        $this->assertSame('Luna', $c['actual']['apodo']);
        $this->assertSame('exploradoras', $c['actual']['nivel_ux']);
        $this->assertCount(4, $c['participantes']); // 1 padre + 3 hijas

        foreach ($c['participantes'] as $p) {
            $this->assertArrayNotHasKey('email', $p);
            $this->assertArrayNotHasKey('password_hash', $p);
            $this->assertArrayNotHasKey('pin_hash', $p);
        }

        // Configurar modo emulador para la prueba del endpoint E-55
        $cfg            = config(FirebaseConfig::class);
        $cfg->emulador  = true;
        $cfg->projectId = 'tareas-en-familia-local';

        $resToken = $this->conEstado($this->api('post', 'chat/token'), 200);
        $t        = $this->cuerpo($resToken);

        $this->assertNotEmpty($t['token']);
        $this->assertTrue($t['configuracion']['emulador']);
        $this->assertSame('hija:' . $this->perfilId('Luna'), $t['actor']['actor_id']);

        $partes  = explode('.', $t['token']);
        $payload = json_decode((string) base64_decode(strtr($partes[1], '-_', '+/')), true);
        $this->assertSame($t['actor']['actor_id'], $payload['uid']);
        $this->assertTrue($payload['claims']['familia']);
        $this->assertSame('hija', $payload['claims']['rol']);
    }

    public function testModoPadreEmiteTokenConRolPadre(): void
    {
        $this->iniciarSesionDemo();
        $this->entrarModoPadre();

        $cfg           = config(FirebaseConfig::class);
        $cfg->emulador = true;

        $res = $this->conEstado($this->api('post', 'chat/token'), 200);
        $t   = $this->cuerpo($res);

        $this->assertSame('padre', $t['actor']['tipo']);
        $this->assertStringStartsWith('padre:', $t['actor']['actor_id']);

        $partes  = explode('.', $t['token']);
        $payload = json_decode((string) base64_decode(strtr($partes[1], '-_', '+/')), true);
        $this->assertSame('padre', $payload['claims']['rol']);
        $this->assertTrue($payload['claims']['familia']);
    }

    public function testFirmaRs256ValidaCuandoHayLlavePrivada(): void
    {
        $this->iniciarSesionDemo();
        $this->entrarPerfil('Sol');

        $recurso = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($recurso);
        openssl_pkey_export($recurso, $privada);
        $detalles = openssl_pkey_get_details($recurso);
        $this->assertIsArray($detalles);
        $publica = $detalles['key'];

        $cfg              = new FirebaseConfig();
        $cfg->habilitado  = true;
        $cfg->emulador    = false;
        $cfg->projectId   = 'tareas-prod';
        $cfg->clientEmail = 'firebase-adminsdk@tareas-prod.iam.gserviceaccount.com';
        $cfg->privateKey  = $privada;

        $servicio = new FirebaseTokenService(new SesionService(), $cfg);
        $salida   = $servicio->emitirToken();

        $partes = explode('.', $salida['token']);
        $this->assertCount(3, $partes);

        $header = json_decode((string) base64_decode(strtr($partes[0], '-_', '+/')), true);
        $this->assertSame('RS256', $header['alg']);

        $firma    = (string) base64_decode(strtr($partes[2], '-_', '+/'));
        $verifico = openssl_verify($partes[0] . '.' . $partes[1], $firma, $publica, OPENSSL_ALGO_SHA256);
        $this->assertSame(1, $verifico);
    }
}
