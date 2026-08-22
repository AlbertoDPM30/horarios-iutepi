<?php

namespace App\Services;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\Env;
use App\Core\Log;
use App\Core\Modelo;
use App\Models\Asignacion;
use App\Models\Catalogo;
use App\Models\Conflicto;
use App\Models\Materia;
use App\Models\Periodo;

/**
 * =====================================================================
 *  Generador automatico de horarios
 * ---------------------------------------------------------------------
 *  Reemplaza el trabajo manual de la coordinacion: recibe un periodo y
 *  produce la parrilla completa (que materia, con que docente, en que
 *  bloque y en que salon o laboratorio).
 *
 *  El problema es un timetabling clasico. La estrategia es:
 *
 *    1. Construir la oferta: por cada seccion, las materias de su plan
 *       repartidas entre los dos modulos del periodo.
 *    2. Asignar docente por afinidad de habilidades (skills vs. perfil
 *       exigido por la materia) penalizando la carga acumulada.
 *    3. Explotar cada materia en "sesiones" (N bloques consecutivos).
 *    4. Colocar las sesiones con una pasada voraz ordenada por
 *       restriccion (MRV: primero la que menos huecos validos tiene).
 *    5. Reparar lo que quedo fuera con busqueda local de min-conflictos:
 *       se desaloja a lo sumo un par de sesiones y se reubican.
 *    6. Lo que no entra se reporta como conflicto para que el
 *       administrador decida (no se inventa una solucion invalida).
 *
 *  Restricciones duras (nunca se violan):
 *    - Un docente no puede estar en dos aulas a la vez.
 *    - Un espacio no puede tener dos clases a la vez.
 *    - Una seccion no puede ver dos materias a la vez (salvo electivas
 *      del mismo grupo, que van en paralelo a proposito).
 *    - El docente solo da clase dentro de la disponibilidad que declaro.
 *    - Una sesion no cruza el receso.
 *    - Una materia no se repite dos veces el mismo dia.
 *    - Las materias de laboratorio van en laboratorio.
 * =====================================================================
 */
final class GeneradorHorarios
{
    private array $periodo;
    private array $bloques = [];          // bloque_id => ['orden','hora_inicio','hora_fin']
    private array $segmentos = [];        // tramos continuos sin receso, como listas de bloque_id
    private array $dias = [];
    private array $espacios = ['SALON' => [], 'LABORATORIO' => []];

    private array $sesiones = [];         // sesiones a colocar en el modulo actual
    private array $ocupSeccion = [];
    private array $ocupProfesor = [];
    private array $ocupEspacio = [];
    private array $diaUsadoPorAsignacion = [];
    private array $dispProfesor = [];     // profesor_id => dia => [[iniMin,finMin], ...]
    private array $cargaProfesor = [];
    private array $topeProfesor = [];
    private array $slotPreferido = [];    // continuidad entre modulo 1 y 2
    private array $candidatosPorMateria = [];

    private array $resumen = [
        'secciones' => 0, 'asignaciones' => 0, 'ubicadas' => 0,
        'sin_ubicar' => 0, 'sin_docente' => 0, 'conflictos' => 0, 'segundos' => 0.0,
    ];
    private array $conflictosDetectados = [];
    private float $inicio = 0.0;

    public function __construct(int $periodoId)
    {
        $periodo = Periodo::buscar($periodoId);
        if (!$periodo) {
            throw ApiException::noEncontrado('Periodo');
        }
        $this->periodo = $periodo;
    }

    /**
     * @param array{limpiar?:bool, reasignar_docentes?:bool, modulo?:int|null, seccion_id?:int|null} $opciones
     */
    public function generar(array $opciones = []): array
    {
        $this->inicio = microtime(true);

        $limpiar    = $opciones['limpiar'] ?? true;
        $reasignar  = $opciones['reasignar_docentes'] ?? true;
        $soloModulo = $opciones['modulo'] ?? null;
        $seccionId  = $opciones['seccion_id'] ?? null;

        $this->cargarCatalogos();

        $secciones = Periodo::secciones((int) $this->periodo['periodo_id']);
        if ($seccionId) {
            $secciones = array_values(array_filter($secciones, static fn ($s) => (int) $s['seccion_id'] === $seccionId));
        }
        if (!$secciones) {
            throw new ApiException(
                'El periodo no tiene secciones cargadas. Crea al menos una seccion antes de generar horarios.',
                422,
                'SIN_SECCIONES'
            );
        }
        $this->resumen['secciones'] = count($secciones);

        $modulos = array_map(
            static fn ($m) => (int) $m['numero'],
            Periodo::modulos((int) $this->periodo['periodo_id'])
        ) ?: [1];

        if ($soloModulo !== null) {
            $modulos = array_values(array_filter($modulos, static fn ($m) => $m === (int) $soloModulo));
        }

        Database::transaccion(function () use ($secciones, $modulos, $limpiar, $reasignar, $seccionId): void {
            if ($limpiar) {
                $this->limpiar($seccionId, $modulos);
            }

            $oferta = $this->construirOferta($secciones, $modulos);
            $this->asignarDocentes($oferta, $reasignar);

            foreach ($modulos as $modulo) {
                $this->resolverModulo($modulo, array_values(array_filter(
                    $oferta,
                    static fn ($a) => (int) $a['modulo'] === $modulo
                )));
            }

            Modelo::ejecutar(
                'UPDATE `periodos` SET `horarios_generados` = 1 WHERE `periodo_id` = ?',
                [(int) $this->periodo['periodo_id']]
            );
        });

        $this->registrarConflictos();

        $this->resumen['segundos'] = round(microtime(true) - $this->inicio, 2);
        $this->resumen['conflictos'] = count($this->conflictosDetectados);

        NotificacionService::resumenGeneracion(
            (int) $this->periodo['periodo_id'],
            $this->periodo['codigo'],
            $this->resumen
        );

        return [
            'resumen'    => $this->resumen,
            'conflictos' => $this->conflictosDetectados,
        ];
    }

    /* =================================================================
       1. CATALOGOS
       ================================================================= */

    private function cargarCatalogos(): void
    {
        $modalidad = $this->periodo['modalidad'];
        $this->dias = Catalogo::dias($modalidad);

        $todos = Catalogo::bloques($modalidad);
        $segmento = [];

        foreach ($todos as $b) {
            $id = (int) $b['bloque_id'];
            $this->bloques[$id] = [
                'orden'       => (int) $b['orden'],
                'hora_inicio' => $b['hora_inicio'],
                'hora_fin'    => $b['hora_fin'],
                'ini_min'     => self::aMinutos($b['hora_inicio']),
                'fin_min'     => self::aMinutos($b['hora_fin']),
                'etiqueta'    => $b['etiqueta'],
            ];

            // El receso corta el tramo: ninguna clase lo atraviesa
            if ((int) $b['es_receso'] === 1) {
                if ($segmento) {
                    $this->segmentos[] = $segmento;
                    $segmento = [];
                }
                continue;
            }
            $segmento[] = $id;
        }
        if ($segmento) {
            $this->segmentos[] = $segmento;
        }

        foreach (['SALON', 'LABORATORIO'] as $tipo) {
            $this->espacios[$tipo] = Modelo::filas(
                'SELECT `espacio_id`,`codigo`,`capacidad` FROM `espacios`
                  WHERE `tipo` = ? AND `activo` = 1 ORDER BY `capacidad`, `codigo`',
                [$tipo]
            );
        }

        if (!$this->espacios['SALON']) {
            throw new ApiException('No hay salones activos registrados.', 422, 'SIN_SALONES');
        }
    }

    private function limpiar(?int $seccionId, array $modulos): void
    {
        $periodoId = (int) $this->periodo['periodo_id'];
        $marcas = implode(',', array_fill(0, count($modulos), '?'));

        if ($seccionId) {
            Modelo::ejecutar(
                "DELETE FROM `horario_bloques`
                  WHERE `periodo_id` = ? AND `seccion_id` = ? AND `modulo` IN ({$marcas})",
                array_merge([$periodoId, $seccionId], $modulos)
            );
        } else {
            Modelo::ejecutar(
                "DELETE FROM `horario_bloques` WHERE `periodo_id` = ? AND `modulo` IN ({$marcas})",
                array_merge([$periodoId], $modulos)
            );
        }

        // El estado de las asignaciones se recalcula en esta corrida:
        // sin esto quedarian marcas SIN_HORARIO de generaciones previas.
        Modelo::ejecutar(
            "UPDATE `asignaciones` SET `estado` = 'BORRADOR'
              WHERE `periodo_id` = ? AND `modulo` IN ({$marcas})" . ($seccionId ? ' AND `seccion_id` = ?' : ''),
            array_merge([$periodoId], $modulos, $seccionId ? [$seccionId] : [])
        );

        Conflicto::limpiarDePeriodo($periodoId);
    }

    /* =================================================================
       2. OFERTA: seccion x materia x modulo
       ================================================================= */

    private function construirOferta(array $secciones, array $modulos): array
    {
        $periodoId = (int) $this->periodo['periodo_id'];
        $oferta = [];

        foreach ($secciones as $seccion) {
            $materias = Materia::delPlan((int) $seccion['carrera_id'], (int) $seccion['semestre']);
            $reparto  = $this->distribuirPorModulo($materias, $modulos);

            foreach ($materias as $materia) {
                foreach ($reparto[(int) $materia['materia_id']] ?? [] as $modulo) {
                    $asignacionId = $this->asegurarAsignacion($periodoId, $modulo, $seccion, $materia);

                    $perfil = $this->perfilCarga($materia);

                    $oferta[] = [
                        'asignacion_id'  => $asignacionId,
                        'modulo'         => $modulo,
                        'seccion_id'     => (int) $seccion['seccion_id'],
                        'seccion'        => $seccion['codigo'],
                        'cupo'           => (int) $seccion['cupo'],
                        'espacio_base'   => $seccion['espacio_id'] !== null ? (int) $seccion['espacio_id'] : null,
                        'materia_id'     => (int) $materia['materia_id'],
                        'materia'        => $materia['nombre'],
                        'materia_codigo' => $materia['codigo'],
                        'requiere_lab'   => (int) $materia['requiere_laboratorio'] === 1,
                        'es_electiva'    => (int) $materia['es_electiva'] === 1,
                        'grupo_electiva' => $materia['grupo_electiva'],
                        'sesiones'       => $perfil['sesiones'],
                        'bloques_sesion' => $perfil['bloques'],
                        'profesor_id'    => null,
                    ];
                }
            }
        }

        $this->resumen['asignaciones'] = count($oferta);
        return $oferta;
    }

    /**
     * Reparto de las materias de una seccion entre los modulos del
     * periodo, sin pasarse de la capacidad real de la rejilla.
     *
     * Criterio (el mismo que aplica hoy la coordinacion, pero medido):
     *   - las materias fuertes (4 UC o mas) se dictan los dos modulos,
     *     siempre que quepan;
     *   - las ligeras se reparten hacia el modulo mas descargado;
     *   - las electivas van al ultimo modulo y, como se dictan en
     *     paralelo, el grupo entero pesa lo que una sola.
     *
     * @return array<int, int[]> materia_id => modulos donde se dicta
     */
    private function distribuirPorModulo(array $materias, array $modulos): array
    {
        $capacidad = count($this->dias) * $this->bloquesLectivos();
        $carga     = array_fill_keys($modulos, 0);
        $reparto   = [];
        $gruposUsados = [];   // grupo de electiva => modulo donde ya se conto

        // Primero las fuertes: son las que necesitan continuidad
        usort($materias, static function ($a, $b) {
            return [(int) $a['es_electiva'], -(int) $a['unidades_credito']]
               <=> [(int) $b['es_electiva'], -(int) $b['unidades_credito']];
        });

        foreach ($materias as $materia) {
            $materiaId = (int) $materia['materia_id'];
            $perfil = $this->perfilCarga($materia);
            $peso   = $perfil['sesiones'] * $perfil['bloques'];

            /* --- Electivas: al ultimo modulo, y el grupo pesa una vez --- */
            if ((int) $materia['es_electiva'] === 1) {
                $grupo = (string) ($materia['grupo_electiva'] ?? 'ELECTIVA');
                $modulo = $gruposUsados[$grupo] ?? end($modulos);

                if (!isset($gruposUsados[$grupo])) {
                    if (($carga[$modulo] ?? 0) + $peso > $capacidad) {
                        $modulo = self::moduloMasLibre($carga);
                    }
                    $carga[$modulo] += $peso;
                    $gruposUsados[$grupo] = $modulo;
                }

                $reparto[$materiaId] = [$modulo];
                continue;
            }

            /* --- Fuertes: los dos modulos si caben --- */
            if ((int) $materia['unidades_credito'] >= 4 && count($modulos) > 1) {
                $cabeEnTodos = true;
                foreach ($modulos as $m) {
                    if ($carga[$m] + $peso > $capacidad) {
                        $cabeEnTodos = false;
                        break;
                    }
                }
                if ($cabeEnTodos) {
                    foreach ($modulos as $m) {
                        $carga[$m] += $peso;
                    }
                    $reparto[$materiaId] = $modulos;
                    continue;
                }
            }

            /* --- Ligeras (o fuertes que ya no caben): al mas libre --- */
            $modulo = self::moduloMasLibre($carga);
            $carga[$modulo] += $peso;
            $reparto[$materiaId] = [$modulo];
        }

        return $reparto;
    }

    private static function moduloMasLibre(array $carga): int
    {
        asort($carga);
        return (int) array_key_first($carga);
    }

    /** Bloques utiles (sin recesos) que tiene un dia de la modalidad. */
    private function bloquesLectivos(): int
    {
        $total = 0;
        foreach ($this->segmentos as $segmento) {
            $total += count($segmento);
        }
        return $total;
    }

    /**
     * Cuantas sesiones semanales y de que largo.
     * Entre semana se reparte en 2 dias; el sabatino concentra todo en
     * una sola sesion mas larga, que es como funciona en la practica.
     */
    private function perfilCarga(array $materia): array
    {
        $uc = (int) $materia['unidades_credito'];

        if ($this->periodo['modalidad'] === 'SABATINO') {
            return ['sesiones' => 1, 'bloques' => $uc >= 5 ? 3 : 2];
        }

        return [
            'sesiones' => max(1, (int) $materia['sesiones_semana']),
            'bloques'  => max(1, (int) $materia['bloques_sesion']),
        ];
    }

    private function asegurarAsignacion(int $periodoId, int $modulo, array $seccion, array $materia): int
    {
        $existente = Modelo::valor(
            'SELECT `asignacion_id` FROM `asignaciones`
              WHERE `periodo_id` = ? AND `modulo` = ? AND `seccion_id` = ? AND `materia_id` = ?',
            [$periodoId, $modulo, (int) $seccion['seccion_id'], (int) $materia['materia_id']]
        );

        if ($existente !== null) {
            return (int) $existente;
        }

        return Modelo::insertar([
            'periodo_id' => $periodoId,
            'modulo'     => $modulo,
            'seccion_id' => (int) $seccion['seccion_id'],
            'materia_id' => (int) $materia['materia_id'],
            'estado'     => 'BORRADOR',
            'origen'     => 'AUTO',
        ], 'asignaciones');
    }

    /* =================================================================
       3. DOCENTES POR AFINIDAD DE HABILIDADES
       ================================================================= */

    private function asignarDocentes(array &$oferta, bool $reasignar): void
    {
        $periodoId = (int) $this->periodo['periodo_id'];

        // Disponibilidad y topes de todos los docentes activos, de una vez
        $profesores = Modelo::filas(
            'SELECT `profesor_id`,`max_bloques_semana` FROM `profesores` WHERE `activo` = 1'
        );
        foreach ($profesores as $p) {
            $this->topeProfesor[(int) $p['profesor_id']] = (int) $p['max_bloques_semana'];
            $this->cargaProfesor[(int) $p['profesor_id']] = 0;
        }

        $franjas = Modelo::filas(
            'SELECT `profesor_id`,`dia`,`hora_inicio`,`hora_fin`,`periodo_id`
             FROM `profesor_disponibilidad`
             WHERE `periodo_id` IS NULL OR `periodo_id` = ?
             ORDER BY `periodo_id` IS NULL',  // primero las especificas del periodo
            [$periodoId]
        );

        $tienePeriodo = [];
        foreach ($franjas as $f) {
            $pid = (int) $f['profesor_id'];
            if ($f['periodo_id'] !== null) {
                $tienePeriodo[$pid] = true;
            } elseif (isset($tienePeriodo[$pid])) {
                continue; // ya tiene disponibilidad propia del periodo
            }
            $this->dispProfesor[$pid][$f['dia']][] = [
                self::aMinutos($f['hora_inicio']),
                self::aMinutos($f['hora_fin']),
            ];
        }

        // Candidatos por materia ordenados por afinidad
        $this->candidatosPorMateria = [];
        foreach (Modelo::filas(
            'SELECT pm.`materia_id`, pm.`profesor_id`, pm.`afinidad`
             FROM `profesor_materias` pm
             JOIN `profesores` p ON p.`profesor_id` = pm.`profesor_id`
             WHERE p.`activo` = 1
             ORDER BY pm.`materia_id`, pm.`afinidad` DESC'
        ) as $c) {
            $this->candidatosPorMateria[(int) $c['materia_id']][] = [
                'profesor_id' => (int) $c['profesor_id'],
                'afinidad'    => (float) $c['afinidad'],
            ];
        }

        // Docente ya asignado manualmente que hay que respetar
        $yaAsignados = [];
        if (!$reasignar) {
            foreach (Modelo::filas(
                'SELECT `asignacion_id`,`profesor_id` FROM `asignaciones`
                  WHERE `periodo_id` = ? AND `profesor_id` IS NOT NULL',
                [$periodoId]
            ) as $a) {
                $yaAsignados[(int) $a['asignacion_id']] = (int) $a['profesor_id'];
            }
        }

        foreach ($oferta as &$item) {
            $asignacionId = $item['asignacion_id'];

            if (isset($yaAsignados[$asignacionId])) {
                $item['profesor_id'] = $yaAsignados[$asignacionId];
                $this->sumarCarga($item);
                continue;
            }

            $elegido = $this->elegirDocente($item, $this->candidatosPorMateria[$item['materia_id']] ?? []);

            if ($elegido === null) {
                $this->resumen['sin_docente']++;
                $this->conflictosDetectados[] = [
                    'tipo'        => 'SIN_DOCENTE',
                    'severidad'   => 'ALTA',
                    'titulo'      => "Sin docente: {$item['materia_codigo']} - {$item['materia']}",
                    'descripcion' => "No hay ningun docente habilitado y disponible para {$item['materia']} "
                                   . "en la seccion {$item['seccion']} (modulo {$item['modulo']}). "
                                   . 'Revisa las habilidades exigidas por la materia o la disponibilidad del personal.',
                    'asignacion_id' => $asignacionId,
                    'seccion_id'  => $item['seccion_id'],
                    'materia_id'  => $item['materia_id'],
                    'contexto'    => ['modulo' => $item['modulo'], 'seccion' => $item['seccion']],
                ];

                Modelo::ejecutar(
                    'UPDATE `asignaciones` SET `profesor_id` = NULL, `estado` = "SIN_DOCENTE" WHERE `asignacion_id` = ?',
                    [$asignacionId]
                );
                continue;
            }

            $item['profesor_id'] = $elegido['profesor_id'];
            $this->sumarCarga($item);

            Modelo::ejecutar(
                'UPDATE `asignaciones`
                    SET `profesor_id` = ?, `afinidad` = ?, `estado` = "CONFIRMADA", `origen` = "AUTO"
                  WHERE `asignacion_id` = ?',
                [$elegido['profesor_id'], $elegido['afinidad'], $asignacionId]
            );
        }
        unset($item);
    }

    private function sumarCarga(array $item): void
    {
        $pid = $item['profesor_id'];
        if ($pid !== null) {
            $this->cargaProfesor[$pid] = ($this->cargaProfesor[$pid] ?? 0) + $item['sesiones'] * $item['bloques_sesion'];
        }
    }

    /**
     * Puntaje = afinidad de habilidades - penalizacion por carga.
     * Sin disponibilidad en los dias de la modalidad, el docente no entra.
     */
    private function elegirDocente(array $item, array $candidatos): ?array
    {
        $mejor = null;
        $mejorPuntaje = -INF;
        $bloquesNecesarios = $item['sesiones'] * $item['bloques_sesion'];

        foreach ($candidatos as $c) {
            $pid = $c['profesor_id'];

            if (!isset($this->topeProfesor[$pid])) {
                continue;
            }

            $disponibleEnModalidad = false;
            foreach ($this->dias as $dia) {
                if (!empty($this->dispProfesor[$pid][$dia])) {
                    $disponibleEnModalidad = true;
                    break;
                }
            }
            if (!$disponibleEnModalidad) {
                continue;
            }

            $carga = $this->cargaProfesor[$pid] ?? 0;
            $tope  = $this->topeProfesor[$pid];
            if ($carga + $bloquesNecesarios > $tope) {
                continue;
            }

            // 0-100 de afinidad menos hasta 40 puntos por ocupacion
            $puntaje = $c['afinidad'] - ($carga / max(1, $tope)) * 40;

            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejor = $c;
            }
        }

        return $mejor;
    }

    /* =================================================================
       4-5. COLOCACION EN LA REJILLA
       ================================================================= */

    private function resolverModulo(int $modulo, array $oferta): void
    {
        if (!$oferta) {
            return;
        }

        $this->ocupSeccion = [];
        $this->ocupProfesor = [];
        $this->ocupEspacio = [];
        $this->diaUsadoPorAsignacion = [];
        $this->sesiones = [];

        // Una entrada por sesion a colocar
        foreach ($oferta as $item) {
            for ($i = 0; $i < $item['sesiones']; $i++) {
                $this->sesiones[] = $item + ['indice' => $i, 'ubicada' => null];
            }
        }

        // MRV: primero lo mas dificil (laboratorios, sesiones largas,
        // docentes con poca disponibilidad)
        usort($this->sesiones, function ($a, $b) {
            $costoA = $this->dificultad($a);
            $costoB = $this->dificultad($b);
            return $costoB <=> $costoA;
        });

        $pendientes = [];

        foreach ($this->sesiones as $idx => $sesion) {
            if (!$this->colocar($idx, $modulo)) {
                $pendientes[] = $idx;
            }
        }

        $pendientes = $this->repararPendientes($pendientes, $modulo);

        // Persistir lo colocado
        foreach ($this->sesiones as $sesion) {
            if ($sesion['ubicada'] === null) {
                continue;
            }
            $this->persistir($sesion, $modulo);
            $this->resumen['ubicadas']++;
        }

        foreach ($pendientes as $idx) {
            $s = $this->sesiones[$idx];
            $this->resumen['sin_ubicar']++;
            $this->conflictosDetectados[] = [
                'tipo'          => 'SIN_BLOQUE',
                'severidad'     => 'ALTA',
                'titulo'        => "Sin bloque libre: {$s['materia_codigo']} - {$s['materia']}",
                'descripcion'   => "No quedo ningun bloque valido para {$s['materia']} en la seccion "
                                 . "{$s['seccion']} (modulo {$modulo}). Puede ser falta de "
                                 . ($s['requiere_lab'] ? 'laboratorios libres' : 'aulas libres')
                                 . ' o que la disponibilidad del docente no alcanza.',
                'asignacion_id' => $s['asignacion_id'],
                'seccion_id'    => $s['seccion_id'],
                'materia_id'    => $s['materia_id'],
                'profesor_id'   => $s['profesor_id'],
                'contexto'      => ['modulo' => $modulo, 'requiere_lab' => $s['requiere_lab']],
            ];

            Modelo::ejecutar(
                'UPDATE `asignaciones` SET `estado` = "SIN_HORARIO" WHERE `asignacion_id` = ?',
                [$s['asignacion_id']]
            );
        }
    }

    /**
     * Ultimo intento antes de declarar conflicto: cambiar el docente por
     * otro habilitado cuya disponibilidad si permita ubicar la materia.
     * Se mueven todas las sesiones de la asignacion a la vez, porque una
     * materia la dicta un solo docente.
     */
    private function reasignarDocenteYUbicar(int $idx, int $modulo): bool
    {
        $sesion = $this->sesiones[$idx];
        $actual = $sesion['profesor_id'];

        $hermanas = [];
        foreach ($this->sesiones as $i => $s) {
            if ($s['asignacion_id'] === $sesion['asignacion_id']) {
                $hermanas[] = $i;
            }
        }

        $estadoPrevio = [];
        foreach ($hermanas as $i) {
            $estadoPrevio[$i] = $this->sesiones[$i]['ubicada'];
        }

        $probados = 0;

        foreach ($this->candidatosPorMateria[$sesion['materia_id']] ?? [] as $candidato) {
            $pid = $candidato['profesor_id'];

            if ($pid === $actual || !isset($this->topeProfesor[$pid]) || $probados >= 8) {
                continue;
            }
            if (!$this->tieneDisponibilidadEnModalidad($pid)) {
                continue;
            }
            $probados++;

            foreach ($hermanas as $i) {
                $this->desocupar($i);
                $this->sesiones[$i]['profesor_id'] = $pid;
            }

            $todas = true;
            foreach ($hermanas as $i) {
                if (!$this->colocar($i, $modulo)) {
                    $todas = false;
                    break;
                }
            }

            if ($todas) {
                Modelo::ejecutar(
                    'UPDATE `asignaciones`
                        SET `profesor_id` = ?, `afinidad` = ?, `estado` = "CONFIRMADA"
                      WHERE `asignacion_id` = ?',
                    [$pid, $candidato['afinidad'], $sesion['asignacion_id']]
                );
                return true;
            }

            // Revertir al docente original y a las posiciones que tenia
            foreach ($hermanas as $i) {
                $this->desocupar($i);
                $this->sesiones[$i]['profesor_id'] = $actual;
            }
            foreach ($hermanas as $i) {
                if ($estadoPrevio[$i] !== null) {
                    $this->ocupar($i, $estadoPrevio[$i], $modulo);
                }
            }
        }

        return false;
    }

    /**
     * Coloca la sesion sin docente. La materia aparece en el horario (los
     * estudiantes la ven) y queda un conflicto SIN_DOCENTE para que
     * coordinacion asigne a alguien.
     */
    private function ubicarSinDocente(int $idx, int $modulo): bool
    {
        $sesion = $this->sesiones[$idx];
        if ($sesion['profesor_id'] === null) {
            return false;
        }

        $original = $sesion['profesor_id'];
        $this->sesiones[$idx]['profesor_id'] = null;

        if (!$this->colocar($idx, $modulo)) {
            $this->sesiones[$idx]['profesor_id'] = $original;
            return false;
        }

        // Todas las sesiones de esa materia quedan sin docente
        foreach ($this->sesiones as $i => $s) {
            if ($i === $idx || $s['asignacion_id'] !== $sesion['asignacion_id'] || $s['profesor_id'] === null) {
                continue;
            }
            $slot = $s['ubicada'];
            if ($slot !== null) {
                $this->desocupar($i);
                $this->sesiones[$i]['profesor_id'] = null;
                $this->ocupar($i, $slot, $modulo);
            } else {
                $this->sesiones[$i]['profesor_id'] = null;
            }
        }

        Modelo::ejecutar(
            'UPDATE `asignaciones` SET `profesor_id` = NULL, `estado` = "SIN_DOCENTE" WHERE `asignacion_id` = ?',
            [$sesion['asignacion_id']]
        );

        $this->resumen['sin_docente']++;
        $this->conflictosDetectados[] = [
            'tipo'          => 'SIN_DOCENTE',
            'severidad'     => 'ALTA',
            'titulo'        => "Sin docente: {$sesion['materia_codigo']} - {$sesion['materia']}",
            'descripcion'   => "La materia quedo ubicada en el horario de la seccion {$sesion['seccion']} "
                             . "(modulo {$modulo}), pero ningun docente habilitado tiene disponibilidad "
                             . 'en ese bloque. Asigna otro docente o mueve la materia a otro bloque.',
            'asignacion_id' => $sesion['asignacion_id'],
            'seccion_id'    => $sesion['seccion_id'],
            'materia_id'    => $sesion['materia_id'],
            'profesor_id'   => $original,
            'contexto'      => ['modulo' => $modulo, 'docente_previsto' => $original],
        ];

        return true;
    }

    private function tieneDisponibilidadEnModalidad(int $profesorId): bool
    {
        foreach ($this->dias as $dia) {
            if (!empty($this->dispProfesor[$profesorId][$dia])) {
                return true;
            }
        }
        return false;
    }

    private function dificultad(array $sesion): float
    {
        $costo = $sesion['bloques_sesion'] * 10;

        if ($sesion['requiere_lab']) {
            $costo += 40;
        }

        $pid = $sesion['profesor_id'];
        if ($pid === null) {
            $costo += 5; // sin docente hay menos restricciones
        } else {
            $minutos = 0;
            foreach ($this->dias as $dia) {
                foreach ($this->dispProfesor[$pid][$dia] ?? [] as [$ini, $fin]) {
                    $minutos += $fin - $ini;
                }
            }
            $costo += max(0, 60 - $minutos / 30); // menos disponibilidad => mas dificil
        }

        return $costo;
    }

    /** Intenta ubicar la sesion; devuelve true si lo logro. */
    private function colocar(int $idx, int $modulo): bool
    {
        $sesion = $this->sesiones[$idx];
        $mejor = null;
        $mejorPuntaje = -INF;

        foreach ($this->candidatosSlot($sesion, $modulo) as $slot) {
            $puntaje = $this->puntuarSlot($sesion, $slot, $modulo);
            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejor = $slot;
            }
        }

        if ($mejor === null) {
            return false;
        }

        $this->ocupar($idx, $mejor, $modulo);
        return true;
    }

    /** Todos los (dia, bloques, espacio) validos para una sesion. */
    private function candidatosSlot(array $sesion, int $modulo): array
    {
        $largo  = $sesion['bloques_sesion'];
        $tipo   = $sesion['requiere_lab'] ? 'LABORATORIO' : 'SALON';
        $salida = [];

        foreach ($this->dias as $dia) {
            // Una misma materia no se dicta dos veces el mismo dia
            if (isset($this->diaUsadoPorAsignacion[$sesion['asignacion_id']][$dia])) {
                continue;
            }

            foreach ($this->segmentos as $segmento) {
                $total = count($segmento);
                for ($i = 0; $i + $largo <= $total; $i++) {
                    $bloques = array_slice($segmento, $i, $largo);

                    if (!$this->seccionLibre($sesion, $dia, $bloques)) {
                        continue;
                    }
                    if (!$this->profesorApto($sesion['profesor_id'], $dia, $bloques)) {
                        continue;
                    }

                    $espacioId = $this->espacioLibre($tipo, $dia, $bloques, $sesion);
                    if ($espacioId === null) {
                        continue;
                    }

                    $salida[] = ['dia' => $dia, 'bloques' => $bloques, 'espacio_id' => $espacioId];
                }
            }
        }

        return $salida;
    }

    /**
     * Preferencias (no son restricciones, solo ordenan):
     *  + repetir el bloque que tuvo en el modulo anterior (continuidad)
     *  + empezar temprano y compactar el dia de la seccion
     *  + usar el salon base de la seccion
     */
    private function puntuarSlot(array $sesion, array $slot, int $modulo): float
    {
        $puntaje = 0.0;
        $primero = $slot['bloques'][0];

        $claveContinuidad = $sesion['seccion_id'] . ':' . $sesion['materia_id'] . ':' . $sesion['indice'];
        if (($this->slotPreferido[$claveContinuidad] ?? null) === $slot['dia'] . ':' . $primero) {
            $puntaje += 60;
        }

        $puntaje += max(0, 30 - $this->bloques[$primero]['orden'] * 1.5);

        if (!$sesion['requiere_lab'] && $sesion['espacio_base'] !== null && $slot['espacio_id'] === $sesion['espacio_base']) {
            $puntaje += 25;
        }

        // Compactar: premiar si la seccion ya tiene clase pegada ese dia
        $claveSeccion = $this->claveSeccion($sesion);
        $ocupadosDia = $this->ocupSeccion[$claveSeccion][$slot['dia']] ?? [];
        if ($ocupadosDia) {
            $ordenPrimero = $this->bloques[$primero]['orden'];
            foreach (array_keys($ocupadosDia) as $bloqueId) {
                $delta = abs($this->bloques[$bloqueId]['orden'] - $ordenPrimero);
                if ($delta <= $sesion['bloques_sesion'] + 1) {
                    $puntaje += 15;
                    break;
                }
            }
        } else {
            $puntaje += 5; // repartir entre dias tambien vale
        }

        // Equilibrar la carga diaria del docente
        if ($sesion['profesor_id'] !== null) {
            $bloquesEseDia = count($this->ocupProfesor[$sesion['profesor_id']][$slot['dia']] ?? []);
            $puntaje -= $bloquesEseDia * 2;
        }

        return $puntaje;
    }

    private function ocupar(int $idx, array $slot, int $modulo): void
    {
        $sesion = $this->sesiones[$idx];
        $claveSeccion = $this->claveSeccion($sesion);

        foreach ($slot['bloques'] as $bloqueId) {
            $this->ocupSeccion[$claveSeccion][$slot['dia']][$bloqueId] = $idx;
            $this->ocupEspacio[$slot['espacio_id']][$slot['dia']][$bloqueId] = $idx;
            if ($sesion['profesor_id'] !== null) {
                $this->ocupProfesor[$sesion['profesor_id']][$slot['dia']][$bloqueId] = $idx;
            }
        }

        $this->diaUsadoPorAsignacion[$sesion['asignacion_id']][$slot['dia']] = true;
        $this->sesiones[$idx]['ubicada'] = $slot;

        $claveContinuidad = $sesion['seccion_id'] . ':' . $sesion['materia_id'] . ':' . $sesion['indice'];
        $this->slotPreferido[$claveContinuidad] = $slot['dia'] . ':' . $slot['bloques'][0];
    }

    private function desocupar(int $idx): void
    {
        $sesion = $this->sesiones[$idx];
        $slot = $sesion['ubicada'];
        if ($slot === null) {
            return;
        }

        $claveSeccion = $this->claveSeccion($sesion);

        foreach ($slot['bloques'] as $bloqueId) {
            unset($this->ocupSeccion[$claveSeccion][$slot['dia']][$bloqueId]);
            unset($this->ocupEspacio[$slot['espacio_id']][$slot['dia']][$bloqueId]);
            if ($sesion['profesor_id'] !== null) {
                unset($this->ocupProfesor[$sesion['profesor_id']][$slot['dia']][$bloqueId]);
            }
        }

        unset($this->diaUsadoPorAsignacion[$sesion['asignacion_id']][$slot['dia']]);
        $this->sesiones[$idx]['ubicada'] = null;
    }

    /**
     * Busqueda local de min-conflictos: para cada sesion sin ubicar se
     * busca un hueco que solo estorbe a una o dos sesiones ya puestas,
     * se las desaloja y se las vuelve a encolar.
     */
    private function repararPendientes(array $pendientes, int $modulo): array
    {
        if (!$pendientes) {
            return [];
        }

        $maxIteraciones = Env::int('GENERADOR_INTENTOS', 400);
        $tiempoMax      = Env::int('GENERADOR_TIEMPO_MAX', 20);
        $iteracion      = 0;
        $sinProgreso    = 0;

        while ($pendientes && $iteracion < $maxIteraciones) {
            if (microtime(true) - $this->inicio > $tiempoMax) {
                Log::aviso('Generador: tiempo maximo agotado, quedan ' . count($pendientes) . ' sesiones sin ubicar.');
                break;
            }
            $iteracion++;

            $idx = array_shift($pendientes);

            if ($this->colocar($idx, $modulo)) {
                $sinProgreso = 0;
                continue;
            }

            $desalojo = $this->mejorDesalojo($idx, $modulo);

            if ($desalojo === null) {
                $pendientes[] = $idx;
                // Una vuelta completa sin mover nada: no hay nada mas que hacer
                if (++$sinProgreso >= count($pendientes)) {
                    break;
                }
                continue;
            }

            foreach ($desalojo['desalojar'] as $victima) {
                $this->desocupar($victima);
                $pendientes[] = $victima;
            }

            if ($this->colocar($idx, $modulo)) {
                $sinProgreso = 0;
            } else {
                $pendientes[] = $idx;
                $sinProgreso++;
            }
        }

        $sinUbicar = array_values(array_unique(array_filter(
            $pendientes,
            fn ($i) => $this->sesiones[$i]['ubicada'] === null
        )));

        // Ultimo recurso, en este orden:
        //   1. cambiar el docente por otro habilitado que si tenga hueco
        //   2. dejar la materia en la parrilla sin docente
        // Solo lo que no entra ni asi se declara SIN_BLOQUE.
        $irreducibles = [];
        foreach ($sinUbicar as $idx) {
            if ($this->reasignarDocenteYUbicar($idx, $modulo)) {
                continue;
            }
            if ($this->ubicarSinDocente($idx, $modulo)) {
                continue;
            }
            $irreducibles[] = $idx;
        }

        return array_values(array_filter(
            $irreducibles,
            fn ($i) => $this->sesiones[$i]['ubicada'] === null
        ));
    }

    /** Hueco que se libera desalojando el menor numero de sesiones (max 2). */
    private function mejorDesalojo(int $idx, int $modulo): ?array
    {
        $sesion = $this->sesiones[$idx];
        $largo = $sesion['bloques_sesion'];
        $tipo  = $sesion['requiere_lab'] ? 'LABORATORIO' : 'SALON';
        $claveSeccion = $this->claveSeccion($sesion);
        $mejor = null;

        foreach ($this->dias as $dia) {
            if (isset($this->diaUsadoPorAsignacion[$sesion['asignacion_id']][$dia])) {
                continue;
            }
            if (!$this->profesorDisponible($sesion['profesor_id'], $dia)) {
                continue;
            }

            foreach ($this->segmentos as $segmento) {
                $total = count($segmento);
                for ($i = 0; $i + $largo <= $total; $i++) {
                    $bloques = array_slice($segmento, $i, $largo);

                    if (!$this->profesorApto($sesion['profesor_id'], $dia, $bloques, true)) {
                        continue;
                    }

                    $victimas = [];

                    foreach ($bloques as $bloqueId) {
                        foreach ([
                            $this->ocupSeccion[$claveSeccion][$dia][$bloqueId] ?? null,
                            $sesion['profesor_id'] !== null
                                ? ($this->ocupProfesor[$sesion['profesor_id']][$dia][$bloqueId] ?? null)
                                : null,
                        ] as $ocupante) {
                            if ($ocupante !== null) {
                                $victimas[$ocupante] = true;
                            }
                        }
                    }

                    $victimas = array_keys($victimas);
                    if (count($victimas) > 2 || in_array($idx, $victimas, true)) {
                        continue;
                    }

                    // Con las victimas fuera, tiene que quedar espacio fisico
                    $hayEspacio = false;
                    foreach ($this->espacios[$tipo] as $espacio) {
                        $libre = true;
                        foreach ($bloques as $bloqueId) {
                            $ocupante = $this->ocupEspacio[(int) $espacio['espacio_id']][$dia][$bloqueId] ?? null;
                            if ($ocupante !== null && !in_array($ocupante, $victimas, true)) {
                                $libre = false;
                                break;
                            }
                        }
                        if ($libre) {
                            $hayEspacio = true;
                            break;
                        }
                    }
                    if (!$hayEspacio) {
                        continue;
                    }

                    if ($mejor === null || count($victimas) < count($mejor['desalojar'])) {
                        $mejor = ['desalojar' => $victimas, 'dia' => $dia, 'bloques' => $bloques];
                        if (!$victimas) {
                            return $mejor;
                        }
                    }
                }
            }
        }

        return $mejor;
    }

    /* =================================================================
       RESTRICCIONES
       ================================================================= */

    /**
     * Clave de ocupacion de la seccion. Las electivas del mismo grupo
     * comparten clave, por eso pueden dictarse en paralelo.
     */
    private function claveSeccion(array $sesion): string
    {
        return $sesion['es_electiva']
            ? 'sec' . $sesion['seccion_id'] . ':grp' . ($sesion['grupo_electiva'] ?? 'X')
            : 'sec' . $sesion['seccion_id'];
    }

    private function seccionLibre(array $sesion, string $dia, array $bloques): bool
    {
        $clave = $this->claveSeccion($sesion);

        foreach ($bloques as $bloqueId) {
            if (isset($this->ocupSeccion[$clave][$dia][$bloqueId])) {
                return false;
            }
            // Una electiva tampoco puede pisar una materia regular de su seccion
            if ($sesion['es_electiva'] && isset($this->ocupSeccion['sec' . $sesion['seccion_id']][$dia][$bloqueId])) {
                return false;
            }
            if (!$sesion['es_electiva']) {
                foreach ($this->ocupSeccion as $otraClave => $porDia) {
                    if ($otraClave !== $clave
                        && str_starts_with($otraClave, 'sec' . $sesion['seccion_id'] . ':')
                        && isset($porDia[$dia][$bloqueId])) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function profesorApto(?int $profesorId, string $dia, array $bloques, bool $ignorarOcupacion = false): bool
    {
        if ($profesorId === null) {
            return true;
        }

        if (!$ignorarOcupacion) {
            foreach ($bloques as $bloqueId) {
                if (isset($this->ocupProfesor[$profesorId][$dia][$bloqueId])) {
                    return false;
                }
            }
        }

        $inicio = $this->bloques[$bloques[0]]['ini_min'];
        $fin    = $this->bloques[$bloques[count($bloques) - 1]]['fin_min'];

        foreach ($this->dispProfesor[$profesorId][$dia] ?? [] as [$dispIni, $dispFin]) {
            if ($inicio >= $dispIni && $fin <= $dispFin) {
                return true;
            }
        }

        return false;
    }

    private function profesorDisponible(?int $profesorId, string $dia): bool
    {
        return $profesorId === null || !empty($this->dispProfesor[$profesorId][$dia]);
    }

    private function espacioLibre(string $tipo, string $dia, array $bloques, array $sesion): ?int
    {
        $candidatos = $this->espacios[$tipo];

        // El salon base de la seccion tiene prioridad
        if ($tipo === 'SALON' && $sesion['espacio_base'] !== null) {
            usort($candidatos, static fn ($a, $b) => ((int) $b['espacio_id'] === $sesion['espacio_base'] ? 1 : 0)
                <=> ((int) $a['espacio_id'] === $sesion['espacio_base'] ? 1 : 0));
        }

        foreach ($candidatos as $espacio) {
            $espacioId = (int) $espacio['espacio_id'];

            if ((int) $espacio['capacidad'] < $sesion['cupo'] && $tipo === 'SALON') {
                continue;
            }

            $libre = true;
            foreach ($bloques as $bloqueId) {
                if (isset($this->ocupEspacio[$espacioId][$dia][$bloqueId])) {
                    $libre = false;
                    break;
                }
            }
            if ($libre) {
                return $espacioId;
            }
        }

        // Si ningun salon tiene el cupo exacto, aceptamos el mas grande libre
        if ($tipo === 'SALON') {
            foreach (array_reverse($this->espacios['SALON']) as $espacio) {
                $espacioId = (int) $espacio['espacio_id'];
                $libre = true;
                foreach ($bloques as $bloqueId) {
                    if (isset($this->ocupEspacio[$espacioId][$dia][$bloqueId])) {
                        $libre = false;
                        break;
                    }
                }
                if ($libre) {
                    return $espacioId;
                }
            }
        }

        return null;
    }

    /* =================================================================
       PERSISTENCIA
       ================================================================= */

    private function persistir(array $sesion, int $modulo): void
    {
        $slot = $sesion['ubicada'];
        $periodoId = (int) $this->periodo['periodo_id'];

        foreach ($slot['bloques'] as $bloqueId) {
            Asignacion::insertarBloque([
                'asignacion_id' => $sesion['asignacion_id'],
                'periodo_id'    => $periodoId,
                'modulo'        => $modulo,
                'dia'           => $slot['dia'],
                'bloque_id'     => $bloqueId,
                'seccion_id'    => $sesion['seccion_id'],
                'slot_seccion'  => $sesion['es_electiva'] ? null : $sesion['seccion_id'],
                'profesor_id'   => $sesion['profesor_id'],
                'espacio_id'    => $slot['espacio_id'],
            ]);
        }

        Modelo::ejecutar(
            'UPDATE `asignaciones`
                SET `espacio_id` = ?, `estado` = IF(`profesor_id` IS NULL, "SIN_DOCENTE", "CONFIRMADA")
              WHERE `asignacion_id` = ?',
            [$slot['espacio_id'], $sesion['asignacion_id']]
        );
    }

    private function registrarConflictos(): void
    {
        $periodoId = (int) $this->periodo['periodo_id'];

        foreach ($this->conflictosDetectados as &$conflicto) {
            $conflicto['periodo_id'] = $periodoId;
            // Se crean sin notificar una por una: al final va un solo aviso
            $conflicto['conflicto_id'] = NotificacionService::conflicto($conflicto, false);
        }
        unset($conflicto);
    }

    private static function aMinutos(string $hora): int
    {
        [$h, $m] = array_map('intval', explode(':', $hora));
        return $h * 60 + $m;
    }
}
