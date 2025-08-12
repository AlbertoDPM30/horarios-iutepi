<?php

class ControladorGenerarHorarios {

    /*=============================================
    MOSTRAR HABILIDAD(ES)
    =============================================*/
    /* static public function ctrMostrarHabilidades($item = null, $valor = null) {
        try {
            $respuesta = ModeloHabilidades::mdlMostrarHabilidades("skills", $item, $valor);
            
            return [
                "status" => 200,
                "success" => true,
                "data" => $respuesta
            ];
            
        } catch (PDOException $e) {
            error_log("Error en ctrMostrarHabilidades: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error al obtener habilidades"
            ];
        }
    } */

    /*=============================================
    GENERAR HORARIO DE LA SEMANA DE PROFESORES
    =============================================*/
    static public function ctrGenerarHorariosProfesoresSemana() {
        try {

            $dataProfesor = [];
            $dataMaterias = [];
            $dataHabilidades = [];

            // Obtener profesores
            $profesores = ModeloProfesores::mdlMostrarProfesores("teachers", null, null);

            if (empty($profesores)) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "No se encontraron profesores."
                ];
            }

            foreach ($profesores['data'] as $key => $profesor) {
                // Obtener habilidades del profesor
                $habilidadesProfesor = ModeloHabilidades::mdlMostrarHabilidadesProfesores("teacher_skills", "teacher_id", $profesor['teacher_id']);

                // Obtener disponibilidad del profesor
                $disponibilidad = ModeloProfesores::mdlMostrarDisponibilidadesProfesores("teacher_availability", "teacher_id", $profesor['teacher_id']);

                $dataProfesor[$key] = [
                    "id" => $profesor['teacher_id'],
                    "nombre" => $profesor['name'],
                    "email" => $profesor['email'],
                    "numero_telefono" => $profesor['phone_number'],
                    "disponibilidad" => $disponibilidad,
                    "habilidades" => $habilidadesProfesor
                ];
            }

            // Obtener Materias
            $materias = ModeloMaterias::mdlMostrarMaterias("subjects", null, null);

            if (empty($materias)) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "No se encontraron materias."
                ];
            }

            foreach ($materias as $key => $materia) {
                // Obtener habilidades de la materia
                $habilidadesMateria = ModeloHabilidades::mdlMostrarMateriasHabilidades("subject_skills", "subject_id", $materia['subject_id']);

                $dataMaterias[$key] = [
                    "id" => $materia['subject_id'],
                    "nombre" => $materia['name'],
                    "horas_semana" => $materia['duration_hours'],
                    "semestre" => $materia['semester'],
                    "habilidades_requeridas" => $habilidadesMateria
                ];
            }

            // Obtener Habilidades
            $habilidades = ModeloHabilidades::mdlMostrarHabilidades("skills", null, null);

            if (empty($habilidades)) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "No se encontraron habilidades."
                ];
            }

            foreach ($habilidades as $key => $habilidad) {

                $dataHabilidades[$key] = [
                    "id" => $habilidad['skill_id'],
                    "nombre" => $habilidad['skill_name']
                ];
            }

            /* GENERAMOS EL HORARIO */

            // // FUNCIONES DE LÓGICA (CALCULAR PUNTUACIÓN Y GENERAR BLOQUES)
            // function calcularPuntuacion(array $profesor, array $materia): int {
            //     $puntuacion = 0;
            //     foreach ($materia['habilidades_requeridas'] as $habilidad => $minimo) {
            //         if (!isset($profesor['habilidades'][$habilidad]) || $profesor['habilidades'][$habilidad] < $minimo) {
            //             return 0;
            //         }
            //     }
            //     $puntuacionHabilidades = 0;
            //     foreach ($materia['habilidades_requeridas'] as $habilidad => $minimo) {
            //         $puntuacionHabilidades += ($profesor['habilidades'][$habilidad] - $minimo);
            //     }
            //     $factorSemestre = 1 + ($materia['semester'] / 10);
            //     $puntuacion = $puntuacionHabilidades * $factorSemestre;
            //     $horas_asignadas = array_sum(array_column($profesor['materias_asignadas'], 'horas_semanales'));
            //     $penalizacion = $horas_asignadas * 2;
            //     $puntuacion -= $penalizacion;
            //     return max(0, (int)$puntuacion);
            // }

            // Enviamos los datos completos al cliente
            return [
                "status" => 200,
                "success" => true,
                "data" => [
                    "profesores" => $dataProfesor,
                    "materias" => $dataMaterias,
                    "habilidades" => $dataHabilidades
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("Error en ctrGenerarHorariosProfesoresSemana: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Error del servidor",
                "error" => $e->getMessage()
            ];
        }
    }
}