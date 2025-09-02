<?php

class ControladorGenerarHorarios {

    /*=============================================
    GENERAR HORARIO DE UN PROFESOR INDIVIDUAL
    =============================================*/
    static public function ctrGenerarHorarioProfesorIndividual($profesorId) {
        try {
            // Obtener el profesor por su ID
            $profesor = ModeloProfesores::mdlMostrarProfesores("teachers", "teacher_id", $profesorId);
            if (empty($profesor['data'])) {
                return ["status" => 404, "success" => false, "message" => "Profesor no encontrado."];
            }
            // Se asume que mdlMostrarProfesores devuelve un solo resultado si se le pasa un ID
            $profesor = $profesor['data'];
            
            // Obtener todas las materias y habilidades
            $materias = ModeloMaterias::mdlMostrarMaterias("subjects", null, null);
            if (empty($materias)) {
                return ["status" => 404, "success" => false, "message" => "No se encontraron materias."];
            }

            $habilidades_db = ModeloHabilidades::mdlMostrarHabilidades("skills", null, null);
            if (empty($habilidades_db)) {
                return ["status" => 404, "success" => false, "message" => "No se encontraron habilidades."];
            }
            
            $skills_list = array_column($habilidades_db, 'skill_name', 'skill_id');

            // Pre-procesar datos del profesor seleccionado
            $habilidades_profesor_db = ModeloHabilidades::mdlMostrarHabilidadesProfesores("teacher_skills", "teacher_id", $profesor['teacher_id']);
            $disponibilidad_db = ModeloProfesores::mdlMostrarDisponibilidadesProfesores("teacher_availability", "teacher_id", $profesor['teacher_id']);
            
            $horas_totales = 0;
            $disponibilidad_formato = [];
            foreach ($disponibilidad_db as $disponibilidad) {
                if ($disponibilidad['day_of_week'] === 'Sábado') {
                    continue;
                }
                $horas_totales += (strtotime($disponibilidad['end_time']) - strtotime($disponibilidad['start_time'])) / 3600;
                $disponibilidad_formato[] = $disponibilidad;
            }
            
            $habilidades_profesor_formato = [];
            foreach ($habilidades_profesor_db as $h) {
                $habilidades_profesor_formato[$skills_list[$h['skill_id']]] = $h['stars'];
            }

            $profesor_procesado = [
                'id' => $profesor['teacher_id'],
                'nombre' => $profesor['name'],
                'horas_disponibles_semana' => floor($horas_totales),
                'disponibilidad' => $disponibilidad_formato,
                'habilidades' => $habilidades_profesor_formato,
                'horario_detallado' => []
            ];

            // Pre-procesar todas las materias
            $materias_procesadas = [];
            foreach ($materias as $materia) {
                $habilidades_materia_db = ModeloHabilidades::mdlMostrarMateriasHabilidades("subject_skills", "subject_id", $materia['subject_id']);
                
                $habilidades_materia_formato = [];
                foreach ($habilidades_materia_db as $hr) {
                    $habilidades_materia_formato[$skills_list[$hr['skill_id']]] = $hr['min_stars'];
                }

                $materias_procesadas[$materia['subject_id']] = [
                    'id' => $materia['subject_id'],
                    'nombre' => $materia['name'],
                    'horas_semana' => $materia['duration_hours'],
                    'semestre' => $materia['semester'],
                    'habilidades_requeridas' => $habilidades_materia_formato,
                    'horas_asignadas' => 0
                ];
            }
            
            // Lógica de asignación de horario para un único profesor
            $materias_por_asignar = $materias_procesadas;
            $materias_sin_asignar_final = [];
            $dias_semana_full = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
            $horas_asignadas_en_la_semana = 0;

            // Función para generar un bloque de una hora
            $generarBloqueIndividual = function (&$profesor, $dia_preferido, $materia_id, $horas_asignadas_en_la_semana) {
                $MINUTOS_POR_HORA = 60;
                $RECESO_INICIO = strtotime('09:30');
                $RECESO_FIN = strtotime('10:00');
                
                // Iterar sobre la disponibilidad del profesor para el día preferido
                foreach ($profesor['disponibilidad'] as $disponibilidad) {
                    if ($disponibilidad['day_of_week'] === $dia_preferido) {
                        $inicioMinutosDia = strtotime($disponibilidad['start_time']) / 60;
                        $finMinutosDia = strtotime($disponibilidad['end_time']) / 60;

                        for ($minutoActual = $inicioMinutosDia; $minutoActual < $finMinutosDia; $minutoActual += $MINUTOS_POR_HORA) {
                            $minutoFinClase = $minutoActual + $MINUTOS_POR_HORA;

                            // Verificar si el bloque se solapa con el receso
                            if ($minutoActual * 60 >= $RECESO_INICIO && $minutoActual * 60 < $RECESO_FIN) {
                                continue;
                            }

                            // Verificar si el bloque ya está ocupado por otra clase
                            $ranuraLibre = true;
                            if (isset($profesor['horario_detallado'][$dia_preferido])) {
                                foreach ($profesor['horario_detallado'][$dia_preferido] as $claseExistente) {
                                    $inicioExistente = strtotime($claseExistente['inicio']) / 60;
                                    $finExistente = strtotime($claseExistente['fin']) / 60;
                                    if (($minutoActual * 60 < $finExistente * 60) && ($minutoFinClase * 60 > $inicioExistente * 60)) {
                                        $ranuraLibre = false;
                                        break;
                                    }
                                }
                            }

                            if ($ranuraLibre) {
                                $horaInicio = date('H:i', $minutoActual * 60);
                                $horaFin = date('H:i', $minutoFinClase * 60);
                                return ['dia' => $dia_preferido, 'inicio' => $horaInicio, 'fin' => $horaFin];
                            }
                        }
                    }
                }
                return null;
            };

            // Ordenar materias por semestre para priorizar las más altas
            uasort($materias_por_asignar, function($a, $b) {
                return $b['semestre'] <=> $a['semestre'];
            });

            foreach ($materias_por_asignar as $materiaId => &$materia) {
                // Verificar si el profesor puede dar la materia
                $habilidades_materia = $materia['habilidades_requeridas'];
                $puede_dar_materia = true;
                foreach ($habilidades_materia as $habilidad => $minimo) {
                    if (!isset($profesor_procesado['habilidades'][$habilidad]) || $profesor_procesado['habilidades'][$habilidad] < $minimo) {
                        $puede_dar_materia = false;
                        break;
                    }
                }
                if (!$puede_dar_materia) {
                    $materias_sin_asignar_final[] = $materia;
                    continue;
                }

                $horas_restantes = $materia['horas_semana'];
                while ($horas_restantes > 0 && $horas_asignadas_en_la_semana < $profesor_procesado['horas_disponibles_semana']) {
                    $dia_asignado = null;
                    shuffle($dias_semana_full);
                    
                    foreach ($dias_semana_full as $dia) {
                        // REGLA: No repetir la misma materia en el mismo día
                        $materia_ya_asignada_en_dia = false;
                        if (isset($profesor_procesado['horario_detallado'][$dia])) {
                            foreach ($profesor_procesado['horario_detallado'][$dia] as $clase) {
                                if ($clase['materia_nombre'] === $materia['nombre']) {
                                    $materia_ya_asignada_en_dia = true;
                                    break;
                                }
                            }
                        }

                        if (!$materia_ya_asignada_en_dia) {
                            $dia_asignado = $dia;
                            break;
                        }
                    }

                    if ($dia_asignado) {
                        $bloque = $generarBloqueIndividual($profesor_procesado, $dia_asignado, $materiaId, $horas_asignadas_en_la_semana);
                        
                        if ($bloque) {
                            $profesor_procesado['horario_detallado'][$bloque['dia']][] = [
                                'materia_nombre' => $materia['nombre'],
                                'semestre' => $materia['semestre'],
                                'inicio' => $bloque['inicio'],
                                'fin' => $bloque['fin']
                            ];
                            $horas_restantes--;
                            $horas_asignadas_en_la_semana++;
                        } else {
                            // No se encontró un bloque en el día, intentar con otro día
                            break;
                        }
                    } else {
                        // No se encontró un día disponible para esta materia, pasar a la siguiente
                        break;
                    }
                }

                if ($horas_restantes > 0) {
                    $materias_sin_asignar_final[] = $materia;
                }
            }
            
            // Llenar los espacios libres y agregar el receso en todos los días
            foreach ($dias_semana_full as $dia) {
                $disponibilidad_dia = array_filter($profesor_procesado['disponibilidad'], function($d) use ($dia) {
                    return $d['day_of_week'] === $dia;
                });
                
                if (empty($disponibilidad_dia)) continue;

                $inicio_disponibilidad = strtotime(array_values($disponibilidad_dia)[0]['start_time']);
                $fin_disponibilidad = strtotime(array_values($disponibilidad_dia)[0]['end_time']);

                $horarioDelDia = $profesor_procesado['horario_detallado'][$dia] ?? [];
                
                // Agregar el bloque de receso si no existe
                $receso_existe = false;
                foreach ($horarioDelDia as $clase) {
                    if ($clase['inicio'] === '09:30' && $clase['fin'] === '10:00') {
                        $receso_existe = true;
                        break;
                    }
                }
                if (!$receso_existe) {
                    $horarioDelDia[] = [
                        'materia_nombre' => 'Receso',
                        'semestre' => '',
                        'inicio' => '09:30',
                        'fin' => '10:00'
                    ];
                }

                usort($horarioDelDia, function($a, $b) {
                    return strtotime($a['inicio']) <=> strtotime($b['inicio']);
                });

                $horarioFinalDelDia = [];
                $lastTime = $inicio_disponibilidad;

                foreach ($horarioDelDia as $clase) {
                    $claseInicio = strtotime($clase['inicio']);
                    $claseFin = strtotime($clase['fin']);

                    // Si hay un hueco, llenarlo con "Horario Libre"
                    if ($claseInicio > $lastTime) {
                        $horarioFinalDelDia[] = [
                            'materia_nombre' => 'Horario Libre',
                            'semestre' => '',
                            'inicio' => date('H:i', $lastTime),
                            'fin' => date('H:i', $claseInicio)
                        ];
                    }
                    
                    $horarioFinalDelDia[] = $clase;
                    $lastTime = $claseFin;
                }
                
                // Llenar el espacio al final del día
                if ($lastTime < $fin_disponibilidad) {
                     $horarioFinalDelDia[] = [
                        'materia_nombre' => 'Horario Libre',
                        'semestre' => '',
                        'inicio' => date('H:i', $lastTime),
                        'fin' => date('H:i', $fin_disponibilidad)
                    ];
                }
                
                $profesor_procesado['horario_detallado'][$dia] = $horarioFinalDelDia;
            }
            
            $response = [
                "status" => 200,
                "success" => true,
                "data" => [
                    "profesor" => $profesor_procesado,
                    "materias_sin_asignar" => array_values($materias_sin_asignar_final)
                ]
            ];
            
            return $response;

        } catch (PDOException $e) {
            error_log("Error en ctrGenerarHorarioProfesorIndividual: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error del servidor",
                "error" => $e->getMessage()
            ];
        }
    }

    static public function ctrGenerarHorarioSabado($profesorId) {
        try {
            // Obtener el profesor por su ID
            $profesor = ModeloProfesores::mdlMostrarProfesores("teachers", "teacher_id", $profesorId);
            if (empty($profesor['data'])) {
                return ["status" => 404, "success" => false, "message" => "Profesor no encontrado."];
            }
            $profesor = $profesor['data'];

            // Obtener materias y habilidades
            $materias = ModeloMaterias::mdlMostrarMaterias("subjects", null, null);
            if (empty($materias)) {
                return ["status" => 404, "success" => false, "message" => "No se encontraron materias."];
            }

            $habilidades_db = ModeloHabilidades::mdlMostrarHabilidades("skills", null, null);
            if (empty($habilidades_db)) {
                return ["status" => 404, "success" => false, "message" => "No se encontraron habilidades."];
            }
            
            $skills_list = array_column($habilidades_db, 'skill_name', 'skill_id');

            // Pre-procesar datos del profesor
            $habilidades_profesor_db = ModeloHabilidades::mdlMostrarHabilidadesProfesores("teacher_skills", "teacher_id", $profesor['teacher_id']);
            $disponibilidad_db = ModeloProfesores::mdlMostrarDisponibilidadesProfesores("teacher_availability", "teacher_id", $profesor['teacher_id']);
            
            $horas_totales_sabado = 0;
            $disponibilidad_sabado = [];
            foreach ($disponibilidad_db as $disponibilidad) {
                if ($disponibilidad['day_of_week'] === 'Sábado') {
                    $horas_totales_sabado += (strtotime($disponibilidad['end_time']) - strtotime($disponibilidad['start_time'])) / 3600;
                    $disponibilidad_sabado[] = $disponibilidad;
                }
            }
            
            $habilidades_profesor_formato = [];
            foreach ($habilidades_profesor_db as $h) {
                $habilidades_profesor_formato[$skills_list[$h['skill_id']]] = $h['stars'];
            }

            $profesor_procesado = [
                'id' => $profesor['teacher_id'],
                'nombre' => $profesor['name'],
                'horas_disponibles_semana' => floor($horas_totales_sabado),
                'disponibilidad' => $disponibilidad_sabado,
                'habilidades' => $habilidades_profesor_formato,
                'horario_detallado' => []
            ];
            
            if (empty($profesor_procesado['disponibilidad'])) {
                return ["status" => 404, "success" => false, "message" => "El profesor no tiene disponibilidad registrada para el Sábado."];
            }

            // Pre-procesar materias
            $materias_procesadas = [];
            foreach ($materias as $materia) {
                $habilidades_materia_db = ModeloHabilidades::mdlMostrarMateriasHabilidades("subject_skills", "subject_id", $materia['subject_id']);
                
                $habilidades_materia_formato = [];
                foreach ($habilidades_materia_db as $hr) {
                    $habilidades_materia_formato[$skills_list[$hr['skill_id']]] = $hr['min_stars'];
                }

                $materias_procesadas[$materia['subject_id']] = [
                    'id' => $materia['subject_id'],
                    'nombre' => $materia['name'],
                    'horas_semana' => $materia['duration_hours'],
                    'semestre' => $materia['semester'],
                    'habilidades_requeridas' => $habilidades_materia_formato,
                    'horas_asignadas' => 0
                ];
            }
            
            $materias_por_asignar = $materias_procesadas;
            $materias_sin_asignar_final = [];
            $dia_sabado = ['Sábado'];
            $horas_asignadas_en_sabado = 0;

            // Función para generar un bloque de una hora
            $generarBloqueIndividual = function (&$profesor, $dia_preferido, $materia_id) {
                $MINUTOS_POR_HORA = 60;
                $RECESO_INICIO = strtotime('11:30'); // Receso para el turno de Sábado
                $RECESO_FIN = strtotime('12:00');
                
                foreach ($profesor['disponibilidad'] as $disponibilidad) {
                    if ($disponibilidad['day_of_week'] === $dia_preferido) {
                        $inicioMinutosDia = strtotime($disponibilidad['start_time']) / 60;
                        $finMinutosDia = strtotime($disponibilidad['end_time']) / 60;

                        for ($minutoActual = $inicioMinutosDia; $minutoActual < $finMinutosDia; $minutoActual += $MINUTOS_POR_HORA) {
                            $minutoFinClase = $minutoActual + $MINUTOS_POR_HORA;

                            if ($minutoActual * 60 >= $RECESO_INICIO && $minutoActual * 60 < $RECESO_FIN) {
                                continue;
                            }

                            $ranuraLibre = true;
                            if (isset($profesor['horario_detallado'][$dia_preferido])) {
                                foreach ($profesor['horario_detallado'][$dia_preferido] as $claseExistente) {
                                    $inicioExistente = strtotime($claseExistente['inicio']) / 60;
                                    $finExistente = strtotime($claseExistente['fin']) / 60;
                                    if (($minutoActual * 60 < $finExistente * 60) && ($minutoFinClase * 60 > $inicioExistente * 60)) {
                                        $ranuraLibre = false;
                                        break;
                                    }
                                }
                            }

                            if ($ranuraLibre) {
                                $horaInicio = date('H:i', $minutoActual * 60);
                                $horaFin = date('H:i', $minutoFinClase * 60);
                                return ['dia' => $dia_preferido, 'inicio' => $horaInicio, 'fin' => $horaFin];
                            }
                        }
                    }
                }
                return null;
            };

            uasort($materias_por_asignar, function($a, $b) {
                return $b['semestre'] <=> $a['semestre'];
            });

            foreach ($materias_por_asignar as $materiaId => &$materia) {
                $habilidades_materia = $materia['habilidades_requeridas'];
                $puede_dar_materia = true;
                foreach ($habilidades_materia as $habilidad => $minimo) {
                    if (!isset($profesor_procesado['habilidades'][$habilidad]) || $profesor_procesado['habilidades'][$habilidad] < $minimo) {
                        $puede_dar_materia = false;
                        break;
                    }
                }
                if (!$puede_dar_materia) {
                    $materias_sin_asignar_final[] = $materia;
                    continue;
                }
                
                $horas_restantes = $materia['horas_semana'];
                while ($horas_restantes > 0 && $horas_asignadas_en_sabado < $profesor_procesado['horas_disponibles_semana']) {
                    $bloque = $generarBloqueIndividual($profesor_procesado, 'Sábado', $materiaId);
                    
                    if ($bloque) {
                        $profesor_procesado['horario_detallado']['Sábado'][] = [
                            'materia_nombre' => $materia['nombre'],
                            'semestre' => $materia['semestre'],
                            'inicio' => $bloque['inicio'],
                            'fin' => $bloque['fin']
                        ];
                        $horas_restantes--;
                        $horas_asignadas_en_sabado++;
                    } else {
                        break;
                    }
                }

                if ($horas_restantes > 0) {
                    $materias_sin_asignar_final[] = $materia;
                }
            }
            
            // Llenar espacios libres y agregar el receso
            $dia = 'Sábado';
            $disponibilidad_dia = array_filter($profesor_procesado['disponibilidad'], function($d) use ($dia) {
                return $d['day_of_week'] === $dia;
            });
            
            if (!empty($disponibilidad_dia)) {
                $inicio_disponibilidad = strtotime(array_values($disponibilidad_dia)[0]['start_time']);
                $fin_disponibilidad = strtotime(array_values($disponibilidad_dia)[0]['end_time']);

                $horarioDelDia = $profesor_procesado['horario_detallado'][$dia] ?? [];
                
                $receso_existe = false;
                foreach ($horarioDelDia as $clase) {
                    if ($clase['inicio'] === '11:30' && $clase['fin'] === '12:00') {
                        $receso_existe = true;
                        break;
                    }
                }
                if (!$receso_existe) {
                    $horarioDelDia[] = [
                        'materia_nombre' => 'Receso',
                        'semestre' => '',
                        'inicio' => '11:30',
                        'fin' => '12:00'
                    ];
                }

                usort($horarioDelDia, function($a, $b) {
                    return strtotime($a['inicio']) <=> strtotime($b['inicio']);
                });

                $horarioFinalDelDia = [];
                $lastTime = $inicio_disponibilidad;

                foreach ($horarioDelDia as $clase) {
                    $claseInicio = strtotime($clase['inicio']);
                    $claseFin = strtotime($clase['fin']);

                    if ($claseInicio > $lastTime) {
                        $horarioFinalDelDia[] = [
                            'materia_nombre' => 'Horario Libre',
                            'semestre' => '',
                            'inicio' => date('H:i', $lastTime),
                            'fin' => date('H:i', $claseInicio)
                        ];
                    }
                    
                    $horarioFinalDelDia[] = $clase;
                    $lastTime = $claseFin;
                }
                
                if ($lastTime < $fin_disponibilidad) {
                    $horarioFinalDelDia[] = [
                        'materia_nombre' => 'Horario Libre',
                        'semestre' => '',
                        'inicio' => date('H:i', $lastTime),
                        'fin' => date('H:i', $fin_disponibilidad)
                    ];
                }
                
                $profesor_procesado['horario_detallado'][$dia] = $horarioFinalDelDia;
            }
            
            $response = [
                "status" => 200,
                "success" => true,
                "data" => [
                    "profesor" => $profesor_procesado,
                    "materias_sin_asignar" => array_values($materias_sin_asignar_final)
                ]
            ];
            
            return $response;

        } catch (PDOException $e) {
            error_log("Error en ctrGenerarHorarioSabado: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error del servidor",
                "error" => $e->getMessage()
            ];
        }
    }
}