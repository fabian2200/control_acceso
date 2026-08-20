class AccesoSchema {
  AccesoSchema._();

  static const tables = <String>[
    '''
CREATE TABLE IF NOT EXISTS acceso_horarios (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT NOT NULL,
  descripcion TEXT,
  activo INTEGER NOT NULL DEFAULT 1,
  created_at TEXT,
  updated_at TEXT
)
''',
    '''
CREATE TABLE IF NOT EXISTS acceso_horario_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  horario_id INTEGER NOT NULL,
  dia_semana INTEGER NOT NULL,
  entrada_jornada_1 TEXT,
  gabela_entrada_jornada_1 INTEGER,
  salida_jornada_1 TEXT,
  gabela_salida_jornada_1 INTEGER,
  entrada_jornada_2 TEXT,
  gabela_entrada_jornada_2 INTEGER,
  salida_jornada_2 TEXT,
  gabela_salida_jornada_2 INTEGER,
  created_at TEXT,
  updated_at TEXT,
  UNIQUE (horario_id, dia_semana)
)
''',
    '''
CREATE TABLE IF NOT EXISTS acceso_empleado_horarios (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  empleado_id INTEGER NOT NULL,
  horario_id INTEGER NOT NULL,
  created_at TEXT,
  updated_at TEXT,
  UNIQUE (empleado_id)
)
''',
    '''
CREATE TABLE IF NOT EXISTS acceso_terminales (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  codigo TEXT NOT NULL UNIQUE,
  nombre TEXT NOT NULL,
  ubicacion TEXT,
  activo INTEGER NOT NULL DEFAULT 1,
  api_token TEXT UNIQUE,
  created_at TEXT,
  updated_at TEXT
)
''',
    '''
CREATE TABLE IF NOT EXISTS acceso_registros (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  empleado_id INTEGER NOT NULL,
  id_horario INTEGER,
  terminal_id INTEGER,
  salida_ocasional_id INTEGER,
  tipo TEXT NOT NULL,
  fecha TEXT NOT NULL,
  hora TEXT NOT NULL,
  registrado_en TEXT NOT NULL,
  foto TEXT,
  hora_esperada TEXT,
  llego_tarde INTEGER NOT NULL DEFAULT 0,
  llego_temprano INTEGER NOT NULL DEFAULT 0,
  salio_temprano INTEGER NOT NULL DEFAULT 0,
  salio_tarde INTEGER NOT NULL DEFAULT 0,
  sincronizado INTEGER NOT NULL DEFAULT 0,
  created_at TEXT,
  updated_at TEXT,
  UNIQUE (empleado_id, tipo, fecha, registrado_en, terminal_id)
)
''',
    '''
CREATE TABLE IF NOT EXISTS acceso_salidas_ocasionales (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  empleado_id INTEGER NOT NULL,
  id_horario INTEGER,
  terminal_id INTEGER,
  motivo_texto TEXT,
  autorizado_por TEXT,
  permiso_id INTEGER,
  salida_en TEXT NOT NULL,
  hora_regreso_esperada TEXT NOT NULL,
  regreso_en TEXT,
  minutos_tarde INTEGER NOT NULL DEFAULT 0,
  foto_salida TEXT,
  foto_regreso TEXT,
  estado TEXT NOT NULL DEFAULT 'abierta',
  revisada_rrhh INTEGER NOT NULL DEFAULT 0,
  sincronizado INTEGER NOT NULL DEFAULT 0,
  created_at TEXT,
  updated_at TEXT,
  UNIQUE (empleado_id, salida_en, terminal_id)
)
''',
    '''
CREATE TABLE IF NOT EXISTS acceso_sync_checkpoints (
  tabla TEXT PRIMARY KEY,
  pulled_at TEXT,
  cursor TEXT,
  updated_at TEXT
)
''',
    '''
CREATE TABLE IF NOT EXISTS acceso_novedades (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  uuid TEXT NOT NULL UNIQUE,
  empleado_id INTEGER NOT NULL,
  terminal_id INTEGER,
  fecha TEXT NOT NULL,
  jornada INTEGER NOT NULL,
  hora_inicio_jornada TEXT,
  hora_fin_jornada TEXT,
  motivo TEXT NOT NULL,
  quien_autoriza TEXT,
  aprobada INTEGER,
  sincronizado INTEGER NOT NULL DEFAULT 0,
  created_at TEXT,
  updated_at TEXT,
  UNIQUE (empleado_id, fecha, jornada)
)
''',
    '''
CREATE TABLE IF NOT EXISTS admin_acceso (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  usuario TEXT NOT NULL UNIQUE,
  password TEXT NOT NULL
)
''',
    '''
CREATE TABLE IF NOT EXISTS cargos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT,
  estado TEXT
)
''',
    '''
CREATE TABLE IF NOT EXISTS departamentos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT,
  estado TEXT,
  lider_id INTEGER
)
''',
    '''
CREATE TABLE IF NOT EXISTS empresas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  nombre TEXT,
  representante TEXT,
  direccion TEXT,
  telefono TEXT,
  nit TEXT,
  estado TEXT,
  logo TEXT
)
''',
    '''
CREATE TABLE IF NOT EXISTS empleados (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  identificacion TEXT,
  nombres TEXT,
  apellidos TEXT,
  empresa INTEGER,
  cargo INTEGER,
  estado TEXT,
  estado_registro TEXT,
  fecha_nacimiento TEXT,
  telefono TEXT,
  email TEXT,
  departamento INTEGER,
  fecha_ingreso TEXT,
  tipo_contrato TEXT,
  direccion TEXT,
  usuario TEXT,
  foto TEXT,
  pw_temporal TEXT,
  lider TEXT
)
''',
    '''
CREATE TABLE IF NOT EXISTS permisos (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  empleado INTEGER,
  fecha_inicio TEXT,
  fecha_fin TEXT,
  hora_inicio TEXT,
  hora_fin TEXT,
  motivo TEXT,
  fecha_solicitud TEXT,
  estado TEXT,
  fecha_decision TEXT,
  comentario TEXT,
  estado_reg TEXT,
  usuario INTEGER,
  motivo_cancelar TEXT,
  fecha_cancelacion TEXT
)
''',
    '''
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  email_verified_at TEXT,
  password TEXT NOT NULL,
  remember_token TEXT,
  created_at TEXT,
  updated_at TEXT,
  tipo_usuario TEXT,
  empleado INTEGER,
  estado TEXT,
  lider TEXT,
  foto TEXT,
  lider_seguimiento TEXT,
  gestor_permisos TEXT,
  independencia TEXT DEFAULT 'No'
)
''',
  ];

  static const catalogTables = <String>[
    'cargos',
    'departamentos',
    'empresas',
    'empleados',
    'users',
    'permisos',
    'admin_acceso',
    'acceso_terminales',
    'acceso_horarios',
    'acceso_horario_items',
    'acceso_empleado_horarios',
  ];

  static const indexes = <String>[
    'CREATE INDEX IF NOT EXISTS idx_empleados_ident ON empleados(identificacion)',
    'CREATE INDEX IF NOT EXISTS idx_users_empleado ON users(empleado)',
    'CREATE INDEX IF NOT EXISTS idx_permisos_empleado ON permisos(empleado)',
    'CREATE INDEX IF NOT EXISTS idx_registros_emp_fecha ON acceso_registros(empleado_id, fecha)',
    'CREATE INDEX IF NOT EXISTS idx_registros_sync ON acceso_registros(sincronizado)',
    'CREATE INDEX IF NOT EXISTS idx_ocasionales_emp_estado ON acceso_salidas_ocasionales(empleado_id, estado)',
    'CREATE INDEX IF NOT EXISTS idx_ocasionales_sync ON acceso_salidas_ocasionales(sincronizado)',
    'CREATE INDEX IF NOT EXISTS idx_horario_items_dia ON acceso_horario_items(horario_id, dia_semana)',
    'CREATE INDEX IF NOT EXISTS idx_novedades_sync ON acceso_novedades(sincronizado)',
    'CREATE INDEX IF NOT EXISTS idx_novedades_fecha ON acceso_novedades(fecha)',
  ];

  static const columns = <String, List<String>>{
    'acceso_horarios': ['id', 'nombre', 'descripcion', 'activo', 'created_at', 'updated_at'],
    'acceso_horario_items': [
      'id', 'horario_id', 'dia_semana',
      'entrada_jornada_1', 'gabela_entrada_jornada_1', 'salida_jornada_1', 'gabela_salida_jornada_1',
      'entrada_jornada_2', 'gabela_entrada_jornada_2', 'salida_jornada_2', 'gabela_salida_jornada_2',
      'created_at', 'updated_at',
    ],
    'acceso_empleado_horarios': ['id', 'empleado_id', 'horario_id', 'created_at', 'updated_at'],
    'acceso_terminales': ['id', 'codigo', 'nombre', 'ubicacion', 'activo', 'api_token', 'created_at', 'updated_at'],
    'acceso_registros': [
      'id', 'empleado_id', 'id_horario', 'terminal_id', 'salida_ocasional_id', 'tipo',
      'fecha', 'hora', 'registrado_en', 'foto', 'hora_esperada',
      'llego_tarde', 'llego_temprano', 'salio_temprano', 'salio_tarde', 'sincronizado',
      'created_at', 'updated_at',
    ],
    'acceso_salidas_ocasionales': [
      'id', 'empleado_id', 'id_horario', 'terminal_id', 'motivo_texto', 'autorizado_por', 'permiso_id',
      'salida_en', 'hora_regreso_esperada', 'regreso_en', 'minutos_tarde',
      'foto_salida', 'foto_regreso', 'estado', 'revisada_rrhh', 'sincronizado',
      'created_at', 'updated_at',
    ],
    'acceso_sync_checkpoints': ['tabla', 'pulled_at', 'cursor', 'updated_at'],
    'acceso_novedades': [
      'id', 'uuid', 'empleado_id', 'terminal_id', 'fecha', 'jornada',
      'hora_inicio_jornada', 'hora_fin_jornada', 'motivo', 'quien_autoriza',
      'aprobada', 'sincronizado', 'created_at', 'updated_at',
    ],
    'admin_acceso': ['id', 'usuario', 'password'],
    'cargos': ['id', 'nombre', 'estado'],
    'departamentos': ['id', 'nombre', 'estado', 'lider_id'],
    'empresas': ['id', 'nombre', 'representante', 'direccion', 'telefono', 'nit', 'estado', 'logo'],
    'empleados': [
      'id', 'identificacion', 'nombres', 'apellidos', 'empresa', 'cargo', 'estado',
      'estado_registro', 'fecha_nacimiento', 'telefono', 'email', 'departamento',
      'fecha_ingreso', 'tipo_contrato', 'direccion', 'usuario', 'foto', 'pw_temporal', 'lider',
    ],
    'permisos': [
      'id', 'empleado', 'fecha_inicio', 'fecha_fin', 'hora_inicio', 'hora_fin', 'motivo',
      'fecha_solicitud', 'estado', 'fecha_decision', 'comentario', 'estado_reg',
      'usuario', 'motivo_cancelar', 'fecha_cancelacion',
    ],
    'users': [
      'id', 'name', 'email', 'email_verified_at', 'password', 'remember_token',
      'created_at', 'updated_at', 'tipo_usuario', 'empleado', 'estado', 'lider',
      'foto', 'lider_seguimiento', 'gestor_permisos', 'independencia',
    ],
  };
}
