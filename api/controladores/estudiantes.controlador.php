<?php

class ControladorEstudiantes {

    /*=============================================
    MOSTRAR ESTUDIANTE(S) (GET)
    =============================================*/
    static public function ctrMostrarEstudiantes($item = null, $valor = null) {
        try {
            $respuesta = ModeloEstudiantes::mdlMostrarEstudiantes("students", $item, $valor);
            
            if ($item !== null && $valor !== null && !$respuesta) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Estudiante no encontrado."
                ];
            }

            return [
                "status" => 200,
                "success" => true,
                "data" => $respuesta
            ];
            
        } catch (Exception $e) {
            error_log("Error en ctrMostrarEstudiantes: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    CREAR ESTUDIANTE (POST)
    =============================================*/
    static public function ctrCrearEstudiante($datos) { 
        try {

            $datosModelo = [
                'name' => $datos['name'],
                'ci_code' => $datos['ci_code'],
                'phone_number' => $datos['phone_number'] ?? null, 
                'email' => $datos['email'] ?? null 
            ];

            $respuesta = ModeloEstudiantes::mdlCrearEstudiante("students", $datosModelo);

            if ($respuesta === "ok") {
                return [
                    "status" => 201, 
                    "success" => true,
                    "message" => "Estudiante creado exitosamente."
                ];
            } else {
                error_log("Error en ModeloEstudiantes::mdlCrearEstudiante: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo crear el Estudiante. Inténtalo de nuevo."
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrCrearEstudiante: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    EDITAR ESTUDIANTE (PUT)
    =============================================*/
    static public function ctrEditarEstudiante($datos) { 
        try {
            $editarIdEstudiante = $datos['student_id']; 

            // Validar si el Estudiante existe antes de editar
            $existenciaEstudiante = ModeloEstudiantes::mdlMostrarEstudiantes("students", "student_id", $editarIdEstudiante);
            if (!$existenciaEstudiante['success'] || !$existenciaEstudiante['data']) { 
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Estudiante no encontrado para actualizar."
                ];
            }

            // Preparar datos para el modelo
            $datosModelo = [
                'student_id' => $editarIdEstudiante
            ];
            if (isset($datos['name'])) $datosModelo['name'] = $datos['name'];
            if (isset($datos['ci_code'])) $datosModelo['ci_code'] = $datos['ci_code'];

            $respuesta = ModeloEstudiantes::mdlEditarEstudiante("students", $datosModelo);

            if ($respuesta === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Estudiante actualizado correctamente."
                ];
            } else if ($respuesta === "no_changes") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Estudiante actualizado correctamente, aunque no se detectaron cambios en los datos enviados (ID válido)."
                ];
            } else {
                error_log("Error en ModeloEstudiantes::mdlEditarEstudiante: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo actualizar el Estudiante. Inténtalo de nuevo."
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrEditarEstudiante: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    ELIMINAR ESTUDIANTE (DELETE)
    =============================================*/
    static public function ctrEliminarEstudiante($student_id) { 
        try {
            // Validar si el Estudiante existe antes de eliminar
            $existenciaEstudiante = ModeloEstudiantes::mdlMostrarEstudiantes("students", "student_id", $student_id);
            if (!$existenciaEstudiante['success'] || !$existenciaEstudiante['data']) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Estudiante no encontrado para eliminar."
                ];
            }

            $respuesta = ModeloEstudiantes::mdlEliminarEstudiante("students", $student_id);

            if ($respuesta === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Estudiante eliminado correctamente."
                ];
            } else {
                error_log("Error en ModeloEstudiantes::mdlEliminarEstudiante: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo eliminar el Estudiante. Inténtalo de nuevo."
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrEliminarEstudiante: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }
}