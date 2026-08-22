import { useState } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import {
  ArrowLeft, ArrowRight, Briefcase, CalendarDays, GraduationCap, IdCard, KeyRound, Mail, UserRound,
} from 'lucide-react'
import { useAuth } from '../context/AuthContext'
import { useAvisos } from '../context/AvisosContext'
import Boton from '../components/ui/Boton'
import { Campo } from '../components/ui/Campos'
import { PantallaCarga } from '../components/ui/Datos'
import Logo from '../components/layout/Logo'
import FondoAcademico from '../components/layout/FondoAcademico'
import { cx, destinoTrasEntrar } from '../lib/utils'

const PERFILES = [
  {
    rol: 'ESTUDIANTE',
    titulo: 'Alumnos',
    descripcion: 'Consulta y arma tu horario',
    icono: UserRound,
    color: 'from-slate-500 to-slate-700',
    campo: {
      nombre: 'codigo',
      etiqueta: 'Codigo de estudiante',
      ayuda: 'Los 6 digitos que aparecen en tu constancia de inscripcion.',
      icono: IdCard,
      placeholder: '264206',
      inputMode: 'numeric',
    },
  },
  {
    rol: 'DOCENTE',
    titulo: 'Docente',
    descripcion: 'Revisa tus clases y laboratorios',
    icono: GraduationCap,
    color: 'from-slate-800 to-slate-950',
    campo: {
      nombre: 'cedula',
      etiqueta: 'Cedula de identidad',
      ayuda: 'Solo los numeros, sin puntos ni guiones.',
      icono: IdCard,
      placeholder: '12345678',
      inputMode: 'numeric',
    },
  },
  {
    rol: 'ADMIN',
    titulo: 'Administrador',
    descripcion: 'Gestiona periodos y horarios',
    icono: Briefcase,
    color: 'from-marca-600 to-marca-800',
    campo: {
      nombre: 'correo',
      etiqueta: 'Correo institucional',
      icono: Mail,
      placeholder: 'coordinacion@iutepi.edu.ve',
      type: 'email',
      autoComplete: 'username',
    },
    conPassword: true,
  },
]

/**
 * Pantalla de acceso en dos pasos:
 *   1. Quien esta entrando (alumno, docente o administrador)
 *   2. La credencial que corresponde a ese perfil
 *
 * Se separa asi para que el alumno no tenga que entender por que a el le
 * piden un codigo y al docente una cedula.
 */
export default function Entrar() {
  const { autenticado, cargando, entrar, rol } = useAuth()
  const { avisar } = useAvisos()
  const navegar = useNavigate()
  const ubicacion = useLocation()

  const [perfil, setPerfil] = useState(null)
  const [valores, setValores] = useState({})
  const [enviando, setEnviando] = useState(false)
  const [error, setError] = useState('')

  if (cargando) return <PantallaCarga />
  if (autenticado) return <Navigate to={destinoTrasEntrar(rol, ubicacion.state?.desde)} replace />

  async function enviar(e) {
    e.preventDefault()
    setError('')
    setEnviando(true)

    try {
      const credenciales =
        perfil.rol === 'ADMIN'
          ? { correo: valores.correo?.trim(), password: valores.password }
          : { [perfil.campo.nombre]: valores[perfil.campo.nombre]?.trim() }

      const usuario = await entrar(perfil.rol, credenciales)
      avisar.exito(`Bienvenido, ${usuario.nombres || usuario.nombre_completo}.`)
      navegar(destinoTrasEntrar(usuario.rol, ubicacion.state?.desde), { replace: true })
    } catch (e2) {
      setError(e2.message)
      avisar.error(e2.message)
    } finally {
      setEnviando(false)
    }
  }

  return (
    <div className="flex min-h-screen flex-col bg-slate-50 lg:flex-row">
      {/* ---- Panel institucional ---- */}
      <div className="relative flex min-h-[19rem] flex-col justify-between overflow-hidden bg-marca-800 px-6 py-8 text-white lg:min-h-screen lg:w-[42%] lg:px-12 lg:py-12">
        <FondoAcademico />

        <div className="relative flex items-center gap-3">
          <Logo sobreOscuro />
          <div className="border-l border-white/25 pl-3">
            <p className="font-titulo text-base font-semibold leading-tight">Horarios academicos</p>
            <p className="text-xs text-marca-100">Instituto Universitario de Tecnologia para la Informatica</p>
          </div>
        </div>

        <div className="relative my-10 lg:my-0">
          <h1 className="font-titulo text-3xl font-semibold leading-snug lg:text-4xl">
            La planificacion academica,
            <br />
            <span className="text-marca-200">sin papel y sin choques.</span>
          </h1>
          <p className="mt-4 max-w-md text-sm leading-relaxed text-marca-100/90">
            Horarios de secciones, docentes y laboratorios generados de forma automatica a partir de la
            disponibilidad y las competencias de cada profesor.
          </p>
        </div>

        <p className="relative hidden text-xs text-marca-200/80 lg:block">
          Periodos entre semana y sabatinos · Analisis de Sistemas · Administracion Industrial · Electronica
        </p>
      </div>

      {/* ---- Formulario ---- */}
      <div className="flex flex-1 items-center justify-center px-4 py-10 sm:px-8">
        <div className="w-full max-w-md">
          {!perfil ? (
            <div className="animate-aparecer">
              <h2 className="font-titulo text-2xl font-semibold text-slate-900">¿Quien esta accediendo?</h2>
              <p className="mt-1.5 text-sm text-slate-500">Elige tu perfil para continuar.</p>

              <div className="mt-6 space-y-3">
                {PERFILES.map((p) => {
                  const Icono = p.icono
                  return (
                    <button
                      key={p.rol}
                      type="button"
                      onClick={() => {
                        setPerfil(p)
                        setValores({})
                        setError('')
                      }}
                      className="group flex w-full items-center gap-4 rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-tarjeta transition hover:border-marca-300 hover:shadow-flotante focus-visible:outline focus-visible:outline-2 focus-visible:outline-marca-700"
                    >
                      <span className={cx('flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br text-white', p.color)}>
                        <Icono className="h-6 w-6" />
                      </span>
                      <span className="min-w-0 flex-1">
                        <span className="block text-base font-semibold text-slate-900">{p.titulo}</span>
                        <span className="block text-sm text-slate-500">{p.descripcion}</span>
                      </span>
                      <ArrowRight className="h-5 w-5 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-marca-700" />
                    </button>
                  )
                })}
              </div>
            </div>
          ) : (
            <form onSubmit={enviar} className="animate-aparecer">
              <button
                type="button"
                onClick={() => {
                  setPerfil(null)
                  setError('')
                }}
                className="mb-5 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-slate-800"
              >
                <ArrowLeft className="h-4 w-4" />
                Cambiar perfil
              </button>

              <div className="mb-6 flex items-center gap-3">
                <span className={cx('flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br text-white', perfil.color)}>
                  <perfil.icono className="h-5 w-5" />
                </span>
                <div>
                  <h2 className="font-titulo text-xl font-semibold text-slate-900">{perfil.titulo}</h2>
                  <p className="text-sm text-slate-500">{perfil.descripcion}</p>
                </div>
              </div>

              <div className="space-y-4">
                <Campo
                  etiqueta={perfil.campo.etiqueta}
                  ayuda={perfil.campo.ayuda}
                  icono={perfil.campo.icono}
                  placeholder={perfil.campo.placeholder}
                  type={perfil.campo.type || 'text'}
                  inputMode={perfil.campo.inputMode}
                  autoCapitalize={perfil.campo.autoCapitalize}
                  autoComplete={perfil.campo.autoComplete}
                  requerido
                  autoFocus
                  value={valores[perfil.campo.nombre] || ''}
                  onChange={(e) => setValores((v) => ({ ...v, [perfil.campo.nombre]: e.target.value }))}
                />

                {perfil.conPassword && (
                  <Campo
                    etiqueta="Contrasena"
                    type="password"
                    icono={KeyRound}
                    placeholder="••••••••"
                    autoComplete="current-password"
                    requerido
                    value={valores.password || ''}
                    onChange={(e) => setValores((v) => ({ ...v, password: e.target.value }))}
                  />
                )}
              </div>

              {error && (
                <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-3 text-sm text-rose-800">
                  {error}
                </div>
              )}

              <Boton type="submit" tamano="lg" bloque cargando={enviando} className="mt-6" iconoDerecha={ArrowRight}>
                Entrar
              </Boton>

              <p className="mt-5 text-center text-xs leading-relaxed text-slate-400">
                {perfil.rol === 'ESTUDIANTE'
                  ? 'Si tu codigo no funciona, verifica tu inscripcion con control de estudios.'
                  : perfil.rol === 'DOCENTE'
                    ? 'Si tu cedula no aparece, pide a coordinacion que te registre.'
                    : 'El acceso queda registrado en la bitacora del sistema.'}
              </p>
            </form>
          )}
        </div>
      </div>
    </div>
  )
}
