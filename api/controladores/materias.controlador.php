<?php

class ControladorMaterias {

    /*=============================================
    MOSTRAR MATERIA(S) (GET)
    =============================================*/
    static public function ctrMostrarMaterias($item = null, $valor = null) {
        try {
            $respuesta = ModeloMaterias::mdlMostrarMaterias("subjects", $item, $valor);
            
            if ($item !== null && $valor !== null && !$respuesta) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Materia no encontrada."
                ];
            }

            return [
                "status" => 200,
                "success" => true,
                "data" => $respuesta
            ];
            
        } catch (Exception $e) {
            error_log("Error en ctrMostrarMaterias: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    CREAR MATERIA (POST)
    =============================================*/
    static public function ctrCrearMateria($datos) { 
        try {

            $datosModelo = [
                'name' => $datos['name'],
                'duration_hours' => $datos['duration_hours'],
                'semester' => $datos['semester']
            ];

            $respuesta = ModeloMaterias::mdlCrearMateria("subjects", $datosModelo);

            if ($respuesta === "ok") {
                return [
                    "status" => 201,
                    "success" => true,
                    "message" => "Materia creada exitosamente."
                ];
            } else {
                error_log("Error en ModeloMaterias::mdlCrearMateria: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo crear la materia. Inténtalo de nuevo."
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrCrearMateria: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    EDITAR MATERIA (PUT)
    =============================================*/
    static public function ctrEditarMateria($datos) { 
        try {
            $editarIdMateria = $datos['subject_id']; // ID es obligatorio y ya validado en el router

            // Validar que la materia exista antes de intentar actualizar
            $materiaExistente = ModeloMaterias::mdlMostrarMaterias("subjects", "subject_id", $editarIdMateria);
            if (!$materiaExistente) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Materia no encontrada para actualizar."
                ];
            }

            // Prepara los datos para el modelo
            $datosModelo = [
                'subject_id' => $editarIdMateria
            ];
            if (isset($datos['name'])) $datosModelo['name'] = $datos['name'];
            if (isset($datos['duration_hours'])) $datosModelo['duration_hours'] = $datos['duration_hours'];
            if (isset($datos['semester'])) $datosModelo['semester'] = $datos['semester'];
            if (isset($datos['is_assigned'])) $datosModelo['is_assigned'] = $datos['is_assigned'];

            $respuesta = ModeloMaterias::mdlEditarMateria("subjects", $datosModelo);

            if ($respuesta === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Materia actualizada correctamente."
                ];
            } else if ($respuesta === "error_no_id") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "No se pudo actualizar la materia, Hace Falta el ID."
                ];
            } else if ($respuesta === "no_data_to_update") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "No se pudo actualizar la materia, No se han recibido datos."
                ];
            }
            else {
                error_log("Error en ModeloMaterias::mdlEditarMateria: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo actualizar la materia. Error en el servidor. Contacta con un administrador",
                    "res" => $respuesta
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrEditarMateria: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    ELIMINAR MATERIA (DELETE)
    =============================================*/
    static public function ctrEliminarMateria($subject_id) { // Recibe el ID directamente
        try {
            // Validar que la materia exista antes de intentar eliminar
            $materiaExistente = ModeloMaterias::mdlMostrarMaterias("subjects", "subject_id", $subject_id);
            if (!$materiaExistente) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Materia no encontrada para eliminar."
                ];
            }

            $respuesta = ModeloMaterias::mdlEliminarMateria("subjects", $subject_id);

            if ($respuesta === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Materia eliminada correctamente."
                ];
            } else {
                error_log("Error en ModeloMaterias::mdlEliminarMateria: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo eliminar la materia. Inténtalo de nuevo."
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrEliminarMateria: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }
}