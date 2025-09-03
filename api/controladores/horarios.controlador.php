<?php

class ControladorHorario {

    /*=============================================
    MOSTRAR MATERIAS ASIGNADAS A UN PROFESOR
    =============================================*/
    public static function ctrMostrarMateriasAsignadas($profesorId) {
        $materiasAsignadas = ModeloProfesores::mdlMostrarMateriasProfesores("teacher_subject_assignments", "teacher_id", $profesorId);

        return [
            "status" => 200,
            "success" => true,
            "message" => "Materias asignadas al profesor.",
            "data" => $materiasAsignadas
        ];
    }

    /*=============================================
    GENERAR HORARIO PROVISIONAL
    =============================================*/
    public static function ctrGenerarHorario($profesorId) {
        $disponibilidad = ModeloProfesores::mdlMostrarDisponibilidadesProfesores("teacher_availability", "teacher_id", $profesorId);
        $materiasAsignadas = ModeloProfesores::mdlMostrarMateriasProfesores("teacher_subject_assignments", "teacher_id", $profesorId);

        if (empty($disponibilidad) || empty($materiasAsignadas)) {
            return [
                "status" => 404,
                "success" => false,
                "message" => "No hay disponibilidad o materias asignadas para generar un horario."
            ];
        }

        $horarioProvisional = [];
        $diasOcupados = [];
        $subjectsUsedThisDay = [];

        foreach ($disponibilidad as $disp) {
            $dayOfWeek = $disp['day_of_week'];
            $startTime = strtotime($disp['start_time']);
            $endTime = strtotime($disp['end_time']);

            for ($time = $startTime; $time < $endTime; $time += 3600) { // Bloques de 1 hora
                foreach ($materiasAsignadas as $key => $materia) {
                    $subjectId = $materia['subject_id'];
                    
                    // Asegurar que la materia no se imparta más de una vez por día
                    if (!isset($subjectsUsedThisDay[$dayOfWeek]) || !in_array($subjectId, $subjectsUsedThisDay[$dayOfWeek])) {
                        
                        $materiaInfo = ModeloMaterias::mdlMostrarMaterias("subjects", "subject_id", $subjectId);
                        
                        $horarioProvisional[] = [
                            'teacher_subject_assignment_id' => $materia['assignment_id'],
                            'day_of_week' => $dayOfWeek,
                            'start_time' => date('H:i:s', $time),
                            'end_time' => date('H:i:s', $time + 3600),
                            'name' => $materiaInfo['name']
                        ];
                        
                        // Marcar el día y la materia como ocupados
                        $diasOcupados[$dayOfWeek][] = date('H:i:s', $time);
                        $subjectsUsedThisDay[$dayOfWeek][] = $subjectId;
                        // unset($materiasAsignadas[$key]); // Eliminar la materia para no volver a usarla en esta iteración
                        break;
                    }
                }
            }
        }

        return [
            "status" => 200,
            "success" => true,
            "message" => "Horario provisional generado con éxito.",
            "data" => $horarioProvisional
        ];
    }

    /*=============================================
    CONFIRMAR Y GUARDAR HORARIO FINAL
    =============================================*/
    public static function ctrConfirmarHorario($horario) {
        if (empty($horario)) {
            return [
                "status" => 400,
                "success" => false,
                "message" => "No hay datos de horario para guardar."
            ];
        }

        $exito = true;
        foreach ($horario as $slot) {
            $datos = [
                'teacher_subject_assignment_id' => $slot['teacher_subject_assignment_id'],
                'day_of_week' => $slot['day_of_week'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time']
            ];
            $resultado = ModeloHorario::mdlCrearHorario("teacher_schedule", $datos);
            if ($resultado !== "ok") {
                $exito = false;
            }
        }

        if ($exito) {
            return [
                "status" => 200,
                "success" => true,
                "message" => "Horario guardado con éxito."
            ];
        } else {
            return [
                "status" => 500,
                "success" => false,
                "message" => "Hubo un error al guardar el horario."
            ];
        }
    }
}
