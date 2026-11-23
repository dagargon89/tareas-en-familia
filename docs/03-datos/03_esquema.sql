-- 03 · Esquema de base de datos · Tareas en Familia
-- Motor: MariaDB 10.11+ · InnoDB · utf8mb4_unicode_ci
-- Fuente de verdad en código: src/api/app/Database/Migrations (deben reproducir este DDL exacto)
-- Versión 1.0 · 2026-11-22

SET NAMES utf8mb4;

CREATE TABLE usuarios (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre          VARCHAR(60)  NOT NULL,
  email           VARCHAR(120) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  pin_hash        VARCHAR(255) NOT NULL,
  pin_intentos    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta DATETIME     NULL,
  ultimo_acceso   DATETIME     NULL,
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE perfiles (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  apodo           VARCHAR(30)  NOT NULL,
  avatar          VARCHAR(40)  NOT NULL,
  color           ENUM('indigo','ambar','menta','rosa','lavanda') NOT NULL DEFAULT 'indigo',
  nivel_ux        ENUM('exploradoras','aventureras','pro') NOT NULL,
  pin_hash        VARCHAR(255) NOT NULL,
  pin_intentos    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta DATETIME     NULL,
  sonidos         TINYINT(1)   NOT NULL DEFAULT 1,
  tema            ENUM('claro','oscuro','sistema') NOT NULL DEFAULT 'claro',
  orden           TINYINT UNSIGNED NOT NULL DEFAULT 0,
  activo          TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categorias (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre  VARCHAR(40)  NOT NULL,
  icono   VARCHAR(40)  NOT NULL,
  color   ENUM('indigo','ambar','menta','rosa','lavanda') NOT NULL,
  activa  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tareas (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  perfil_id             INT UNSIGNED NOT NULL,
  categoria_id          INT UNSIGNED NOT NULL,
  titulo                VARCHAR(80)  NOT NULL,
  icono                 VARCHAR(40)  NOT NULL,
  puntos                SMALLINT UNSIGNED NOT NULL,
  tipo                  ENUM('casa','escolar','actividad') NOT NULL,
  materia               VARCHAR(40)  NULL,
  fecha_entrega         DATE         NULL,
  requiere_foto         TINYINT(1)   NOT NULL DEFAULT 0,
  creada_por            ENUM('padre','perfil') NOT NULL,
  creada_por_usuario_id INT UNSIGNED NULL,
  activa                TINYINT(1)   NOT NULL DEFAULT 1,
  created_at            DATETIME     NOT NULL,
  updated_at            DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY ix_tareas_perfil_activa (perfil_id, activa),
  KEY ix_tareas_categoria (categoria_id),
  KEY ix_tareas_usuario (creada_por_usuario_id),
  CONSTRAINT fk_tareas_perfil    FOREIGN KEY (perfil_id)             REFERENCES perfiles (id)   ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_tareas_categoria FOREIGN KEY (categoria_id)          REFERENCES categorias (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_tareas_usuario   FOREIGN KEY (creada_por_usuario_id) REFERENCES usuarios (id)   ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tarea_recurrencias (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tarea_id     INT UNSIGNED NOT NULL,
  frecuencia   ENUM('una_vez','diaria','semanal') NOT NULL,
  dias_semana  SET('lu','ma','mi','ju','vi','sa','do') NULL,
  hora         TIME         NULL,
  fecha_inicio DATE         NOT NULL,
  fecha_fin    DATE         NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recurrencias_tarea (tarea_id),
  CONSTRAINT fk_recurrencias_tarea FOREIGN KEY (tarea_id) REFERENCES tareas (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tarea_instancias (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tarea_id           INT UNSIGNED NOT NULL,
  perfil_id          INT UNSIGNED NOT NULL,
  fecha              DATE         NOT NULL,
  hora               TIME         NULL,
  fecha_entrega      DATE         NULL,
  puntos             SMALLINT UNSIGNED NOT NULL,
  estado             ENUM('pendiente','hecha','aprobada','devuelta') NOT NULL DEFAULT 'pendiente',
  mensaje_devolucion VARCHAR(140) NULL,
  completada_en      DATETIME     NULL,
  aprobada_en        DATETIME     NULL,
  created_at         DATETIME     NOT NULL,
  updated_at         DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_instancias_tarea_fecha (tarea_id, fecha),
  KEY ix_instancias_perfil_fecha (perfil_id, fecha),
  KEY ix_instancias_estado (estado, completada_en),
  CONSTRAINT fk_instancias_tarea  FOREIGN KEY (tarea_id)  REFERENCES tareas (id)   ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT fk_instancias_perfil FOREIGN KEY (perfil_id) REFERENCES perfiles (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE evidencias (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  instancia_id   INT UNSIGNED NOT NULL,
  ruta           VARCHAR(255) NOT NULL,
  mime           VARCHAR(30)  NOT NULL,
  bytes          INT UNSIGNED NOT NULL,
  borrar_despues DATE         NULL,
  created_at     DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_evidencias_instancia (instancia_id),
  KEY ix_evidencias_borrar (borrar_despues),
  CONSTRAINT fk_evidencias_instancia FOREIGN KEY (instancia_id) REFERENCES tarea_instancias (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE revisiones (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  instancia_id INT UNSIGNED NOT NULL,
  usuario_id   INT UNSIGNED NOT NULL,
  decision     ENUM('aprobada','devuelta') NOT NULL,
  mensaje      VARCHAR(140) NULL,
  created_at   DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY ix_revisiones_instancia (instancia_id),
  KEY ix_revisiones_usuario (usuario_id),
  CONSTRAINT fk_revisiones_instancia FOREIGN KEY (instancia_id) REFERENCES tarea_instancias (id) ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT fk_revisiones_usuario   FOREIGN KEY (usuario_id)   REFERENCES usuarios (id)         ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE movimientos_puntos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  perfil_id   INT UNSIGNED NOT NULL,
  origen      ENUM('tarea','insignia','canje','ajuste') NOT NULL,
  origen_id   INT UNSIGNED NULL,
  puntos      INT          NOT NULL,
  descripcion VARCHAR(120) NOT NULL,
  created_at  DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_movimientos_origen (origen, origen_id),
  KEY ix_movimientos_perfil_fecha (perfil_id, created_at),
  CONSTRAINT fk_movimientos_perfil FOREIGN KEY (perfil_id) REFERENCES perfiles (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE recompensas (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo     VARCHAR(60)  NOT NULL,
  icono      VARCHAR(40)  NOT NULL,
  costo      SMALLINT UNSIGNED NOT NULL,
  activa     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL,
  updated_at DATETIME     NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE canjes (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  perfil_id     INT UNSIGNED NOT NULL,
  recompensa_id INT UNSIGNED NOT NULL,
  costo         SMALLINT UNSIGNED NOT NULL,
  estado        ENUM('solicitado','aprobado','entregado','rechazado') NOT NULL DEFAULT 'solicitado',
  revisado_por  INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL,
  updated_at    DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY ix_canjes_perfil_estado (perfil_id, estado),
  KEY ix_canjes_recompensa (recompensa_id),
  KEY ix_canjes_revisor (revisado_por),
  CONSTRAINT fk_canjes_perfil     FOREIGN KEY (perfil_id)     REFERENCES perfiles (id)    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_canjes_recompensa FOREIGN KEY (recompensa_id) REFERENCES recompensas (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_canjes_revisor    FOREIGN KEY (revisado_por)  REFERENCES usuarios (id)    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE insignias (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo      VARCHAR(40)  NOT NULL,
  nombre      VARCHAR(40)  NOT NULL,
  descripcion VARCHAR(120) NOT NULL,
  icono       VARCHAR(40)  NOT NULL,
  color       ENUM('indigo','ambar','menta','rosa','lavanda') NOT NULL,
  regla       JSON         NOT NULL,
  xp          SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  orden       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_insignias_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE perfil_insignias (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  perfil_id   INT UNSIGNED NOT NULL,
  insignia_id INT UNSIGNED NOT NULL,
  obtenida_en DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_perfil_insignia (perfil_id, insignia_id),
  KEY ix_perfil_insignias_insignia (insignia_id),
  CONSTRAINT fk_pi_perfil   FOREIGN KEY (perfil_id)   REFERENCES perfiles (id)  ON DELETE CASCADE  ON UPDATE CASCADE,
  CONSTRAINT fk_pi_insignia FOREIGN KEY (insignia_id) REFERENCES insignias (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE metas_familiares (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo          VARCHAR(60)  NOT NULL,
  premio          VARCHAR(80)  NOT NULL,
  objetivo_puntos INT UNSIGNED NOT NULL,
  fecha_inicio    DATE         NOT NULL,
  fecha_limite    DATE         NULL,
  estado          ENUM('activa','cumplida','cancelada') NOT NULL DEFAULT 'activa',
  activa_unica    TINYINT      GENERATED ALWAYS AS (IF(estado = 'activa', 1, NULL)) STORED,
  cumplida_en     DATETIME     NULL,
  created_at      DATETIME     NOT NULL,
  updated_at      DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_metas_activa_unica (activa_unica)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rachas (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  perfil_id  INT UNSIGNED NOT NULL,
  semana_iso CHAR(8)      NOT NULL,
  asignadas  SMALLINT UNSIGNED NOT NULL,
  aprobadas  SMALLINT UNSIGNED NOT NULL,
  porcentaje DECIMAL(5,2) NOT NULL,
  cumplida   TINYINT(1)   NOT NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rachas_perfil_semana (perfil_id, semana_iso),
  CONSTRAINT fk_rachas_perfil FOREIGN KEY (perfil_id) REFERENCES perfiles (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE configuracion (
  clave      VARCHAR(50) NOT NULL,
  valor      JSON        NOT NULL,
  updated_at DATETIME    NOT NULL,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vista de saldos (XP de por vida, saldo y reservado por canjes solicitados)
CREATE VIEW v_saldos_perfil AS
SELECT p.id AS perfil_id,
       COALESCE((SELECT SUM(m.puntos) FROM movimientos_puntos m
                  WHERE m.perfil_id = p.id AND m.origen IN ('tarea','insignia') AND m.puntos > 0), 0) AS xp,
       COALESCE((SELECT SUM(m.puntos) FROM movimientos_puntos m WHERE m.perfil_id = p.id), 0) AS saldo,
       COALESCE((SELECT SUM(c.costo) FROM canjes c
                  WHERE c.perfil_id = p.id AND c.estado = 'solicitado'), 0) AS reservado
FROM perfiles p;
