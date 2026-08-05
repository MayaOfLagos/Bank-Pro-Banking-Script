import forms from '@tailwindcss/forms'
import typography from '@tailwindcss/typography'

export default {
  // The theme system sets [data-theme] on <html> (see composables/useTheme.js)
  // and never adds a `.dark` class, so plain 'class' made every `dark:` variant
  // dead code. Point the variant at the attribute that actually toggles.
  darkMode: ['selector', '[data-theme="dark"]'],
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#eef4ff',
          100: '#dbe8ff',
          200: '#bdd3ff',
          300: '#8fb3ff',
          400: '#5b8bff',
          500: '#2f66ff',
          600: '#2148f5',
          700: '#1d3be1',
          800: '#1f34b6',
          900: '#1f328f'
        },
        navy: {
          950: '#080d18',
          900: '#0c1220',
          800: '#111827',
          700: '#141f30',
          600: '#1a2436',
          500: '#1e2d44',
          400: '#243452',
          300: '#2d4166'
        }
      },
      fontFamily: {
        sans: ['-apple-system', 'BlinkMacSystemFont', 'SF Pro Display', 'Inter', 'system-ui', 'sans-serif']
      }
    }
  },
  plugins: [forms, typography]
}
