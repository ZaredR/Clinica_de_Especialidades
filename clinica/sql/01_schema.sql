-- =====================================================
-- CLÍNICA DE ESPECIALIDADES - SCHEMA COMPLETO
-- =====================================================
CREATE DATABASE IF NOT EXISTS clinica_especialidades
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clinica_especialidades;

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- TABLAS BASE (sin dependencias)
-- =====================================================

CREATE TABLE IF NOT EXISTS roles (
    id_rol       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descripcion  VARCHAR(50) NOT NULL,
    UNIQUE KEY uk_rol_desc (descripcion)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS especialidades (
    especialidad_id  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    especialidad     VARCHAR(100) NOT NULL,
    UNIQUE KEY uk_especialidad (especialidad)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tipo_sangre (
    id_tipo_sangre  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descripcion     VARCHAR(10) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tipo_consulta (
    id_tipo_consulta  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descripcion       VARCHAR(80) NOT NULL,
    precio            DECIMAL(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tipo_movimiento (
    id_tipo  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_tipo_fk INT UNSIGNED,
    descripcion VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS consultorios (
    id_consultorio  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero          VARCHAR(10) NOT NULL,
    piso            VARCHAR(10) NOT NULL,
    descripcion     VARCHAR(100)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS alergias (
    id_alergia   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alergia      VARCHAR(100) NOT NULL,
    descripcion  TEXT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS enfermedades_cronicas (
    id_enfermedad  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descripcion    VARCHAR(150) NOT NULL
) ENGINE=InnoDB;

-- =====================================================
-- USUARIOS Y AUTENTICACIÓN
-- =====================================================

CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(60) NOT NULL,
    contrasena   VARCHAR(255) NOT NULL,
    id_rol       INT UNSIGNED NOT NULL,
    activo       TINYINT(1) NOT NULL DEFAULT 1,
    creado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_username (username),
    CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
) ENGINE=InnoDB;

-- =====================================================
-- MÉDICOS
-- =====================================================

CREATE TABLE IF NOT EXISTS medicos (
    medico_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario         INT UNSIGNED NOT NULL,
    especialidad_id    INT UNSIGNED NOT NULL,
    nombre             VARCHAR(80) NOT NULL,
    apellidos          VARCHAR(100) NOT NULL,
    cedula             VARCHAR(20) NOT NULL,
    telefono           VARCHAR(20),
    correo             VARCHAR(100),
    fecha_nacimiento   DATE,
    horario_inicio     TIME,
    horario_salida     TIME,
    activo             TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uk_medico_cedula (cedula),
    CONSTRAINT fk_medico_usuario     FOREIGN KEY (id_usuario)      REFERENCES usuarios(id_usuario),
    CONSTRAINT fk_medico_especialidad FOREIGN KEY (especialidad_id) REFERENCES especialidades(especialidad_id)
) ENGINE=InnoDB;

-- =====================================================
-- PACIENTES
-- =====================================================

CREATE TABLE IF NOT EXISTS pacientes (
    paciente_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario       INT UNSIGNED,
    nombre           VARCHAR(80) NOT NULL,
    apellidos        VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    sexo             CHAR(1) NOT NULL COMMENT 'M=Masculino, F=Femenino',
    telefono         VARCHAR(20),
    correo           VARCHAR(100),
    observaciones_generales TEXT,
    activo           TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_paciente_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB;

-- =====================================================
-- EXPEDIENTES CLÍNICOS
-- =====================================================

CREATE TABLE IF NOT EXISTS expedientes (
    id_expediente   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    paciente_id     INT UNSIGNED NOT NULL,
    id_tipo_sangre  INT UNSIGNED,
    fecha_apertura  DATE NOT NULL DEFAULT (CURDATE()),
    peso_actual     DECIMAL(5,2),
    altura          DECIMAL(4,2),
    observaciones_generales TEXT,
    UNIQUE KEY uk_expediente_paciente (paciente_id),
    CONSTRAINT fk_exp_paciente    FOREIGN KEY (paciente_id)    REFERENCES pacientes(paciente_id),
    CONSTRAINT fk_exp_tipo_sangre FOREIGN KEY (id_tipo_sangre) REFERENCES tipo_sangre(id_tipo_sangre)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expediente_alergias (
    id_expediente  INT UNSIGNED NOT NULL,
    id_alergia     INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_expediente, id_alergia),
    CONSTRAINT fk_expal_exp    FOREIGN KEY (id_expediente) REFERENCES expedientes(id_expediente),
    CONSTRAINT fk_expal_alergia FOREIGN KEY (id_alergia)   REFERENCES alergias(id_alergia)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expediente_enfermedades (
    id_expediente  INT UNSIGNED NOT NULL,
    id_enfermedad  INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_expediente, id_enfermedad),
    CONSTRAINT fk_expef_exp  FOREIGN KEY (id_expediente) REFERENCES expedientes(id_expediente),
    CONSTRAINT fk_expef_enf  FOREIGN KEY (id_enfermedad) REFERENCES enfermedades_cronicas(id_enfermedad)
) ENGINE=InnoDB;

-- =====================================================
-- CITAS
-- =====================================================

CREATE TABLE IF NOT EXISTS citas (
    cita_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medico_id    INT UNSIGNED NOT NULL,
    paciente_id  INT UNSIGNED NOT NULL,
    fecha        DATE NOT NULL,
    hora         TIME NOT NULL,
    estado       ENUM('programada','confirmada','cancelada','completada','reprogramada') NOT NULL DEFAULT 'programada',
    motivo       VARCHAR(255),
    creado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cita_medico   FOREIGN KEY (medico_id)   REFERENCES medicos(medico_id),
    CONSTRAINT fk_cita_paciente FOREIGN KEY (paciente_id) REFERENCES pacientes(paciente_id)
) ENGINE=InnoDB;

-- =====================================================
-- CONSULTAS
-- =====================================================

CREATE TABLE IF NOT EXISTS consultas (
    id_consulta        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cita_id            INT UNSIGNED,
    medico_id          INT UNSIGNED NOT NULL,
    paciente_id        INT UNSIGNED NOT NULL,
    id_tipo_consulta   INT UNSIGNED,
    id_consultorio     INT UNSIGNED,
    diagnostico        TEXT,
    observaciones      TEXT,
    fecha              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cons_cita         FOREIGN KEY (cita_id)          REFERENCES citas(cita_id),
    CONSTRAINT fk_cons_medico       FOREIGN KEY (medico_id)        REFERENCES medicos(medico_id),
    CONSTRAINT fk_cons_paciente     FOREIGN KEY (paciente_id)      REFERENCES pacientes(paciente_id),
    CONSTRAINT fk_cons_tipo         FOREIGN KEY (id_tipo_consulta) REFERENCES tipo_consulta(id_tipo_consulta),
    CONSTRAINT fk_cons_consultorio  FOREIGN KEY (id_consultorio)   REFERENCES consultorios(id_consultorio)
) ENGINE=InnoDB;

-- =====================================================
-- MEDICAMENTOS E INVENTARIO
-- =====================================================

CREATE TABLE IF NOT EXISTS medicamentos (
    id_medicamento  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    descripcion     TEXT,
    precio          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    activo          TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventario (
    id_inventario    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_medicamento   INT UNSIGNED NOT NULL,
    stock            INT NOT NULL DEFAULT 0,
    fecha_caducidad  DATE,
    CONSTRAINT fk_inv_med FOREIGN KEY (id_medicamento) REFERENCES medicamentos(id_medicamento)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movimiento_inventario (
    id_movimiento   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_inventario   INT UNSIGNED NOT NULL,
    id_tipo         INT UNSIGNED NOT NULL,
    cantidad        INT NOT NULL,
    fecha           DATE NOT NULL DEFAULT (CURDATE()),
    hora            TIME NOT NULL DEFAULT (CURTIME()),
    CONSTRAINT fk_mov_inv  FOREIGN KEY (id_inventario) REFERENCES inventario(id_inventario),
    CONSTRAINT fk_mov_tipo FOREIGN KEY (id_tipo)       REFERENCES tipo_movimiento(id_tipo)
) ENGINE=InnoDB;

-- =====================================================
-- RECETAS
-- =====================================================

CREATE TABLE IF NOT EXISTS recetas (
    id_receta    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_consulta  INT UNSIGNED NOT NULL,
    fecha        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_receta_consulta FOREIGN KEY (id_consulta) REFERENCES consultas(id_consulta)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS receta_medicamentos (
    id_receta       INT UNSIGNED NOT NULL,
    id_medicamento  INT UNSIGNED NOT NULL,
    frecuencia      VARCHAR(80),
    duracion        VARCHAR(80),
    PRIMARY KEY (id_receta, id_medicamento),
    CONSTRAINT fk_rm_receta FOREIGN KEY (id_receta)      REFERENCES recetas(id_receta),
    CONSTRAINT fk_rm_med    FOREIGN KEY (id_medicamento)  REFERENCES medicamentos(id_medicamento)
) ENGINE=InnoDB;

-- =====================================================
-- PAGOS
-- =====================================================

CREATE TABLE IF NOT EXISTS pagos (
    id_pago         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_consulta     INT UNSIGNED,
    fecha           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    monto_total     DECIMAL(10,2) NOT NULL,
    estado          ENUM('pendiente','pagado','cancelado') NOT NULL DEFAULT 'pendiente',
    CONSTRAINT fk_pago_consulta FOREIGN KEY (id_consulta) REFERENCES consultas(id_consulta)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pago_medicamentos (
    id_pago         INT UNSIGNED NOT NULL,
    id_medicamento  INT UNSIGNED NOT NULL,
    cantidad        INT NOT NULL DEFAULT 1,
    PRIMARY KEY (id_pago, id_medicamento),
    CONSTRAINT fk_pm_pago FOREIGN KEY (id_pago)        REFERENCES pagos(id_pago),
    CONSTRAINT fk_pm_med  FOREIGN KEY (id_medicamento)  REFERENCES medicamentos(id_medicamento)
) ENGINE=InnoDB;

-- =====================================================
-- BITÁCORA / LOG
-- =====================================================

CREATE TABLE IF NOT EXISTS bitacora (
    id_log          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accion          VARCHAR(20) NOT NULL,
    tabla_afectada  VARCHAR(60) NOT NULL,
    registro_id     VARCHAR(60),
    usuario_id      INT UNSIGNED,
    ip              VARCHAR(45),
    descripcion     TEXT,
    fecha_hora      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_fecha (fecha_hora),
    INDEX idx_log_tabla (tabla_afectada)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
