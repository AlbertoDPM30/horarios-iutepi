/**
 * Fondo del panel institucional del login.
 *
 * Va dibujado en SVG y no como fotografia a proposito:
 *
 *  - las fotos del sitio del instituto son de graduandos identificables
 *    o banners con texto incrustado, y ninguna funciona detras de un
 *    titular;
 *  - un SVG pesa unos pocos kB, se ve nitido en cualquier pantalla y no
 *    añade una descarga al primer render.
 *
 * Si mas adelante hay una foto de campus, basta con sustituir este
 * componente por un <img> con el mismo velo encima.
 */
export default function FondoAcademico() {
  return (
    <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
      <svg
        className="absolute inset-0 h-full w-full"
        viewBox="0 0 800 1000"
        preserveAspectRatio="xMidYMax slice"
        fill="none"
      >
        <defs>
          {/* Rejilla de horario, el motivo de fondo */}
          <pattern id="fa-rejilla" width="84" height="56" patternUnits="userSpaceOnUse">
            <path d="M84 0H0v56" stroke="#fff" strokeOpacity="0.10" strokeWidth="1" fill="none" />
          </pattern>

          {/* El contenido se desvanece hacia arriba para no pelearse con el titular */}
          <linearGradient id="fa-velo" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0.30" stopColor="#fff" stopOpacity="0" />
            <stop offset="0.72" stopColor="#fff" stopOpacity="0.55" />
            <stop offset="1" stopColor="#fff" stopOpacity="1" />
          </linearGradient>
          <mask id="fa-mascara">
            <rect width="800" height="1000" fill="url(#fa-velo)" />
          </mask>

          <radialGradient id="fa-brillo" cx="0.5" cy="0.15" r="0.75">
            <stop offset="0" stopColor="#fff" stopOpacity="0.16" />
            <stop offset="1" stopColor="#fff" stopOpacity="0" />
          </radialGradient>
        </defs>

        <rect width="800" height="1000" fill="url(#fa-rejilla)" />
        <rect width="800" height="1000" fill="url(#fa-brillo)" />

        {/* ---- Edificio academico: frontón, columnas y arcos ---- */}
        <g mask="url(#fa-mascara)" stroke="#fff" strokeOpacity="0.5" fill="none" strokeWidth="2">
          {/* Escalinata */}
          <path d="M120 940h560M150 916h500M180 892h440" strokeOpacity="0.32" />

          {/* Basamento */}
          <path d="M180 892V706M620 892V706" strokeOpacity="0.4" />

          {/* Columnas con arcos */}
          {[0, 1, 2, 3, 4, 5].map((i) => {
            const x = 226 + i * 70
            return (
              <g key={i}>
                <path d={`M${x} 880V760`} strokeOpacity="0.42" />
                <path d={`M${x - 22} 760a22 22 0 0 1 44 0`} strokeOpacity="0.42" />
                <path d={`M${x - 26} 880h52`} strokeOpacity="0.3" />
              </g>
            )
          })}

          {/* Entablamento y frontón */}
          <path d="M168 706h464" strokeOpacity="0.5" />
          <path d="M168 690h464" strokeOpacity="0.32" />
          <path d="M400 604 640 690H160L400 604Z" strokeOpacity="0.5" />

          {/* Reloj del frontón: la hora manda en un horario */}
          <circle cx="400" cy="662" r="17" strokeOpacity="0.42" />
          <path d="M400 662v-10M400 662l7 5" strokeOpacity="0.42" strokeLinecap="round" />
        </g>

        {/* ---- Libros apilados, a la izquierda ---- */}
        <g mask="url(#fa-mascara)" stroke="#fff" strokeOpacity="0.34" fill="none" strokeWidth="2">
          <rect x="60" y="856" width="96" height="18" rx="3" />
          <rect x="52" y="874" width="112" height="18" rx="3" />
          <rect x="66" y="892" width="84" height="18" rx="3" />
          <path d="M74 865h34M66 883h44M80 901h28" strokeOpacity="0.24" />
        </g>

        {/* ---- Birrete, a la derecha ---- */}
        <g mask="url(#fa-mascara)" stroke="#fff" strokeOpacity="0.34" fill="none" strokeWidth="2">
          <path d="M646 862 706 838l60 24-60 24-60-24Z" />
          <path d="M666 872v26c0 9 18 15 40 15s40-6 40-15v-26" strokeOpacity="0.26" />
          <path d="M766 862v34" strokeOpacity="0.26" strokeLinecap="round" />
          <circle cx="766" cy="900" r="4" strokeOpacity="0.26" />
        </g>
      </svg>
    </div>
  )
}
