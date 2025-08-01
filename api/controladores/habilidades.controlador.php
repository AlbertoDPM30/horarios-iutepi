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

    /*===================== PROFESORES ========================*/

    /*=============================================
    MOSTRAR HABILIDADES DE UN PROFESOR
    =============================================*/
    static public function ctrMostrarHabilidadesProfesores($item1 = null,  $item2 = null, $valor1 = null, $valor2 = null) {
        try {
            $respuesta = ModeloHabilidades::mdlMostrarHabilidadesProfesores("teacher_skills", $item1, $valor1, $item2, $valor2);
            
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
                "message" => "Error al obtener habilidades",
            ];
        }
    }

    /*=============================================
    CREAR HABILIDAD
    =============================================*/
    static public function ctrCrearHabilidadProfesor($datos) {
        try {
            // Validar que los id's no estén vacío
            if (empty($datos['teacher_id']) || empty($datos['skill_id'])) {
                return [
                    "status" => 400,
                    "success" => false,
                    "message" => "El 'id' de la habilidad y el 'id' del Profesor es requerido"
                ];
            }

            // Crear la habilidad
            $datos = [
                "teacher_id" => trim($datos['teacher_id']),
                "skill_id" => trim($datos['skill_id']),
                "stars" => trim($datos['stars'])
            ];

            $resultado = ModeloHabilidades::mdlCrearHabilidadProfesor("teacher_skills", $datos);

            if ($resultado === "ok") {

                $respuestaProfesor = ModeloProfesores::mdlMostrarProfesores("teachers", "teacher_id", $datos['teacher_id']);
                $respuestaHabilidad = ModeloHabilidades::mdlMostrarHabilidades("skills", "skill_id", $datos['skill_id']);

                if (!empty($respuestaProfesor) && !empty($respuestaHabilidad)) {

                    return [
                        "status" => 201,
                        "success" => true,
                        "message" => "Habilidad asignada exitosamente al profesor",
                        "data" => [
                            "profesor" => $respuestaProfesor['data']['name'],
                            "habilidad" => $respuestaHabilidad['skill_name'],
                            "stars" => $datos['stars']
                        ]
                    ];
                } else {
                    
                    return [
                        "status" => 201,
                        "success" => true,
                        "message" => "Habilidad asignada exitosamente al profesor",
                        "warning" => "No DATA, reporte con un administrador"
                    ];
                }
            } else {
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "Error al asignar la habilidad al profesor",
                    "error" => $resultado
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Error en ctrCrearHabilidadProfesor: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error del servidor al asignar la habilidad al profesor"
            ];
        }
    }

    /*=============================================
    ACTUALIZAR HABILIDAD DEL PROFESOR
    =============================================*/
    static public function ctrEditarHabilidadProfesor($datos) {
        try {
            // Validar datos de entrada
            if (empty($datos['skill_id']) || empty($datos['teacher_id']) || empty($datos['stars'])) {
                return [
                    "status" => 400,
                    "success" => false,
                    "message" => "ID de habilidad, ID del profesor, y el campo 'stars' son requeridos"
                ];
            }

            // Verificar si la habilidad está asignada al profesor
            $habilidad = ModeloHabilidades::mdlMostrarHabilidadesProfesores("teacher_skills", "teacher_id", $datos['teacher_id'], "skill_id", $datos['skill_id']);
            if (!$habilidad) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Habilidad no encontrada"
                ];
            }

            // Actualizar la habilidad del profesor
            $datos = [
                "skill_id" => $datos['skill_id'],
                "teacher_id" => $datos['teacher_id'],
                "stars" => $datos['stars']
            ];

            $resultado = ModeloHabilidades::mdlEditarHabilidadProfesor("teacher_skills", $datos);

            $respuestaProfesor = ModeloProfesores::mdlMostrarProfesores("teachers", "teacher_id", $datos['teacher_id']);
            $respuestaHabilidad = ModeloHabilidades::mdlMostrarHabilidades("skills", "skill_id", $datos['skill_id']);

            if ($resultado === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Habilidad del profesor actualizada exitosamente",
                    "data" => [
                        "profesor" => $respuestaProfesor['data']['name'],
                        "habilidad" => $respuestaHabilidad['skill_name'],
                        "stars" => $datos['stars']
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
    static public function ctrEliminarHabilidadProfesor($teacher_skill_id) {
        try {
            // Validar ID
            if (empty($teacher_skill_id)) {
                return [
                    "status" => 400,
                    "success" => false,
                    "message" => "ID es requerido"
                ];
            }

            // Verificar si la habilidad existe
            $habilidad = ModeloHabilidades::mdlMostrarHabilidadesProfesores("teacher_skills", "teacher_skill_id", $teacher_skill_id);
            if (!$habilidad) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Habilidad no encontrada"
                ];
            }

            // Eliminar la habilidad
            $resultado = ModeloHabilidades::mdlEliminarHabilidadProfesor("teacher_skills", $teacher_skill_id);

            if ($resultado === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Habilidad eliminada del profesor exitosamente"
                ];
            } else {
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "Error al eliminar la habilidad",
                    "resultado" => $resultado
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