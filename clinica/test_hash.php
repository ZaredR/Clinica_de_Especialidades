<?php
// test_hash.php

$pacientes = [
    'robertoc' => '19820315',
    'valeriam' => '19950722',
    'jorgee'   => '19781105',
    'sofian'   => '20010914',
    'armandov' => '19650130'
];

echo "<h3>Hashes generados. Copia y pega esto en tu consola SQL:</h3>";
echo "<pre>";

foreach ($pacientes as $user => $pass) {
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    echo "UPDATE usuarios SET contrasena = '$hash' WHERE username = '$user';<br>";
}

echo "</pre>";
?>