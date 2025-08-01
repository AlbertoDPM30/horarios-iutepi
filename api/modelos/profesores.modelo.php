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
}