<?php

require_once "conexion.php";

class ModeloUsuarios {

    /*=============================================
    MOSTRAR USUARIOS (GET)
    =============================================*/
    static public function mdlMostrarUsuarios($tabla, $item, $valor) {
        try {
            if ($item != null) {
                // Obtener un usuario específico
                $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :valor");
                $stmt->bindParam(":valor", $valor, PDO::PARAM_STR);
            } else {
                // Obtener todos los usuarios
                $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla ORDER BY first_name ASC");
            }

            $stmt->execute();

            if ($item != null) {
                return $stmt->fetch(PDO::FETCH_ASSOC); 
            } else {
                return $stmt->fetchAll(PDO::FETCH_ASSOC); 
            }

        } catch (PDOException $e) {
            error_log("Error en mdlMostrarUsuarios: " . $e->getMessage());
            return false; // Retorna false en caso de error
        } finally {
            if ($stmt) {
                $stmt = null; // Asegura que el statement se cierre
            }
        }
    }

    /*=============================================
    REGISTRO DE USUARIO (POST)
    =============================================*/
    static public function mdlCrearUsuario($tabla, $datos) {
        try {
            // Consulta SQL para insertar un nuevo usuario
            $stmt = Conexion::conectar()->prepare(
                "INSERT INTO 
                $tabla (first_name, last_name, ci, username, password) 
                VALUES (:first_name, :last_name, :ci, :username, :password)"
            );

            // Vincular los parámetros
            $stmt->bindParam(":first_name", $datos["nombres"], PDO::PARAM_STR);
            $stmt->bindParam(":last_name", $datos["apellidos"], PDO::PARAM_STR);
            $stmt->bindParam(":ci", $datos["ci"], PDO::PARAM_STR);
            $stmt->bindParam(":username", $datos["username"], PDO::PARAM_STR);
            $stmt->bindParam(":password", $datos["password"], PDO::PARAM_STR);

            // Ejecutar la consulta SQL
            if ($stmt->execute()) {
                return "ok"; // Retornar 'ok' si la inserción fue exitosa
            } else {
                error_log("Error al crear usuario: " . implode(" ", $stmt->errorInfo()));
                return "error"; // Retornar 'error' si hubo un problema
            }

        } catch (PDOException $e) {
            error_log("Error en mdlCrearUsuario: " . $e->getMessage());
            return "error"; // Retornar 'error' en caso de excepción
            // return "error"; // Retornar 'error' en caso de excepción
        } finally {
            if ($stmt) {
                $stmt = null; // Cerrar la conexión y liberar recursos
            }
        }
    }

    /*=============================================
    ACTUALIZAR USUARIO (PUT)
    =============================================*/
    static public function mdlEditarUsuario($tabla, $datos) {
        try {

            // Agregar "user_id" si no está presente
            if (!isset($datos['user_id'])) {
                error_log("Error en mdlEditarUsuario: 'user_id' no está presente en los datos.");
                return "error_no_user_id";
            }

            // Iniciar la construcción de la cláusula SET
            $setClauses = [];
            $bindParams = [];
            
            // Añadir los campos que vienen en $datos (excluyendo user_id)
            foreach ($datos as $key => $value) {
                if ($key !== 'user_id') {
                    $setClauses[] = "$key = :$key";
                    $bindParams[":$key"] = $value;
                }
            }

            // Si no hay campos para actualizar además del ID y updated_at, salir.
            if (empty($setClauses) && !isset($datos['updated_at'])) {
                return "no_data";
            }

           // Construir la consulta SQL
            $sql = "UPDATE $tabla SET " . implode(", ", $setClauses) . " WHERE user_id = :user_id";
            $stmt = Conexion::conectar()->prepare($sql);

            // Vincular los parámetros dinámicamente
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

            // Vincular 'user_id' 
            $stmt->bindValue(":user_id", $datos['user_id'], PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return "ok";
            } else {
                error_log("Error al actualizar usuario: " . implode(" ", $stmt->errorInfo()));
                return "error";
            }

        } catch (PDOException $e) {
            error_log("Error en mdlEditarUsuario: " . $e->getMessage());
            return "error";
        } finally {
            if ($stmt) {
                $stmt = null;
            }
        }
    }

    /*=============================================
    ELIMINAR USUARIO (DELETE)
    =============================================*/
    static public function mdlEliminarUsuario($tabla, $id) {
        try {
            $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE user_id = :user_id");
            $stmt->bindParam(":user_id", $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return "ok";
            } else {
                error_log("Error al eliminar usuario: " . implode(" ", $stmt->errorInfo()));
                return "error";
            }

        } catch (PDOException $e) {
            error_log("Error en mdlEliminarUsuario: " . $e->getMessage());
            return "error";
        } finally {
            if ($stmt) {
                $stmt = null;
            }
        }
    }

}