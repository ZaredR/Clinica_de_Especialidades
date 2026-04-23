<?php
class Auth {
    /** Verifica que el usuario tenga sesión activa */
    public static function verificar(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['id_usuario'])) {
            Response::error('No autenticado', 401);
        }
    }

    /** Verifica que el usuario tenga uno de los roles permitidos */
    public static function requiereRol(array $roles): void {
        self::verificar();
        if (!in_array($_SESSION['rol'], $roles, true)) {
            Response::error('Sin permisos para esta acción', 403);
        }
    }

    /** Retorna los datos del usuario actual */
    public static function usuario(): array {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return [
            'id_usuario' => $_SESSION['id_usuario'] ?? null,
            'username'   => $_SESSION['username']   ?? null,
            'rol'        => $_SESSION['rol']        ?? null,
            'rol_id'     => $_SESSION['rol_id']     ?? null,
        ];
    }

    /** Inicia sesión: verifica credenciales y crea la sesión */
    public static function login(string $username, string $password): array {
        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT u.id_usuario, u.username, u.contrasena, r.descripcion AS rol, r.id_rol
            FROM usuarios u
            JOIN roles r ON u.id_rol = r.id_rol
            WHERE u.username = :username AND u.activo = 1
        ");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['contrasena'])) {
            Response::error('Credenciales inválidas', 401);
        }

        if (session_status() === PHP_SESSION_NONE) session_start();
        session_regenerate_id(true);

        $_SESSION['id_usuario'] = $user['id_usuario'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['rol']        = $user['rol'];
        $_SESSION['rol_id']     = $user['id_rol'];

        Log::registrar('LOGIN', 'usuarios', $user['id_usuario'], 'Inicio de sesión');

        return [
            'id_usuario' => $user['id_usuario'],
            'username'   => $user['username'],
            'rol'        => $user['rol'],
        ];
    }

    /** Cierra la sesión */
    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $uid = $_SESSION['id_usuario'] ?? null;
        Log::registrar('LOGOUT', 'usuarios', $uid, 'Cierre de sesión');
        session_destroy();
    }
}
