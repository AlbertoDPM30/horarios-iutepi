<?php

require_once "conexion.php";

class ModeloHabilidades {

    /*=============================================
    MOSTRAR HABILIDADES (GET)
    =============================================*/
    static public function mdlMostrarHabilidades($tabla, $item = null, $valor = null) {
        try {
            if ($item != null) {
                // Obtener una habilidad específica
                $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :valor");
                $stmt->bindParam(":valor", $valor, PDO::PARAM_STR);
            } else {
                // Obtener todas las habilidades
                $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla ORDER BY skill_name ASC");
            }
            
            $stmt->execute();
            
            return ($item != null) ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error en mdlMostrarHabilidades: " . $e->getMessage());
            return false;
        } finally {
            if ($stmt) {
                $stmt = null;
            }
        }
    }

    /*=============================================
    CREAR HABILIDAD (POST)
    =============================================*/
    static public function mdlCrearHabilidad($tabla, $datos) {
        try {
            $stmt = Conexion::conectar()->prepare("INSERT INTO $tabla(skill_name) VALUES (:skill_name)");
            $stmt->bindParam(":skill_name", $datos["skill_name"], PDO::PARAM_STR);
            
            return ($stmt->execute()) ? "ok" : "error";
            
        } catch (PDOException $e) {
            error_log("Error en mdlCrearHabilidad: " . $e->getMessage());
            return "error";
        } finally {
            if ($stmt) {
                $stmt = null;
            }
        }
    }

    /*=============================================
    EDITAR HABILIDAD (PUT / PATCH)
    =============================================*/
    static public function mdlEditarHabilidad($tabla, $datos) {
        try {
            $stmt = Conexion::conectar()->prepare("UPDATE $tabla SET 
                skill_name = :skill_name,
                updated_at = :updated_at
                WHERE skill_id = :skill_id");
            
            $stmt->bindParam(":skill_id", $datos["skill_id"], PDO::PARAM_INT);
            $stmt->bindParam(":skill_name", $datos["skill_name"], PDO::PARAM_STR);
            $stmt->bindParam(":updated_at", $datos["updated_at"], PDO::PARAM_STR);
            
            return ($stmt->execute()) ? "ok" : "error";
            
        } catch (PDOException $e) {
            error_log("Error en mdlEditarHabilidad: " . $e->getMessage());
            return "error";
        } finally {
            if ($stmt) {
                $stmt = null;
            }
        }
    }

    /*=============================================
    ELIMINAR HABILIDAD (DELETE)
    =============================================*/
    static public function mdlEliminarHabilidad($tabla, $id) {
        try {
            $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE skill_id = :skill_id");
            $stmt->bindParam(":skill_id", $id, PDO::PARAM_INT);
            
            return ($stmt->execute()) ? "ok" : "error";
            
        } catch (PDOException $e) {
            error_log("Error en mdlEliminarHabilidad: " . $e->getMessage());
            return "error";
        } finally {
            if ($stmt) {
                $stmt = null;
            }
        }
    }
}