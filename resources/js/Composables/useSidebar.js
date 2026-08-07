import { ref } from 'vue';

// Module-level state so the top-bar toggle button and the sidebar itself
// (rendered in different components on either side of a slot boundary)
// share one source of truth without prop/emit drilling.
const STORAGE_KEY = 'sidebar-expanded';
const DESKTOP_QUERY = '(min-width: 768px)';

const expanded = ref(JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'false'));
const mobileOpen = ref(false);

const isDesktop = () => window.matchMedia(DESKTOP_QUERY).matches;

function toggle() {
    if (isDesktop()) {
        expanded.value = !expanded.value;
        localStorage.setItem(STORAGE_KEY, JSON.stringify(expanded.value));
    } else {
        mobileOpen.value = !mobileOpen.value;
    }
}

function closeMobile() {
    mobileOpen.value = false;
}

export function useSidebar() {
    return { expanded, mobileOpen, toggle, closeMobile };
}
