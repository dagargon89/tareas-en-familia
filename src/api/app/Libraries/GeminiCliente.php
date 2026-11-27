<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Gemini as GeminiConfig;
use Config\Services;
use RuntimeException;

/**
 * Cliente REST para Gemini Developer API usando `CURLRequest` de CI4 (ADR-012).
 */
final class GeminiCliente implements GeminiClienteInterface
{
    private readonly GeminiConfig $config;

    public function __construct(?GeminiConfig $config = null)
    {
        $this->config = $config ?? config(GeminiConfig::class);
    }

    /**
     * @param list<array{rol: 'usuario'|'asistente', texto: string}> $historial
     */
    public function generar(string $systemInstruction, array $historial, string $mensaje): string
    {
        $apiKey = trim($this->config->apiKey);

        if ($apiKey === '') {
            throw new RuntimeException('Falta configurar gemini.apiKey.');
        }

        $contents = [];

        foreach ($historial as $turno) {
            $contents[] = [
                'role'  => $turno['rol'] === 'asistente' ? 'model' : 'user',
                'parts' => [['text' => $turno['texto']]],
            ];
        }

        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $mensaje]],
        ];

        $url = rtrim($this->config->baseUrl, '/') . '/' . rawurlencode($this->config->model) . ':generateContent';

        $cliente   = Services::curlrequest(['timeout' => max(3, $this->config->timeout), 'http_errors' => false]);
        $respuesta = $cliente->post($url, [
            'headers' => [
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $apiKey,
            ],
            'json' => [
                'systemInstruction' => [
                    'parts' => [['text' => $systemInstruction]],
                ],
                'contents'         => $contents,
                'generationConfig' => [
                    'temperature'     => 0.3,
                    'maxOutputTokens' => 450,
                ],
            ],
        ]);

        if ($respuesta->getStatusCode() < 200 || $respuesta->getStatusCode() >= 300) {
            throw new RuntimeException('Gemini respondió con estado ' . $respuesta->getStatusCode());
        }

        $datos = json_decode((string) $respuesta->getBody(), true);
        $texto = is_array($datos) ? ($datos['candidates'][0]['content']['parts'][0]['text'] ?? null) : null;

        if (! is_string($texto) || trim($texto) === '') {
            throw new RuntimeException('Gemini devolvió una respuesta vacía.');
        }

        return trim($texto);
    }
}
