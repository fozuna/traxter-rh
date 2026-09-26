/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './app/views/**/*.php',
  ],
  safelist: [
    'ct-btn',
    'ct-btn-primary',
    'ct-btn-success',
    'ct-btn-warning',
    'ct-btn-muted',
    'ct-badge',
    'ct-badge-active',
    'ct-badge-inactive',
  ],
  theme: {
    extend: {
      screens: {
        xs: '480px',
        sm: '640px',
        md: '768px',
        lg: '1024px',
        xl: '1280px'
      },
      colors: {
        ctdark: 'var(--ctdark)',
        ctgreen: 'var(--ctgreen)',
        ctlight: 'var(--ctlight)',
        ctpblue: 'var(--ctpblue)',
      },
      fontFamily: {
        sans: ['Montserrat','system-ui','-apple-system','sans-serif']
      }
    },
  },
  plugins: [],
}
