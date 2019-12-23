// tailwind.config.js
const {
  fontFamily,
  colors: { teal, orange, pink, purple, green, indigo, yellow, ...colors },
} = require('tailwindcss/defaultTheme');

module.exports = {
  corePlugins: {
    preflight: true,
    float: false,
  },
  theme: {
    fontFamily: {
      sans: ['Px Grotesk', ...fontFamily.sans],
      serif: ['LyonText', ...fontFamily.serif],
      display: ['Px Grotesk', ...fontFamily.sans],
      mono: fontFamily.mono,
    },
    colors,
    screens: {
      sm: '640px',
      md: '768px',
      lg: '1024px',
    },
    extend: {},
  },
  variants: {},
  plugins: [],
};
