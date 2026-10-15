import { createApp } from 'vue'
import { createPinia } from 'pinia'
import PrimeVue from 'primevue/config'
import cleanWhiteThemePreset from './enterprise-preset'
import ToastService from 'primevue/toastservice'
import ConfirmationService from 'primevue/confirmationservice'
import Tooltip from 'primevue/tooltip'

import 'primeicons/primeicons.css'
import './style.css'
import router from './router'
import App from './App.vue'
import Dialog from 'primevue/dialog'
import ConfirmDialog from 'primevue/confirmdialog'

// Tắt chế độ kéo thả (draggable = false) mặc định cho toàn bộ Dialog và ConfirmDialog
if (Dialog?.extends?.props?.draggable) {
  Dialog.extends.props.draggable.default = false
} else if (Dialog?.props?.draggable) {
  Dialog.props.draggable.default = false
}

if (ConfirmDialog?.extends?.props?.draggable) {
  ConfirmDialog.extends.props.draggable.default = false
} else if (ConfirmDialog?.props?.draggable) {
  ConfirmDialog.props.draggable.default = false
}

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
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

// Triệt tiêu hoàn toàn watermark 'Invalid PrimeUI License' của PrimeVue v5
if (typeof window !== 'undefined') {
  const purgeLicense = () => {
    const host = document.getElementById('p-license-host');
    if (host && (host.shadowRoot || host.childNodes.length > 0)) {
      host.remove();
    }
    if (!document.getElementById('p-license-host')) {
      const dummy = document.createElement('div');
      dummy.id = 'p-license-host';
      dummy.style.display = 'none';
      if (document.body) {
        document.body.appendChild(dummy);
      }
    }
  };
  purgeLicense();
  window.addEventListener('DOMContentLoaded', purgeLicense);
  setTimeout(purgeLicense, 100);
  setTimeout(purgeLicense, 500);
  setTimeout(purgeLicense, 1500);
  try {
    const obs = new MutationObserver(() => {
      const host = document.getElementById('p-license-host');
      if (host && (host.shadowRoot || host.childNodes.length > 0)) {
        host.remove();
      }
    });
    obs.observe(document.body || document.documentElement, { childList: true, subtree: true });
  } catch (e) {}
}
