import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { configureEcho } from '@laravel/echo-vue';
import { createBareeI18n } from './i18n';

configureEcho({
    broadcaster: 'reverb',
});

const appName = 'BAREE';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name === 'Dashboard':
                return AppLayout;
            default:
                // Every BAREE page (employee, institution admin, platform admin, assessment...) brings its own shell
                // (StudioShell / AdminShell / standalone). Wrapping them in the starter kit's sidebar layout is wrong.
                return null;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });

        // vue-i18n MUST be installed: every BAREE page calls useI18n(). The server renders <html lang="ar|en">.
        app.use(createBareeI18n(document.documentElement.lang));
    },
    progress: {
        color: '#4B5563',
    },
});

// Keep the UI language and direction in step with the server's locale after every visit (login, language switch).
router.on('navigate', (event) => {
    const locale = (event.detail.page.props as { locale?: string }).locale;
    if (locale) {
        createBareeI18n(locale);
    }
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
