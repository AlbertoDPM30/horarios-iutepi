<?php

/**
 * =====================================================================
 *  Generador de datos de demostracion  (solo linea de comandos)
 * ---------------------------------------------------------------------
 *  Corre el motor de horarios sobre los periodos indicados y arma el
 *  horario de los estudiantes inscritos. Se usa para dejar la base de
 *  prueba con datos realistas sin tener que hacerlo a mano.
 *
 *  Uso:
 *      php scripts/generar-demo.php                # todos los periodos
 *      php scripts/generar-demo.php 3 4            # solo esos periodos
 *      php scripts/generar-demo.php --sin-alumnos  # omite los horarios de alumnos
 * =====================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/Core/App.php';

use App\Core\Env;
use App\Core\Modelo;
use App\Services\GeneradorHorarios;
use App\Services\HorarioEstudianteService;

// Arranque manual: el autoload y el .env sin despachar ninguna ruta
(static function (): void {
    $raiz = dirname(__DIR__);
    spl_autoload_register(static function (string $clase) use ($raiz): void {
        if (!str_starts_with($clase, 'App\\')) {
            return;
        }
        $archivo = $raiz . '/app/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
        if (is_file($archivo)) {
            require_once $archivo;
        }
    });
    Env::cargar($raiz . '/.env');
    date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'America/Caracas'));
})();

$argumentos   = array_slice($argv, 1);
$conAlumnos   = !in_array('--sin-alumnos', $argumentos, true);
$idsPedidos   = array_values(array_filter($argumentos, 'ctype_digit'));

$periodos = $idsPedidos
    ? Modelo::filas(
        'SELECT * FROM `periodos` WHERE `periodo_id` IN (' . implode(',', array_map('intval', $idsPedidos)) . ')'
    )
    : Modelo::filas('SELECT * FROM `periodos` WHERE `estado` <> "FINALIZADO" ORDER BY `periodo_id`');

if (!$periodos) {
    fwrite(STDERR, "No hay periodos que procesar.\n");
    exit(1);
}

echo str_repeat('=', 70), "\n";
echo "  Generacion de horarios de demostracion\n";
echo str_repeat('=', 70), "\n\n";

foreach ($periodos as $periodo) {
    $id     = (int) $periodo['periodo_id'];
    $estado = $periodo['estado'];

    printf("[%s] %s  (%s)\n", $periodo['codigo'], $periodo['nombre'], $estado);

    // El generador solo trabaja sobre periodos en planificacion; para
    // sembrar datos de demo se baja el estado y se restaura al terminar.
    if ($estado !== 'PLANIFICACION') {
        Modelo::ejecutar('UPDATE `periodos` SET `estado` = "PLANIFICACION" WHERE `periodo_id` = ?', [$id]);
    }

    try {
        $resultado = (new GeneradorHorarios($id))->generar(['limpiar' => true, 'reasignar_docentes' => true]);
        $r = $resultado['resumen'];

        printf(
            "   secciones %d · materias %d · sesiones ubicadas %d · sin ubicar %d · sin docente %d · %ss\n",
            $r['secciones'], $r['asignaciones'], $r['ubicadas'], $r['sin_ubicar'], $r['sin_docente'], $r['segundos']
        );

        foreach ($resultado['conflictos'] as $c) {
            printf("   ! %-14s %s\n", $c['tipo'], $c['titulo']);
        }
    } catch (\Throwable $e) {
        printf("   ERROR: %s\n", $e->getMessage());
    } finally {
        if ($estado !== 'PLANIFICACION') {
            Modelo::ejecutar('UPDATE `periodos` SET `estado` = ? WHERE `periodo_id` = ?', [$estado, $id]);
        }
    }

    /* ---- Horarios de los estudiantes inscritos ---- */
    if ($conAlumnos) {
        $inscritos = Modelo::filas(
            'SELECT `estudiante_id` FROM `estudiante_inscripciones`
              WHERE `periodo_id` = ? AND `estado` = "INSCRITO"',
            [$id]
        );

        $ok = 0;
        $virtuales = 0;

        foreach ($inscritos as $fila) {
            try {
                $res = HorarioEstudianteService::generarAutomatico((int) $fila['estudiante_id'], $id);
                $ok++;
                $virtuales += $res['virtuales'];
            } catch (\Throwable $e) {
                // Un alumno sin oferta valida no detiene al resto
            }
        }

        printf("   horarios de alumnos: %d generados (%d materias en virtual)\n", $ok, $virtuales);

        if ($estado === 'EN_CURSO') {
            Modelo::ejecutar(
                'UPDATE `estudiante_inscripciones`
                    SET `horario_confirmado` = 1, `confirmado_en` = NOW()
                  WHERE `periodo_id` = ?',
                [$id]
            );
        }
    }

    echo "\n";
}

echo "Listo.\n";
