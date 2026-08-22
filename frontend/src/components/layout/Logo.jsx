import { cx } from '../../lib/utils'

/**
 * Marca del instituto.
 *
 * Los archivos salen del sitio institucional (`public/marca/`):
 *   logo-iutepi.jpg   logotipo completo, fondo blanco
 *   favicon-192.png   solo el simbolo de la llave, con transparencia
 *
 * Como el logotipo trae fondo blanco, sobre superficies oscuras se monta
 * dentro de una pastilla blanca en vez de recortarlo.
 */
export default function Logo({ variante = 'completo', sobreOscuro = false, className }) {
  if (variante === 'simbolo') {
    return (
      <span
        className={cx(
          'flex shrink-0 items-center justify-center overflow-hidden rounded-xl',
          sobreOscuro ? 'bg-white' : 'bg-slate-50',
          className || 'h-10 w-10'
        )}
      >
        <img
          src="./marca/favicon-192.png"
          alt="IUTEPI"
          width={192}
          height={192}
          className="h-[70%] w-[70%] object-contain"
        />
      </span>
    )
  }

  return (
    <span
      className={cx(
        'inline-flex items-center overflow-hidden rounded-lg',
        sobreOscuro && 'bg-white px-2.5 py-1.5',
        className
      )}
    >
      <img
        src="./marca/logo-iutepi.jpg"
        alt="IUTEPI · Instituto Universitario de Tecnologia para la Informatica"
        width={230}
        height={55}
        className="h-8 w-auto object-contain"
      />
    </span>
  )
}
