<?php

require_once "conexion.php";

class ModeloHorario {

    // Método para mostrar horarios guardados de un profesor
    public static function mdlMostrarHorarios($tabla, $item = null, $valor = null) {
        try {
            if ($item != null && $valor != null) {
                $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :valor ORDER BY schedule_id ASC");
                $stmt->bindParam(":valor", $valor, PDO::PARAM_INT);
                $stmt->execute();
                return [
                    "status" => 200,
                    "success" => true,
                    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
                ];
            } else {
                $stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla ORDER BY schedule_id ASC");
                $stmt->execute();
                return [
                    "status" => 200,
                    "success" => true,
                    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
                ];
            }
        } catch (PDOException $e) {
            error_log("Error en mdlMostrarHorarios: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error al obtener horarios"
            ];
        }
    }

    // Método para guardar el horario final en la base de datos
    public static function mdlCrearHorario($tabla, $datos) {
        $stmt = Conexion::conectar()->prepare("INSERT INTO $tabla(teacher_subject_assignment_id, day_of_week, start_time, end_time) VALUES (:assignment_id, :day, :start, :end)");

        $stmt->bindParam(":assignment_id", $datos["teacher_subject_assignment_id"], PDO::PARAM_INT);
        $stmt->bindParam(":day", $datos["day_of_week"], PDO::PARAM_STR);
        $stmt->bindParam(":start", $datos["start_time"], PDO::PARAM_STR);
        $stmt->bindParam(":end", $datos["end_time"], PDO::PARAM_STR);

        if ($stmt->execute()) {
            return "ok";
        } else {
            return "error";
        }
    }

    // Método para actualizar un horario existente
    public static function mdlEditarHorario($tabla, $datos) {
        try {
            $stmt = Conexion::conectar()->prepare("UPDATE $tabla SET day_of_week = :day, start_time = :start, end_time = :end WHERE schedule_id = :schedule_id");

            $stmt->bindParam(":schedule_id", $datos["schedule_id"], PDO::PARAM_INT);
            $stmt->bindParam(":day", $datos["day_of_week"], PDO::PARAM_STR);
            $stmt->bindParam(":start", $datos["start_time"], PDO::PARAM_STR);
            $stmt->bindParam(":end", $datos["end_time"], PDO::PARAM_STR);

            if ($stmt->execute()) {
                return "ok";
            } else {
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Error en mdlEditarHorario: " . $e->getMessage());
            return "error";
        }
    }

    // Método para eliminar un horario
    public static function mdlEliminarHorario($tabla, $schedule_id) {
        try {
            $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE schedule_id = :schedule_id");
            $stmt->bindParam(":schedule_id", $schedule_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return "ok";
            } else {
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Error en mdlEliminarHorario: " . $e->getMessage());
            return "error";
        }
    }

    // Método para eliminar todos los horarios de un profesor
    public static function mdlEliminarHorariosProfesor($tabla, $teacher_id) {
        try {
            $stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE teacher_subject_assignment_id IN (SELECT assignment_id FROM teacher_subject_assignments WHERE teacher_id = :teacher_id)");
            $stmt->bindParam(":teacher_id", $teacher_id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return "ok";
            } else {
                return "error";
            }
        } catch (PDOException $e) {
            error_log("Error en mdlEliminarHorariosProfesor: " . $e->getMessage());
            return "error";
        }
    }
}
