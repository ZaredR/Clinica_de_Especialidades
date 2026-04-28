<?php
declare(strict_types=1);

// ── Sesión ────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

// ── Cabeceras CORS ────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Autoload ──────────────────────────────────────────────────────────
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Log.php';
require_once __DIR__ . '/helpers/Email.php';
require_once __DIR__ . '/middlewares/Auth.php';
require_once __DIR__ . '/models/Models.php';
require_once __DIR__ . '/controllers/Controllers.php';

// ── Parsear ruta ──────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Eliminar el prefijo del proyecto de forma dinámica
$base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if ($base === '/') $base = '';

$uri    = str_starts_with($uri, $base) ? substr($uri, strlen($base)) : $uri;
$uri    = trim($uri, '/');
$parts  = $uri !== '' ? explode('/', $uri) : [];

$resource = $parts[0] ?? '';
$id       = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : null;
$action   = $parts[1] ?? null;    // para sub-rutas no numéricas

// ── Manejador de errores global ───────────────────────────────────────
set_exception_handler(function(Throwable $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
});

// ════════════════════════════════════════════════════════════════════
// RUTAS
// ════════════════════════════════════════════════════════════════════

// ── AUTH ─────────────────────────────────────────────────────────────
if ($resource === 'auth') {
    $ctrl = new AuthController();
    match(true) {
        $method === 'POST' && $action === 'login'              => $ctrl->login(),
        $method === 'POST' && $action === 'logout'             => $ctrl->logout(),
        $method === 'GET'  && $action === 'me'                 => $ctrl->me(),
        $method === 'POST' && $action === 'cambiar-password'   => $ctrl->cambiarPassword(),
        $method === 'POST' && $action === 'recuperar-password' => $ctrl->recuperarPassword(),
        $method === 'POST' && $action === 'reset-password'     => $ctrl->resetPassword(),
        default => Response::error('Ruta auth no encontrada', 404),
    };
    exit;
}

// ── USUARIOS / STAFF ──────────────────────────────────────────────────
if ($resource === 'usuarios') {
    Auth::verificar();
    $ctrl = new UsuarioController();
    match(true) {
        $method === 'GET'    && $action === 'recepcionistas' => $ctrl->indexRecepcionistas(),
        $method === 'POST'   && $action === 'staff'          => $ctrl->storeStaff(),
        $method === 'DELETE' && $id !== null                 => $ctrl->destroy($id),
        default => Response::error('Ruta de usuarios no encontrada', 404),
    };
    exit;
}

// ── MÉDICOS ───────────────────────────────────────────────────────────
if ($resource === 'medicos') {
    Auth::verificar();
    $ctrl = new MedicoController();
    match(true) {
        $method === 'GET'    && $action === 'buscar'  => $ctrl->buscar(),
        $method === 'GET'    && $id !== null          => $ctrl->show($id),
        $method === 'GET'                             => $ctrl->index(),
        $method === 'POST'                            => $ctrl->store(),
        $method === 'PUT'    && $id !== null          => $ctrl->update($id),
        $method === 'DELETE' && $id !== null          => $ctrl->destroy($id),
        default => Response::error('Ruta no encontrada', 404),
    };
    exit;
}

// ── PACIENTES ─────────────────────────────────────────────────────────
if ($resource === 'pacientes') {
    Auth::verificar();
    $ctrl      = new PacienteController();
    $subaction = $parts[2] ?? null;
    match(true) {
        $method === 'GET'    && $action === 'buscar'                     => $ctrl->buscar(),
        $method === 'GET'    && $id !== null && $subaction==='historial' => $ctrl->historial($id),
        $method === 'GET'    && $id !== null && $subaction==='cirugias'  => (new CirugiaController())->porPaciente($id),
        $method === 'GET'    && $id !== null                             => $ctrl->show($id),
        $method === 'GET'                                                => $ctrl->index(),
        $method === 'POST'                                               => $ctrl->store(),
        $method === 'PUT'    && $id !== null                             => $ctrl->update($id),
        $method === 'DELETE' && $id !== null                             => $ctrl->destroy($id),
        default => Response::error('Ruta no encontrada', 404),
    };
    exit;
}

// ── CITAS ─────────────────────────────────────────────────────────────
if ($resource === 'citas') {
    Auth::verificar();
    $ctrl = new CitaController();
    match(true) {
        $method === 'GET'    && $action === 'hoy'            => $ctrl->hoy(),
        $method === 'GET'    && $action === 'disponibilidad' => $ctrl->disponibilidad(),
        $method === 'GET'    && $id !== null                  => $ctrl->show($id),
        $method === 'GET'                                     => $ctrl->index(),
        $method === 'POST'                                    => $ctrl->store(),
        $method === 'PUT'    && $id !== null                  => $ctrl->update($id),
        $method === 'DELETE' && $id !== null                  => $ctrl->destroy($id),
        default => Response::error('Ruta no encontrada', 404),
    };
    exit;
}

// ── CONSULTAS ─────────────────────────────────────────────────────────
if ($resource === 'consultas') {
    Auth::verificar();
    $ctrl      = new ConsultaController();
    $subaction = $parts[2] ?? null;
    match(true) {
        $method === 'GET'  && $id !== null && $subaction==='receta'    => $ctrl->receta($id),
        $method === 'GET'  && $id !== null && $subaction==='servicios' => (new ServicioController())->porConsulta($id),
        $method === 'GET'  && $id !== null                             => $ctrl->show($id),
        $method === 'GET'                                              => $ctrl->index(),
        $method === 'POST'                                             => $ctrl->store(),
        default => Response::error('Ruta no encontrada', 404),
    };
    exit;
}

// ── MEDICAMENTOS / INVENTARIO ─────────────────────────────────────────
if ($resource === 'medicamentos') {
    Auth::verificar();
    $ctrl = new MedicamentoController();
    match(true) {
        $method === 'GET'  && $action === 'catalogo'  => $ctrl->catalogo(),
        $method === 'GET'  && $action === 'caducos'   => $ctrl->caducos(),
        $method === 'POST' && $action === 'entrada'   => $ctrl->entrada(),
        $method === 'GET'                             => $ctrl->index(),
        $method === 'POST'                            => $ctrl->store(),
        $method === 'PUT'  && $id !== null            => $ctrl->update($id),
        default => Response::error('Ruta no encontrada', 404),
    };
    exit;
}

// ── PAGOS ─────────────────────────────────────────────────────────────
if ($resource === 'pagos') {
    Auth::verificar();
    $ctrl = new PagoController();
    match(true) {
        $method === 'PUT'  && $id !== null && ($parts[2]??'')==='pagar' => $ctrl->pagar($id),
        $method === 'GET'  && $id !== null => $ctrl->show($id),
        $method === 'GET'                  => $ctrl->index(),
        $method === 'POST'                 => $ctrl->store(),
        default => Response::error('Ruta no encontrada', 404),
    };
    exit;
}

// ── CIRUGÍAS ──────────────────────────────────────────────────────────
if ($resource === 'cirugias') {
    Auth::verificar();
    $ctrl = new CirugiaController();
    match(true) {
        $method === 'GET'  => $ctrl->index(),
        $method === 'POST' => $ctrl->store(),
        $method === 'PUT'  && $id !== null => $ctrl->update($id),
        default => Response::error('Ruta no encontrada', 404),
    };
    exit;
}

// ── SERVICIOS ADICIONALES ─────────────────────────────────────────────
if ($resource === 'servicios') {
    Auth::verificar();
    $ctrl = new ServicioController();
    match(true) {
        $method === 'GET'  && $action === 'catalogo' => $ctrl->catalogo(),
        $method === 'GET'  => $ctrl->index(),
        $method === 'POST' => $ctrl->store(),
        $method === 'PUT'  && $id !== null => $ctrl->update($id),
        default => Response::error('Ruta no encontrada', 404),
    };
    exit;
}

// ── REPORTES ──────────────────────────────────────────────────────────
if ($resource === 'reportes') {
    Auth::verificar();
    $ctrl = new ReporteController();
    match($action) {
        'ingresos'               => $ctrl->ingresos(),
        'pacientes-genero'       => $ctrl->pacientesPorGenero(),
        'consultas-periodo'      => $ctrl->consultasPorPeriodo(),
        'medicos-especialidad'   => $ctrl->medicosPorEspecialidad(),
        'bitacora'               => $ctrl->bitacora(),
        'enfermedades-periodo'   => $ctrl->enfermedadesPorPeriodo(),
        'inventario-completo'    => $ctrl->inventarioCompleto(),
        'servicios-periodo'      => $ctrl->serviciosPorPeriodo(),
        'notificaciones'         => $ctrl->notificaciones(),
        default => Response::error('Reporte no encontrado', 404),
    };
    exit;
}

// ── CATÁLOGOS ─────────────────────────────────────────────────────────
if ($resource === 'catalogos') {
    Auth::verificar();
    $ctrl = new CatalogoController();
    match(true) {
        $method === 'GET'  && $action === 'especialidades' => $ctrl->especialidades(),
        $method === 'POST' && $action === 'especialidades' => $ctrl->crearEspecialidad(),
        $method === 'GET'  && $action === 'roles'          => $ctrl->roles(),
        $method === 'GET'  && $action === 'tipo-sangre'    => $ctrl->tipoSangre(),
        $method === 'GET'  && $action === 'consultorios'   => $ctrl->consultorios(),
        $method === 'POST' && $action === 'consultorios'   => $ctrl->crearConsultorio(),
        $method === 'GET'  && $action === 'tipo-consulta'  => $ctrl->tipoConsulta(),
        default => Response::error('Catálogo no encontrado', 404),
    };
    exit;
}

// ── Ruta raíz: info de la API ─────────────────────────────────────────
if ($resource === '') {
    Response::success([
        'sistema'  => 'Clínica de Especialidades',
        'version'  => '1.0.0',
        'endpoints'=> [
            'auth'         => ['POST /auth/login','POST /auth/logout','GET /auth/me','POST /auth/cambiar-password'],
            'usuarios'     => ['GET /usuarios/recepcionistas', 'POST /usuarios/staff', 'DELETE /usuarios/{id}'],
            'medicos'      => ['GET','GET /{id}','POST','PUT /{id}','DELETE /{id}','GET /buscar?q='],
            'pacientes'    => ['GET','GET /{id}','POST','PUT /{id}','DELETE /{id}','GET /buscar?q=','GET /{id}/historial'],
            'citas'        => ['GET','GET /{id}','POST','PUT /{id}','DELETE /{id}','GET /hoy','GET /disponibilidad?medico_id=&fecha='],
            'consultas'    => ['GET','GET /{id}','POST','GET /{id}/receta'],
            'medicamentos' => ['GET','GET /catalogo','GET /caducos','POST','PUT /{id}','POST /entrada'],
            'pagos'        => ['GET','GET /{id}','POST','PUT /{id}/pagar'],
            'reportes'     => ['ingresos','pacientes-genero','consultas-periodo','medicos-especialidad','bitacora'],
            'catalogos'    => ['especialidades','roles','tipo-sangre','consultorios','tipo-consulta'],
        ],
    ]);
    exit;
}

Response::error('Recurso no encontrado', 404);