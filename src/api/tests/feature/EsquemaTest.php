<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use mysqli_sql_exception;

/**
 * T-S0-02 · Las migraciones reproducen exactamente `docs/03-datos/03_esquema.sql`.
 *
 * Compara `information_schema` de la base de pruebas (recién migrada) contra
 * `docs/03-datos/03_esquema.json`, y lo que el JSON no cubre (índices no
 * únicos, motor/colación, vista y CHECK de JSON) contra `03_esquema.sql`.
 *
 * @internal
 */
final class EsquemaTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = true;
    protected $namespace   = 'App';

    private static function rutaDocs(string $archivo): string
    {
        return dirname(__DIR__, 4) . '/docs/03-datos/' . $archivo;
    }

    /**
     * @return array<string, mixed>
     */
    private static function esperado(): array
    {
        $json = json_decode((string) file_get_contents(self::rutaDocs('03_esquema.json')), true, 512, JSON_THROW_ON_ERROR);

        return $json['tablas'];
    }

    private function bd(): BaseConnection
    {
        return $this->db;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function consulta(string $sql): array
    {
        return $this->bd()->query($sql)->getResultArray();
    }

    /**
     * Normaliza `COLUMN_DEFAULT` de MariaDB (`'texto'`, `NULL`, `1`) al formato del JSON.
     */
    private static function normalizarDefault(?string $valor): ?string
    {
        if ($valor === null || strtoupper($valor) === 'NULL') {
            return null;
        }

        if (preg_match("/^'(.*)'$/s", $valor, $m) === 1) {
            return str_replace("''", "'", $m[1]);
        }

        return $valor;
    }

    /**
     * `int(10) unsigned` → `INT(10) UNSIGNED`; los valores entre comillas de ENUM/SET se respetan.
     */
    private static function normalizarTipo(string $tipo): string
    {
        $partes = preg_split("/('(?:[^']|'')*')/", $tipo, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$tipo];

        return implode('', array_map(
            static fn (string $p): string => str_starts_with($p, "'") ? $p : strtoupper($p),
            $partes,
        ));
    }

    /**
     * Esquema real con la misma forma que `03_esquema.json`.
     *
     * @return array<string, mixed>
     */
    private function real(): array
    {
        $tablas = [];

        $columnas = $this->consulta(
            "SELECT c.TABLE_NAME, c.COLUMN_NAME, c.COLUMN_TYPE, c.IS_NULLABLE, c.COLUMN_DEFAULT, c.EXTRA
               FROM information_schema.COLUMNS c
               JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
              WHERE c.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE' AND c.TABLE_NAME <> 'migrations'
              ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION",
        );

        foreach ($columnas as $c) {
            $col = [
                'tipo' => self::normalizarTipo($c['COLUMN_TYPE']),
                'nulo' => $c['IS_NULLABLE'] === 'YES',
            ];
            $extra = strtolower((string) $c['EXTRA']);

            if (str_contains($extra, 'auto_increment')) {
                $col['auto_incremento'] = true;
            }

            if (str_contains($extra, 'generated')) {
                $col['generada'] = true;
            }

            if (preg_match("/^(enum|set)\((.*)\)$/i", $c['COLUMN_TYPE'], $m) === 1) {
                preg_match_all("/'((?:[^']|'')*)'/", $m[2], $valores);
                $col['valores'] = $valores[1];
            }

            $default = self::normalizarDefault($c['COLUMN_DEFAULT']);

            if ($default !== null && ! isset($col['generada'])) {
                $col['default'] = $default;
            }

            $tablas[$c['TABLE_NAME']]['columnas'][$c['COLUMN_NAME']] = $col;
        }

        foreach (array_keys($tablas) as $tabla) {
            $tablas[$tabla]['pk']     = [];
            $tablas[$tabla]['unicas'] = [];
            $tablas[$tabla]['fks']    = [];
        }

        $indices = $this->consulta(
            "SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, NON_UNIQUE
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME <> 'migrations'
              ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
        );

        foreach ($indices as $i) {
            if ($i['INDEX_NAME'] === 'PRIMARY') {
                $tablas[$i['TABLE_NAME']]['pk'][] = $i['COLUMN_NAME'];
            } elseif ((int) $i['NON_UNIQUE'] === 0) {
                $tablas[$i['TABLE_NAME']]['unicas'][$i['INDEX_NAME']][] = $i['COLUMN_NAME'];
            }
        }

        $fks = $this->consulta(
            "SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
                    r.DELETE_RULE, r.UPDATE_RULE
               FROM information_schema.KEY_COLUMN_USAGE k
               JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                 ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                AND r.TABLE_NAME = k.TABLE_NAME
              WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL
              ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME",
        );

        foreach ($fks as $f) {
            $tablas[$f['TABLE_NAME']]['fks'][] = [
                'nombre'     => $f['CONSTRAINT_NAME'],
                'columna'    => $f['COLUMN_NAME'],
                'referencia' => $f['REFERENCED_TABLE_NAME'] . '.' . $f['REFERENCED_COLUMN_NAME'],
                'on_delete'  => $f['DELETE_RULE'],
                'on_update'  => $f['UPDATE_RULE'],
            ];
        }

        return $tablas;
    }

    public function testExistenLasDieciseisTablasDelDoc03(): void
    {
        $esperadas = array_keys(self::esperado());
        $reales    = array_keys($this->real());
        sort($esperadas);
        sort($reales);

        $this->assertCount(16, $esperadas);
        $this->assertSame($esperadas, $reales);
    }

    public function testColumnasCoincidenConElJson(): void
    {
        $real  = $this->real();
        $total = 0;

        foreach (self::esperado() as $tabla => $definicion) {
            $this->assertSame(
                array_keys($definicion['columnas']),
                array_keys($real[$tabla]['columnas']),
                "Columnas y orden de {$tabla}",
            );

            foreach ($definicion['columnas'] as $columna => $esperada) {
                $actual = $real[$tabla]['columnas'][$columna];
                ksort($esperada);
                ksort($actual);
                $this->assertSame($esperada, $actual, "{$tabla}.{$columna}");
                $total++;
            }
        }

        $this->assertSame(134, $total, 'El doc 03 declara 134 columnas.');
    }

    public function testLlavesPrimariasYUnicasCoincidenConElJson(): void
    {
        $real = $this->real();

        foreach (self::esperado() as $tabla => $definicion) {
            $this->assertSame($definicion['pk'], $real[$tabla]['pk'], "PK de {$tabla}");

            $esperadas = $definicion['unicas'];
            $actuales  = $real[$tabla]['unicas'];
            ksort($esperadas);
            ksort($actuales);
            $this->assertSame($esperadas, $actuales, "Únicas de {$tabla}");
        }
    }

    public function testLlavesForaneasCoincidenConElJson(): void
    {
        $real  = $this->real();
        $total = 0;

        foreach (self::esperado() as $tabla => $definicion) {
            $orden = static fn (array $a, array $b): int => strcmp($a['nombre'], $b['nombre']);
            $esperadas = $definicion['fks'];
            $actuales  = $real[$tabla]['fks'];
            usort($esperadas, $orden);
            usort($actuales, $orden);

            $this->assertSame($esperadas, $actuales, "FKs de {$tabla}");
            $total += count($esperadas);
        }

        $this->assertSame(16, $total, 'El doc 03 declara 16 llaves foráneas.');
    }

    public function testIndicesNoUnicosCoincidenConElSql(): void
    {
        $sql = (string) file_get_contents(self::rutaDocs('03_esquema.sql'));
        preg_match_all('/CREATE TABLE (\w+) \((.*?)\n\) ENGINE/s', $sql, $tablas, PREG_SET_ORDER);

        $esperados = [];

        foreach ($tablas as [, $tabla, $cuerpo]) {
            preg_match_all('/^\s+KEY (\w+) \(([^)]+)\)/m', $cuerpo, $llaves, PREG_SET_ORDER);

            foreach ($llaves as [, $nombre, $columnas]) {
                $esperados[$tabla . '.' . $nombre] = array_map('trim', explode(',', $columnas));
            }
        }

        $reales = [];

        foreach ($this->consulta(
            "SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND NON_UNIQUE = 1 AND TABLE_NAME <> 'migrations'
              ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
        ) as $i) {
            $reales[$i['TABLE_NAME'] . '.' . $i['INDEX_NAME']][] = $i['COLUMN_NAME'];
        }

        ksort($esperados);
        ksort($reales);
        $this->assertNotEmpty($esperados);
        $this->assertSame($esperados, $reales);
    }

    public function testMotorYColacionDeTodasLasTablas(): void
    {
        $tablas = $this->consulta(
            "SELECT TABLE_NAME, ENGINE, TABLE_COLLATION FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME <> 'migrations'",
        );

        $this->assertCount(16, $tablas);

        foreach ($tablas as $t) {
            $this->assertSame('InnoDB', $t['ENGINE'], $t['TABLE_NAME']);
            $this->assertSame('utf8mb4_unicode_ci', $t['TABLE_COLLATION'], $t['TABLE_NAME']);
        }
    }

    public function testColumnasJsonTienenCheckJsonValid(): void
    {
        $checks = $this->consulta(
            "SELECT TABLE_NAME, CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE() ORDER BY TABLE_NAME, CONSTRAINT_NAME",
        );

        $pares = array_map(static fn (array $c): string => $c['TABLE_NAME'] . '.' . $c['CONSTRAINT_NAME'], $checks);
        $this->assertSame(['configuracion.valor', 'insignias.regla'], $pares);

        foreach ($checks as $c) {
            $this->assertStringContainsString('json_valid', $c['CHECK_CLAUSE']);
        }
    }

    public function testColumnaGeneradaActivaUnica(): void
    {
        $fila = $this->consulta(
            "SELECT IS_GENERATED, GENERATION_EXPRESSION, EXTRA FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'metas_familiares' AND COLUMN_NAME = 'activa_unica'",
        )[0];

        $this->assertSame('ALWAYS', $fila['IS_GENERATED']);
        $this->assertSame('STORED GENERATED', $fila['EXTRA']);
        $this->assertSame("if(`estado` = 'activa',1,NULL)", $fila['GENERATION_EXPRESSION']);
    }

    public function testSoloUnaMetaActivaALaVez(): void
    {
        $meta = static fn (string $estado): array => [
            'titulo' => 'Meta', 'premio' => 'Premio', 'objetivo_puntos' => 100,
            'fecha_inicio' => '2026-12-01', 'estado' => $estado,
            'created_at' => '2026-12-01 10:00:00', 'updated_at' => '2026-12-01 10:00:00',
        ];

        $this->bd()->table('metas_familiares')->insert($meta('cumplida'));
        $this->bd()->table('metas_familiares')->insert($meta('cancelada'));
        $this->bd()->table('metas_familiares')->insert($meta('activa'));

        try {
            $this->bd()->table('metas_familiares')->insert($meta('activa'));
            $this->fail('Se permitió una segunda meta activa.');
        } catch (DatabaseException|mysqli_sql_exception $e) {
            $this->assertStringContainsString('uq_metas_activa_unica', $e->getMessage());
        } finally {
            $this->bd()->table('metas_familiares')->truncate();
        }
    }

    public function testVistaSaldosPerfil(): void
    {
        $vistas = $this->consulta(
            "SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE()",
        );
        $this->assertSame(['v_saldos_perfil'], array_column($vistas, 'TABLE_NAME'));

        $columnas = $this->consulta(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_saldos_perfil' ORDER BY ORDINAL_POSITION",
        );
        $this->assertSame(['perfil_id', 'xp', 'saldo', 'reservado'], array_column($columnas, 'COLUMN_NAME'));
    }
}
