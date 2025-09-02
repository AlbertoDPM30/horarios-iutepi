<?php

class ControladorAsignacion {

    /*=============================================
    MOSTRAR MATERIAS ELEGIBLES PARA UN PROFESOR
    =============================================*/
    public static function ctrMostrarMateriasElegibles($profesorId) {
        if (!isset($profesorId) || !is_numeric($profesorId)) {
            return [
                "status" => 400,
                "success" => false,
                "message" => "ID de profesor inválido."
            ];
        }

        try {
            $habilidades_profesor_db = ModeloHabilidades::mdlMostrarHabilidadesProfesores("teacher_skills", "teacher_id", $profesorId);
            
            if (!is_array($habilidades_profesor_db) || empty($habilidades_profesor_db)) {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "El profesor no tiene habilidades registradas.",
                    "data" => []
                ];
            }

            $habilidadesProfesor = [];
            foreach ($habilidades_profesor_db as $h) {
                $habilidadesProfesor[$h['skill_id']] = $h['stars'];
            }

            $materias = ModeloMaterias::mdlMostrarMaterias("subjects", null, null);
            
            if (!is_array($materias) || empty($materias)) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "No se encontraron materias."
                ];
            }

            $materiasElegibles = [];

            foreach ($materias as $materia) {
                // Validación para evitar mostrar materias ya asignadas a CUALQUIER profesor
                if (isset($materia['is_assigned']) && $materia['is_assigned'] == 1) {
                    continue;
                }

                $habilidades_materia_db = ModeloHabilidades::mdlMostrarMateriasHabilidades("subject_skills", "subject_id", $materia['subject_id']);
                
                $esElegible = true;
                
                if (!is_array($habilidades_materia_db) || empty($habilidades_materia_db)) {
                    $materiasElegibles[] = $materia;
                    continue;
                }

                foreach ($habilidades_materia_db as $habilidadRequerida) {
                    $skillIdRequerido = $habilidadRequerida['skill_id'];
                    $minStarsRequeridas = $habilidadRequerida['min_stars'];

                    if (!isset($habilidadesProfesor[$skillIdRequerido]) || $habilidadesProfesor[$skillIdRequerido] < $minStarsRequeridas) {
                        $esElegible = false;
                        break;
                    }
                }
                
                if ($esElegible) {
                    $materiasElegibles[] = $materia;
                }
            }

            return [
                "status" => 200,
                "success" => true,
                "message" => "Materias elegibles para el profesor.",
                "data" => $materiasElegibles
            ];

        } catch (Exception $e) {
            error_log("Error en ctrMostrarMateriasElegibles: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error del servidor: " . $e->getMessage()
            ];
        }
    }


    /*=============================================
    GUARDAR MATERIAS ASIGNADAS A UN PROFESOR
    =============================================*/
    public static function ctrGuardarAsignaciones($profesorId, $subjectIds) {
        try {
            if (!is_array($subjectIds) || empty($subjectIds)) {
                return ["status" => 400, "success" => false, "message" => "No se seleccionaron materias."];
            }

            $assignedSubjectsCount = 0;

            foreach ($subjectIds as $subjectId) {
                // Validar si la materia ya está asignada a este profesor
                $asignacionExistente = ModeloProfesores::mdlVerificarAsignacionExistente("teacher_subject_assignments", $profesorId, $subjectId);
                
                if ($asignacionExistente) {
                    // Si ya existe la asignación, la ignoramos y pasamos a la siguiente
                    continue;
                }

                $datosAsignacion = [
                    "teacher_id" => $profesorId,
                    "subject_id" => $subjectId
                ];
                
                $resultado = ModeloProfesores::mdlCrearAsignacionMateriaProfesor("teacher_subject_assignments", $datosAsignacion);

                if ($resultado === "ok") {
                    $assignedSubjectsCount++;

                    // Actualizar el campo 'is_assigned' en la tabla 'subjects'
                    $datosMateria = [
                        "subject_id" => $subjectId,
                        "is_assigned" => 1
                    ];

                    ModeloMaterias::mdlEditarMateria("subjects", $datosMateria);
                }
            }

            return [
                "status" => 200,
                "success" => true,
                "message" => "Se asignaron " . $assignedSubjectsCount . " materias con éxito."
            ];

        } catch (Exception $e) {
            error_log("Error al guardar asignaciones: " . $e->getMessage());
            return ["status" => 500, "success" => false, "message" => "Error del servidor."];
        }
    }
}
