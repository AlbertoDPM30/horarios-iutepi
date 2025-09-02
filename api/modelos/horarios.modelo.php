<?php

require_once "conexion.php";

class ModeloHorario {

    // Método para guardar el horario final en la base de datos
    public static function mdlCrearHorario($tabla, $datos) {
        $stmt = ModeloConexion::conectar()->prepare("INSERT INTO $tabla(teacher_subject_assignment_id, day_of_week, start_time, end_time) VALUES (:assignment_id, :day, :start, :end)");

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
}
