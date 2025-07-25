<?php

require_once "conexion.php"; 

class ModeloEstudiantes {

    /*=============================================
    MOSTRAR ESTUDIANTE(S)
    =============================================*/
    static public function mdlMostrarEstudiantes($tabla, $item, $valor) {
        if ($item != null) {
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");
            $stmt->bindParam(":" . $item, $valor, PDO::PARAM_STR);
            $stmt->execute();
            return [
                "success" => true,
                "data" => $stmt->fetch(PDO::FETCH_ASSOC) // Obtener un solo registro
            ];
        } else {
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla");
            $stmt->execute();
            return [
                "success" => true,
                "data" => $stmt->fetchAll(PDO::FETCH_ASSOC) // Obtener todos los registros
            ];
        }
        $stmt = null;
    }

    /*=============================================
    CREAR ESTUDIANTE
    =============================================*/
    static public function mdlCrearEstudiante($tabla, $datos) {
        try {
            $stmt = Conexion::conectar()->prepare(
                "INSERT INTO $tabla (name, ci_code) 
                VALUES (:name, :ci_code)"
            );

            $stmt->bindParam(":name", $datos["name"], PDO::PARAM_STR);
            $stmt->bindParam(":ci_code", $datos["ci_code"], PDO::PARAM_STR);

            if ($stmt->execute()) {
                return "ok";
            } else {
                error_log("Error al crear Estudiante en BD: " . implode(" - ", $stmt->errorInfo()));
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Excepción en mdlCrearEstudiante: " . $e->getMessage());
            return "error";
        } finally {
            $stmt = null;
        }
    }

    /*=============================================
    EDITAR ESTUDIANTE
    =============================================*/
    static public function mdlEditarEstudiante($tabla, $datos) {
        try {
            if (!isset($datos['student_id'])) {
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

            date_default_timezone_set('America/Caracas');
            $current_datetime = date('Y-m-d H:i:s');
            $setClauses[] = "updated_at = :updated_at";
            $bindParams[":updated_at"] = $current_datetime;

            if (empty($setClauses)) {
                return "no_data_to_update";
            }

            $sql = "UPDATE $tabla SET " . implode(", ", $setClauses) . " WHERE student_id = :student_id";
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
            
            $stmt->bindValue(":student_id", $datos['student_id'], PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return "ok";
                } else {
                    return "no_changes";
                }
            } else {
                error_log("Error al editar Estudiante en BD: " . implode(" - ", $stmt->errorInfo()));
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Excepción en mdlEditarEstudiante: " . $e->getMessage());
            return "error";
        } finally {
            $stmt = null;
        }
    }

    /*=============================================
    ELIMINAR ESTUDIANTE
    =============================================*/
    static public function mdlEliminarEstudiante($tabla, $id_Estudiante) {
        try {
            $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE student_id = :student_id");
            $stmt->bindParam(":student_id", $id_Estudiante, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return "ok";
                } else {
                    // Si no existe un ID
                    return "no_found"; 
                }
            } else {
                error_log("Error al eliminar Estudiante en BD: " . implode(" - ", $stmt->errorInfo()));
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Excepción en mdlEliminarEstudiante: " . $e->getMessage());
            return "error";
        } finally {
            $stmt = null;
        }
    }
}