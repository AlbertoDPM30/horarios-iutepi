<?php

require_once "modelos/conexion.php";

class ControladorHorario {

    /*=============================================
    MOSTRAR HORARIOS GUARDADOS DE UN PROFESOR
    =============================================*/
    public static function ctrMostrarHorarios($teacherId = null) {
        if ($teacherId === null) {
            return [
                "status" => 400,
                "success" => false,
                "message" => "El teacher_id es requerido."
            ];
        }

        require_once "modelos/profesores.modelo.php";
        
        $materiasAsignadas = ModeloProfesores::mdlMostrarMateriasProfesores("teacher_subject_assignments", "teacher_id", $teacherId);
        
        if (empty($materiasAsignadas)) {
            return [
                "status" => 200,
                "success" => true,
                "message" => "No hay materias asignadas a este profesor.",
                "data" => []
            ];
        }

        $assignmentIds = array_column($materiasAsignadas, 'assignment_id');
        
        if (empty($assignmentIds)) {
            return [
                "status" => 200,
                "success" => true,
                "message" => "No hay asignaciones.",
                "data" => []
            ];
        }

        $placeholders = implode(',', array_fill(0, count($assignmentIds), '?'));
        
        try {
            $stmt = Conexion::conectar()->prepare("SELECT * FROM teacher_schedule WHERE teacher_subject_assignment_id IN ($placeholders) ORDER BY day_of_week, start_time");
            $stmt->execute($assignmentIds);
            $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                "status" => 200,
                "success" => true,
                "message" => "Horarios obtenidos correctamente.",
                "data" => $horarios
            ];
        } catch (PDOException $e) {
            error_log("Error en ctrMostrarHorarios: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error al obtener los horarios."
            ];
        }
    }

    /*=============================================
    ELIMINAR UN HORARIO
    =============================================*/
    public static function ctrEliminarHorario($scheduleId = null) {
        if ($scheduleId === null) {
            return [
                "status" => 400,
                "success" => false,
                "message" => "El schedule_id es requerido."
            ];
        }

        $resultado = ModeloHorario::mdlEliminarHorario("teacher_schedule", $scheduleId);
        
        if ($resultado === "ok") {
            return [
                "status" => 200,
                "success" => true,
                "message" => "Horario eliminado correctamente."
            ];
        } else {
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error al eliminar el horario."
            ];
        }
    }

    /*=============================================
    MOSTRAR MATERIAS ASIGNADAS A UN PROFESOR
    =============================================*/
    public static function ctrMostrarMateriasAsignadas($profesorId) {
        $materiasAsignadas =ModeloProfesores::mdlMostrarMateriasProfesores("teacher_subject_assignments", "teacher_id", $profesorId);

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
    public static function ctrConfirmarHorario($horario, $teacherId = null) {
        if (empty($horario)) {
            return [
                "status" => 400,
                "success" => false,
                "message" => "No hay datos de horario para guardar."
            ];
        }

        if ($teacherId !== null) {
            ModeloHorario::mdlEliminarHorariosProfesor("teacher_schedule", $teacherId);
        }

        $exito = true;
        $errores = [];
        
        foreach ($horario as $slot) {
            $assignmentId = $slot['assignment_id'] ?? $slot['teacher_subject_assignment_id'] ?? null;
            
            if ($assignmentId === null) {
                $exito = false;
                $errores[] = "Falta assignment_id";
                continue;
            }
            
            $dayOfWeek = $slot['day_of_week'];
            $startTime = $slot['start_time'];
            $endTime = $slot['end_time'];
            
            if ($dayOfWeek === "Sábado") {
                list($h, $m) = sscanf($startTime, "%d:%d");
                $horaInt = intval($h);
                $endHour = $horaInt + 1;
                $endTime = sprintf("%02d%s", $endHour, substr($startTime, 2));
            }
            
            $datos = [
                'teacher_subject_assignment_id' => $assignmentId,
                'day_of_week' => $dayOfWeek,
                'start_time' => $startTime,
                'end_time' => $endTime
            ];
            $resultado = ModeloHorario::mdlCrearHorario("teacher_schedule", $datos);
            if ($resultado !== "ok") {
                $exito = false;
                $errores[] = "Error al guardar: $assignmentId";
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
                "message" => "Hubo un error al guardar el horario.",
                "errors" => $errores
            ];
        }
    }
}
