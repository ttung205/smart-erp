import { ref, watch, onBeforeUnmount } from 'vue';
import { chatApi } from '../../api/chat.api';

// Cache dùng chung: cùng một ảnh chỉ tải một lần trong phiên
const cache = new Map();

/** Trả về object URL cho ảnh/tệp cần token (thẻ <img> không gửi được header Authorization). */
export function useAuthBlob(urlSource) {
  const objectUrl = ref('');
  const failed = ref(false);
  let active = true;

  watch(
    urlSource,
    async (url) => {
      objectUrl.value = '';
      failed.value = false;
      if (!url) return;
      try {
        if (!cache.has(url)) cache.set(url, chatApi.fetchBlob(url).then((blob) => URL.createObjectURL(blob)));
        const value = await cache.get(url);
        if (active) objectUrl.value = value;
      } catch {
        cache.delete(url);
        if (active) failed.value = true;
      }
    },
    { immediate: true }
  );

  onBeforeUnmount(() => (active = false));
  return { objectUrl, failed };
}

/** Tải tệp về máy (tạo link tạm từ blob). */
export async function downloadAuthFile(url, fileName) {
  const blob = await chatApi.fetchBlob(url);
  const href = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = href;
  link.download = fileName;
  link.click();
  setTimeout(() => URL.revokeObjectURL(href), 1000);
}
