<?php

class ControladorGenerarHorarios {

    /*=============================================
    GENERAR HORARIO DE LA SEMANA DE PROFESORES
    =============================================*/
    static public function ctrGenerarHorariosProfesoresSemana() {
        try {
            // Obtener datos del modelo
            $profesores = ModeloProfesores::mdlMostrarProfesores("teachers", null, null);
            if (empty($profesores)) {
                return ["status" => 404, "success" => false, "message" => "No se encontraron profesores."];
            }
            
            $materias = ModeloMaterias::mdlMostrarMaterias("subjects", null, null);
            if (empty($materias)) {
                return ["status" => 404, "success" => false, "message" => "No se encontraron materias."];
            }

            $habilidades_db = ModeloHabilidades::mdlMostrarHabilidades("skills", null, null);
            if (empty($habilidades_db)) {
                return ["status" => 404, "success" => false, "message" => "No se encontraron habilidades."];
            }
            
            // Mapear habilidades a un formato más usable
            $skills_list = array_column($habilidades_db, 'skill_name', 'skill_id');

            // Pre-procesar datos para el algoritmo
            $profesores_procesados = [];
            foreach ($profesores['data'] as $profesor) {
                $habilidades_profesor_db = ModeloHabilidades::mdlMostrarHabilidadesProfesores("teacher_skills", "teacher_id", $profesor['teacher_id']);
                $disponibilidad_db = ModeloProfesores::mdlMostrarDisponibilidadesProfesores("teacher_availability", "teacher_id", $profesor['teacher_id']);
                
                $horas_totales = 0;
                $disponibilidad_formato = [];
                if (!empty($disponibilidad_db)) {
                    foreach ($disponibilidad_db as $disponibilidad) {
                        $horas_totales += (strtotime($disponibilidad['end_time']) - strtotime($disponibilidad['start_time'])) / 3600;
                        $disponibilidad_formato[] = $disponibilidad;
                    }
                }
                
                $habilidades_profesor_formato = [];
                if (!empty($habilidades_profesor_db)) {
                    foreach ($habilidades_profesor_db as $h) {
                        $habilidades_profesor_formato[$skills_list[$h['skill_id']]] = $h['stars'];
                    }
                }

                $profesores_procesados[$profesor['teacher_id']] = [
                    'id' => $profesor['teacher_id'],
                    'nombre' => $profesor['name'],
                    'horas_disponibles_semana' => floor($horas_totales),
                    'disponibilidad' => $disponibilidad_formato,
                    'habilidades' => $habilidades_profesor_formato,
                    'materias_asignadas' => [],
                    'horario_detallado' => []
                ];
            }

            $materias_procesadas = [];
            foreach ($materias as $materia) {
                $habilidades_materia_db = ModeloHabilidades::mdlMostrarMateriasHabilidades("subject_skills", "subject_id", $materia['subject_id']);
                
                $habilidades_materia_formato = [];
                if (!empty($habilidades_materia_db)) {
                    foreach ($habilidades_materia_db as $hr) {
                        $habilidades_materia_formato[$skills_list[$hr['skill_id']]] = $hr['min_stars'];
                    }
                }

                $materias_procesadas[$materia['subject_id']] = [
                    'id' => $materia['subject_id'],
                    'nombre' => $materia['name'],
                    'horas_semana' => $materia['duration_hours'],
                    'semestre' => $materia['semester'],
                    'habilidades_requeridas' => $habilidades_materia_formato
                ];
            }
            
            /* ================================================================= */
            /* ===             INICIO DE LA LÓGICA DEL ALGORITMO             === */
            /* ================================================================= */

            /* Calcula la puntuación de un profesor para una materia. */
            $calcularPuntuacion = function (array $profesor, array $materia): int {
                $puntuacion = 0;
                foreach ($materia['habilidades_requeridas'] as $habilidad => $minimo) {
                    if (!isset($profesor['habilidades'][$habilidad]) || $profesor['habilidades'][$habilidad] < $minimo) {
                        return 0; // El profesor no cumple con los requisitos mínimos
                    }
                }

                $puntuacionHabilidades = 0;
                foreach ($materia['habilidades_requeridas'] as $habilidad => $minimo) {
                    $puntuacionHabilidades += ($profesor['habilidades'][$habilidad] - $minimo);
                }
                
                // Penalización por la carga de trabajo actual del profesor
                $horas_asignadas = array_sum(array_column($profesor['materias_asignadas'], 'horas_semana'));
                $penalizacion = $horas_asignadas * 2;
                $puntuacion = $puntuacionHabilidades - $penalizacion;

                return max(1, (int)$puntuacion);
            };

            /* Genera bloques de horario para una materia, sin conflictos y sin repetición en el mismo día. */
            $generarBloquesHorarioMejorado = function (array &$profesor, array $materia) {
                $MINUTOS_POR_HORA_ACADEMICA = 45;
                $horasAcademicasRestantes = $materia['horas_semana'];
                $bloquesAsignados = [];
                $horasAcademicasPorBloque = 2; // Un bloque de 90 minutos
                $duracionBloqueMinutos = $horasAcademicasPorBloque * $MINUTOS_POR_HORA_ACADEMICA;

                $dias_con_materia = [];
                $disponibilidad_ordenada = $profesor['disponibilidad'];
                
                usort($disponibilidad_ordenada, function($a, $b) {
                    return (strtotime($a['start_time']) - strtotime($b['start_time']));
                });

                foreach ($disponibilidad_ordenada as $disponibilidad) {
                    if ($horasAcademicasRestantes <= 0) break;
                    
                    $dia = $disponibilidad['day_of_week'];
                    if (in_array($dia, $dias_con_materia)) {
                        continue;
                    }

                    $inicioMinutosDia = strtotime($disponibilidad['start_time']) / 60;
                    $finMinutosDia = strtotime($disponibilidad['end_time']) / 60;

                    for ($i = $inicioMinutosDia; $i + $duracionBloqueMinutos <= $finMinutosDia; $i += $duracionBloqueMinutos) {
                        if ($horasAcademicasRestantes <= 0) break;

                        $horaInicio = date('H:i', $i * 60);
                        $horaFin = date('H:i', ($i + $duracionBloqueMinutos) * 60);
                        
                        $ranuraLibre = true;
                        if (isset($profesor['horario_detallado'][$dia])) {
                            foreach ($profesor['horario_detallado'][$dia] as $claseExistente) {
                                $inicioExistente = strtotime($claseExistente['inicio']) / 60;
                                $finExistente = strtotime($claseExistente['fin']) / 60;
                                if (($i < $finExistente && ($i + $duracionBloqueMinutos) > $inicioExistente)) {
                                    $ranuraLibre = false;
                                    break;
                                }
                            }
                        }

                        if ($ranuraLibre) {
                            $horas_ya_asignadas = array_sum(array_column($profesor['materias_asignadas'], 'horas_semana'));
                            if ($horas_ya_asignadas + $horasAcademicasPorBloque > $profesor['horas_disponibles_semana']) {
                                continue;
                            }
                            
                            $bloquesAsignados[] = "$dia ({$horaInicio} - {$horaFin})";
                            $horasAcademicasRestantes -= $horasAcademicasPorBloque;
                            
                            $profesor['horario_detallado'][$dia][] = [
                                'materia_nombre' => $materia['nombre'],
                                'inicio' => $horaInicio,
                                'fin' => $horaFin
                            ];
                            $dias_con_materia[] = $dia;
                            break;
                        }
                    }
                }
                return $bloquesAsignados;
            };

            // Ejecución del algoritmo principal
            $materias_a_asignar = $materias_procesadas;
            $materias_sin_asignar_final = [];
            $iteracion = 0;
            
            while (!empty($materias_a_asignar) && $iteracion < count($materias_procesadas)) {
                $materia_asignada_en_este_ciclo = false;
                
                uasort($materias_a_asignar, function($a, $b) {
                    return $b['semestre'] <=> $a['semestre'];
                });

                foreach (array_keys($materias_a_asignar) as $materiaId) {
                    $materia = $materias_a_asignar[$materiaId];
                    $mejorPuntuacion = -1;
                    $mejorProfesorId = null;
                    
                    foreach ($profesores_procesados as $profesorId => &$profesor) {
                        $puntuacionActual = $calcularPuntuacion($profesor, $materia);
                        if ($puntuacionActual > $mejorPuntuacion) {
                            $mejorPuntuacion = $puntuacionActual;
                            $mejorProfesorId = $profesorId;
                        }
                    }

                    if ($mejorProfesorId && $mejorPuntuacion > 0) {
                        $bloquesAsignados = $generarBloquesHorarioMejorado($profesores_procesados[$mejorProfesorId], $materia);
                        
                        if (count($bloquesAsignados) >= ceil($materia['horas_semana'] / 2)) {
                            $profesores_procesados[$mejorProfesorId]['materias_asignadas'][$materiaId] = $materia;
                            unset($materias_a_asignar[$materiaId]);
                            $materia_asignada_en_este_ciclo = true;
                        }
                    }
                }
                
                if (!$materia_asignada_en_este_ciclo) {
                    $materias_sin_asignar_final = $materias_a_asignar;
                    break;
                }
                $iteracion++;
            }

            /* ================================================================= */
            /* ===              FIN DE LA LÓGICA DEL ALGORITMO               === */
            /* ================================================================= */

            // Preparar la respuesta final
            $response = [
                "status" => 200,
                "success" => true,
                "data" => [
                    "profesores" => array_values($profesores_procesados),
                    "materias_sin_asignar" => array_values($materias_sin_asignar_final)
                ]
            ];
            
            return $response;

        } catch (PDOException $e) {
            error_log("Error en ctrGenerarHorariosProfesoresSemana: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error del servidor",
                "error" => $e->getMessage()
            ];
        }
    }
}