<?php
// =====================================================================
// MODELO: Médico
// =====================================================================
class MedicoModel {
    private PDO $db;
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function todos(): array {
        return $this->db->query("
            SELECT m.*, e.especialidad,
                   CONCAT(m.nombre,' ',m.apellidos) AS nombre_completo
            FROM medicos m
            JOIN especialidades e ON m.especialidad_id=e.especialidad_id
            WHERE m.activo=1 ORDER BY m.apellidos
        ")->fetchAll();
    }

    public function porId(int $id): array|false {
        $s = $this->db->prepare("
            SELECT m.*, e.especialidad FROM medicos m
            JOIN especialidades e ON m.especialidad_id=e.especialidad_id
            WHERE m.medico_id=:id AND m.activo=1
        ");
        $s->execute([':id' => $id]);
        return $s->fetch();
    }

    public function crear(array $d): int {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            // Crear usuario del médico
            $s = $this->db->prepare("
                INSERT INTO usuarios(username,contrasena,id_rol) VALUES(:u,:p,3)
            ");
            $s->execute([':u' => $d['username'], ':p' => password_hash($d['password'], PASSWORD_BCRYPT)]);
            $uid = (int)$this->db->lastInsertId();

            $s = $this->db->prepare("
                INSERT INTO medicos(id_usuario,especialidad_id,nombre,apellidos,cedula,telefono,correo,fecha_nacimiento,horario_inicio,horario_salida)
                VALUES(:uid,:esp,:nom,:ape,:ced,:tel,:cor,:fn,:hi,:hs)
            ");
            $s->execute([
                ':uid' => $uid, ':esp' => $d['especialidad_id'],
                ':nom' => $d['nombre'], ':ape' => $d['apellidos'],
                ':ced' => $d['cedula'], ':tel' => $d['telefono'] ?? null,
                ':cor' => $d['correo'] ?? null, ':fn' => $d['fecha_nacimiento'] ?? null,
                ':hi'  => $d['horario_inicio'] ?? null, ':hs' => $d['horario_salida'] ?? null,
            ]);
            $id = (int)$this->db->lastInsertId();
            $db->commit();
            Log::registrar('INSERT','medicos',$id,'Médico registrado');
            return $id;
        } catch(Throwable $e) { $db->rollback(); throw $e; }
    }

    public function actualizar(int $id, array $d): bool {
        $s = $this->db->prepare("
            UPDATE medicos SET especialidad_id=:esp,nombre=:nom,apellidos=:ape,
            cedula=:ced,telefono=:tel,correo=:cor,horario_inicio=:hi,horario_salida=:hs
            WHERE medico_id=:id
        ");
        $r = $s->execute([
            ':esp'=>$d['especialidad_id'],':nom'=>$d['nombre'],':ape'=>$d['apellidos'],
            ':ced'=>$d['cedula'],':tel'=>$d['telefono']??null,':cor'=>$d['correo']??null,
            ':hi'=>$d['horario_inicio']??null,':hs'=>$d['horario_salida']??null,':id'=>$id
        ]);
        if($r) Log::registrar('UPDATE','medicos',$id,'Datos actualizados');
        return $r;
    }

    public function eliminar(int $id): bool {
        $s = $this->db->prepare("UPDATE medicos SET activo=0 WHERE medico_id=:id");
        $r = $s->execute([':id'=>$id]);
        if($r) Log::registrar('DELETE','medicos',$id,'Baja lógica');
        return $r;
    }

    public function buscar(string $q): array {
        $s = $this->db->prepare("
            SELECT m.medico_id, CONCAT(m.nombre,' ',m.apellidos) AS nombre_completo,
                   m.cedula, m.telefono, e.especialidad
            FROM medicos m JOIN especialidades e ON m.especialidad_id=e.especialidad_id
            WHERE m.activo=1 AND (m.nombre LIKE :q OR m.apellidos LIKE :q OR m.cedula LIKE :q OR e.especialidad LIKE :q)
        ");
        $s->execute([':q' => "%$q%"]);
        return $s->fetchAll();
    }
}

// =====================================================================
// MODELO: Paciente
// =====================================================================
class PacienteModel {
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance()->getConnection(); }

    public function todos(): array {
        return $this->db->query("
            SELECT p.*, TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,CURDATE()) AS edad
            FROM pacientes p WHERE p.activo=1 ORDER BY p.apellidos
        ")->fetchAll();
    }

    public function porId(int $id): array|false {
        $s = $this->db->prepare("
            SELECT p.*, TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,CURDATE()) AS edad,
                   e.id_expediente, e.peso_actual, e.altura, e.fecha_apertura,
                   ts.descripcion AS tipo_sangre
            FROM pacientes p
            LEFT JOIN expedientes e ON p.paciente_id=e.paciente_id
            LEFT JOIN tipo_sangre ts ON e.id_tipo_sangre=ts.id_tipo_sangre
            WHERE p.paciente_id=:id AND p.activo=1
        ");
        $s->execute([':id'=>$id]);
        return $s->fetch();
    }

   public function crear(array $d): int {
    $db = Database::getInstance();
    $db->beginTransaction();
    try {
        // 1. GENERAR CREDENCIALES AUTOMÁTICAS
        // Usuario: nombre en minúsculas y sin espacios (ej: Zared Isaac -> zaredisaac)
        $username = strtolower(str_replace(' ', '', $d['nombre']));
        
        // Contraseña: fecha de nacimiento sin símbolos (ej: 2004-10-09 -> 20041009)
        $password_plana = str_replace(['-', '/'], '', $d['fecha_nacimiento']);
        $password_hash = password_hash($password_plana, PASSWORD_BCRYPT);

        // 2. INSERTAR EN TABLA USUARIOS (Obligatorio ahora)
        $s1 = $this->db->prepare("INSERT INTO usuarios(username, contrasena, id_rol, activo) VALUES(:u, :p, 4, 1)");
        $s1->execute([
            ':u' => $username,
            ':p' => $password_hash
        ]);
        $uid = (int)$this->db->lastInsertId();

        // 3. INSERTAR EN TABLA PACIENTES vinculando el id_usuario
        $s2 = $this->db->prepare("
            INSERT INTO pacientes(id_usuario, nombre, apellidos, fecha_nacimiento, sexo, telefono, correo, observaciones_generales)
            VALUES(:uid, :nom, :ape, :fn, :sex, :tel, :cor, :obs)
        ");
        $s2->execute([
            ':uid' => $uid,
            ':nom' => $d['nombre'],
            ':ape' => $d['apellidos'],
            ':fn'  => $d['fecha_nacimiento'],
            ':sex' => $d['sexo'],
            ':tel' => $d['telefono'] ?? null,
            ':cor' => $d['correo'] ?? null,
            ':obs' => $d['observaciones_generales'] ?? null
        ]);
        $pid = (int)$this->db->lastInsertId();

        // 4. CREAR EXPEDIENTE AUTOMÁTICAMENTE
        $s3 = $this->db->prepare("
            INSERT INTO expedientes(paciente_id, id_tipo_sangre, fecha_apertura)
            VALUES(:pid, :ts, CURDATE())
        ");
        $s3->execute([
            ':pid' => $pid,
            ':ts'  => $d['id_tipo_sangre'] ?? null
        ]);

        $db->commit();
        Log::registrar('INSERT', 'pacientes', $pid, "Paciente y usuario ($username) creados");
        return $pid;

    } catch(Throwable $e) { 
        $db->rollback(); 
        throw $e; 
    }
}

    public function actualizar(int $id, array $d): bool {
        $s = $this->db->prepare("
            UPDATE pacientes SET nombre=:nom,apellidos=:ape,fecha_nacimiento=:fn,
            sexo=:sex,telefono=:tel,correo=:cor,observaciones_generales=:obs
            WHERE paciente_id=:id
        ");
        $r = $s->execute([
            ':nom'=>$d['nombre'],':ape'=>$d['apellidos'],':fn'=>$d['fecha_nacimiento'],
            ':sex'=>$d['sexo'],':tel'=>$d['telefono']??null,':cor'=>$d['correo']??null,
            ':obs'=>$d['observaciones_generales']??null,':id'=>$id
        ]);
        if($r) Log::registrar('UPDATE','pacientes',$id);
        return $r;
    }

    public function eliminar(int $id): bool {
        $s = $this->db->prepare("UPDATE pacientes SET activo=0 WHERE paciente_id=:id");
        $r = $s->execute([':id'=>$id]);
        if($r) Log::registrar('DELETE','pacientes',$id,'Baja lógica');
        return $r;
    }

    public function buscar(string $q): array {
        $s = $this->db->prepare("
            SELECT p.paciente_id, CONCAT(p.nombre,' ',p.apellidos) AS nombre_completo,
                   p.telefono, p.correo, p.sexo,
                   TIMESTAMPDIFF(YEAR,p.fecha_nacimiento,CURDATE()) AS edad
            FROM pacientes p
            WHERE p.activo=1 AND (p.nombre LIKE :q OR p.apellidos LIKE :q OR p.correo LIKE :q)
        ");
        $s->execute([':q'=>"%$q%"]);
        return $s->fetchAll();
    }

    public function historialCitas(int $id): array {
        $s = $this->db->prepare("
            SELECT * FROM v_citas_detalle WHERE paciente_id=:id ORDER BY fecha DESC
        ");
        // Usar query directa ya que la vista no expone paciente_id directo
        $s = $this->db->prepare("
            SELECT c.cita_id,c.fecha,c.hora,c.estado,c.motivo,
                   CONCAT(m.nombre,' ',m.apellidos) AS medico, e.especialidad
            FROM citas c JOIN medicos m ON c.medico_id=m.medico_id
            JOIN especialidades e ON m.especialidad_id=e.especialidad_id
            WHERE c.paciente_id=:id ORDER BY c.fecha DESC
        ");
        $s->execute([':id'=>$id]);
        return $s->fetchAll();
    }
    public function misPacientes(int $id_usuario_medico): array {
        $s = $this->db->prepare("
            SELECT DISTINCT p.*, TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad
            FROM pacientes p
            JOIN citas c ON p.paciente_id = c.paciente_id
            JOIN medicos m ON c.medico_id = m.medico_id
            WHERE m.id_usuario = :uid AND p.activo = 1
            ORDER BY p.apellidos
        ");
        $s->execute([':uid' => $id_usuario_medico]);
        return $s->fetchAll();
    }
}
// =====================================================================
// MODELO: Usuarios (Staff / Recepción)
// =====================================================================
class UsuarioModel {
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance()->getConnection(); }

    public function crearStaff(array $d): int {
        $s = $this->db->prepare("
            INSERT INTO usuarios (username, contrasena, id_rol, activo) 
            VALUES (:u, :p, :rol, 1)
        ");
        $s->execute([
            ':u' => $d['username'],
            ':p' => password_hash($d['password'], PASSWORD_BCRYPT),
            ':rol' => $d['id_rol']
        ]);
        $id = (int)$this->db->lastInsertId();
        Log::registrar('INSERT', 'usuarios', $id, 'Nuevo personal de recepción creado');
        return $id;
    }

    public function todosRecepcionistas(): array {
        return $this->db->query("
            SELECT id_usuario, username, activo 
            FROM usuarios 
            WHERE id_rol = 2 
            ORDER BY id_usuario DESC
        ")->fetchAll();
    }

    public function bajaLogica(int $id): bool {
        $s = $this->db->prepare("UPDATE usuarios SET activo = 0 WHERE id_usuario = :id");
        $r = $s->execute([':id' => $id]);
        if($r) Log::registrar('UPDATE', 'usuarios', $id, 'Baja lógica de recepcionista');
        return $r;
    }
}

// =====================================================================
// MODELO: Cita
// =====================================================================
class CitaModel {
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance()->getConnection(); }

    public function todas(array $filtros = []): array {
        $where = ['1=1'];
        $params = [];
        if(!empty($filtros['fecha'])) { $where[]='c.fecha=:fecha'; $params[':fecha']=$filtros['fecha']; }
        if(!empty($filtros['medico_id'])) { $where[]='c.medico_id=:mid'; $params[':mid']=$filtros['medico_id']; }
        if(!empty($filtros['estado'])) { $where[]='c.estado=:est'; $params[':est']=$filtros['estado']; }
        $sql = "SELECT c.*,CONCAT(m.nombre,' ',m.apellidos) AS medico,
                       CONCAT(p.nombre,' ',p.apellidos) AS paciente,
                       e.especialidad
                FROM citas c
                JOIN medicos m ON c.medico_id=m.medico_id
                JOIN especialidades e ON m.especialidad_id=e.especialidad_id
                JOIN pacientes p ON c.paciente_id=p.paciente_id
                WHERE ".implode(' AND ',$where)." ORDER BY c.fecha,c.hora";
        $s = $this->db->prepare($sql);
        $s->execute($params);
        return $s->fetchAll();
    }

    public function porId(int $id): array|false {
        $s = $this->db->prepare("
            SELECT c.*,CONCAT(m.nombre,' ',m.apellidos) AS medico,
                   CONCAT(p.nombre,' ',p.apellidos) AS paciente,
                   e.especialidad
            FROM citas c
            JOIN medicos m ON c.medico_id=m.medico_id
            JOIN especialidades e ON m.especialidad_id=e.especialidad_id
            JOIN pacientes p ON c.paciente_id=p.paciente_id
            WHERE c.cita_id=:id
        ");
        $s->execute([':id'=>$id]);
        return $s->fetch();
    }

    public function crear(array $d): int {
        // Verificar disponibilidad
        $s = $this->db->prepare("
            SELECT COUNT(*) FROM citas
            WHERE medico_id=:mid AND fecha=:fecha AND hora=:hora
            AND estado NOT IN ('cancelada','reprogramada')
        ");
        $s->execute([':mid'=>$d['medico_id'],':fecha'=>$d['fecha'],':hora'=>$d['hora']]);
        if($s->fetchColumn() > 0) throw new RuntimeException('El médico no está disponible en ese horario');

        $s = $this->db->prepare("
            INSERT INTO citas(medico_id,paciente_id,fecha,hora,estado,motivo)
            VALUES(:mid,:pid,:fecha,:hora,'programada',:motivo)
        ");
        $s->execute([
            ':mid'=>$d['medico_id'],':pid'=>$d['paciente_id'],
            ':fecha'=>$d['fecha'],':hora'=>$d['hora'],
            ':motivo'=>$d['motivo']??null
        ]);
        $id = (int)$this->db->lastInsertId();
        Log::registrar('INSERT','citas',$id,'Cita programada');
        return $id;
    }

    public function cambiarEstado(int $id, string $estado): bool {
        $permitidos = ['programada','confirmada','cancelada','completada','reprogramada'];
        if(!in_array($estado,$permitidos)) throw new InvalidArgumentException('Estado inválido');
        $s = $this->db->prepare("UPDATE citas SET estado=:est WHERE cita_id=:id");
        $r = $s->execute([':est'=>$estado,':id'=>$id]);
        if($r) Log::registrar('UPDATE','citas',$id,"Estado → $estado");
        return $r;
    }

    public function hoy(): array {
        return $this->todas(['fecha'=>date('Y-m-d')]);
    }

    public function disponibilidadMedico(int $medico_id, string $fecha): array {
        $medico = $this->db->prepare("SELECT horario_inicio,horario_salida FROM medicos WHERE medico_id=:id");
        $medico->execute([':id'=>$medico_id]);
        $m = $medico->fetch();
        if(!$m) return [];

        $ocupadas = $this->db->prepare("
            SELECT hora FROM citas WHERE medico_id=:mid AND fecha=:fecha
            AND estado NOT IN ('cancelada','reprogramada')
        ");
        $ocupadas->execute([':mid'=>$medico_id,':fecha'=>$fecha]);
        $ocupado = array_column($ocupadas->fetchAll(),'hora');

        $slots = [];
        $inicio = strtotime($m['horario_inicio'] ?? '08:00:00');
        $fin    = strtotime($m['horario_salida'] ?? '17:00:00');
        for($t=$inicio; $t<$fin; $t+=30*60) {
            $hora = date('H:i:s',$t);
            $slots[] = ['hora'=>$hora,'disponible'=>!in_array($hora,$ocupado)];
        }
        return $slots;
    }
}

// =====================================================================
// MODELO: Consulta
// =====================================================================
class ConsultaModel {
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance()->getConnection(); }

    public function todas(): array {
        return $this->db->query("SELECT * FROM v_consultas_detalle ORDER BY fecha DESC LIMIT 100")->fetchAll();
    }

    public function porId(int $id): array|false {
        $s = $this->db->prepare("SELECT * FROM v_consultas_detalle WHERE id_consulta=:id");
        $s->execute([':id'=>$id]);
        return $s->fetch();
    }

    public function crear(array $d): int {
        // sp_registrar_consulta manages its own transaction internally;
        // calling beginTransaction() here would cause "no active transaction" on commit.
        $s = $this->db->prepare("
            CALL sp_registrar_consulta(:cita,:med,:pac,:tipo,:cons,:dx,:obs,@out_id)
        ");
        $s->execute([
            ':cita'=>$d['cita_id']??null,':med'=>$d['medico_id'],
            ':pac'=>$d['paciente_id'],':tipo'=>$d['id_tipo_consulta']??null,
            ':cons'=>$d['id_consultorio']??null,
            ':dx'=>$d['diagnostico']??null,':obs'=>$d['observaciones']??null
        ]);
        $id = (int)$this->db->query("SELECT @out_id")->fetchColumn();

        // Wrap post-SP inserts in a separate transaction
        $this->db->beginTransaction();
        try {
            // Receta si viene con medicamentos
            if(!empty($d['medicamentos'])) {
                $sr = $this->db->prepare("INSERT INTO recetas(id_consulta) VALUES(:ic)");
                $sr->execute([':ic'=>$id]);
                $rid = (int)$this->db->lastInsertId();
                $sm = $this->db->prepare("
                    INSERT INTO receta_medicamentos(id_receta,id_medicamento,frecuencia,duracion)
                    VALUES(:rid,:mid,:frec,:dur)
                ");
                foreach($d['medicamentos'] as $med) {
                    $sm->execute([':rid'=>$rid,':mid'=>$med['id_medicamento'],':frec'=>$med['frecuencia']??null,':dur'=>$med['duracion']??null]);
                }
            }

            // Servicios adicionales vinculados a la consulta
            if (!empty($d['servicios'])) {
                $srvModel = new ServicioModel();
                $srvModel->agregarAConsulta($id, $d['servicios']);
            }

            // Crear pago automático (precio consulta + servicios)
            $precioQ = $this->db->prepare("SELECT precio FROM tipo_consulta WHERE id_tipo_consulta=:t");
            $precioQ->execute([':t'=>$d['id_tipo_consulta']??null]);
            $precio = (float)($precioQ->fetchColumn() ?: 350);
            if (!empty($d['servicios'])) {
                foreach ($d['servicios'] as $srv) {
                    $ps = $this->db->prepare("SELECT precio FROM servicios_adicionales WHERE id_servicio=:id AND activo=1");
                    $ps->execute([':id' => $srv['id_servicio']]);
                    $precio += (float)($ps->fetchColumn() ?: 0);
                }
            }
            $sp = $this->db->prepare("INSERT INTO pagos(id_consulta,monto_total,estado) VALUES(:ic,:mt,'pendiente')");
            $sp->execute([':ic'=>$id,':mt'=>$precio]);

            $this->db->commit();
            return $id;
        } catch(Throwable $e) { $this->db->rollBack(); throw $e; }
    }

    public function receta(int $id_consulta): array|false {
        $s = $this->db->prepare("
            SELECT r.id_receta,m.nombre,m.descripcion,rm.frecuencia,rm.duracion
            FROM recetas r JOIN receta_medicamentos rm ON r.id_receta=rm.id_receta
            JOIN medicamentos m ON rm.id_medicamento=m.id_medicamento
            WHERE r.id_consulta=:ic
        ");
        $s->execute([':ic'=>$id_consulta]);
        return $s->fetchAll();
    }
}

// =====================================================================
// MODELO: Medicamento e Inventario
// =====================================================================
class MedicamentoModel {
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance()->getConnection(); }

    public function todos(): array {
        return $this->db->query("SELECT * FROM v_inventario_detalle ORDER BY medicamento")->fetchAll();
    }

    public function catalogoTodos(): array {
        return $this->db->query("SELECT * FROM medicamentos WHERE activo=1 ORDER BY nombre")->fetchAll();
    }

    public function crear(array $d): int {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $s = $this->db->prepare("INSERT INTO medicamentos(nombre,descripcion,precio) VALUES(:n,:d,:p)");
            $s->execute([':n'=>$d['nombre'],':d'=>$d['descripcion']??null,':p'=>$d['precio']??0]);
            $mid = (int)$this->db->lastInsertId();

            $si = $this->db->prepare("INSERT INTO inventario(id_medicamento,stock,fecha_caducidad) VALUES(:mid,:st,:fc)");
            $si->execute([':mid'=>$mid,':st'=>$d['stock']??0,':fc'=>$d['fecha_caducidad']??null]);
            $iid = (int)$this->db->lastInsertId();

            // Registrar movimiento de entrada
            if(($d['stock']??0) > 0) {
                $sm = $this->db->prepare("INSERT INTO movimiento_inventario(id_inventario,id_tipo,cantidad) VALUES(:iid,1,:c)");
                $sm->execute([':iid'=>$iid,':c'=>$d['stock']]);
            }
            $db->commit();
            Log::registrar('INSERT','medicamentos',$mid,'Medicamento creado');
            return $mid;
        } catch(Throwable $e) { $db->rollback(); throw $e; }
    }

    public function actualizar(int $id, array $d): bool {
        $s = $this->db->prepare("UPDATE medicamentos SET nombre=:n,descripcion=:d,precio=:p WHERE id_medicamento=:id");
        return $s->execute([':n'=>$d['nombre'],':d'=>$d['descripcion']??null,':p'=>$d['precio'],':id'=>$id]);
    }

    public function entradaStock(int $id_inventario, int $cantidad): bool {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $s = $this->db->prepare("UPDATE inventario SET stock=stock+:c WHERE id_inventario=:id");
            $s->execute([':c'=>$cantidad,':id'=>$id_inventario]);
            $sm = $this->db->prepare("INSERT INTO movimiento_inventario(id_inventario,id_tipo,cantidad) VALUES(:id,1,:c)");
            $sm->execute([':id'=>$id_inventario,':c'=>$cantidad]);
            $db->commit();
            Log::registrar('UPDATE','inventario',$id_inventario,"Entrada: $cantidad unidades");
            return true;
        } catch(Throwable $e) { $db->rollback(); throw $e; }
    }

    public function caducos(): array {
        return $this->db->query("SELECT * FROM v_inventario_detalle WHERE estado_caducidad IN ('Caducado','Por caducar') ORDER BY fecha_caducidad")->fetchAll();
    }
}

// =====================================================================
// MODELO: Pago
// =====================================================================
class PagoModel {
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance()->getConnection(); }

    public function todos(): array {
        return $this->db->query("
            SELECT pg.*, CONCAT(p.nombre,' ',p.apellidos) AS paciente,
                   CONCAT(m.nombre,' ',m.apellidos) AS medico, tc.descripcion AS tipo
            FROM pagos pg
            LEFT JOIN consultas co ON pg.id_consulta=co.id_consulta
            LEFT JOIN pacientes p ON co.paciente_id=p.paciente_id
            LEFT JOIN medicos m ON co.medico_id=m.medico_id
            LEFT JOIN tipo_consulta tc ON co.id_tipo_consulta=tc.id_tipo_consulta
            ORDER BY pg.fecha DESC LIMIT 200
        ")->fetchAll();
    }

    public function porId(int $id): array|false {
        $s = $this->db->prepare("SELECT * FROM pagos WHERE id_pago=:id");
        $s->execute([':id'=>$id]);
        return $s->fetch();
    }

    public function pagar(int $id): bool {
        $s = $this->db->prepare("UPDATE pagos SET estado='pagado' WHERE id_pago=:id");
        $r = $s->execute([':id'=>$id]);
        if($r) Log::registrar('UPDATE','pagos',$id,'Pago registrado');
        return $r;
    }

    public function crear(array $d): int {
        $s = $this->db->prepare("INSERT INTO pagos(id_consulta,monto_total,estado) VALUES(:ic,:mt,:est)");
        $s->execute([':ic'=>$d['id_consulta']??null,':mt'=>$d['monto_total'],':est'=>$d['estado']??'pendiente']);
        $id = (int)$this->db->lastInsertId();
        Log::registrar('INSERT','pagos',$id,'Pago creado');
        return $id;
    }
}

// =====================================================================
// MODELO: Reportes
// =====================================================================
class ReporteModel {
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance()->getConnection(); }

    public function ingresos(string $desde, string $hasta): array {
        $s = $this->db->prepare("
            SELECT DATE(pg.fecha) AS fecha, COUNT(*) AS pagos, SUM(pg.monto_total) AS total
            FROM pagos pg WHERE pg.estado='pagado' AND DATE(pg.fecha) BETWEEN :d AND :h
            GROUP BY DATE(pg.fecha) ORDER BY fecha
        ");
        $s->execute([':d'=>$desde,':h'=>$hasta]);
        return $s->fetchAll();
    }

    public function pacientesPorGenero(): array {
        return $this->db->query("
            SELECT sexo, COUNT(*) AS total FROM pacientes WHERE activo=1 GROUP BY sexo
        ")->fetchAll();
    }

    public function consultasPorPeriodo(string $desde, string $hasta): array {
        $s = $this->db->prepare("
            SELECT DATE(co.fecha) AS dia, COUNT(*) AS total,
                   e.especialidad
            FROM consultas co
            JOIN medicos m ON co.medico_id=m.medico_id
            JOIN especialidades e ON m.especialidad_id=e.especialidad_id
            WHERE DATE(co.fecha) BETWEEN :d AND :h
            GROUP BY dia,e.especialidad ORDER BY dia
        ");
        $s->execute([':d'=>$desde,':h'=>$hasta]);
        return $s->fetchAll();
    }

    public function medicosPorEspecialidad(): array {
        return $this->db->query("
            SELECT e.especialidad, COUNT(m.medico_id) AS total
            FROM especialidades e LEFT JOIN medicos m ON e.especialidad_id=m.especialidad_id AND m.activo=1
            GROUP BY e.especialidad ORDER BY total DESC
        ")->fetchAll();
    }

    public function bitacora(int $limit=100): array {
        $s = $this->db->prepare("SELECT * FROM bitacora ORDER BY fecha_hora DESC LIMIT :l");
        $s->bindValue(':l',$limit,PDO::PARAM_INT);
        $s->execute();
        return $s->fetchAll();
    }

    public function enfermedadesPorPeriodo(string $desde, string $hasta): array {
        $s = $this->db->prepare("
            SELECT co.diagnostico, COUNT(*) AS total, e.especialidad
            FROM consultas co
            JOIN medicos m ON co.medico_id = m.medico_id
            JOIN especialidades e ON m.especialidad_id = e.especialidad_id
            WHERE DATE(co.fecha) BETWEEN :d AND :h
              AND co.diagnostico IS NOT NULL AND co.diagnostico <> ''
            GROUP BY co.diagnostico, e.especialidad
            ORDER BY total DESC
            LIMIT 50
        ");
        $s->execute([':d' => $desde, ':h' => $hasta]);
        return $s->fetchAll();
    }

    public function inventarioCompleto(): array {
        return $this->db->query(
            "SELECT * FROM v_inventario_detalle ORDER BY estado_caducidad DESC, medicamento"
        )->fetchAll();
    }
}

// =====================================================================
// MODELO: Cirugías
// =====================================================================
class CirugiaModel {
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance()->getConnection(); }

    public function todas(array $filtros = []): array {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filtros['paciente_id'])) { $where[] = 'c.paciente_id=:pid'; $params[':pid'] = $filtros['paciente_id']; }
        if (!empty($filtros['estado']))       { $where[] = 'c.estado=:est';       $params[':est'] = $filtros['estado']; }
        $sql = "SELECT c.*,
                       CONCAT(p.nombre,' ',p.apellidos) AS paciente,
                       CONCAT(m.nombre,' ',m.apellidos) AS medico
                FROM cirugias c
                JOIN pacientes p ON c.paciente_id = p.paciente_id
                JOIN medicos   m ON c.medico_id   = m.medico_id
                WHERE " . implode(' AND ', $where) . " ORDER BY c.fecha DESC";
        $s = $this->db->prepare($sql);
        $s->execute($params);
        return $s->fetchAll();
    }

    public function porPaciente(int $paciente_id): array {
        return $this->todas(['paciente_id' => $paciente_id]);
    }

    public function crear(array $d): int {
        $s = $this->db->prepare("
            INSERT INTO cirugias(paciente_id, medico_id, id_consultorio, fecha,
                                 tipo_cirugia, descripcion, resultado, estado, observaciones)
            VALUES(:pid, :mid, :cons, :fecha, :tipo, :desc, :res, :est, :obs)
        ");
        $s->execute([
            ':pid'   => $d['paciente_id'],
            ':mid'   => $d['medico_id'],
            ':cons'  => $d['id_consultorio'] ?? null,
            ':fecha' => $d['fecha'],
            ':tipo'  => $d['tipo_cirugia'],
            ':desc'  => $d['descripcion'] ?? null,
            ':res'   => $d['resultado'] ?? null,
            ':est'   => $d['estado'] ?? 'programada',
            ':obs'   => $d['observaciones'] ?? null,
        ]);
        $id = (int)$this->db->lastInsertId();
        Log::registrar('INSERT', 'cirugias', $id, 'Cirugía registrada: ' . $d['tipo_cirugia']);
        return $id;
    }

    public function actualizar(int $id, array $d): bool {
        $s = $this->db->prepare("
            UPDATE cirugias SET medico_id=:mid, id_consultorio=:cons, fecha=:fecha,
            tipo_cirugia=:tipo, descripcion=:desc, resultado=:res, estado=:est, observaciones=:obs
            WHERE id_cirugia=:id
        ");
        $r = $s->execute([
            ':mid'  => $d['medico_id'],
            ':cons' => $d['id_consultorio'] ?? null,
            ':fecha'=> $d['fecha'],
            ':tipo' => $d['tipo_cirugia'],
            ':desc' => $d['descripcion'] ?? null,
            ':res'  => $d['resultado'] ?? null,
            ':est'  => $d['estado'],
            ':obs'  => $d['observaciones'] ?? null,
            ':id'   => $id,
        ]);
        if ($r) Log::registrar('UPDATE', 'cirugias', $id, 'Cirugía actualizada: estado=' . $d['estado']);
        return $r;
    }
}

// =====================================================================
// MODELO: Servicios Adicionales
// =====================================================================
class ServicioModel {
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance()->getConnection(); }

    public function catalogo(): array {
        return $this->db->query("SELECT * FROM servicios_adicionales WHERE activo=1 ORDER BY nombre")->fetchAll();
    }

    public function todos(): array {
        return $this->db->query("SELECT * FROM servicios_adicionales ORDER BY nombre")->fetchAll();
    }

    public function crear(array $d): int {
        $s = $this->db->prepare("INSERT INTO servicios_adicionales(nombre,descripcion,precio) VALUES(:n,:d,:p)");
        $s->execute([':n'=>$d['nombre'],':d'=>$d['descripcion']??null,':p'=>$d['precio']??0]);
        $id = (int)$this->db->lastInsertId();
        Log::registrar('INSERT', 'servicios_adicionales', $id, 'Servicio creado: ' . $d['nombre']);
        return $id;
    }

    public function actualizar(int $id, array $d): bool {
        $s = $this->db->prepare("
            UPDATE servicios_adicionales SET nombre=:n,descripcion=:d,precio=:p,activo=:a WHERE id_servicio=:id
        ");
        return $s->execute([':n'=>$d['nombre'],':d'=>$d['descripcion']??null,':p'=>$d['precio'],':a'=>$d['activo']??1,':id'=>$id]);
    }

    public function porConsulta(int $id_consulta): array {
        $s = $this->db->prepare("
            SELECT cs.*, sa.nombre, sa.precio, sa.descripcion AS descripcion_servicio
            FROM consulta_servicios cs
            JOIN servicios_adicionales sa ON cs.id_servicio = sa.id_servicio
            WHERE cs.id_consulta = :ic
        ");
        $s->execute([':ic' => $id_consulta]);
        return $s->fetchAll();
    }

    public function agregarAConsulta(int $id_consulta, array $servicios): void {
        $s = $this->db->prepare("
            INSERT INTO consulta_servicios(id_consulta, id_servicio, observaciones)
            VALUES(:ic, :is, :obs)
        ");
        foreach ($servicios as $srv) {
            $s->execute([':ic' => $id_consulta, ':is' => $srv['id_servicio'], ':obs' => $srv['observaciones'] ?? null]);
        }
    }

    public function porPeriodo(string $desde, string $hasta): array {
        $s = $this->db->prepare("
            SELECT sa.nombre, COUNT(*) AS total, SUM(sa.precio) AS ingresos
            FROM consulta_servicios cs
            JOIN servicios_adicionales sa ON cs.id_servicio = sa.id_servicio
            JOIN consultas co ON cs.id_consulta = co.id_consulta
            WHERE DATE(co.fecha) BETWEEN :d AND :h
            GROUP BY sa.id_servicio, sa.nombre
            ORDER BY total DESC
        ");
        $s->execute([':d' => $desde, ':h' => $hasta]);
        return $s->fetchAll();
    }
}

// =====================================================================
// MODELO: Catálogos (especialidades, roles, tipo sangre, consultorios)
// =====================================================================
class CatalogoModel {
    private PDO $db;
    public function __construct() { $this->db = Database::getInstance()->getConnection(); }

    public function especialidades(): array { return $this->db->query("SELECT * FROM especialidades ORDER BY especialidad")->fetchAll(); }
    public function roles(): array          { return $this->db->query("SELECT * FROM roles")->fetchAll(); }
    public function tipoSangre(): array     { return $this->db->query("SELECT * FROM tipo_sangre ORDER BY descripcion")->fetchAll(); }
    public function consultorios(): array   { return $this->db->query("SELECT * FROM consultorios ORDER BY piso,numero")->fetchAll(); }
    public function tipoConsulta(): array   { return $this->db->query("SELECT * FROM tipo_consulta ORDER BY descripcion")->fetchAll(); }

    public function crearEspecialidad(string $nombre): int {
        $s = $this->db->prepare("INSERT INTO especialidades(especialidad) VALUES(:n)");
        $s->execute([':n'=>$nombre]);
        return (int)$this->db->lastInsertId();
    }
    public function crearConsultorio(array $d): int {
        $s = $this->db->prepare("INSERT INTO consultorios(numero,piso,descripcion) VALUES(:n,:p,:d)");
        $s->execute([':n'=>$d['numero'],':p'=>$d['piso'],':d'=>$d['descripcion']??null]);
        return (int)$this->db->lastInsertId();
    }
}
