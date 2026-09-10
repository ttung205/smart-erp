import { createApp } from 'vue'
import PrimeVue from 'primevue/config'
import cleanWhiteThemePreset from './enterprise-preset'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'

import 'primeicons/primeicons.css'
import './style.css'
import App from './App.vue'

const app = createApp(App)

app.use(PrimeVue, {
  theme: {
    preset: cleanWhiteThemePreset,
    options: {
      prefix: 'p',
      darkModeSelector: false, // Clean White "Clean Canvas & Subtle Elevation"
      cssLayer: false
    }
  }
})

app.use(ToastService)
app.use(ConfirmationService)
app.directive('tooltip', Tooltip)

app.mount('#app')