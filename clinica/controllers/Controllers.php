<?php
// =====================================================================
// CONTROLADOR: Autenticación
// =====================================================================
class AuthController {
    public function login(): void {
        $d = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        if (empty($d['username']) || empty($d['password']))
            Response::error('Usuario y contraseña requeridos');
        try {
            $user = Auth::login($d['username'], $d['password']);
            Response::success($user, 200, 'Sesión iniciada');
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 401);
        }
    }

    public function logout(): void {
        Auth::logout();
        Response::success(null, 200, 'Sesión cerrada');
    }

    public function me(): void {
        Auth::verificar();
        Response::success(Auth::usuario());
    }

    public function cambiarPassword(): void {
        Auth::verificar();
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($d['password_actual']) || empty($d['password_nueva']))
            Response::error('Campos requeridos');
        $db = Database::getInstance()->getConnection();
        $uid = Auth::usuario()['id_usuario'];
        $s   = $db->prepare("SELECT contrasena FROM usuarios WHERE id_usuario=:id");
        $s->execute([':id'=>$uid]);
        $hash = $s->fetchColumn();
        if (!password_verify($d['password_actual'], $hash))
            Response::error('Contraseña actual incorrecta', 401);
        $s2 = $db->prepare("UPDATE usuarios SET contrasena=:p WHERE id_usuario=:id");
        $s2->execute([':p'=>password_hash($d['password_nueva'],PASSWORD_BCRYPT),':id'=>$uid]);
        Log::registrar('UPDATE','usuarios',$uid,'Cambio de contraseña');
        Response::success(null, 200, 'Contraseña actualizada');
    }

    public function recuperarPassword(): void {
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($d['username'])) Response::error('Usuario requerido');

        $db = Database::getInstance()->getConnection();
        $s  = $db->prepare("
            SELECT u.id_usuario, u.username,
                   COALESCE(m.correo, p.correo) AS correo
            FROM usuarios u
            LEFT JOIN medicos   m ON u.id_usuario = m.id_usuario
            LEFT JOIN pacientes p ON u.id_usuario = p.id_usuario
            WHERE u.username = :u AND u.activo = 1
        ");
        $s->execute([':u' => $d['username']]);
        $user = $s->fetch();

        // Siempre responde éxito para no revelar si el usuario existe
        if (!$user || empty($user['correo'])) {
            Response::success(null, 200, 'Si el usuario existe y tiene correo registrado, recibirá instrucciones');
            return;
        }

        $token = bin2hex(random_bytes(32));
        $db->prepare("INSERT INTO password_resets(id_usuario, token) VALUES(:uid, :tok)")
           ->execute([':uid' => $user['id_usuario'], ':tok' => $token]);

        Email::resetPassword($user['correo'], $user['username'], $token);
        Log::registrar('INSERT', 'password_resets', $user['id_usuario'], 'Solicitud de recuperación de contraseña');
        Response::success(['token_registrado' => true], 200, 'Token enviado al correo registrado');
    }

    public function resetPassword(): void {
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($d['token']) || empty($d['password_nueva'])) Response::error('Token y nueva contraseña requeridos');

        $db = Database::getInstance()->getConnection();
        $s  = $db->prepare("
            SELECT id_usuario FROM password_resets
            WHERE token=:tok AND usado=0 AND creado_en >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $s->execute([':tok' => $d['token']]);
        $row = $s->fetch();
        if (!$row) Response::error('Token inválido o expirado', 400);

        $db->prepare("UPDATE usuarios SET contrasena=:p WHERE id_usuario=:id")
           ->execute([':p' => password_hash($d['password_nueva'], PASSWORD_BCRYPT), ':id' => $row['id_usuario']]);
        $db->prepare("UPDATE password_resets SET usado=1 WHERE token=:tok")
           ->execute([':tok' => $d['token']]);

        Log::registrar('UPDATE', 'usuarios', $row['id_usuario'], 'Contraseña restablecida vía token de recuperación');
        Response::success(null, 200, 'Contraseña actualizada correctamente');
    }
}

// =====================================================================
// CONTROLADOR: Médicos
// =====================================================================
class MedicoController {
    private MedicoModel $model;
    public function __construct() { $this->model = new MedicoModel(); }

    public function index(): void {
        Response::success($this->model->todos());
    }
    public function show(int $id): void {
        $m = $this->model->porId($id);
        $m ? Response::success($m) : Response::error('Médico no encontrado', 404);
    }
    public function store(): void {
        Auth::requiereRol(['administrador','recepcionista']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        $requeridos = ['nombre','apellidos','cedula','especialidad_id','username','password'];
        foreach ($requeridos as $r) if (empty($d[$r])) Response::error("Campo requerido: $r");
        try {
            $id = $this->model->crear($d);
            Response::created(['medico_id'=>$id]);
        } catch (Throwable $e) { Response::error($e->getMessage()); }
    }
    public function update(int $id): void {
        Auth::requiereRol(['administrador','recepcionista']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        $this->model->actualizar($id, $d);
        Response::success(null, 200, 'Actualizado');
    }
    public function destroy(int $id): void {
        Auth::requiereRol(['administrador']);
        $this->model->eliminar($id);
        Response::success(null, 200, 'Eliminado');
    }
    public function buscar(): void {
        $q = $_GET['q'] ?? '';
        Response::success($this->model->buscar($q));
    }
}

// =====================================================================
// CONTROLADOR: Pacientes
// =====================================================================
class PacienteController {
    private PacienteModel $model;
    public function __construct() { $this->model = new PacienteModel(); }

public function index(): void { 
    $usuario = Auth::usuario();
    
    if ($usuario['rol'] === 'medico') {
        // Si es médico, solo devolvemos SUS pacientes
        Response::success($this->model->misPacientes($usuario['id_usuario']));
    } else {
        // Administradores y recepción ven a todos
        Response::success($this->model->todos()); 
    }
}
    public function store(): void {
        Auth::requiereRol(['administrador','recepcionista']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        foreach (['nombre','apellidos','fecha_nacimiento','sexo'] as $r)
            if (empty($d[$r])) Response::error("Campo requerido: $r");
        try {
            $id = $this->model->crear($d);
            Response::created(['paciente_id'=>$id]);
        } catch (Throwable $e) { Response::error($e->getMessage()); }
    }
    public function update(int $id): void {
        Auth::requiereRol(['administrador','recepcionista']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        $this->model->actualizar($id, $d);
        Response::success(null, 200, 'Actualizado');
    }
    public function destroy(int $id): void {
        Auth::requiereRol(['administrador']);
        $this->model->eliminar($id);
        Response::success(null, 200, 'Eliminado');
    }
    public function buscar(): void {
        $q = $_GET['q'] ?? '';
        Response::success($this->model->buscar($q));
    }
    public function historial(int $id): void {
        Response::success($this->model->historialCitas($id));
    }
}
// =====================================================================
// CONTROLADOR: Usuarios
// =====================================================================
class UsuarioController {
    private UsuarioModel $model;
    public function __construct() { $this->model = new UsuarioModel(); }

    public function indexRecepcionistas(): void {
        Auth::requiereRol(['administrador']);
        Response::success($this->model->todosRecepcionistas());
    }

    public function storeStaff(): void {
        Auth::requiereRol(['administrador']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        
        if (empty($d['username']) || empty($d['password'])) {
            Response::error('El usuario y la contraseña son obligatorios');
        }

        try {
            $d['id_rol'] = 2; // ID fijo para recepcionista
            $id = $this->model->crearStaff($d);
            Response::created(['id_usuario' => $id], 'Recepcionista creada con éxito');
        } catch (Throwable $e) {
            Response::error('Error al crear usuario: ' . $e->getMessage());
        }
    }

    public function destroy(int $id): void {
        Auth::requiereRol(['administrador']);
        $this->model->bajaLogica($id);
        Response::success(null, 200, 'Baja exitosa');
    }
}
// =====================================================================
// CONTROLADOR: Citas
// =====================================================================
class CitaController {
    private CitaModel $model;
    public function __construct() { $this->model = new CitaModel(); }

    public function index(): void {
        $user = Auth::usuario();
        // Doctors always see only their own appointments
        $medico_id = ($user['rol'] === 'Médico' || $user['rol'] === 'medico')
            ? ($user['medico_id'] ?? $_GET['medico_id'] ?? null)
            : ($_GET['medico_id'] ?? null);
        $f = [
            'fecha'     => $_GET['fecha']  ?? null,
            'medico_id' => $medico_id,
            'estado'    => $_GET['estado'] ?? null,
        ];
        Response::success($this->model->todas(array_filter($f)));
    }
    public function show(int $id): void {
        $c = $this->model->porId($id);
        $c ? Response::success($c) : Response::error('Cita no encontrada', 404);
    }
    public function store(): void {
        Auth::requiereRol(['administrador','recepcionista','paciente']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        foreach (['medico_id','paciente_id','fecha','hora'] as $r)
            if (empty($d[$r])) Response::error("Campo requerido: $r");
        try {
            $id = $this->model->crear($d);
            Response::created(['cita_id'=>$id]);
        } catch (Throwable $e) { Response::error($e->getMessage()); }
    }
    public function update(int $id): void {
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        if (!empty($d['estado'])) {
            try {
                $this->model->cambiarEstado($id, $d['estado']);

                // Notificación por correo al paciente si tiene correo registrado
                $cita = $this->model->porId($id);
                if ($cita) {
                    $db = Database::getInstance()->getConnection();
                    $s  = $db->prepare("SELECT correo FROM pacientes WHERE paciente_id=:pid");
                    $s->execute([':pid' => $cita['paciente_id']]);
                    $correo = $s->fetchColumn();
                    if ($correo) {
                        $info = array_merge((array)$cita, ['correo' => $correo, 'cita_id' => $id]);
                        match($d['estado']) {
                            'confirmada'   => Email::citaConfirmada($info),
                            'cancelada'    => Email::citaCancelada($info),
                            'reprogramada' => Email::citaReprogramada($info),
                            default        => null,
                        };
                    }
                }

                Response::success(null, 200, 'Estado actualizado');
            } catch (Throwable $e) { Response::error($e->getMessage()); }
        }
        Response::error('Nada que actualizar');
    }
    public function destroy(int $id): void {
        $this->model->cambiarEstado($id, 'cancelada');
        Response::success(null, 200, 'Cita cancelada');
    }
    public function hoy(): void { Response::success($this->model->hoy()); }
    public function disponibilidad(): void {
        $mid   = (int)($_GET['medico_id'] ?? 0);
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        if (!$mid) Response::error('medico_id requerido');
        Response::success($this->model->disponibilidadMedico($mid, $fecha));
    }
}

// =====================================================================
// CONTROLADOR: Consultas
// =====================================================================
class ConsultaController {
    private ConsultaModel $model;
    public function __construct() { $this->model = new ConsultaModel(); }

    public function index(): void { Response::success($this->model->todas()); }
    public function show(int $id): void {
        $c = $this->model->porId($id);
        $c ? Response::success($c) : Response::error('Consulta no encontrada', 404);
    }
    public function store(): void {
        Auth::requiereRol(['administrador','medico']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        foreach (['medico_id','paciente_id'] as $r)
            if (empty($d[$r])) Response::error("Campo requerido: $r");
        try {
            $id = $this->model->crear($d);
            Response::created(['id_consulta'=>$id]);
        } catch (Throwable $e) { Response::error($e->getMessage()); }
    }
    public function receta(int $id): void {
        $r = $this->model->receta($id);
        Response::success($r ?: []);
    }
}

// =====================================================================
// CONTROLADOR: Medicamentos / Inventario
// =====================================================================
class MedicamentoController {
    private MedicamentoModel $model;
    public function __construct() { $this->model = new MedicamentoModel(); }

    public function index(): void { Response::success($this->model->todos()); }
    public function catalogo(): void { Response::success($this->model->catalogoTodos()); }
    public function store(): void {
        Auth::requiereRol(['administrador','recepcionista']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($d['nombre'])) Response::error('Nombre requerido');
        try {
            $id = $this->model->crear($d);
            Response::created(['id_medicamento'=>$id]);
        } catch (Throwable $e) { Response::error($e->getMessage()); }
    }
    public function update(int $id): void {
        Auth::requiereRol(['administrador','recepcionista']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        $this->model->actualizar($id, $d);
        Response::success(null, 200, 'Actualizado');
    }
    public function entrada(): void {
        Auth::requiereRol(['administrador','recepcionista']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($d['id_inventario']) || empty($d['cantidad'])) Response::error('Campos requeridos');
        try {
            $this->model->entradaStock((int)$d['id_inventario'], (int)$d['cantidad']);
            Response::success(null, 200, 'Stock actualizado');
        } catch (Throwable $e) { Response::error($e->getMessage()); }
    }
    public function caducos(): void { Response::success($this->model->caducos()); }
}

// =====================================================================
// CONTROLADOR: Pagos
// =====================================================================
class PagoController {
    private PagoModel $model;
    public function __construct() { $this->model = new PagoModel(); }

    public function index(): void { Response::success($this->model->todos()); }
    public function show(int $id): void {
        $p = $this->model->porId($id);
        $p ? Response::success($p) : Response::error('Pago no encontrado', 404);
    }
    public function store(): void {
        Auth::requiereRol(['administrador','recepcionista']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($d['monto_total'])) Response::error('monto_total requerido');
        $id = $this->model->crear($d);
        Response::created(['id_pago'=>$id]);
    }
    public function pagar(int $id): void {
        Auth::requiereRol(['administrador','recepcionista']);
        $this->model->pagar($id);
        Response::success(null, 200, 'Pago registrado');
    }
}

// =====================================================================
// CONTROLADOR: Reportes
// =====================================================================
class ReporteController {
    private ReporteModel $model;
    public function __construct() { $this->model = new ReporteModel(); }

    public function ingresos(): void {
        Auth::requiereRol(['administrador']);
        $desde = $_GET['desde'] ?? date('Y-m-01');
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        Response::success($this->model->ingresos($desde, $hasta));
    }
    public function pacientesPorGenero(): void { Response::success($this->model->pacientesPorGenero()); }
    public function consultasPorPeriodo(): void {
        $desde = $_GET['desde'] ?? date('Y-m-01');
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        Response::success($this->model->consultasPorPeriodo($desde, $hasta));
    }
    public function medicosPorEspecialidad(): void { Response::success($this->model->medicosPorEspecialidad()); }
    public function bitacora(): void {
        Auth::requiereRol(['administrador']);
        $limit = (int)($_GET['limit'] ?? 100);
        Response::success($this->model->bitacora($limit));
    }

    public function enfermedadesPorPeriodo(): void {
        $desde = $_GET['desde'] ?? date('Y-m-01');
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        Response::success($this->model->enfermedadesPorPeriodo($desde, $hasta));
    }

    public function inventarioCompleto(): void {
        Response::success($this->model->inventarioCompleto());
    }

    public function serviciosPorPeriodo(): void {
        $desde = $_GET['desde'] ?? date('Y-m-01');
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        Response::success((new ServicioModel())->porPeriodo($desde, $hasta));
    }

    public function notificaciones(): void {
        Auth::requiereRol(['administrador']);
        $limit = (int)($_GET['limit'] ?? 50);
        $s = Database::getInstance()->getConnection()->prepare(
            "SELECT * FROM notificaciones ORDER BY fecha_creacion DESC LIMIT :l"
        );
        $s->bindValue(':l', $limit, PDO::PARAM_INT);
        $s->execute();
        Response::success($s->fetchAll());
    }
}

// =====================================================================
// CONTROLADOR: Cirugías
// =====================================================================
class CirugiaController {
    private CirugiaModel $model;
    public function __construct() { $this->model = new CirugiaModel(); }

    public function index(): void {
        $filtros = array_filter([
            'paciente_id' => $_GET['paciente_id'] ?? null,
            'estado'      => $_GET['estado'] ?? null,
        ]);
        Response::success($this->model->todas($filtros));
    }

    public function porPaciente(int $paciente_id): void {
        Response::success($this->model->porPaciente($paciente_id));
    }

    public function store(): void {
        Auth::requiereRol(['administrador','recepcionista','medico']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        foreach (['paciente_id','medico_id','fecha','tipo_cirugia'] as $r)
            if (empty($d[$r])) Response::error("Campo requerido: $r");
        try {
            $id = $this->model->crear($d);
            Response::created(['id_cirugia' => $id]);
        } catch (Throwable $e) { Response::error($e->getMessage()); }
    }

    public function update(int $id): void {
        Auth::requiereRol(['administrador','recepcionista','medico']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        $this->model->actualizar($id, $d);
        Response::success(null, 200, 'Cirugía actualizada');
    }
}

// =====================================================================
// CONTROLADOR: Servicios Adicionales
// =====================================================================
class ServicioController {
    private ServicioModel $model;
    public function __construct() { $this->model = new ServicioModel(); }

    public function index(): void   { Response::success($this->model->todos()); }
    public function catalogo(): void { Response::success($this->model->catalogo()); }

    public function store(): void {
        Auth::requiereRol(['administrador']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($d['nombre'])) Response::error('Nombre requerido');
        $id = $this->model->crear($d);
        Response::created(['id_servicio' => $id]);
    }

    public function update(int $id): void {
        Auth::requiereRol(['administrador']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        $this->model->actualizar($id, $d);
        Response::success(null, 200, 'Servicio actualizado');
    }

    public function porConsulta(int $id): void {
        Response::success($this->model->porConsulta($id));
    }
}

// =====================================================================
// CONTROLADOR: Catálogos
// =====================================================================
class CatalogoController {
    private CatalogoModel $model;
    public function __construct() { $this->model = new CatalogoModel(); }

    public function especialidades(): void { Response::success($this->model->especialidades()); }
    public function roles(): void          { Response::success($this->model->roles()); }
    public function tipoSangre(): void     { Response::success($this->model->tipoSangre()); }
    public function consultorios(): void   { Response::success($this->model->consultorios()); }
    public function tipoConsulta(): void   { Response::success($this->model->tipoConsulta()); }

    public function crearEspecialidad(): void {
        Auth::requiereRol(['administrador']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($d['especialidad'])) Response::error('Nombre requerido');
        $id = $this->model->crearEspecialidad($d['especialidad']);
        Response::created(['especialidad_id'=>$id]);
    }
    public function crearConsultorio(): void {
        Auth::requiereRol(['administrador']);
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        foreach (['numero','piso'] as $r) if (empty($d[$r])) Response::error("$r requerido");
        $id = $this->model->crearConsultorio($d);
        Response::created(['id_consultorio'=>$id]);
    }
}
