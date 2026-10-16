import { defineStore } from 'pinia';
import { ref, reactive, computed } from 'vue';
import * as signalR from '@microsoft/signalr';
import { chatApi } from '../api/chat.api';
import { storage } from '../utils/storage';
import { parseJwt } from '../utils/jwt';

const TYPING_TTL_MS = 4000;
const TYPING_THROTTLE_MS = 3000;
const SOUND_KEY = 'chat.sound';

export const REACTIONS = ['❤️', '👍', '😄', '😮', '😢', '😡'];
export const RECALL_WINDOW_MS = 24 * 60 * 60 * 1000;

/**
 * Trạng thái chat dùng chung cho trang /chat và widget nổi.
 * Mọi thao tác ghi đi qua REST; SignalR chỉ nhận sự kiện server đẩy xuống theo user.
 */
export const useChatStore = defineStore('chat', () => {
  const meId = ref(0);
  const conversations = ref([]);
  const messages = reactive({}); // convId -> { items, hasMore, loaded, loadingOlder }
  const pins = reactive({}); // convId -> [message]
  const typing = reactive({}); // convId -> { userId: { name, until } }
  const online = ref(new Set());
  const aliases = ref({});
  const activeId = ref(null);
  const connectionState = ref('disconnected');
  const loadingConversations = ref(false);
  const soundEnabled = ref(readSoundSetting());

  let connection = null;
  let startedToken = '';
  let lastTypingSentAt = 0;

  // ---------- Getter ----------

  const conversationMap = computed(() => new Map(conversations.value.map((c) => [c.id, c])));
  const activeConversation = computed(() => conversationMap.value.get(activeId.value) || null);
  const unreadTotal = computed(() => conversations.value.reduce((sum, c) => sum + (c.muted ? 0 : c.unread_count || 0), 0));

  function partnerOf(conv) {
    if (!conv || conv.type !== 'direct') return null;
    return conv.members.find((m) => m.user_id !== meId.value) || null;
  }

  function displayName(userId, fallback) {
    return aliases.value[userId] || fallback || `User #${userId}`;
  }

  function conversationName(conv) {
    if (!conv) return '';
    if (conv.type === 'group') return conv.title || 'Nhóm chat';
    const partner = partnerOf(conv);
    return partner ? displayName(partner.user_id, partner.name) : 'Hội thoại';
  }

  function memberName(conv, userId, fallback) {
    const member = conv?.members.find((m) => m.user_id === userId);
    return displayName(userId, member?.name || fallback);
  }

  const isOnline = (userId) => online.value.has(userId);

  function typingNames(convId) {
    const now = Date.now();
    return Object.values(typing[convId] || {}).filter((t) => t.until > now).map((t) => t.name);
  }

  // ---------- Khởi động & kết nối ----------

  async function init() {
    const token = storage.getToken();
    if (!token) return;
    if (startedToken === token) return;
    await stop();
    startedToken = token;
    meId.value = Number(parseJwt(token)?.sub || 0);

    await Promise.allSettled([fetchConversations(), fetchPreferences(), fetchOnline()]);
    await connect();
  }

  async function stop() {
    startedToken = '';
    if (connection) {
      const old = connection;
      connection = null;
      await old.stop().catch(() => {});
    }
    conversations.value = [];
    Object.keys(messages).forEach((k) => delete messages[k]);
    Object.keys(pins).forEach((k) => delete pins[k]);
    activeId.value = null;
    connectionState.value = 'disconnected';
  }

  async function connect() {
    connection = new signalR.HubConnectionBuilder()
      .withUrl(chatApi.hubUrl, { accessTokenFactory: () => storage.getToken() })
      .withAutomaticReconnect([0, 2000, 5000, 10000, 20000, 30000])
      .configureLogging(signalR.LogLevel.Warning)
      .build();

    connection.on('conversation.upserted', ({ conversation }) => upsertConversation(conversation));
    connection.on('conversation.removed', ({ conversation_id }) => removeConversation(conversation_id));
    connection.on('message.created', ({ message }) => onMessageCreated(message));
    connection.on('message.updated', ({ message }) => onMessageUpdated(message));
    connection.on('message.hidden', ({ conversation_id, message_id }) => removeMessage(conversation_id, message_id));
    connection.on('read.updated', onReadUpdated);
    connection.on('typing', onTyping);
    connection.on('presence', ({ user_id, online: isUp }) => setOnline(user_id, isUp));

    connection.onreconnecting(() => (connectionState.value = 'reconnecting'));
    connection.onreconnected(async () => {
      connectionState.value = 'connected';
      await resync();
    });
    connection.onclose(() => (connectionState.value = 'disconnected'));

    try {
      connectionState.value = 'connecting';
      await connection.start();
      connectionState.value = 'connected';
    } catch (e) {
      connectionState.value = 'disconnected';
      console.warn('Không kết nối được chat realtime:', e);
    }
  }

  /** Sau khi mất kết nối: tải lại danh sách và tải bù tin mới của các hội thoại đã mở. */
  async function resync() {
    await Promise.allSettled([fetchConversations(), fetchOnline()]);
    for (const convId of Object.keys(messages).map(Number)) {
      if (!conversationMap.value.has(convId)) {
        delete messages[convId];
        continue;
      }
      const bucket = messages[convId];
      let afterId = bucket.items.length ? bucket.items[bucket.items.length - 1].id : 0;
      for (let page = 0; page < 10; page++) {
        const result = await chatApi.messages(convId, { after_id: afterId, limit: 100 }).catch(() => null);
        if (!result) break;
        result.data.forEach((m) => insertMessage(m));
        if (!result.has_more || !result.data.length) break;
        afterId = result.data[result.data.length - 1].id;
      }
    }
    if (activeId.value) await loadPins(activeId.value);
  }

  // ---------- Danh sách hội thoại ----------

  async function fetchConversations() {
    loadingConversations.value = true;
    try {
      const all = [];
      for (let page = 1; page <= 20; page++) {
        const result = await chatApi.conversations({ page, per_page: 100 });
        all.push(...result.data);
        if (all.length >= result.total || !result.data.length) break;
      }
      conversations.value = sortConversations(all);
    } finally {
      loadingConversations.value = false;
    }
  }

  async function fetchPreferences() {
    aliases.value = (await chatApi.preferences()).aliases || {};
  }

  async function fetchOnline() {
    online.value = new Set(await chatApi.onlineUsers());
  }

  function sortConversations(list) {
    return [...list].sort((a, b) => {
      if (!!a.pinned_at !== !!b.pinned_at) return a.pinned_at ? -1 : 1;
      if (a.pinned_at && b.pinned_at && a.pinned_at !== b.pinned_at) return a.pinned_at > b.pinned_at ? -1 : 1;
      const at = a.last_message_at || a.created_at;
      const bt = b.last_message_at || b.created_at;
      if (at !== bt) return at > bt ? -1 : 1;
      return b.id - a.id;
    });
  }

  function upsertConversation(conv) {
    const index = conversations.value.findIndex((c) => c.id === conv.id);
    const next = [...conversations.value];
    if (index >= 0) next[index] = conv;
    else next.push(conv);
    conversations.value = sortConversations(next);
    return conv;
  }

  function patchConversation(id, patch) {
    const conv = conversationMap.value.get(id);
    if (conv) upsertConversation({ ...conv, ...patch });
  }

  function removeConversation(id) {
    conversations.value = conversations.value.filter((c) => c.id !== id);
    delete messages[id];
    delete pins[id];
    if (activeId.value === id) activeId.value = null;
  }

  // ---------- Tin nhắn ----------

  function bucketOf(convId) {
    if (!messages[convId]) messages[convId] = { items: [], hasMore: true, loaded: false, loadingOlder: false };
    return messages[convId];
  }

  function insertMessage(message) {
    const bucket = messages[message.conversation_id];
    if (!bucket || !bucket.loaded) return false;
    const index = bucket.items.findIndex((m) => m.id === message.id);
    if (index >= 0) {
      bucket.items[index] = message;
      return false;
    }
    // Giữ thứ tự tăng dần theo id
    let position = bucket.items.length;
    while (position > 0 && bucket.items[position - 1].id > message.id) position--;
    bucket.items.splice(position, 0, message);
    return true;
  }

  function removeMessage(convId, messageId) {
    const bucket = messages[convId];
    if (bucket) bucket.items = bucket.items.filter((m) => m.id !== messageId);
    if (pins[convId]) pins[convId] = pins[convId].filter((m) => m.id !== messageId);
  }

  async function openConversation(id) {
    activeId.value = id;
    if (!id) return;
    if (!conversationMap.value.has(id)) {
      try {
        upsertConversation(await chatApi.conversation(id));
      } catch {
        activeId.value = null;
        return;
      }
    }
    const bucket = bucketOf(id);
    if (!bucket.loaded) {
      const result = await chatApi.messages(id, { limit: 50 });
      bucket.items = result.data;
      bucket.hasMore = result.has_more;
      bucket.loaded = true;
    }
    await loadPins(id);
    await markRead(id);
  }

  async function loadOlder(id) {
    const bucket = bucketOf(id);
    if (!bucket.loaded || !bucket.hasMore || bucket.loadingOlder) return 0;
    bucket.loadingOlder = true;
    try {
      const result = await chatApi.messages(id, { limit: 50, before_id: bucket.items[0]?.id || 0 });
      bucket.items = [...result.data, ...bucket.items];
      bucket.hasMore = result.has_more;
      return result.data.length;
    } finally {
      bucket.loadingOlder = false;
    }
  }

  /** Tải lần lượt tin cũ hơn tới khi thấy tin cần cuộn tới (tối đa 20 trang). */
  async function ensureMessageLoaded(convId, messageId) {
    const bucket = bucketOf(convId);
    for (let i = 0; i < 20 && !bucket.items.some((m) => m.id === messageId) && bucket.hasMore; i++) {
      await loadOlder(convId);
    }
    return bucket.items.some((m) => m.id === messageId);
  }

  async function loadPins(id) {
    pins[id] = await chatApi.pins(id).catch(() => []);
  }

  function isViewing(convId) {
    return activeId.value === convId && document.visibilityState === 'visible' && document.hasFocus();
  }

  function onMessageCreated(message) {
    const convId = message.conversation_id;
    const conv = conversationMap.value.get(convId);
    const fromOther = message.sender && message.sender.id !== meId.value;

    insertMessage(message);
    if (message.sender) clearTyping(convId, message.sender.id);

    if (!conv) {
      // Hội thoại mới với mình (vd: tin đầu tiên của chat 1-1): tải về
      chatApi.conversation(convId).then((c) => {
        upsertConversation(c);
        if (fromOther && !c.muted) notify(c, message);
      }).catch(() => {});
      return;
    }

    const members = message.sender
      ? conv.members.map((m) => (m.user_id === message.sender.id ? { ...m, last_read_message_id: Math.max(m.last_read_message_id || 0, message.id) } : m))
      : conv.members;
    const viewing = isViewing(convId);
    patchConversation(convId, {
      members,
      last_message: toPreview(message),
      last_message_at: message.created_at,
      unread_count: fromOther && !viewing ? (conv.unread_count || 0) + 1 : conv.unread_count
    });

    if (viewing) markRead(convId);
    else if (fromOther && !conv.muted) notify(conv, message);
  }

  function onMessageUpdated(message) {
    const convId = message.conversation_id;
    insertMessage(message);
    if (pins[convId]) {
      const others = pins[convId].filter((m) => m.id !== message.id);
      pins[convId] = message.pinned_at ? [message, ...others].sort((a, b) => (a.pinned_at > b.pinned_at ? -1 : 1)) : others;
    }
    const conv = conversationMap.value.get(convId);
    if (conv?.last_message?.id === message.id) patchConversation(convId, { last_message: toPreview(message) });
  }

  function toPreview(m) {
    return {
      id: m.id,
      sender_id: m.sender?.id ?? null,
      sender_name: m.sender?.name ?? '',
      type: m.type,
      content: m.recalled ? 'Tin nhắn đã được thu hồi' : m.type === 'file' ? '[Tệp đính kèm]' : m.content.slice(0, 120),
      recalled: m.recalled,
      created_at: m.created_at
    };
  }

  function onReadUpdated({ conversation_id, user_id, message_id }) {
    const conv = conversationMap.value.get(conversation_id);
    if (!conv) return;
    const members = conv.members.map((m) =>
      m.user_id === user_id ? { ...m, last_read_message_id: Math.max(m.last_read_message_id || 0, message_id) } : m);
    const patch = { members };
    // Mình đọc ở tab khác: xoá số chưa đọc nếu đã đọc tới tin cuối
    if (user_id === meId.value && message_id >= (conv.last_message?.id || 0)) patch.unread_count = 0;
    patchConversation(conversation_id, patch);
  }

  function onTyping({ conversation_id, user_id, user_name }) {
    if (!typing[conversation_id]) typing[conversation_id] = {};
    typing[conversation_id][user_id] = { name: user_name, until: Date.now() + TYPING_TTL_MS };
    setTimeout(() => {
      const entry = typing[conversation_id]?.[user_id];
      if (entry && entry.until <= Date.now()) delete typing[conversation_id][user_id];
    }, TYPING_TTL_MS + 50);
  }

  function clearTyping(convId, userId) {
    if (typing[convId]) delete typing[convId][userId];
  }

  function setOnline(userId, isUp) {
    const next = new Set(online.value);
    if (isUp) next.add(userId);
    else next.delete(userId);
    online.value = next;
  }

  // ---------- Thao tác ----------

  async function markRead(convId) {
    const conv = conversationMap.value.get(convId);
    const bucket = messages[convId];
    const lastId = Math.max(bucket?.items[bucket.items.length - 1]?.id || 0, conv?.last_message?.id || 0);
    const mine = conv?.members.find((m) => m.user_id === meId.value);
    if (!conv || !lastId) return;
    if ((mine?.last_read_message_id || 0) >= lastId && !conv.unread_count) return;
    patchConversation(convId, { unread_count: 0 });
    await chatApi.markRead(convId, lastId).catch(() => {});
  }

  async function markAllRead() {
    await chatApi.markAllRead();
    conversations.value = conversations.value.map((c) => ({ ...c, unread_count: 0 }));
  }

  async function send(convId, payload) {
    const message = await chatApi.send(convId, payload);
    onMessageCreated(message);
    return message;
  }

  const edit = async (messageId, content) => onMessageUpdated(await chatApi.edit(messageId, content));
  const recall = async (messageId) => onMessageUpdated(await chatApi.recall(messageId));
  const react = async (messageId, emoji) => onMessageUpdated(await chatApi.react(messageId, emoji));
  const setMessagePinned = async (messageId, pinned) => onMessageUpdated(await chatApi.setMessagePinned(messageId, pinned));
  const forward = (messageId, conversationIds) => chatApi.forward(messageId, conversationIds);

  async function hide(convId, messageId) {
    await chatApi.hide(messageId);
    removeMessage(convId, messageId);
  }

  async function openDirect(userId) {
    return upsertConversation(await chatApi.openDirect(userId)).id;
  }

  async function createGroup(title, userIds) {
    return upsertConversation(await chatApi.createGroup(title, userIds)).id;
  }

  const rename = async (id, title) => upsertConversation(await chatApi.rename(id, title));
  const setAvatar = async (id, file) => upsertConversation(await chatApi.setAvatar(id, file));
  const removeAvatar = async (id) => upsertConversation(await chatApi.removeAvatar(id));
  const addMembers = async (id, userIds) => upsertConversation(await chatApi.addMembers(id, userIds));
  const setRole = async (id, userId, role) => upsertConversation(await chatApi.setRole(id, userId, role));
  const transferOwner = async (id, userId) => upsertConversation(await chatApi.transferOwner(id, userId));
  const setPinned = async (id, pinned) => upsertConversation(await chatApi.setPinned(id, pinned));
  const setMuted = async (id, muted) => upsertConversation(await chatApi.setMuted(id, muted));

  async function removeMember(id, userId) {
    await chatApi.removeMember(id, userId);
    const conv = conversationMap.value.get(id);
    if (conv) patchConversation(id, { members: conv.members.filter((m) => m.user_id !== userId) });
  }

  async function leave(id, newOwnerId = null) {
    await chatApi.leave(id, newOwnerId);
    removeConversation(id);
  }

  async function dissolve(id) {
    await chatApi.dissolve(id);
    removeConversation(id);
  }

  async function setAlias(userId, alias) {
    const result = await chatApi.setAlias(userId, alias);
    const next = { ...aliases.value };
    if (result.alias) next[userId] = result.alias;
    else delete next[userId];
    aliases.value = next;
  }

  function sendTyping(convId) {
    const now = Date.now();
    if (!connection || connectionState.value !== 'connected' || now - lastTypingSentAt < TYPING_THROTTLE_MS) return;
    lastTypingSentAt = now;
    connection.invoke('Typing', convId).catch(() => {});
  }

  // ---------- Thông báo ----------

  function readSoundSetting() {
    try {
      return localStorage.getItem(SOUND_KEY) !== 'off';
    } catch {
      return true;
    }
  }

  function setSoundEnabled(enabled) {
    soundEnabled.value = enabled;
    try {
      localStorage.setItem(SOUND_KEY, enabled ? 'on' : 'off');
    } catch { /* trình duyệt chặn localStorage */ }
    if (enabled && 'Notification' in window && Notification.permission === 'default') Notification.requestPermission();
  }

  function notify(conv, message) {
    if (!soundEnabled.value) return;
    playChime();
    if (document.visibilityState === 'hidden' && 'Notification' in window && Notification.permission === 'granted') {
      const body = message.recalled ? 'Tin nhắn đã được thu hồi' : message.content || '[Tệp đính kèm]';
      const title = conv.type === 'group' ? `${message.sender?.name} · ${conversationName(conv)}` : conversationName(conv);
      new Notification(title, { body: body.slice(0, 140), tag: `chat-${conv.id}` });
    }
  }

  let audioContext = null;
  function playChime() {
    try {
      audioContext ||= new (window.AudioContext || window.webkitAudioContext)();
      const oscillator = audioContext.createOscillator();
      const gain = audioContext.createGain();
      oscillator.frequency.value = 880;
      gain.gain.setValueAtTime(0.08, audioContext.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.25);
      oscillator.connect(gain).connect(audioContext.destination);
      oscillator.start();
      oscillator.stop(audioContext.currentTime + 0.25);
    } catch { /* trình duyệt chưa cho phát âm thanh */ }
  }

  return {
    meId, conversations, messages, pins, typing, online, aliases, activeId, connectionState, loadingConversations, soundEnabled,
    conversationMap, activeConversation, unreadTotal,
    partnerOf, displayName, conversationName, memberName, isOnline, typingNames,
    init, stop, fetchConversations, openConversation, loadOlder, ensureMessageLoaded, markRead, markAllRead,
    send, edit, recall, react, setMessagePinned, forward, hide,
    openDirect, createGroup, rename, setAvatar, removeAvatar, addMembers, removeMember, setRole, transferOwner, leave, dissolve,
    setPinned, setMuted, setAlias, sendTyping, setSoundEnabled
  };
});
