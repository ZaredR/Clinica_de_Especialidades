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

    public function index(): void { Response::success($this->model->todos()); }
    public function show(int $id): void {
        $p = $this->model->porId($id);
        $p ? Response::success($p) : Response::error('Paciente no encontrado', 404);
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
// CONTROLADOR: Citas
// =====================================================================
class CitaController {
    private CitaModel $model;
    public function __construct() { $this->model = new CitaModel(); }

    public function index(): void {
        $f = ['fecha'=>$_GET['fecha']??null,'medico_id'=>$_GET['medico_id']??null,'estado'=>$_GET['estado']??null];
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
