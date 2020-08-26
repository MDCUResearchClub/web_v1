const defaultTheme = require('tailwindcss/defaultTheme')

module.exports = {
  purge: [
    './templates/**/*.twig'
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: [
          'Cabin',
          ...defaultTheme.fontFamily.sans,
        ],
        serif: [
          'Avro',
          ...defaultTheme.fontFamily.serif,
        ]
      }
    },
  },
  variants: {},
  plugins: [],
}
