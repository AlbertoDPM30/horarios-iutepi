<?php

require_once "conexion.php"; 

class ModeloProfesores {

    /*=============================================
    MOSTRAR PROFESOR(ES)
    =============================================*/
    static public function mdlMostrarProfesores($tabla, $item, $valor) {
        if ($item != null) {
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");
            $stmt->bindParam(":" . $item, $valor, PDO::PARAM_STR);
            $stmt->execute();
            return [
                "status" => 200,
                "success" => true,
                "data" => $stmt->fetch(PDO::FETCH_ASSOC) // Obtener un solo registro
            ];
        } else {
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla");
            $stmt->execute();
            return [
                "status" => 200,
                "success" => true,
                "data" => $stmt->fetchAll(PDO::FETCH_ASSOC) // Obtener todos los registros
            ];
        }
        $stmt = null;
    }

    /*=============================================
    CREAR PROFESOR
    =============================================*/
    static public function mdlCrearProfesor($tabla, $datos) {
        try {
            $stmt = Conexion::conectar()->prepare(
                "INSERT INTO $tabla (name, ci_code, phone_number, email) 
                VALUES (:name, :ci_code, :phone_number, :email)"
            );

            $stmt->bindParam(":name", $datos["name"], PDO::PARAM_STR);
            $stmt->bindParam(":ci_code", $datos["ci_code"], PDO::PARAM_STR);
            $stmt->bindParam(":phone_number", $datos["phone_number"], PDO::PARAM_STR);
            $stmt->bindParam(":email", $datos["email"], PDO::PARAM_STR);

            if ($stmt->execute()) {
                return "ok";
            } else {
                error_log("Error al crear profesor en BD: " . implode(" - ", $stmt->errorInfo()));
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Excepción en mdlCrearProfesor: " . $e->getMessage());
            return "error";
        } finally {
            $stmt = null;
        }
    }

    /*=============================================
    EDITAR PROFESOR
    =============================================*/
    static public function mdlEditarProfesor($tabla, $datos) {
        try {
            if (!isset($datos['teacher_id'])) {
                return "error_no_id";
            }

            $setClauses = [];
            $bindParams = [];

            if (isset($datos['name'])) {
                $setClauses[] = "name = :name";
                $bindParams[":name"] = $datos['name'];
            }
            if (isset($datos['ci_code'])) {
                $setClauses[] = "ci_code = :ci_code";
                $bindParams[":ci_code"] = $datos['ci_code'];
            }
            if (isset($datos['phone_number'])) {
                $setClauses[] = "phone_number = :phone_number";
                $bindParams[":phone_number"] = $datos['phone_number'];
            }
            if (isset($datos['email'])) {
                $setClauses[] = "email = :email";
                $bindParams[":email"] = $datos['email'];
            }

            date_default_timezone_set('America/Caracas');
            $current_datetime = date('Y-m-d H:i:s');
            $setClauses[] = "updated_at = :updated_at";
            $bindParams[":updated_at"] = $current_datetime;

            if (empty($setClauses)) {
                return "no_data_to_update";
            }

            $sql = "UPDATE $tabla SET " . implode(", ", $setClauses) . " WHERE teacher_id = :teacher_id";
            $stmt = Conexion::conectar()->prepare($sql);

            foreach ($bindParams as $param => $value) {
                $paramType = PDO::PARAM_STR;
                if (is_int($value)) {
                    $paramType = PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $paramType = PDO::PARAM_BOOL;
                } elseif (is_null($value)) {
                    $paramType = PDO::PARAM_NULL;
                }
                $stmt->bindValue($param, $value, $paramType);
            }
            
            $stmt->bindValue(":teacher_id", $datos['teacher_id'], PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return "ok";
                } else {
                    return "no_changes";
                }
            } else {
                error_log("Error al editar profesor en BD: " . implode(" - ", $stmt->errorInfo()));
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Excepción en mdlEditarProfesor: " . $e->getMessage());
            return "error";
        } finally {
            $stmt = null;
        }
    }

    /*=============================================
    ELIMINAR PROFESOR
    =============================================*/
    static public function mdlEliminarProfesor($tabla, $id_profesor) {
        try {
            $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE teacher_id = :teacher_id");
            $stmt->bindParam(":teacher_id", $id_profesor, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return "ok";
                } else {
                    // Si no existe un ID
                    return "no_found"; 
                }
            } else {
                error_log("Error al eliminar profesor en BD: " . implode(" - ", $stmt->errorInfo()));
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Excepción en mdlEliminarProfesor: " . $e->getMessage());
            return "error";
        } finally {
            $stmt = null;
        }
    }
    
    /*====================== DISPONIBILIDAD =======================*/

    /*=============================================
    MOSTRAR DISPONIBILIDADES DEL PROFESOR (GET)
    =============================================*/
    static public function mdlMostrarDisponibilidadesProfesores($tabla, $item = null, $valor = null) {
        try {
            if ($item != null) {
                
                // Obtener una Disponibilidad específica
                $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :valor");
                $stmt->bindParam(":valor", $valor, PDO::PARAM_STR);

            } elseif ($item == "teacher_id") {
                
                // Obtener todas las disponibilidades de un profesor
                $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :valor ORDER BY availability_id ASC");
                $stmt->bindParam(":valor", $valor, PDO::PARAM_STR);

            } else {
                // Obtener todas las disponibilidades
                $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla ORDER BY availability_id ASC");
            }
            
            $stmt->execute();

            return ($item != null && $item != "teacher_id") ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error en mdlMostrarDisponibilidadesProfesores: " . $e->getMessage());
            return false;

        } finally {
            if ($stmt) {
                $stmt = null;
            }
        }
    }

    /*=============================================
    ASIGNAR DISPONIBILIDAD AL PROFESOR (POST)
    =============================================*/
    static public function mdlCrearDisponibilidadProfesor($tabla, $datos) {
        try {
            $stmt = Conexion::conectar()->prepare("INSERT INTO  $tabla 
                                                                (teacher_id,
                                                                day_of_week,
                                                                start_time,
                                                                end_time)
                                                        VALUES  (:teacher_id,
                                                                :day_of_week,
                                                                :start_time,
                                                                :end_time)");

            $stmt->bindParam(":teacher_id", $datos["teacher_id"], PDO::PARAM_INT);
            $stmt->bindParam(":day_of_week", $datos["day_of_week"], PDO::PARAM_STR);
            $stmt->bindParam(":start_time", $datos["start_time"], PDO::PARAM_STR);
            $stmt->bindParam(":end_time", $datos["end_time"], PDO::PARAM_STR);
            
            return ($stmt->execute()) ? "ok" : "error";
            
        } catch (PDOException $e) {
            error_log("Error en mdlCrearDisponibilidadProfesor: " . $e->getMessage());
            return "error";
        } finally {
            if ($stmt) {
                $stmt = null;
            }
        }
    }

    /*=============================================
    EDITAR DISPONIBILIDAD DEL PROFESOR (PUT / PATCH)
    =============================================*/
    static public function mdlEditarDisponibilidadProfesor($tabla, $datos) {
        try {
            $stmt = Conexion::conectar()->prepare("UPDATE $tabla SET 
                                                                teacher_id      = :teacher_id,
                                                                day_of_week     = :day_of_week,
                                                                start_time      = :start_time,
                                                                end_time        = :end_time
                                                        WHERE   availability_id = :availability_id");
            
            $stmt->bindParam(":availability_id", $datos["availability_id"], PDO::PARAM_INT);
            $stmt->bindParam(":teacher_id", $datos["teacher_id"], PDO::PARAM_INT);
            $stmt->bindParam(":day_of_week", $datos["day_of_week"], PDO::PARAM_STR);
            $stmt->bindParam(":start_time", $datos["start_time"], PDO::PARAM_STR);
            $stmt->bindParam(":end_time", $datos["end_time"], PDO::PARAM_STR);
            
            return ($stmt->execute()) ? "ok" : "error";
            
        } catch (PDOException $e) {
            error_log("Error en mdlEditarDisponibilidadProfesor: " . $e->getMessage());
            return "error";
        } finally {
            if ($stmt) {
                $stmt = null;
            }
        }
    }

    /*=============================================
    ELIMINAR DISPONIBILIDAD DEL PROFESOR (DELETE)
    =============================================*/
    static public function mdlEliminarDisponibilidadProfesor($tabla, $availability_id) {
        try {
            $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE availability_id = :availability_id");
            $stmt->bindParam(":availability_id", $availability_id, PDO::PARAM_INT);
            
            return ($stmt->execute()) ? "ok" : "error";
            
        } catch (PDOException $e) {
            error_log("Error en mdlEliminarDisponibilidadProfesor: " . $e->getMessage());
            return "error";
        } finally {
            if ($stmt) {
                $stmt = null;
            }
        }
    }
    
    /*====================== MATERIAS =======================*/

    /*=============================================
    ELIMINAR ASIGNACIONES DE MATERIAS POR PROFESOR
    =============================================*/
    public static function mdlEliminarMateriaProfesor($tabla, $item, $valor) {
        $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE $item = :$item");
        $stmt->bindParam(":" . $item, $valor, PDO::PARAM_INT);
        return $stmt->execute();
        $stmt = null;
    }

    /*=============================================
    VERIFICAR ASIGNACIÓN EXISTENTE
    =============================================*/
    public static function mdlVerificarAsignacionExistente($tabla, $teacher_id, $subject_id) {
        $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE teacher_id = :teacher_id AND subject_id = :subject_id");
        $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT);
        $stmt->bindParam(":subject_id", $subject_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
        $stmt = null;
    }

    /*=============================================
    CREAR ASIGNACIÓN DE MATERIA A PROFESOR
    =============================================*/
    public static function mdlCrearAsignacionMateriaProfesor($tabla, $datos) {
        $stmt = Conexion::conectar()->prepare("INSERT INTO $tabla(teacher_id, subject_id) VALUES (:teacher_id, :subject_id)");
        $stmt->bindParam(":teacher_id", $datos["teacher_id"], PDO::PARAM_INT);
        $stmt->bindParam(":subject_id", $datos["subject_id"], PDO::PARAM_INT);
        if ($stmt->execute()) {
            return "ok";
        } else {
            return "error";
        }
        $stmt = null;
    }
    
    /*=============================================
    MOSTRAR MATERIAS DEL PROFESOR (GET)
    =============================================*/
    static public function mdlMostrarMateriasProfesores($tabla, $item = null, $valor = null) {
        try {
            if ($item != null && $item !== "teacher_id") {

                // Obtener una Disponibilidad específica
                $stmt = Conexion::conectar()->prepare("SELECT
                                                            pm.assignment_id,
                                                            pm.teacher_id,
                                                            pm.subject_id,
                                                            p.name as teacher_name,
                                                            m.name as subject_name
                                                        FROM $tabla as pm 
                                                        LEFT JOIN subjects as m
                                                        ON m.subject_id = pm.subject_id
                                                        LEFT JOIN teachers as p
                                                        ON p.teacher_id = pm.teacher_id
                                                        pm.$item = :valor");

                $stmt->bindParam(":valor", $valor, PDO::PARAM_STR);

            } elseif ($item == "teacher_id") {
                
                    // Obtener todas las Materias de un profesor
                    $stmt = Conexion::conectar()->prepare("SELECT
                                                                pm.assignment_id,
                                                                pm.teacher_id,
                                                                pm.subject_id,
                                                                p.name as teacher_name,
                                                                m.name as subject_name
                                                            FROM $tabla as pm 
                                                            LEFT JOIN subjects as m
                                                            ON m.subject_id = pm.subject_id
                                                            LEFT JOIN teachers as p
                                                            ON p.teacher_id = pm.teacher_id
                                                            WHERE pm.$item = :valor ORDER BY pm.assignment_id DESC");

                    $stmt->bindParam(":valor", $valor, PDO::PARAM_STR);

                } else {
                // Obtener todas las Materias
                $stmt = Conexion::conectar()->prepare("SELECT
                                                        pm.assignment_id,
                                                        pm.teacher_id,
                                                        pm.subject_id,
                                                        p.name as teacher_name,
                                                        m.name as subject_name
                                                        FROM $tabla as pm 
                                                        LEFT JOIN subjects as m
                                                        ON m.subject_id = pm.subject_id
                                                        LEFT JOIN teachers as p
                                                        ON p.teacher_id = pm.teacher_id
                                                        ORDER BY pm.assignment_id DESC");
            }
            
            $stmt->execute();

            return ($item != null && $item != "teacher_id") ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error en mdlMostrarMateriasProfesores: " . $e->getMessage());
            return $e->getMessage();

        } finally {
            if ($stmt) {
                $stmt = null;
            }
        }
    }

    // /*=============================================
    // ASIGNAR MATERIA AL PROFESOR (POST)
    // =============================================*/
    // static public function mdlCrearMateriaProfesor($tabla, $datos) {
    //     try {
    //         $stmt = Conexion::conectar()->prepare("INSERT INTO  $tabla 
    //                                                             (teacher_id,
    //                                                             subject_id,
    //                                                             day_of_week,
    //                                                             start_time,
    //                                                             end_time)
    //                                                     VALUES  (:teacher_id,
    //                                                             :subject_id,
    //                                                             :day_of_week,
    //                                                             :start_time,
    //                                                             :end_time)");

    //         $stmt->bindParam(":teacher_id", $datos["teacher_id"], PDO::PARAM_INT);
    //         $stmt->bindParam(":subject_id", $datos["subject_id"], PDO::PARAM_INT);
    //         $stmt->bindParam(":day_of_week", $datos["day_of_week"], PDO::PARAM_STR);
    //         $stmt->bindParam(":start_time", $datos["start_time"], PDO::PARAM_STR);
    //         $stmt->bindParam(":end_time", $datos["end_time"], PDO::PARAM_STR);
            
    //         return ($stmt->execute()) ? "ok" : "error";
            
    //     } catch (PDOException $e) {
    //         error_log("Error en mdlCrearMateriaProfesor: " . $e->getMessage());
    //         return "error";
    //     } finally {
    //         if ($stmt) {
    //             $stmt = null;
    //         }
    //     }
    // }

    // /*=============================================
    // EDITAR MATERIA DEL PROFESOR (PUT / PATCH)
    // =============================================*/
    // static public function mdlEditarMateriaProfesor($tabla, $datos) {
    //     try {
    //         $stmt = Conexion::conectar()->prepare("UPDATE $tabla SET 
    //                                                             teacher_id      = :teacher_id,
    //                                                             subject_id      = :subject_id,
    //                                                             day_of_week     = :day_of_week,
    //                                                             start_time      = :start_time,
    //                                                             end_time        = :end_time
    //                                                     WHERE   assignment_id   = :assignment_id");
            
    //         $stmt->bindParam(":assignment_id", $datos["assignment_id"], PDO::PARAM_INT);
    //         $stmt->bindParam(":subject_id", $datos["subject_id"], PDO::PARAM_INT);
    //         $stmt->bindParam(":teacher_id", $datos["teacher_id"], PDO::PARAM_INT);
    //         $stmt->bindParam(":day_of_week", $datos["day_of_week"], PDO::PARAM_STR);
    //         $stmt->bindParam(":start_time", $datos["start_time"], PDO::PARAM_STR);
    //         $stmt->bindParam(":end_time", $datos["end_time"], PDO::PARAM_STR);
            
    //         return ($stmt->execute()) ? "ok" : "error";
            
    //     } catch (PDOException $e) {
    //         error_log("Error en mdlEditarMateriaProfesor: " . $e->getMessage());
    //         return "error";
    //     } finally {
    //         if ($stmt) {
    //             $stmt = null;
    //         }
    //     }
    // }

    // /*=============================================
    // ELIMINAR MATERIA DEL PROFESOR (DELETE)
    // =============================================*/
    // static public function mdlEliminarMateriaProfesor($tabla, $assignment_id) {
    //     try {
    //         $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE assignment_id = :assignment_id");
    //         $stmt->bindParam(":assignment_id", $assignment_id, PDO::PARAM_INT);
            
    //         return ($stmt->execute()) ? "ok" : "error";
            
    //     } catch (PDOException $e) {
    //         error_log("Error en mdlEliminarMateriaProfesor: " . $e->getMessage());
    //         return "error";
    //     } finally {
    //         if ($stmt) {
    //             $stmt = null;
    //         }
    //     }
    // }


}