<?php

class ControladorHabilidades {

    /*=============================================
    MOSTRAR HABILIDAD(ES)
    =============================================*/
    static public function ctrMostrarHabilidades($item = null, $valor = null) {
        try {
            $respuesta = ModeloHabilidades::mdlMostrarHabilidades("skills", $item, $valor);
            
            return [
                "status" => 200,
                "success" => true,
                "data" => $respuesta
            ];
            
        } catch (PDOException $e) {
            error_log("Error en ctrMostrarHabilidades: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error al obtener habilidades"
            ];
        }
    }

    /*=============================================
    CREAR HABILIDAD
    =============================================*/
    static public function ctrCrearHabilidad($datos) {
        try {
            // Validar que el nombre no esté vacío
            if (empty($datos['skill_name'])) {
                return [
                    "status" => 400,
                    "success" => false,
                    "message" => "El nombre de la habilidad es requerido"
                ];
            }

            // Verificar si la habilidad ya existe
            $existente = ModeloHabilidades::mdlMostrarHabilidades("skills", "skill_name", $datos['skill_name']);
            if ($existente) {
                return [
                    "status" => 409,
                    "success" => false,
                    "message" => "La habilidad ya existe"
                ];
            }

            // Crear la habilidad
            $datos = ["skill_name" => trim($datos['skill_name'])];
            $resultado = ModeloHabilidades::mdlCrearHabilidad("skills", $datos);

            if ($resultado === "ok") {
                return [
                    "status" => 201,
                    "success" => true,
                    "message" => "Habilidad creada exitosamente",
                    "data" => $datos
                ];
            } else {
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "Error al crear habilidad"
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Error en ctrCrearHabilidad: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error del servidor al crear habilidad",
                "error" => $e->getMessage()
            ];
        }
    }

    /*=============================================
    ACTUALIZAR HABILIDAD
    =============================================*/
    static public function ctrEditarHabilidad($datos) {
        try {
            // Validar datos de entrada
            if (empty($datos['skill_id']) || empty($datos)) {
                return [
                    "status" => 400,
                    "success" => false,
                    "message" => "ID y nombre son requeridos"
                ];
            }

            // Verificar si la habilidad existe
            $habilidad = ModeloHabilidades::mdlMostrarHabilidades("skills", "skill_id", $datos['skill_id']);
            if (!$habilidad) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Habilidad no encontrada"
                ];
            }

            // Verificar si el nuevo nombre ya existe (para otra habilidad)
            $existente = ModeloHabilidades::mdlMostrarHabilidades("skills", "skill_name", $datos['skill_name']);
            if ($existente && $existente['skill_id'] != $datos['skill_id']) {
                return [
                    "status" => 409,
                    "success" => false,
                    "message" => "El nombre ya está en uso por otra habilidad"
                ];
            }

            // Actualizar la habilidad
            $datos = [
                "skill_id" => $datos['skill_id'],
                "skill_name" => trim($datos['skill_name']),
                "updated_at" => date('Y-m-d H:i:s')
            ];

            $resultado = ModeloHabilidades::mdlEditarHabilidad("skills", $datos);

            if ($resultado === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Habilidad actualizada exitosamente",
                    "data" => [
                        "skill_id" => $datos['skill_id'],
                        "skill_name" => $datos['skill_name']
                    ]
                ];
            } else {
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "Error al actualizar habilidad"
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Error en ctrEditarHabilidad: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error del servidor al actualizar habilidad"
            ];
        }
    }

    /*=============================================
    ELIMINAR HABILIDAD
    =============================================*/
    static public function ctrEliminarHabilidad($id) {
        try {
            // Validar ID
            if (empty($id)) {
                return [
                    "status" => 400,
                    "success" => false,
                    "message" => "ID de habilidad es requerido"
                ];
            }

            // Verificar si la habilidad existe
            $habilidad = ModeloHabilidades::mdlMostrarHabilidades("skills", "skill_id", $id);
            if (!$habilidad) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Habilidad no encontrada"
                ];
            }

            // Eliminar la habilidad
            $resultado = ModeloHabilidades::mdlEliminarHabilidad("skills", $id);

            if ($resultado === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Habilidad eliminada exitosamente"
                ];
            } else {
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "Error al eliminar habilidad"
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Error en ctrEliminarHabilidad: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error del servidor al eliminar habilidad"
            ];
        }
    }
}