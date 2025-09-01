<?php

require_once "conexion.php";

class ModeloMaterias {

    /*=============================================
    MOSTRAR MATERIA(S) (GET)
    =============================================*/
    static public function mdlMostrarMaterias($tabla, $item, $valor) {
        if ($item != null) {
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");
            $stmt->bindParam(":" . $item, $valor, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC); // Obtener un solo registro
        } else {
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla ORDER BY name ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC); // Obtener todos los registros
        }
        $stmt = null;
    }

    /*=============================================
    CREAR MATERIA (POST)
    =============================================*/
    static public function mdlCrearMateria($tabla, $datos) {
        try {
            $stmt = Conexion::conectar()->prepare(
                "INSERT INTO $tabla (name, duration_hours, semester) 
                VALUES (:name, :duration_hours, :semester)"
            );

            $stmt->bindParam(":name", $datos["name"], PDO::PARAM_STR);
            $stmt->bindParam(":duration_hours", $datos["duration_hours"], PDO::PARAM_INT); 
            $stmt->bindParam(":semester", $datos["semester"], PDO::PARAM_INT); 

            if ($stmt->execute()) {
                return "ok";
            } else {
                error_log("Error al crear materia en BD: " . implode(" - ", $stmt->errorInfo()));
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Excepción en mdlCrearMateria: " . $e->getMessage());
            return $e->getMessage();
        } finally {
            $stmt = null;
        }
    }

    /*=============================================
    EDITAR MATERIA (PUT / PATCH)
    =============================================*/
    static public function mdlEditarMateria($tabla, $datos) {
        try {
            if (!isset($datos['subject_id'])) {
                return "error_no_id";
            }

            $setClauses = [];
            $bindParams = [];

            if (isset($datos['name'])) {
                $setClauses[] = "name = :name";
                $bindParams[":name"] = $datos['name'];
            }
            if (isset($datos['duration_hours'])) {
                $setClauses[] = "duration_hours = :duration_hours";
                $bindParams[":duration_hours"] = $datos['duration_hours'];
            }
            if (isset($datos['semester'])) {
                $setClauses[] = "semester = :semester";
                $bindParams[":semester"] = $datos['semester'];
            }

            // Establecer la zona horaria y obtener la fecha actual para updated_at
            date_default_timezone_set('America/Caracas');
            $current_datetime = date('Y-m-d H:i:s');
            $setClauses[] = "updated_at = :updated_at"; 
            $bindParams[":updated_at"] = $current_datetime; 

            //Si $datos está vacio
            if (empty($setClauses)) {
                return "no_data_to_update"; 
            }

            // Definir y ejecutar consulta SQL
            $sql = "UPDATE $tabla SET " . implode(", ", $setClauses) . " WHERE subject_id = :subject_id";
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
            
            $stmt->bindValue(":subject_id", $datos['subject_id'], PDO::PARAM_INT);

            if ($stmt->execute()) {
                return "ok";
            } else {
                error_log("Error al editar materia en BD: " . implode(" - ", $stmt->errorInfo()));
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Excepción en mdlEditarMateria: " . $e->getMessage());
            return $e->getMessage();
        } finally {
            $stmt = null;
        }
    }

    /*=============================================
    ELIMINAR MATERIA (DELETE)
    =============================================*/
    static public function mdlEliminarMateria($tabla, $id_materia) {
        try {
            $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE subject_id = :subject_id");
            $stmt->bindParam(":subject_id", $id_materia, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return "ok";
            } else {
                error_log("Error al eliminar materia en BD: " . implode(" - ", $stmt->errorInfo()));
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Excepción en mdlEliminarMateria: " . $e->getMessage());
            return $e->getMessage();
        } finally {
            $stmt = null;
        }
    }
}