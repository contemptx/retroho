import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/sass/main.scss',
        'resources/sass/pages/_auth.scss',
        'resources/sass/themes/_cosmic-void.scss',
        'resources/sass/themes/_dark-blue.scss',
        'resources/sass/themes/_dark-green.scss',
        'resources/sass/themes/_dark-pink.scss',
        'resources/sass/themes/_dark-purple.scss',
        'resources/sass/themes/_dark-red.scss',
        'resources/sass/themes/_dark-teal.scss',
        'resources/sass/themes/_dark-yellow.scss',
        'resources/sass/themes/_galactic.scss',
        'resources/sass/themes/_light.scss',
        'resources/sass/themes/_material-design-v3-amoled.scss',
        'resources/sass/themes/_material-design-v3-dark.scss',
        'resources/sass/themes/_material-design-v3-light.scss',
        'resources/sass/themes/_nord.scss',
        'resources/sass/themes/_revel.scss',
        'resources/js/app.js',
      ],
      refresh: true,
    }),
  ],
});