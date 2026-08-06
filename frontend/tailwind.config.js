/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,ts}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'Segoe UI', 'system-ui', '-apple-system', 'Helvetica Neue', 'Arial', 'sans-serif'],
      },
      colors: {
        /**
         * Paleta YIGM ERP v3 — azul acero/marino desaturado.
         * Diseñada para jornadas largas: sin azules eléctricos,
         * contraste alto sobre fondos claros.
         */
        primary: {
          50: '#f4f6f9',
          100: '#e8edf3',
          200: '#d4dde8',
          300: '#b4c3d6',
          400: '#8ca3bf',
          500: '#6d87a8',
          600: '#57708f',
          700: '#485c77',
          800: '#3e4d63',
          900: '#374253',
          950: '#242b38',
        },
        // Marino profundo de la barra lateral y panel de marca
        sidebar: '#121927',
        // Acento inspirado en Yamaha Racing: solo marca y señales puntuales
        accent: {
          DEFAULT: '#c8102e',
          soft: '#e35d6a',
        },
      },
      boxShadow: {
        card: '0 1px 2px 0 rgb(15 23 42 / 0.05), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
        overlay: '0 20px 50px -12px rgb(15 23 42 / 0.35)',
      },
    },
  },
  plugins: [],
}
