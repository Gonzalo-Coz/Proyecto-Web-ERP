/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,ts}'],
  theme: {
    extend: {
      colors: {
        // Identidad corporativa YIGM (ajustable desde Configuración visual)
        primary: {
          50: '#eef6ff',
          100: '#d9eaff',
          200: '#bcdbff',
          300: '#8ec4ff',
          400: '#59a3ff',
          500: '#337fff',
          600: '#1b5ef5',
          700: '#1449e1',
          800: '#173cb6',
          900: '#19388f',
          950: '#142357',
        },
      },
    },
  },
  plugins: [],
}
