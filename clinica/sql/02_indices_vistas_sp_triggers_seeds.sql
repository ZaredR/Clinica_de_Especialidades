USE clinica_especialidades;

-- =====================================================
-- ÍNDICES DE OPTIMIZACIÓN
-- =====================================================
CREATE INDEX idx_citas_fecha        ON citas(fecha);
CREATE INDEX idx_citas_medico       ON citas(medico_id);
CREATE INDEX idx_citas_paciente     ON citas(paciente_id);
CREATE INDEX idx_citas_estado       ON citas(estado);
CREATE INDEX idx_consultas_fecha    ON consultas(fecha);
CREATE INDEX idx_consultas_medico   ON consultas(medico_id);
CREATE INDEX idx_consultas_paciente ON consultas(paciente_id);
CREATE INDEX idx_inv_caducidad      ON inventario(fecha_caducidad);
CREATE INDEX idx_medicos_esp        ON medicos(especialidad_id);
CREATE UNIQUE INDEX idx_usuario_username ON usuarios(username);
-- =====================================================
-- VISTAS
-- =====================================================

CREATE OR REPLACE VIEW v_citas_detalle AS
SELECT
    c.cita_id, c.fecha, c.hora, c.estado, c.motivo,
    CONCAT(m.nombre,' ',m.apellidos) AS medico,
    e.especialidad,
    CONCAT(p.nombre,' ',p.apellidos) AS paciente,
    p.telefono AS tel_paciente
FROM citas c
JOIN medicos m ON c.medico_id = m.medico_id
JOIN especialidades e ON m.especialidad_id = e.especialidad_id
JOIN pacientes p ON c.paciente_id = p.paciente_id;

CREATE OR REPLACE VIEW v_consultas_detalle AS
SELECT
    co.id_consulta, co.fecha, co.diagnostico, co.observaciones,
    CONCAT(m.nombre,' ',m.apellidos) AS medico,
    CONCAT(p.nombre,' ',p.apellidos) AS paciente,
    tc.descripcion AS tipo_consulta, tc.precio,
    ct.numero AS consultorio, ct.piso
FROM consultas co
JOIN medicos m ON co.medico_id = m.medico_id
JOIN pacientes p ON co.paciente_id = p.paciente_id
LEFT JOIN tipo_consulta tc ON co.id_tipo_consulta = tc.id_tipo_consulta
LEFT JOIN consultorios ct ON co.id_consultorio = ct.id_consultorio;

CREATE OR REPLACE VIEW v_inventario_detalle AS
SELECT
    i.id_inventario, m.nombre AS medicamento, m.precio,
    i.stock, i.fecha_caducidad,
    CASE WHEN i.fecha_caducidad < CURDATE() THEN 'Caducado'
         WHEN i.fecha_caducidad < DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Por caducar'
         ELSE 'Vigente' END AS estado_caducidad
FROM inventario i
JOIN medicamentos m ON i.id_medicamento = m.id_medicamento;

CREATE OR REPLACE VIEW v_reporte_ingresos AS
SELECT
    DATE_FORMAT(pg.fecha,'%Y-%m') AS periodo,
    COUNT(*) AS total_pagos,
    SUM(pg.monto_total) AS ingresos
FROM pagos pg
WHERE pg.estado = 'pagado'
GROUP BY periodo
ORDER BY periodo DESC;

-- =====================================================
-- PROCEDIMIENTOS ALMACENADOS
-- =====================================================
DELIMITER //

CREATE PROCEDURE sp_registrar_consulta(
    IN p_cita_id       INT UNSIGNED,
    IN p_medico_id     INT UNSIGNED,
    IN p_paciente_id   INT UNSIGNED,
    IN p_tipo          INT UNSIGNED,
    IN p_consultorio   INT UNSIGNED,
    IN p_diagnostico   TEXT,
    IN p_observaciones TEXT,
    OUT p_id_consulta  INT UNSIGNED
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    INSERT INTO consultas(cita_id,medico_id,paciente_id,id_tipo_consulta,id_consultorio,diagnostico,observaciones)
    VALUES(p_cita_id,p_medico_id,p_paciente_id,p_tipo,p_consultorio,p_diagnostico,p_observaciones);

    SET p_id_consulta = LAST_INSERT_ID();

    -- Actualizar estado de la cita
    IF p_cita_id IS NOT NULL THEN
        UPDATE citas SET estado='completada' WHERE cita_id=p_cita_id;
    END IF;

    COMMIT;
END //

CREATE PROCEDURE sp_dispensar_medicamento(
    IN p_id_inventario INT UNSIGNED,
    IN p_cantidad      INT,
    IN p_id_tipo       INT UNSIGNED
)
BEGIN
    DECLARE v_stock INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    SELECT stock INTO v_stock FROM inventario WHERE id_inventario=p_id_inventario FOR UPDATE;

    IF v_stock < p_cantidad THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock insuficiente';
    END IF;

    UPDATE inventario SET stock = stock - p_cantidad WHERE id_inventario = p_id_inventario;

    INSERT INTO movimiento_inventario(id_inventario, id_tipo, cantidad, fecha, hora)
    VALUES(p_id_inventario, p_id_tipo, p_cantidad, CURDATE(), CURTIME());

    COMMIT;
END //

DELIMITER ;

-- =====================================================
-- TRIGGERS
-- =====================================================
DELIMITER //

CREATE TRIGGER trg_after_cita_update
AFTER UPDATE ON citas
FOR EACH ROW
BEGIN
    INSERT INTO bitacora(accion, tabla_afectada, registro_id, descripcion, fecha_hora)
    VALUES('UPDATE','citas', NEW.cita_id,
           CONCAT('Estado cambió de ',OLD.estado,' a ',NEW.estado), NOW());
END //

CREATE TRIGGER trg_after_consulta_insert
AFTER INSERT ON consultas
FOR EACH ROW
BEGIN
    INSERT INTO bitacora(accion, tabla_afectada, registro_id, descripcion, fecha_hora)
    VALUES('INSERT','consultas', NEW.id_consulta,
           CONCAT('Consulta registrada para paciente ',NEW.paciente_id), NOW());
END //

CREATE TRIGGER trg_after_pago_insert
AFTER INSERT ON pagos
FOR EACH ROW
BEGIN
    INSERT INTO bitacora(accion, tabla_afectada, registro_id, descripcion, fecha_hora)
    VALUES('INSERT','pagos', NEW.id_pago,
           CONCAT('Pago registrado: $',NEW.monto_total), NOW());
END //

DELIMITER ;

-- =====================================================
-- DATOS INICIALES (SEEDS)
-- =====================================================

INSERT INTO roles(descripcion) VALUES
    ('administrador'),('recepcionista'),('medico'),('paciente');

INSERT INTO tipo_sangre(descripcion) VALUES
    ('A+'),('A-'),('B+'),('B-'),('AB+'),('AB-'),('O+'),('O-');

INSERT INTO especialidades(especialidad) VALUES
    ('Medicina General'),('Cardiología'),('Dermatología'),
    ('Ginecología'),('Neurología'),('Ortopedia'),
    ('Oftalmología'),('Pediatría'),('Psiquiatría'),('Endocrinología');

INSERT INTO tipo_consulta(descripcion, precio) VALUES
    ('Consulta general',350.00),
    ('Consulta de especialidad',600.00),
    ('Urgencia',800.00),
    ('Revisión / seguimiento',250.00);

INSERT INTO tipo_movimiento(descripcion) VALUES
    ('Entrada'),('Salida'),('Ajuste');

INSERT INTO consultorios(numero, piso, descripcion) VALUES
    ('C-101','1','Consultorio de Medicina General'),
    ('C-102','1','Consultorio de Cardiología'),
    ('C-201','2','Consultorio de Dermatología'),
    ('C-202','2','Consultorio de Ginecología'),
    ('C-301','3','Consultorio de Neurología');

-- Usuario admin por defecto (contraseña: Admin1234)
INSERT INTO usuarios(username, contrasena, id_rol) VALUES
    ('admin', '$2y$12$XNwHhPkOl.6rV6Dii0MFLu5lCEp0ZXhFvq0CkVBf3zKMk0Dh0q5Dm', 1);
