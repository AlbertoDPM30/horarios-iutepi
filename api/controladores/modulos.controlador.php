<?php

class ControladorModulos {

    /*=============================================
    MOSTRAR MODULO(S) (GET)
    =============================================*/
    static public function ctrMostrarModulos($item = null, $valor = null) {
        try {
            $respuesta = ModeloModulos::mdlMostrarModulos("modules", $item, $valor);
            
            if ($item !== null && $valor !== null && !$respuesta) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Modulo no encontrado."
                ];
            }

            return [
                "status" => 200,
                "success" => true,
                "data" => $respuesta
            ];
            
        } catch (Exception $e) {
            error_log("Error en ctrMostrarModulos: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    CREAR MODULO (POST)
    =============================================*/
    static public function ctrCrearModulo($datos) { 
        try {

            $datosModelo = [
                'name' => $datos['name'],
                'description' => $datos['description'],
                'route' => $datos['route'] ?? null
            ];

            $respuesta = ModeloModulos::mdlCrearModulo("modules", $datosModelo);

            if ($respuesta === "ok") {
                return [
                    "status" => 201, 
                    "success" => true,
                    "message" => "Modulo creado exitosamente."
                ];
            } else {
                error_log("Error en ModeloModulos::mdlCrearModulo: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo crear el Modulo. Inténtalo de nuevo."
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrCrearModulo: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    EDITAR MODULO (PUT)
    =============================================*/
    static public function ctrEditarModulo($datos) { 
        try {
            $editarIdModulo = $datos['module_id']; 

            // Validar si el Modulo existe antes de editar
            $existenciaModulo = ModeloModulos::mdlMostrarModulos("modules", "module_id", $editarIdModulo);
            if (empty($existenciaModulo)) { 
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Modulo no encontrado para actualizar."
                ];
            }

            // Preparar datos para el modelo
            $datosModelo = [
                'module_id' => $editarIdModulo
            ];
            if (isset($datos['name'])) $datosModelo['name'] = $datos['name'];
            if (isset($datos['description'])) $datosModelo['description'] = $datos['description'];
            if (isset($datos['route'])) $datosModelo['route'] = $datos['route'];

            $respuesta = ModeloModulos::mdlEditarModulo("modules", $datosModelo);

            if ($respuesta === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Modulo actualizado correctamente."
                ];
            } else if ($respuesta === "no_changes") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Modulo actualizado correctamente, aunque no se detectaron cambios en los datos enviados (ID válido)."
                ];
            } else {
                error_log("Error en ModeloModulos::mdlEditarModulo: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo actualizar el Modulo. Inténtalo de nuevo."
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrEditarModulo: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    ELIMINAR MODULO (DELETE)
    =============================================*/
    static public function ctrEliminarModulo($module_id) { 
        try {
            // Validar si el Modulo existe antes de eliminar
            $existenciaModulo = ModeloModulos::mdlMostrarModulos("modules", "module_id", $module_id);
            if (empty($existenciaModulo)) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Modulo no encontrado para eliminar."
                ];
            }

            $respuesta = ModeloModulos::mdlEliminarModulo("modules", $module_id);

            if ($respuesta === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Modulo eliminado correctamente."
                ];
            } else {
                error_log("Error en ModeloModulos::mdlEliminarModulo: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo eliminar el Modulo. Inténtalo de nuevo."
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrEliminarModulo: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }
}