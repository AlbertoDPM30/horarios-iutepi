<?php

require_once "conexion.php"; 

class ModeloModulos {

    /*=============================================
    MOSTRAR MODULO(S)
    =============================================*/
    static public function mdlMostrarModulos($tabla, $item, $valor) {
        if ($item != null) {
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");
            $stmt->bindParam(":" . $item, $valor, PDO::PARAM_STR);
            $stmt->execute();
            return  $stmt->fetch(PDO::FETCH_ASSOC); // Obtener un solo registro
        } else {
            $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC); // Obtener todos los registros
        }
        $stmt = null;
    }

    /*=============================================
    CREAR MODULO
    =============================================*/
    static public function mdlCrearModulo($tabla, $datos) {
        try {
            $stmt = Conexion::conectar()->prepare(
                "INSERT INTO $tabla (name, description, route) 
                VALUES (:name, :description, :route)"
            );

            $stmt->bindParam(":name", $datos["name"], PDO::PARAM_STR);
            $stmt->bindParam(":description", $datos["description"], PDO::PARAM_STR);
            $stmt->bindParam(":route", $datos["route"], PDO::PARAM_STR);

            if ($stmt->execute()) {
                return "ok";
            } else {
                error_log("Error al crear Modulo en BD: " . implode(" - ", $stmt->errorInfo()));
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Excepción en mdlCrearModulo: " . $e->getMessage());
            return "error";
        } finally {
            $stmt = null;
        }
    }

    /*=============================================
    EDITAR MODULO
    =============================================*/
    static public function mdlEditarModulo($tabla, $datos) {
        try {
            if (!isset($datos['module_id'])) {
                return "error_no_id";
            }

            $setClauses = [];
            $bindParams = [];

            if (isset($datos['name'])) {
                $setClauses[] = "name = :name";
                $bindParams[":name"] = $datos['name'];
            }
            if (isset($datos['description'])) {
                $setClauses[] = "description = :description";
                $bindParams[":description"] = $datos['description'];
            }
            if (isset($datos['route'])) {
                $setClauses[] = "route = :route";
                $bindParams[":route"] = $datos['route'];
            }

            date_default_timezone_set('America/Caracas');
            $current_datetime = date('Y-m-d H:i:s');
            $setClauses[] = "updated_at = :updated_at";
            $bindParams[":updated_at"] = $current_datetime;

            if (empty($setClauses)) {
                return "no_data_to_update";
            }

            $sql = "UPDATE $tabla SET " . implode(", ", $setClauses) . " WHERE module_id = :module_id";
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
            
            $stmt->bindValue(":module_id", $datos['module_id'], PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return "ok";
                } else {
                    return "no_changes";
                }
            } else {
                error_log("Error al editar Module en BD: " . implode(" - ", $stmt->errorInfo()));
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Excepción en mdlEditarModule: " . $e->getMessage());
            return "error";
        } finally {
            $stmt = null;
        }
    }

    /*=============================================
    ELIMINAR MODULO
    =============================================*/
    static public function mdlEliminarModulo($tabla, $id_module) {
        try {
            $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE module_id = :module_id");
            $stmt->bindParam(":module_id", $id_module, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return "ok";
                } else {
                    // Si no existe un ID
                    return "no_found"; 
                }
            } else {
                error_log("Error al eliminar Module en BD: " . implode(" - ", $stmt->errorInfo()));
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Excepción en mdlEliminarModule: " . $e->getMessage());
            return "error";
        } finally {
            $stmt = null;
        }
    }
}