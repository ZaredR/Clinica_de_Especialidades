<?php
class Email {
    public static function enviar(
        string $tipo,
        string $destinatario,
        string $asunto,
        string $cuerpo,
        ?int $referencia_id = null
    ): bool {
        try {
            $db = Database::getInstance()->getConnection();
            $s  = $db->prepare("
                INSERT INTO notificaciones(tipo, destinatario, asunto, cuerpo, estado, referencia_id)
                VALUES(:tipo, :dest, :asunto, :cuerpo, 'pendiente', :ref)
            ");
            $s->execute([
                ':tipo'   => $tipo,
                ':dest'   => $destinatario,
                ':asunto' => $asunto,
                ':cuerpo' => $cuerpo,
                ':ref'    => $referencia_id,
            ]);
            $id = (int)$db->lastInsertId();

            // Intentar envío real con mail()
            $enviado = false;
            if (filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
                $headers  = "From: clinica@clinicaespecialidades.mx\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8";
                $enviado  = @mail($destinatario, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, $headers);
            }

            $db->prepare("UPDATE notificaciones SET estado=:est, fecha_envio=NOW() WHERE id_notificacion=:id")
               ->execute([':est' => $enviado ? 'enviado' : 'pendiente', ':id' => $id]);

            return true;
        } catch (Throwable $e) {
            error_log('[EMAIL ERROR] ' . $e->getMessage());
            return false;
        }
    }

    public static function citaConfirmada(array $cita): void {
        if (empty($cita['correo'])) return;
        self::enviar(
            'confirmacion_cita',
            $cita['correo'],
            'Confirmación de cita — Clínica de Especialidades',
            "Estimado(a) {$cita['paciente']},\n\n" .
            "Su cita ha sido CONFIRMADA.\n\n" .
            "Fecha: {$cita['fecha']}\nHora: {$cita['hora']}\n" .
            "Médico: {$cita['medico']}\nEspecialidad: {$cita['especialidad']}\n\n" .
            "Clínica de Especialidades",
            $cita['cita_id']
        );
    }

    public static function citaCancelada(array $cita): void {
        if (empty($cita['correo'])) return;
        self::enviar(
            'cancelacion_cita',
            $cita['correo'],
            'Cancelación de cita — Clínica de Especialidades',
            "Estimado(a) {$cita['paciente']},\n\n" .
            "Su cita del {$cita['fecha']} a las {$cita['hora']} con el Dr. {$cita['medico']} ha sido CANCELADA.\n\n" .
            "Contáctenos para reagendarla.\n\nClínica de Especialidades",
            $cita['cita_id']
        );
    }

    public static function citaReprogramada(array $cita): void {
        if (empty($cita['correo'])) return;
        self::enviar(
            'recordatorio_cita',
            $cita['correo'],
            'Reprogramación de cita — Clínica de Especialidades',
            "Estimado(a) {$cita['paciente']},\n\n" .
            "Su cita ha sido REPROGRAMADA.\n\n" .
            "Nueva Fecha: {$cita['fecha']}\nNueva Hora: {$cita['hora']}\n" .
            "Médico: {$cita['medico']}\n\nClínica de Especialidades",
            $cita['cita_id']
        );
    }

    public static function resetPassword(string $correo, string $username, string $token): void {
        self::enviar(
            'reset_password',
            $correo,
            'Recuperación de contraseña — Clínica de Especialidades',
            "Hola {$username},\n\n" .
            "Se ha solicitado recuperar su contraseña.\n\n" .
            "Token de verificación: {$token}\n\n" .
            "Ingrese este código en el formulario de recuperación. Es válido por 1 hora.\n\n" .
            "Si no solicitó este cambio, ignore este correo.\n\nClínica de Especialidades"
        );
    }
}
