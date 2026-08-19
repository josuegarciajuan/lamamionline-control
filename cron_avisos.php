<?php
// Lock de instancia única: si ya hay un cron_avisos en marcha, salir sin hacer nada.
// Evita que se apilen ejecuciones cuando una pasada tarda más de 1 minuto (el cron
// corre cada minuto y avisos_run_all_generators es pesado: ~40 generadores + JSON).
$lockFile = __DIR__ . '/data/cron_avisos.lock';
$lock = @fopen($lockFile, 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    if ($lock !== false) {
        fclose($lock);
    }
    echo "SKIP: cron_avisos ya en marcha\n";
    exit(0);
}

require_once __DIR__ . '/app/bootstrap.php';

avisos_run_all_generators(true);
echo "OK\n";

flock($lock, LOCK_UN);
fclose($lock);
