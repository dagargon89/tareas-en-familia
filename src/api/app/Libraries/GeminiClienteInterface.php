<?php

declare(strict_types=1);

namespace App\Libraries;

/**
 * Contrato del cliente HTTP hacia la Gemini Developer API (ADR-012).
 * Permite sustituir la llamada externa por un doble en PHPUnit.
 */
interface GeminiClienteInterface
{
    /**
     * @param list<array{rol: 'usuario'|'asistente', texto: string}> $historial
     */
    public function generar(string $systemInstruction, array $historial, string $mensaje): string;
}
