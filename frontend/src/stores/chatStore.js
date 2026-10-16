import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import * as signalR from '@microsoft/signalr';
import { chatApi } from '../api/chat.api';
import { storage } from '../utils/storage';
import { parseJwt } from '../utils/jwt';

export const useChatStore = defineStore('chat', () => {
  // State
  const hubConnection = ref(null);
  const connectionState = ref('DISCONNECTED'); // DISCONNECTED | CONNECTING | CONNECTED | RECONNECTING
  const conversations = ref([]);
  const activeConversationId = ref(null);
  const messages = ref({}); // { [conversationId]: Array<ChatMessageDto> }
  const onlineUserIds = ref(new Set());
  const typingUsers = ref({}); // { [conversationId]: { [userId]: userName } }
  const typingTimeouts = {};

  const loading = ref({
    conversations: false,
    messages: false,
    sending: false
  });

  const pagination = ref({}); // { [conversationId]: { currentPage, totalPages, totalItems } }

  // Getters
  const activeConversation = computed(() => {
    return conversations.value.find(c => c.id === activeConversationId.value) || null;
  });

  const activeMessages = computed(() => {
    if (!activeConversationId.value) return [];
    return messages.value[activeConversationId.value] || [];
  });

  const totalUnreadCount = computed(() => {
    return conversations.value.reduce((acc, c) => acc + (c.unread_count || 0), 0);
  });

  const isUserOnline = (userId) => {
    return onlineUserIds.value.has(Number(userId));
  };

  const getTypingText = computed(() => {
    if (!activeConversationId.value) return '';
    const typers = typingUsers.value[activeConversationId.value];
    if (!typers) return '';
    const names = Object.values(typers);
    if (names.length === 0) return '';
    if (names.length === 1) return `${names[0]} đang soạn tin...`;
    return `${names.slice(0, 2).join(', ')} và những người khác đang soạn tin...`;
  });

  // Actions
  async function initHub() {
    const token = storage.getToken();
    if (!token) return;

    if (hubConnection.value && hubConnection.value.state === signalR.HubConnectionState.Connected) {
      return;
    }

    try {
      connectionState.value = 'CONNECTING';

      const connection = new signalR.HubConnectionBuilder()
        .withUrl('/api/v1/chat/hub', {
          accessTokenFactory: () => storage.getToken(),
          skipNegotiation: false,
          transport: signalR.HttpTransportType.WebSockets | signalR.HttpTransportType.LongPolling
        })
        .withAutomaticReconnect([0, 2000, 5000, 10000, 30000])
        .configureLogging(signalR.LogLevel.Warning)
        .build();

      // Sự kiện: Nhận tin nhắn mới
      connection.on('ReceiveMessage', (message) => {
        handleIncomingMessage(message);
      });

      // Sự kiện: Người dùng đang soạn tin
      connection.on('UserTyping', (convId, userId, userName, isTyping) => {
        handleUserTyping(convId, userId, userName, isTyping);
      });

      // Sự kiện: Tin nhắn đã được đọc
      connection.on('MessageRead', (messageId, userId) => {
        handleMessageRead(messageId, userId);
      });

      // Sự kiện: Trạng thái Online / Offline
      connection.on('UserOnlineStatus', (userId, isOnline) => {
        if (isOnline) {
          onlineUserIds.value.add(Number(userId));
        } else {
          onlineUserIds.value.delete(Number(userId));
        }
      });

      connection.onreconnecting(() => {
        connectionState.value = 'RECONNECTING';
      });

      connection.onreconnected(() => {
        connectionState.value = 'CONNECTED';
        // Tải lại danh sách sau khi reconnect
        fetchConversations();
      });

      connection.onclose(() => {
        connectionState.value = 'DISCONNECTED';
      });

      await connection.start();
      hubConnection.value = connection;
      connectionState.value = 'CONNECTED';

      // Lấy danh sách user online ban đầu
      fetchOnlineUsers();
    } catch (err) {
      console.error('[SignalR] Lỗi kết nối Hub:', err);
      connectionState.value = 'DISCONNECTED';
    }
  }

  function handleIncomingMessage(message) {
    const convId = message.conversation_id;
    if (!messages.value[convId]) {
      messages.value[convId] = [];
    }

    // Tránh trùng lặp tin nhắn nếu đã có trong store
    const exists = messages.value[convId].some(m => m.id === message.id);
    if (!exists) {
      messages.value[convId].push(message);
    }

    // Cập nhật cuộc hội thoại trong danh sách
    const convIndex = conversations.value.findIndex(c => c.id === convId);
    if (convIndex !== -1) {
      const conv = conversations.value[convIndex];
      conv.last_message = message;

      if (convId !== activeConversationId.value) {
        conv.unread_count = (conv.unread_count || 0) + 1;
      } else {
        // Đang mở hội thoại này -> tự động đánh dấu đã đọc
        markAsRead(message.id);
      }

      // Đưa hội thoại vừa có tin nhắn lên đầu danh sách
      conversations.value.splice(convIndex, 1);
      conversations.value.unshift(conv);
    } else {
      // Nếu là hội thoại mới tạo chưa có trong danh sách, fetch lại
      fetchConversations();
    }
  }

  function handleUserTyping(convId, userId, userName, isTyping) {
    if (!typingUsers.value[convId]) {
      typingUsers.value[convId] = {};
    }

    const key = `${convId}_${userId}`;
    if (typingTimeouts[key]) {
      clearTimeout(typingTimeouts[key]);
      delete typingTimeouts[key];
    }

    if (isTyping) {
      typingUsers.value[convId][userId] = userName;
      // Tự động clear sau 3s nếu không nhận được tín hiệu tiếp
      typingTimeouts[key] = setTimeout(() => {
        if (typingUsers.value[convId]) {
          delete typingUsers.value[convId][userId];
        }
      }, 3000);
    } else {
      delete typingUsers.value[convId][userId];
    }
  }

  function handleMessageRead(messageId, userId) {
    for (const convId in messages.value) {
      const msg = messages.value[convId].find(m => m.id === messageId);
      if (msg) {
        if (!msg.read_by) msg.read_by = [];
        if (!msg.read_by.includes(Number(userId))) {
          msg.read_by.push(Number(userId));
        }
        break;
      }
    }
  }

  async function fetchConversations() {
    loading.value.conversations = true;
    try {
      const list = await chatApi.getConversations();
      conversations.value = list;

      // Nếu chưa chọn cuộc hội thoại nào, mặc định chọn kênh #chung
      if (!activeConversationId.value && list.length > 0) {
        const generalChan = list.find(c => c.code === 'dept:general') || list[0];
        selectConversation(generalChan.id);
      }
    } catch (err) {
      console.error('Lỗi lấy danh sách hội thoại:', err);
    } finally {
      loading.value.conversations = false;
    }
  }

  async function fetchOnlineUsers() {
    try {
      const onlineIds = await chatApi.getOnlineUsers();
      onlineUserIds.value = new Set(onlineIds.map(Number));
    } catch (err) {
      console.error('Lỗi lấy danh sách online:', err);
    }
  }

  async function selectConversation(convId) {
    if (!convId) return;
    activeConversationId.value = convId;

    // Reset unread count trên UI
    const conv = conversations.value.find(c => c.id === convId);
    if (conv) {
      conv.unread_count = 0;
    }

    // Tham gia group SignalR nếu chưa vào
    if (hubConnection.value && hubConnection.value.state === signalR.HubConnectionState.Connected) {
      try {
        await hubConnection.value.invoke('JoinConversation', convId);
      } catch (e) {
        console.warn('Không thể join conversation group:', e);
      }
    }

    // Lấy tin nhắn nếu chưa có trong cache
    if (!messages.value[convId] || messages.value[convId].length === 0) {
      await fetchMessages(convId, 1);
    }

    // Đánh dấu đã đọc các tin nhắn chưa đọc
    markAllActiveAsRead();
  }

  async function fetchMessages(convId, page = 1) {
    loading.value.messages = true;
    try {
      const res = await chatApi.getMessages(convId, { page, pageSize: 50 });
      messages.value[convId] = res.messages;
      pagination.value[convId] = res.meta?.pagination || { currentPage: 1, totalPages: 1 };
    } catch (err) {
      console.error('Lỗi lấy lịch sử tin nhắn:', err);
    } finally {
      loading.value.messages = false;
    }
  }

  async function sendMessage(content, attachmentUrl = null) {
    if (!activeConversationId.value || !content.trim()) return;

    loading.value.sending = true;
    const conv = activeConversation.value;
    let sentViaWs = false;

    try {
      // 1. Thử gửi qua WebSocket SignalR Hub nếu đang kết nối
      if (hubConnection.value && hubConnection.value.state === signalR.HubConnectionState.Connected) {
        try {
          if (conv.type === 'Channel') {
            await hubConnection.value.invoke('SendChannelMessage', {
              conversation_id: conv.id,
              department_code: conv.department_code,
              content: content.trim(),
              attachment_url: attachmentUrl
            });
          } else {
            const otherMember = conv.members?.find(m => !isCurrentUser(m.user_id));
            await hubConnection.value.invoke('SendDirectMessage', {
              conversation_id: conv.id,
              recipient_id: otherMember ? Number(otherMember.user_id) : 0,
              content: content.trim(),
              attachment_url: attachmentUrl
            });
          }
          sentViaWs = true;
        } catch (wsErr) {
          console.warn('[SignalR] Lỗi gửi qua WebSocket, chuyển sang gửi qua REST API:', wsErr);
        }
      }

      // 2. Fallback gửi qua REST API nếu chưa gửi được qua WebSocket
      if (!sentViaWs) {
        const result = await chatApi.sendMessage(conv.id, {
          content: content.trim(),
          attachment_url: attachmentUrl
        });
        if (result) {
          handleIncomingMessage(result);
        }
      }
    } catch (err) {
      console.error('Lỗi gửi tin nhắn:', err);
      throw err;
    } finally {
      loading.value.sending = false;
    }
  }

  async function startDirectChat(recipientId, recipientName) {
    try {
      const conv = await chatApi.getOrCreateDirectConversation(recipientId, recipientName);
      
      const existsIndex = conversations.value.findIndex(c => c.id === conv.id);
      if (existsIndex === -1) {
        conversations.value.unshift(conv);
      }
      
      await selectConversation(conv.id);
      return conv;
    } catch (err) {
      console.error('Lỗi bắt đầu hội thoại 1-1:', err);
      throw err;
    }
  }

  function sendTyping(isTyping) {
    if (!activeConversationId.value || !hubConnection.value || hubConnection.value.state !== signalR.HubConnectionState.Connected) {
      return;
    }
    hubConnection.value.invoke('SendTypingIndicator', activeConversationId.value, isTyping)
      .catch(() => {});
  }

  async function markAsRead(messageId) {
    if (!messageId) return;
    try {
      if (hubConnection.value && hubConnection.value.state === signalR.HubConnectionState.Connected) {
        await hubConnection.value.invoke('MarkMessageAsRead', messageId);
      } else {
        await chatApi.markAsRead(messageId);
      }
    } catch (err) {
      // Bỏ qua lỗi đánh dấu đọc ngầm
    }
  }

  function markAllActiveAsRead() {
    const activeMsgs = activeMessages.value;
    if (activeMsgs && activeMsgs.length > 0) {
      const lastMsg = activeMsgs[activeMsgs.length - 1];
      markAsRead(lastMsg.id);
    }
  }

  function disconnectHub() {
    if (hubConnection.value) {
      hubConnection.value.stop();
      hubConnection.value = null;
      connectionState.value = 'DISCONNECTED';
    }
  }

  function isCurrentUser(userId) {
    const token = storage.getToken();
    if (!token) return false;
    const payload = parseJwt(token);
    if (!payload) return false;
    return String(payload.sub || payload.id) === String(userId);
  }

  return {
    hubConnection,
    connectionState,
    conversations,
    activeConversationId,
    activeConversation,
    activeMessages,
    messages,
    onlineUserIds,
    loading,
    totalUnreadCount,
    getTypingText,
    isUserOnline,
    initHub,
    disconnectHub,
    fetchConversations,
    fetchOnlineUsers,
    selectConversation,
    fetchMessages,
    sendMessage,
    startDirectChat,
    sendTyping,
    markAsRead
  };
});
