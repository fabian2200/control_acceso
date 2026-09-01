DROP TABLE IF EXISTS `acceso_motivos`;

CREATE TABLE IF NOT EXISTS `acceso_terminales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `ubicacion` varchar(200) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_inicio_funcionamiento` date DEFAULT NULL,
  `api_token` char(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acceso_terminales_codigo_unique` (`codigo`),
  UNIQUE KEY `acceso_terminales_api_token_unique` (`api_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `acceso_salidas_ocasionales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empleado_id` int(11) NOT NULL,
  `id_horario` bigint(20) unsigned DEFAULT NULL,
  `terminal_id` bigint(20) unsigned DEFAULT NULL,
  `motivo_texto` varchar(120) DEFAULT NULL,
  `autorizado_por` varchar(80) DEFAULT NULL,
  `permiso_id` int(11) DEFAULT NULL,
  `salida_en` datetime NOT NULL,
  `hora_regreso_esperada` time NOT NULL,
  `regreso_en` datetime DEFAULT NULL,
  `minutos_tarde` int(10) unsigned NOT NULL DEFAULT 0,
  `foto_salida` varchar(255) DEFAULT NULL,
  `foto_regreso` varchar(255) DEFAULT NULL,
  `estado` enum('abierta','cerrada','vencida') NOT NULL DEFAULT 'abierta',
  `revisada_rrhh` tinyint(1) NOT NULL DEFAULT 0,
  `sincronizado` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `acceso_salidas_empleado_estado` (`empleado_id`,`estado`),
  KEY `acceso_salidas_id_horario` (`id_horario`),
  UNIQUE KEY `acceso_salidas_sync_clave_unique` (`empleado_id`,`salida_en`,`terminal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `acceso_registros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empleado_id` int(11) NOT NULL,
  `id_horario` bigint(20) unsigned DEFAULT NULL,
  `terminal_id` bigint(20) unsigned DEFAULT NULL,
  `salida_ocasional_id` bigint(20) unsigned DEFAULT NULL,
  `tipo` enum('entrada','salida') NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `registrado_en` datetime NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `hora_esperada` time DEFAULT NULL,
  `llego_tarde` int(10) unsigned NOT NULL DEFAULT 0,
  `llego_temprano` int(10) unsigned NOT NULL DEFAULT 0,
  `salio_temprano` int(10) unsigned NOT NULL DEFAULT 0,
  `salio_tarde` int(10) unsigned NOT NULL DEFAULT 0,
  `sincronizado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `acceso_registros_empleado_fecha` (`empleado_id`,`fecha`),
  KEY `acceso_registros_tipo_index` (`tipo`),
  KEY `acceso_registros_id_horario` (`id_horario`),
  KEY `acceso_registros_sincronizado_index` (`sincronizado`),
  UNIQUE KEY `acceso_registros_sync_clave_unique` (`empleado_id`,`tipo`,`fecha`,`registrado_en`,`terminal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_acceso` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario` varchar(80) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_acceso_usuario_unique` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `acceso_horarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `acceso_horario_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `horario_id` bigint(20) unsigned NOT NULL,
  `dia_semana` tinyint(3) unsigned NOT NULL,
  `entrada_jornada_1` time DEFAULT NULL,
  `gabela_entrada_jornada_1` smallint(5) unsigned DEFAULT NULL,
  `salida_jornada_1` time DEFAULT NULL,
  `gabela_salida_jornada_1` smallint(5) unsigned DEFAULT NULL,
  `entrada_jornada_2` time DEFAULT NULL,
  `gabela_entrada_jornada_2` smallint(5) unsigned DEFAULT NULL,
  `salida_jornada_2` time DEFAULT NULL,
  `gabela_salida_jornada_2` smallint(5) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acceso_horario_items_dia` (`horario_id`,`dia_semana`),
  CONSTRAINT `acceso_horario_items_horario_id_foreign` FOREIGN KEY (`horario_id`) REFERENCES `acceso_horarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `acceso_empleado_horarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empleado_id` int(11) NOT NULL,
  `horario_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acceso_empleado_horario_unico` (`empleado_id`),
  CONSTRAINT `acceso_empleado_horarios_horario_id_foreign` FOREIGN KEY (`horario_id`) REFERENCES `acceso_horarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `acceso_sync_checkpoints` (
  `tabla` varchar(64) NOT NULL,
  `pulled_at` timestamp NULL DEFAULT NULL,
  `cursor` varchar(64) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`tabla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `acceso_novedades` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `terminal_id` bigint(20) unsigned DEFAULT NULL,
  `fecha` date NOT NULL,
  `jornada` tinyint(3) unsigned NOT NULL,
  `hora_inicio_jornada` time DEFAULT NULL,
  `hora_fin_jornada` time DEFAULT NULL,
  `motivo` varchar(120) NOT NULL,
  `quien_autoriza` varchar(80) DEFAULT NULL,
  `aprobada` tinyint(1) DEFAULT NULL,
  `sincronizado` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acceso_novedades_uuid_unique` (`uuid`),
  UNIQUE KEY `acceso_novedades_empleado_fecha_jornada_unique` (`empleado_id`,`fecha`,`jornada`),
  KEY `acceso_novedades_empleado_fecha` (`empleado_id`,`fecha`),
  KEY `acceso_novedades_updated_at` (`updated_at`),
  KEY `acceso_novedades_sincronizado` (`sincronizado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `acceso_festivos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acceso_festivos_fecha_unique` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `acceso_terminales`
(`codigo`,`nombre`,`ubicacion`,`activo`,`api_token`,`created_at`,`updated_at`)
VALUES
('REC-01','Recepción','Torre Norte',1,
'e329d6926b529b6fa6133580c19b3382fcf9d4bbda240850cceeba61058ed3ac',
NOW(),NOW());

-- Idempotente: bases ya creadas con columnas mañana/tarde
SET @db := DATABASE();
SET @old := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'acceso_horario_items'
    AND COLUMN_NAME = 'entrada_manana'
);
SET @sql := IF(@old > 0,
  'ALTER TABLE `acceso_horario_items`
    CHANGE COLUMN `entrada_manana` `entrada_jornada_1` time DEFAULT NULL,
    CHANGE COLUMN `gabela_entrada_manana` `gabela_entrada_jornada_1` smallint(5) unsigned DEFAULT NULL,
    CHANGE COLUMN `salida_manana` `salida_jornada_1` time DEFAULT NULL,
    CHANGE COLUMN `gabela_salida_manana` `gabela_salida_jornada_1` smallint(5) unsigned DEFAULT NULL,
    CHANGE COLUMN `entrada_tarde` `entrada_jornada_2` time DEFAULT NULL,
    CHANGE COLUMN `gabela_entrada_tarde` `gabela_entrada_jornada_2` smallint(5) unsigned DEFAULT NULL,
    CHANGE COLUMN `salida_tarde` `salida_jornada_2` time DEFAULT NULL,
    CHANGE COLUMN `gabela_salida_tarde` `gabela_salida_jornada_2` smallint(5) unsigned DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Snapshot del horario vigente en cada marca (informes)
SET @db := DATABASE();
SET @has_reg := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'acceso_registros' AND COLUMN_NAME = 'id_horario'
);
SET @sql := IF(@has_reg = 0,
  'ALTER TABLE `acceso_registros` ADD COLUMN `id_horario` bigint(20) unsigned DEFAULT NULL AFTER `empleado_id`, ADD KEY `acceso_registros_id_horario` (`id_horario`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_occ := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'acceso_salidas_ocasionales' AND COLUMN_NAME = 'id_horario'
);
SET @sql := IF(@has_occ = 0,
  'ALTER TABLE `acceso_salidas_ocasionales` ADD COLUMN `id_horario` bigint(20) unsigned DEFAULT NULL AFTER `empleado_id`, ADD KEY `acceso_salidas_id_horario` (`id_horario`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `acceso_registros` r
INNER JOIN `acceso_empleado_horarios` a ON a.empleado_id = r.empleado_id
SET r.id_horario = a.horario_id
WHERE r.id_horario IS NULL;

UPDATE `acceso_salidas_ocasionales` o
INNER JOIN `acceso_empleado_horarios` a ON a.empleado_id = o.empleado_id
SET o.id_horario = a.horario_id
WHERE o.id_horario IS NULL;

-- Token de API del kiosko REC-01
SET @db := DATABASE();
SET @has_token := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'acceso_terminales' AND COLUMN_NAME = 'api_token'
);
SET @sql := IF(@has_token = 0,
  'ALTER TABLE `acceso_terminales` ADD COLUMN `api_token` char(64) DEFAULT NULL AFTER `activo`, ADD UNIQUE KEY `acceso_terminales_api_token_unique` (`api_token`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `acceso_terminales`
SET `api_token` = 'e329d6926b529b6fa6133580c19b3382fcf9d4bbda240850cceeba61058ed3ac'
WHERE `codigo` = 'REC-01' AND (`api_token` IS NULL OR `api_token` = '');

-- Flag de cola LOCAL → NUBE en salidas ocasionales
SET @has_occ_sync := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'acceso_salidas_ocasionales' AND COLUMN_NAME = 'sincronizado'
);
SET @sql := IF(@has_occ_sync = 0,
  'ALTER TABLE `acceso_salidas_ocasionales` ADD COLUMN `sincronizado` tinyint(1) NOT NULL DEFAULT 0 AFTER `revisada_rrhh`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_reg_sync_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'acceso_registros' AND INDEX_NAME = 'acceso_registros_sincronizado_index'
);
SET @sql := IF(@has_reg_sync_idx = 0,
  'ALTER TABLE `acceso_registros` ADD KEY `acceso_registros_sincronizado_index` (`sincronizado`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `acceso_registros` r
INNER JOIN `acceso_terminales` t ON t.codigo = 'REC-01'
SET r.terminal_id = t.id
WHERE r.terminal_id IS NULL;

UPDATE `acceso_salidas_ocasionales` o
INNER JOIN `acceso_terminales` t ON t.codigo = 'REC-01'
SET o.terminal_id = t.id
WHERE o.terminal_id IS NULL;

SET @has_reg_uk := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'acceso_registros' AND INDEX_NAME = 'acceso_registros_sync_clave_unique'
);
SET @sql := IF(@has_reg_uk = 0,
  'ALTER TABLE `acceso_registros` ADD UNIQUE KEY `acceso_registros_sync_clave_unique` (`empleado_id`,`tipo`,`fecha`,`registrado_en`,`terminal_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_occ_uk := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'acceso_salidas_ocasionales' AND INDEX_NAME = 'acceso_salidas_sync_clave_unique'
);
SET @sql := IF(@has_occ_uk = 0,
  'ALTER TABLE `acceso_salidas_ocasionales` ADD UNIQUE KEY `acceso_salidas_sync_clave_unique` (`empleado_id`,`salida_en`,`terminal_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @db := DATABASE();
SET @has_mandado := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'acceso_salidas_ocasionales' AND COLUMN_NAME = 'mandado_por'
);
SET @has_autorizado := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'acceso_salidas_ocasionales' AND COLUMN_NAME = 'autorizado_por'
);
SET @sql := IF(@has_mandado > 0 AND @has_autorizado = 0,
  'ALTER TABLE `acceso_salidas_ocasionales` CHANGE `mandado_por` `autorizado_por` varchar(80) DEFAULT NULL',
  IF(@has_mandado = 0 AND @has_autorizado = 0,
    'ALTER TABLE `acceso_salidas_ocasionales` ADD COLUMN `autorizado_por` varchar(80) DEFAULT NULL AFTER `motivo_texto`',
    'SELECT 1')
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Fecha desde la cual el kiosko cuenta como operativo (informes de marcación incompleta)
SET @db := DATABASE();
SET @has_inicio_func := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'acceso_terminales' AND COLUMN_NAME = 'fecha_inicio_funcionamiento'
);
SET @sql := IF(@has_inicio_func = 0,
  'ALTER TABLE `acceso_terminales` ADD COLUMN `fecha_inicio_funcionamiento` date DEFAULT NULL AFTER `activo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
