USE clinica_especialidades;

-- =====================================================
-- CIRUGÍAS
-- =====================================================
CREATE TABLE IF NOT EXISTS cirugias (
    id_cirugia      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    paciente_id     INT UNSIGNED NOT NULL,
    medico_id       INT UNSIGNED NOT NULL,
    id_consultorio  INT UNSIGNED,
    fecha           DATE NOT NULL,
    tipo_cirugia    VARCHAR(150) NOT NULL,
    descripcion     TEXT,
    resultado       TEXT,
    estado          ENUM('programada','realizada','cancelada') NOT NULL DEFAULT 'programada',
    observaciones   TEXT,
    CONSTRAINT fk_cir_paciente    FOREIGN KEY (paciente_id)    REFERENCES pacientes(paciente_id),
    CONSTRAINT fk_cir_medico      FOREIGN KEY (medico_id)      REFERENCES medicos(medico_id),
    CONSTRAINT fk_cir_consultorio FOREIGN KEY (id_consultorio) REFERENCES consultorios(id_consultorio)
) ENGINE=InnoDB;

-- =====================================================
-- SERVICIOS ADICIONALES (ultrasonidos, rayos X, labs, etc.)
-- =====================================================
CREATE TABLE IF NOT EXISTS servicios_adicionales (
    id_servicio   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100) NOT NULL,
    descripcion   VARCHAR(255),
    precio        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    activo        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS consulta_servicios (
    id_consulta_servicio INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_consulta          INT UNSIGNED NOT NULL,
    id_servicio          INT UNSIGNED NOT NULL,
    observaciones        VARCHAR(255),
    CONSTRAINT fk_cs_consulta FOREIGN KEY (id_consulta) REFERENCES consultas(id_consulta),
    CONSTRAINT fk_cs_servicio FOREIGN KEY (id_servicio)  REFERENCES servicios_adicionales(id_servicio)
) ENGINE=InnoDB;

-- =====================================================
-- RECUPERACIÓN DE CONTRASEÑA
-- =====================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT UNSIGNED NOT NULL,
    token      VARCHAR(64) NOT NULL,
    creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usado      TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uk_pr_token (token),
    CONSTRAINT fk_pr_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB;

-- =====================================================
-- NOTIFICACIONES (log de correos y alertas enviadas)
-- =====================================================
CREATE TABLE IF NOT EXISTS notificaciones (
    id_notificacion  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo             ENUM('confirmacion_cita','cancelacion_cita','recordatorio_cita','reset_password','otro') NOT NULL,
    destinatario     VARCHAR(150) NOT NULL,
    asunto           VARCHAR(200) NOT NULL,
    cuerpo           TEXT NOT NULL,
    estado           ENUM('pendiente','enviado','fallido') NOT NULL DEFAULT 'pendiente',
    fecha_creacion   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_envio      DATETIME,
    referencia_id    INT UNSIGNED COMMENT 'ID del registro relacionado (cita_id, etc.)',
    INDEX idx_notif_estado (estado),
    INDEX idx_notif_fecha  (fecha_creacion)
) ENGINE=InnoDB;

-- =====================================================
-- TRIGGERS: Bitácora de cirugías
-- =====================================================
DELIMITER //

CREATE TRIGGER trg_after_cirugia_insert
AFTER INSERT ON cirugias
FOR EACH ROW
BEGIN
    INSERT INTO bitacora(accion, tabla_afectada, registro_id, descripcion, fecha_hora)
    VALUES('INSERT', 'cirugias', NEW.id_cirugia,
           CONCAT('Cirugía registrada: ', NEW.tipo_cirugia, ' — paciente_id=', NEW.paciente_id), NOW());
END //

CREATE TRIGGER trg_after_cirugia_update
AFTER UPDATE ON cirugias
FOR EACH ROW
BEGIN
    INSERT INTO bitacora(accion, tabla_afectada, registro_id, descripcion, fecha_hora)
    VALUES('UPDATE', 'cirugias', NEW.id_cirugia,
           CONCAT('Estado: ', OLD.estado, ' → ', NEW.estado), NOW());
END //

DELIMITER ;

-- =====================================================
-- ÍNDICES ADICIONALES
-- =====================================================
CREATE INDEX idx_cirugias_paciente ON cirugias(paciente_id);
CREATE INDEX idx_cirugias_medico   ON cirugias(medico_id);
CREATE INDEX idx_cirugias_fecha    ON cirugias(fecha);
CREATE INDEX idx_cs_consulta       ON consulta_servicios(id_consulta);

-- =====================================================
-- DATOS INICIALES: Servicios Adicionales
-- =====================================================
INSERT INTO servicios_adicionales(nombre, descripcion, precio) VALUES
    ('Ultrasonido',          'Estudio diagnóstico por ultrasonido',          450.00),
    ('Rayos X',              'Radiografía diagnóstica',                      300.00),
    ('Resonancia Magnética', 'Imagen por resonancia magnética',             1800.00),
    ('Tomografía',           'Tomografía axial computarizada',              1200.00),
    ('Laboratorio Clínico',  'Análisis de sangre y orina básico',            350.00),
    ('Electrocardiograma',   'Registro de la actividad eléctrica cardiaca',  400.00);
