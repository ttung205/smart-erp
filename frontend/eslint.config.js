import js from '@eslint/js';
import stylistic from '@stylistic/eslint-plugin';
import pluginVue from 'eslint-plugin-vue';
import globals from 'globals';

export default [
  { ignores: ['dist/**', 'node_modules/**'] },

  js.configs.recommended,
  // essential: chỉ bắt lỗi thật của Vue, không ép định dạng template
  ...pluginVue.configs['flat/essential'],

  {
    files: ['**/*.{js,vue}'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: { ...globals.browser }
    },
    plugins: { '@stylistic': stylistic },
    rules: {
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_', caughtErrors: 'none' }],
      'vue/multi-word-component-names': 'off',

      // Định dạng JS (template Vue không bị ép)
      '@stylistic/indent': ['warn', 2, { SwitchCase: 1 }],
      '@stylistic/no-trailing-spaces': 'warn',
      '@stylistic/comma-dangle': ['warn', 'never'],
      '@stylistic/object-curly-newline': ['warn', { consistent: true }],
      '@stylistic/object-curly-spacing': ['warn', 'always'],
      '@stylistic/quotes': ['warn', 'single', { avoidEscape: true, allowTemplateLiterals: 'always' }],
      '@stylistic/eol-last': 'warn'
    }
  },

  {
    files: ['vite.config.js', 'eslint.config.js'],
    languageOptions: { globals: { ...globals.node } }
  }
];
