import { Routes, Route, Navigate } from 'react-router-dom'
import Layout from './components/Layout'
import ProtectedRoute from './components/ProtectedRoute'
import Login from './pages/Login'
import Dashboard from './pages/Dashboard'
import Teachers from './pages/Teachers'
import Students from './pages/Students'
import Subjects from './pages/Subjects'
import Skills from './pages/Skills'
import Modules from './pages/Modules'
import Users from './pages/Users'
import TeacherSkills from './pages/TeacherSkills'
import TeacherAvailability from './pages/TeacherAvailability'
import SubjectSkills from './pages/SubjectSkills'
import TeacherSubjects from './pages/TeacherSubjects'
import GenerateSchedule from './pages/GenerateSchedule'
import ViewSchedules from './pages/ViewSchedules'

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/" element={<ProtectedRoute><Layout /></ProtectedRoute>}>
        <Route index element={<Navigate to="/dashboard" replace />} />
        <Route path="dashboard" element={<Dashboard />} />
        <Route path="profesores" element={<Teachers />} />
        <Route path="estudiantes" element={<Students />} />
        <Route path="materias" element={<Subjects />} />
        <Route path="habilidades" element={<Skills />} />
        <Route path="modulos" element={<Modules />} />
        <Route path="usuarios" element={<Users />} />
        <Route path="profesores-habilidades" element={<TeacherSkills />} />
        <Route path="profesores-disponibilidad" element={<TeacherAvailability />} />
        <Route path="materias-habilidades" element={<SubjectSkills />} />
        <Route path="profesores-materias" element={<TeacherSubjects />} />
        <Route path="generar-horario" element={<GenerateSchedule />} />
        <Route path="ver-horarios" element={<ViewSchedules />} />
      </Route>
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  )
}
