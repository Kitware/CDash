/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('daisyui'),
  ],
  daisyui: {
    logs: false,
    darkTheme: 'light',
    themes: [
      {
        light: {
          "primary": "#0068c7",
          "primary-content": "#e0f0ff",
          "secondary": "#3dae2c",
          "secondary-content": "#e2ffde",
          "accent": "#e2e2e2",
          "accent-content": "#40434b",
          "neutral": "#4b5563",
          "base-100": "#ffffff",
          "base-content": "#3e3e3e",
          "info": "#0ea5e9",
          "success": "#22c55e",          
          "warning": "#fbbf24",
          "error": "#f43f5e",

          "--rounded-box": "0.5rem", // border radius rounded-box utility class, used in card and other large boxes
          "--rounded-btn": "0.25rem", // border radius rounded-btn utility class, used in buttons and similar element
          "--rounded-badge": "1.9rem", // border radius rounded-badge utility class, used in badges and similar
          "--tab-radius": "0.25rem", // border radius of tabs
        },
      },
    ],
  },
  prefix: 'tw-',
};

