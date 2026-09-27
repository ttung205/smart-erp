import Preset from '@primeuix/themes/aura';
import { definePreset } from '@primeuix/themes';

// Chuẩn hóa Preset PrimeVue theo phong cách Soft Azure Radiant White (UI_RULES.md)
const cleanWhiteThemePreset = definePreset(Preset, {
    semantic: {
        primary: {
            50: '#f0f9ff',  // Ice Blue / Sky-50
            100: '#e0f2fe', // Hairline Border / Sky-100
            200: '#bae6fd', // Border Focus / Sky-200
            300: '#7dd3fc',
            400: '#38bdf8', // Light Accent / Sky-400
            500: '#0ea5e9',
            600: '#0284c7', // Primary Brand Azure Blue / Sky-600
            700: '#0369a1', // Deep Azure Hover / Sky-700
            800: '#075985',
            900: '#0c4a6e',
            950: '#082f49',
        },
        colorScheme: {
            light: {
                surface: {
                    0: '#ffffff',     // Pure white
                    50: '#f0f9ff',    // Ice blue backdrop tint
                    100: '#f8fafc',   // Hover bg
                    200: '#e0f2fe',   // Hairline border
                    300: '#bae6fd',   // Border highlight
                    400: '#94a3b8',
                    500: '#64748b',   // Subtext
                    600: '#475569',
                    700: '#334155',   // Body text
                    800: '#1e293b',
                    900: '#0f172a',   // Main headings
                },
            },
        },
    },
});

export default cleanWhiteThemePreset;