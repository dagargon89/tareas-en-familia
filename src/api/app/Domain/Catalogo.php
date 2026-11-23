<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Catálogo único de íconos y avatares (doc 08 §5). Son las mismas claves que
 * `src/web/src/lib/iconos.ts` y `avatares.ts`; `CatalogoTest` verifica que no se desalineen.
 * En la base se guarda la clave en español, nunca el nombre de Lucide.
 */
final class Catalogo
{
    /**
     * Claves válidas para `tareas.icono`, `categorias.icono`, `insignias.icono` y `recompensas.icono`.
     *
     * @var list<string>
     */
    public const ICONOS = [
        'cama', 'libro', 'lapiz', 'calculadora', 'mochila', 'ropa', 'comida', 'bano', 'ducha', 'dientes',
        'basura', 'perro', 'mascota', 'musica', 'arte', 'bici', 'ejercicio', 'trofeo', 'regalo', 'helado',
        'videojuego', 'tele', 'palomitas', 'pizza', 'ciencia', 'mundo', 'idiomas', 'sillon', 'lavadora',
        'manzana', 'verdura', 'sonrisa', 'tiempo', 'rompecabezas', 'teatro', 'boleto', 'ayudar', 'planta',
        'caminar', 'brillo', 'casa', 'corazon', 'repetir', 'estrella', 'medalla', 'corona', 'fuego', 'cine',
        'tableta', 'arboles', 'nadar', 'agua',
    ];

    /**
     * Claves válidas para `perfiles.avatar`.
     *
     * @var list<string>
     */
    public const AVATARES = [
        'gato', 'perro', 'conejo', 'ardilla', 'tortuga', 'pajaro', 'pez', 'caracol', 'catarina', 'flor',
        'estrella', 'luna', 'sol', 'hoja', 'corazon', 'nube',
    ];

    public static function esIcono(mixed $clave): bool
    {
        return is_string($clave) && in_array($clave, self::ICONOS, true);
    }

    public static function esAvatar(mixed $clave): bool
    {
        return is_string($clave) && in_array($clave, self::AVATARES, true);
    }
}
