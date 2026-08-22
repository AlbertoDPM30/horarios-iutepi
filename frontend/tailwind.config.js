/** @type {import('tailwindcss').Config} */

/*
 * Paleta tomada del sitio institucional (https://www.iutepi.edu):
 *   carmesi  #B20016   color de marca, botones y acentos
 *   grises   #F1F1F1 · #AAAAAA · #666666 · #4A4A4A · #2D2D2D · #0D0D0D
 *   blanco   #FFFFFF   fondo dominante
 *   tipografia Poppins
 */
export default {
  content: ['./index.html', './src/**/*.{js,jsx}'],
  theme: {
    extend: {
      colors: {
        // Carmesi institucional. El tono 700 es el exacto de la web.
        marca: {
          50: '#fff1f3',
          100: '#ffe0e5',
          200: '#ffc6d0',
          300: '#fd9caf',
          400: '#f96387',
          500: '#f03260',
          600: '#dd1148',
          700: '#b20016',
          800: '#97051a',
          900: '#810b1c',
          950: '#48000b',
        },

        /*
         * `slate` se redefine con los grises neutros del sitio. Toda la
         * interfaz ya usa `slate-*` como color neutro, asi que cambiarlo
         * aqui retiñe la aplicacion entera de una sola vez y evita que
         * los grises azulados de Tailwind choquen con el carmesi.
         */
        slate: {
          50: '#f7f7f7',
          100: '#f1f1f1',
          200: '#e4e4e4',
          300: '#d4d4d4',
          400: '#aaaaaa',
          500: '#808080',
          600: '#666666',
          700: '#4a4a4a',
          800: '#2d2d2d',
          900: '#1a1a1a',
          950: '#0d0d0d',
        },
      },

      fontFamily: {
        // Poppins para titulos y cifras (geometrica, la del sitio);
        // Inter para texto corrido, que a tamano chico se lee mejor.
        sans: ['Inter', 'Segoe UI', 'system-ui', '-apple-system', 'sans-serif'],
        titulo: ['Poppins', 'Inter', 'Segoe UI', 'system-ui', 'sans-serif'],
      },

      boxShadow: {
        tarjeta: '0 1px 2px rgba(13,13,13,.05), 0 4px 16px -6px rgba(13,13,13,.10)',
        flotante: '0 8px 30px -8px rgba(13,13,13,.22)',
      },

      keyframes: {
        aparecer: { '0%': { opacity: 0, transform: 'translateY(6px)' }, '100%': { opacity: 1, transform: 'none' } },
        entrarDerecha: { '0%': { opacity: 0, transform: 'translateX(20px)' }, '100%': { opacity: 1, transform: 'none' } },
        latido: { '0%,100%': { transform: 'scale(1)' }, '50%': { transform: 'scale(1.12)' } },
      },
      animation: {
        aparecer: 'aparecer .25s ease-out',
        entrarDerecha: 'entrarDerecha .25s ease-out',
        latido: 'latido 1.6s ease-in-out infinite',
      },
    },
  },
  plugins: [],
}
