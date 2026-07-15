import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

// Core pages live in resources/js/Pages; each module ships its own pages
// under Modules/<Name>/Vue/Pages using the same Inertia page names, so
// controllers don't need to know where a page physically lives.
const corePages = import.meta.glob('./Pages/**/*.vue');
const modulePages = import.meta.glob('../../Modules/*/Vue/Pages/**/*.vue');

export function resolvePage(name) {
    const corePath = `./Pages/${name}.vue`;

    if (corePages[corePath]) {
        return resolvePageComponent(corePath, corePages);
    }

    const suffix = `/Vue/Pages/${name}.vue`;
    const match = Object.keys(modulePages).find((path) => path.endsWith(suffix));

    if (match) {
        return resolvePageComponent(match, modulePages);
    }

    throw new Error(`Inertia page not found in core or module pages: ${name}`);
}
