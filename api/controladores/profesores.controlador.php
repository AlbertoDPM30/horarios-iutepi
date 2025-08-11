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
    
    /*===================== DISPONIBILIDAD ========================*/

    /*=============================================
    MOSTRAR DISPONIBILIDADES DE UN PROFESOR
    =============================================*/
    static public function ctrMostrarDisponibilidadesProfesores($item = null,  $valor = null) {
        try {
            $respuesta = ModeloProfesores::mdlMostrarDisponibilidadesProfesores("teacher_availability", $item, $valor);
            
            return [
                "status" => 200,
                "success" => true,
                "data" => $respuesta
            ];
            
        } catch (PDOException $e) {
            error_log("Error en ctrMostrarDisponibilidadesProfesor: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error al obtener la Disponibilidad",
            ];
        }
    }

    /*=============================================
    CREAR DISPONIBILIDAD
    =============================================*/
    static public function ctrCrearDisponibilidadProfesor($datos) {
        try {
            // Validar que el id no estén vacío
            if (empty($datos['availability_id'])) {
                return [
                    "status" => 400,
                    "success" => false,
                    "message" => "El 'id' de la disponibilidad es requerido"
                ];
            }

            // Crear la Disponobilidad
            $datos = [
                "teacher_id" => trim($datos['teacher_id']),
                "day_of_week" => trim($datos['day_of_week']),
                "start_time" => trim($datos['start_time']),
                "end_time" => trim($datos['end_time']),
                "stars" => trim($datos['stars'])
            ];

            $resultado = ModeloProfesores::mdlCrearDisponibilidadProfesor("teacher_availability", $datos);

            if ($resultado === "ok") {

                // Validar si el profesor existe
                $respuestaProfesor = ModeloProfesores::mdlMostrarProfesores("teachers", "teacher_id", $datos['teacher_id']);

                if (!empty($respuestaProfesor)) {

                    return [
                        "status" => 201,
                        "success" => true,
                        "message" => "Disponibilidad asignada exitosamente al profesor",
                        "data" => [
                            "profesor" => $respuestaProfesor['data']['name'],
                            "dia_semana" => $datos['day_of_week'],
                            "hora_inicio" => $datos['start_time'],
                            "hora_final" => $datos['end_time']
                        ]
                    ];

                } else {
                    
                    return [
                        "status" => 201,
                        "success" => true,
                        "message" => "Disponibilidad asignada exitosamente al profesor",
                        "warning" => "No DATA, reporte con un administrador"
                    ];
                }
            } else {

                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "Error al registrar la disponibilidad"
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Error en ctrCrearDisponibilidadProfesor: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error del servidor al asignar la habilidad al profesor"
            ];
        }
    }

    /*=============================================
    ACTUALIZAR DISPONIBILIDAD DEL PROFESOR
    ==============================================*/
    static public function ctrEditarDisponibilidadProfesor($datos) {
        try {
            // Validar datos de entrada
            if (empty($datos['availability_id'])) {
                return [
                    "status" => 400,
                    "success" => false,
                    "message" => "ID de la disponibilidad es requerido"
                ];
            }

            // Actualizar la habilidad del profesor
            $datos = [
                "availability_id" => $datos['availability_id'],
                "teacher_id" => $datos['teacher_id'],
                "day_of_week" => $datos['day_of_week'],
                "start_time" => $datos['start_time'],
                "end_time" => $datos['end_time']
            ];

            $resultado = ModeloProfesores::mdlEditarDisponibilidadProfesor("teacher_availability", $datos);

            $respuestaProfesor = ModeloProfesores::mdlMostrarProfesores("teachers", "teacher_id", $datos['teacher_id']);

            if ($resultado === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Habilidad del profesor actualizada exitosamente",
                    "data" => [
                        "profesor" => $respuestaProfesor['data']['name'],
                        "dia_semana" => $datos['day_of_week'],
                        "hora_inicio" => $datos['start_time'],
                        "hora_start" => $datos['end_time']
                    ]
                ];
            } else {
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "Error al actualizar la Disponibilidad"
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Error en ctrEditarDisponibilidadProfesor: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error del servidor al actualizar disponibilidad"
            ];
        }
    }

    /*=============================================
    ELIMINAR DISPONIBILIDAD DEL PROFESOR
    =============================================*/
    static public function ctrEliminarDisponibilidadProfesor($availability_id) {
        try {
            // Validar ID
            if (empty($availability_id)) {
                return [
                    "status" => 400,
                    "success" => false,
                    "message" => "ID es requerido"
                ];
            }

            // Verificar si la disponibilidad existe
            $disponibilidad = ModeloProfesores::mdlMostrarDisponibilidadesProfesores("teacher_availability", "availability_id", $availability_id);
            if (!$disponibilidad) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Disponibilidad no encontrada"
                ];
            }

            // Eliminar la Disponibilidad del profesor
            $resultado = ModeloProfesores::mdlEliminarDisponibilidadProfesor("teacher_availability", $availability_id);

            if ($resultado === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Disponibilidad eliminada del profesor exitosamente"
                ];
            } else {
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "Error al eliminar la Disponibilidad"
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Error en ctrEliminarDisponibilidadProfesor: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error del servidor al eliminar la disponibilidad"
            ];
        }
    }
}