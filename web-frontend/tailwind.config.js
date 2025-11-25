/** @type {import('tailwindcss').Types.Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f8fafc',
          100: '#f1f5f9',
          500: '#64748b',
          600: '#475569',
          700: '#334155',
          800: '#1e293b',
          900: '#0f172a',
        }
      }
    },
  },
  plugins: [
    function ({ addComponents }) {
      addComponents({
        '.input-field': {
          '@apply w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition-all duration-200': {}
        },
        '.input-field:disabled': {
          '@apply bg-gray-100 text-gray-500 cursor-not-allowed': {}
        }
      });
    }
  ],
}