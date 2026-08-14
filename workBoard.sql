/* =====================================================================
 * Control de acceso — tablas creadas sobre el esquema workboard
 * ===================================================================== */

DROP TABLE IF EXISTS `acceso_motivos`;

CREATE TABLE IF NOT EXISTS `acceso_terminales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `ubicacion` varchar(200) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acceso_terminales_codigo_unique` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `acceso_salidas_ocasionales` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empleado_id` int(11) NOT NULL,
  `terminal_id` bigint(20) unsigned DEFAULT NULL,
  `motivo_texto` varchar(120) DEFAULT NULL,
  `permiso_id` int(11) DEFAULT NULL,
  `salida_en` datetime NOT NULL,
  `hora_regreso_esperada` time NOT NULL,
  `regreso_en` datetime DEFAULT NULL,
  `minutos_tarde` int(10) unsigned NOT NULL DEFAULT 0,
  `foto_salida` varchar(255) DEFAULT NULL,
  `foto_regreso` varchar(255) DEFAULT NULL,
  `estado` enum('abierta','cerrada','vencida') NOT NULL DEFAULT 'abierta',
  `revisada_rrhh` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `acceso_salidas_empleado_estado` (`empleado_id`,`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `acceso_registros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `empleado_id` int(11) NOT NULL,
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
  KEY `acceso_registros_tipo_index` (`tipo`)
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
  `entrada_manana` time DEFAULT NULL,
  `gabela_entrada_manana` smallint(5) unsigned DEFAULT NULL,
  `salida_manana` time DEFAULT NULL,
  `gabela_salida_manana` smallint(5) unsigned DEFAULT NULL,
  `entrada_tarde` time DEFAULT NULL,
  `gabela_entrada_tarde` smallint(5) unsigned DEFAULT NULL,
  `salida_tarde` time DEFAULT NULL,
  `gabela_salida_tarde` smallint(5) unsigned DEFAULT NULL,
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

INSERT INTO `acceso_terminales` (`codigo`,`nombre`,`ubicacion`,`activo`,`created_at`,`updated_at`)
SELECT 'REC-01','Recepción','Torre Norte',1,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `acceso_terminales` WHERE `codigo`='REC-01');
