<?php

class ControladorProfesores {

    /*=============================================
    MOSTRAR PROFESOR(ES) (GET)
    =============================================*/
    static public function ctrMostrarProfesores($item = null, $valor = null) {
        try {
            $respuesta = ModeloProfesores::mdlMostrarProfesores("teachers", $item, $valor);
            
            if ($item !== null && $valor !== null && !$respuesta) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Profesor no encontrado."
                ];
            }

            return $respuesta;
            
        } catch (Exception $e) {
            error_log("Error en ctrMostrarProfesores: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    CREAR PROFESOR (POST)
    =============================================*/
    static public function ctrCrearProfesor($datos) { 
        try {

            $datosModelo = [
                'name' => $datos['name'],
                'ci_code' => $datos['ci_code'],
                'phone_number' => $datos['phone_number'] ?? null, 
                'email' => $datos['email'] ?? null 
            ];

            $respuesta = ModeloProfesores::mdlCrearProfesor("teachers", $datosModelo);

            if ($respuesta === "ok") {
                return [
                    "status" => 201, 
                    "success" => true,
                    "message" => "Profesor creado exitosamente."
                ];
            } else {
                error_log("Error en ModeloProfesores::mdlCrearProfesor: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo crear el profesor. Inténtalo de nuevo."
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrCrearProfesor: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    EDITAR PROFESOR (PUT)
    =============================================*/
    static public function ctrEditarProfesor($datos) { 
        try {
            $editarIdProfesor = $datos['teacher_id']; 

            // Validar si el profesor existe antes de editar
            $profesorExistencia = ModeloProfesores::mdlMostrarProfesores("teachers", "teacher_id", $editarIdProfesor);
            if (!$profesorExistencia['success'] || !$profesorExistencia['data']) { 
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Profesor no encontrado para actualizar."
                ];
            }

            // Preparar datos para el modelo
            $datosModelo = [
                'teacher_id' => $editarIdProfesor
            ];
            if (isset($datos['name'])) $datosModelo['name'] = $datos['name'];
            if (isset($datos['ci_code'])) $datosModelo['ci_code'] = $datos['ci_code'];
            if (isset($datos['phone_number'])) $datosModelo['phone_number'] = $datos['phone_number'];
            if (isset($datos['email'])) $datosModelo['email'] = $datos['email'];

            $respuesta = ModeloProfesores::mdlEditarProfesor("teachers", $datosModelo);

            if ($respuesta === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Profesor actualizado correctamente."
                ];
            } else if ($respuesta === "no_changes") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Profesor actualizado correctamente, aunque no se detectaron cambios en los datos enviados (ID válido)."
                ];
            } else {
                error_log("Error en ModeloProfesores::mdlEditarProfesor: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo actualizar el profesor. Inténtalo de nuevo."
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrEditarProfesor: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    ELIMINAR PROFESOR (DELETE)
    =============================================*/
    static public function ctrEliminarProfesor($teacher_id) { 
        try {
            // Validar si el profesor existe antes de eliminar
            $profesorExistencia = ModeloProfesores::mdlMostrarProfesores("teachers", "teacher_id", $teacher_id);
            if (!$profesorExistencia['success'] || !$profesorExistencia['data']) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Profesor no encontrado para eliminar."
                ];
            }

            $respuesta = ModeloProfesores::mdlEliminarProfesor("teachers", $teacher_id);

            if ($respuesta === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Profesor eliminado correctamente."
                ];
            } else {
                error_log("Error en ModeloProfesores::mdlEliminarProfesor: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo eliminar el profesor. Inténtalo de nuevo."
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrEliminarProfesor: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }
}