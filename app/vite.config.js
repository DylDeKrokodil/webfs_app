import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

const redirectViteRootToLaravel = (appUrl) => ({
    name: 'redirect-vite-root-to-laravel',
    configureServer(server) {
        server.middlewares.use((request, response, next) => {
            const path = request.url?.split('?')[0];
            const acceptsHtml = request.headers.accept?.includes('text/html');

            if ((request.method === 'GET' || request.method === 'HEAD') && path === '/' && acceptsHtml) {
                response.statusCode = 302;
                response.setHeader('Location', appUrl);
                response.end();
                return;
            }

            next();
        });
    },
});

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const appUrl = env.APP_URL || 'http://localhost:8080';

    return {
        plugins: [
            redirectViteRootToLaravel(appUrl),
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
                fonts: [
                    bunny('Instrument Sans', {
                        weights: [400, 500, 600],
                    }),
                ],
            }),
            tailwindcss(),
            vue(),
        ],
        server: {
            host: '0.0.0.0',
            cors: true,
            hmr: {
                host: 'localhost',
                port: 5173,
            },
            watch: {
                usePolling: true,
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
