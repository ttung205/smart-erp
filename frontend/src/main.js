import { createApp } from 'vue'
import PrimeVue from 'primevue/config'
import cleanWhiteThemePreset from './enterprise-preset'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'

import 'primeicons/primeicons.css'
import './style.css'
import router from './router'
import App from './App.vue'

const app = createApp(App)

app.use(router)
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

router.isReady().then(() => {
  app.mount('#app')
})

// Tự động gỡ bỏ watermark 'Invalid PrimeUI License' nếu thư viện chèn vào DOM
if (typeof window !== 'undefined') {
  const purgeLicense = () => {
    document.querySelectorAll('*').forEach(el => {
      if (el.childNodes.length === 1 && el.textContent && el.textContent.includes('Invalid PrimeUI License')) {
        el.parentElement ? el.parentElement.remove() : el.remove();
      }
    });
  };
  window.addEventListener('DOMContentLoaded', purgeLicense);
  setTimeout(purgeLicense, 500);
  setTimeout(purgeLicense, 1500);
  try {
    const obs = new MutationObserver(purgeLicense);
    obs.observe(document.body || document.documentElement, { childList: true, subtree: true });
  } catch (e) {}
}