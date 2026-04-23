<?php
// Vamos a generar el hash para la contraseña "admin123"
echo password_hash('admin123', PASSWORD_BCRYPT);
?>