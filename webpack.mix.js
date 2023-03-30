const mix = require('laravel-mix');

const SCSSDIR = 'src/scss';

mix.sass(`${SCSSDIR}/_main.scss`, 'client/dist/styles.css');

// Move all fontawesome content to the client/dist/thirdparty/fontawesome folder
mix.copy(
    'node_modules/@fortawesome/fontawesome-free/webfonts',
    'client/dist/thirdparty/fontawesome/webfonts'
);
mix.copy(
    'node_modules/@fortawesome/fontawesome-free/css/all.min.css',
    'client/dist/thirdparty/fontawesome/css/all.min.css'
);
