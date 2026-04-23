<?php
class Log {
    public static function registrar(
        string $accion,
        string $tabla,
        $registro_id = null,
        string $descripcion = ''
    ): void {
        try {
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO bitacora(accion, tabla_afectada, registro_id, usuario_id, ip, descripcion, fecha_hora)
                VALUES (:accion, :tabla, :rid, :uid, :ip, :desc, NOW())
            ");
            $stmt->execute([
                ':accion' => strtoupper($accion),
                ':tabla'  => $tabla,
                ':rid'    => $registro_id,
                ':uid'    => $_SESSION['id_usuario'] ?? null,
                ':ip'     => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                ':desc'   => $descripcion,
            ]);
        } catch (Throwable $e) {
            // Log no debe interrumpir el flujo principal
            error_log('[LOG ERROR] ' . $e->getMessage());
        }
    }
}
