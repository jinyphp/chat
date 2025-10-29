/**
 * Jiny Chat - Real-time WebSocket Integration
 *
 * 채팅 시스템의 실시간 기능을 위한 JavaScript 클라이언트
 * Laravel Echo와 Pusher/Socket.IO를 사용한 WebSocket 연결 관리
 *
 * 주요 기능:
 * - 실시간 메시지 수신/전송
 * - 타이핑 상태 표시
 * - 온라인 사용자 목록
 * - 메시지 읽음 상태
 * - 자동 재연결
 */

class JinyChatRealtime {
    constructor(options = {}) {
        this.options = {
            broadcaster: 'pusher', // pusher, socket.io, null
            key: null,
            cluster: 'mt1',
            encrypted: true,
            authEndpoint: '/broadcasting/auth',
            csrfToken: null,
            debug: false,
            ...options
        };

        this.echo = null;
        this.currentUser = null;
        this.currentRoom = null;
        this.channels = new Map();
        this.typingTimer = null;
        this.typingTimeout = 3000; // 3초

        this.callbacks = {
            onMessage: [],
            onUserJoined: [],
            onUserLeft: [],
            onUserTyping: [],
            onMessageUpdated: [],
            onConnectionStateChanged: [],
            onError: []
        };

        this.init();
    }

    /**
     * 초기화
     */
    init() {
        this.log('Initializing Jiny Chat Realtime...');

        // Laravel Echo 설정
        if (window.Echo) {
            this.echo = window.Echo;
        } else {
            this.setupEcho();
        }

        // 전역 이벤트 리스너 설정
        this.setupGlobalListeners();
    }

    /**
     * Laravel Echo 설정
     */
    setupEcho() {
        if (this.options.broadcaster === 'null' || !this.options.key) {
            this.log('Broadcasting disabled or no key provided');
            return;
        }

        try {
            const echoConfig = {
                broadcaster: this.options.broadcaster,
                key: this.options.key,
                cluster: this.options.cluster,
                encrypted: this.options.encrypted,
                authEndpoint: this.options.authEndpoint,
                auth: {
                    headers: {
                        'X-CSRF-TOKEN': this.options.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content,
                        'Authorization': `Bearer ${this.getJwtToken()}`
                    }
                }
            };

            this.echo = new Echo(echoConfig);
            this.log('Echo initialized', echoConfig);

            // 연결 상태 이벤트
            this.echo.connector.pusher?.connection.bind('connected', () => {
                this.triggerCallback('onConnectionStateChanged', 'connected');
                this.log('WebSocket connected');
            });

            this.echo.connector.pusher?.connection.bind('disconnected', () => {
                this.triggerCallback('onConnectionStateChanged', 'disconnected');
                this.log('WebSocket disconnected');
            });

        } catch (error) {
            this.log('Failed to initialize Echo', error);
            this.triggerCallback('onError', error);
        }
    }

    /**
     * 전역 이벤트 리스너 설정
     */
    setupGlobalListeners() {
        // 페이지 언로드 시 연결 정리
        window.addEventListener('beforeunload', () => {
            this.disconnect();
        });

        // 페이지 포커스 이벤트
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.handlePageBlur();
            } else {
                this.handlePageFocus();
            }
        });
    }

    /**
     * 사용자 설정
     */
    setUser(user) {
        this.currentUser = user;
        this.log('User set', user);

        // 사용자 개인 채널 구독
        if (user.uuid) {
            this.subscribeToUserChannel(user.uuid);
        }
    }

    /**
     * 채팅방 입장
     */
    joinRoom(roomId) {
        this.log(`Joining room: ${roomId}`);

        // 이전 방 나가기
        if (this.currentRoom && this.currentRoom !== roomId) {
            this.leaveRoom();
        }

        this.currentRoom = roomId;

        // 채팅방 채널들 구독
        this.subscribeToRoomChannel(roomId);
        this.subscribeToPresenceChannel(roomId);
    }

    /**
     * 채팅방 나가기
     */
    leaveRoom() {
        if (!this.currentRoom) return;

        this.log(`Leaving room: ${this.currentRoom}`);

        // 타이핑 상태 정리
        this.stopTyping();

        // 채널 구독 해제
        this.unsubscribeFromRoom(this.currentRoom);

        this.currentRoom = null;
    }

    /**
     * 채팅방 채널 구독
     */
    subscribeToRoomChannel(roomId) {
        if (!this.echo) return;

        const channelName = `chat-room.${roomId}`;

        try {
            const channel = this.echo.private(channelName);
            this.channels.set(channelName, channel);

            // 새 메시지 수신
            channel.listen('MessageSent', (data) => {
                this.handleNewMessage(data);
            });

            // 메시지 업데이트 (편집, 반응 등)
            channel.listen('MessageUpdated', (data) => {
                this.handleMessageUpdated(data);
            });

            // 사용자 입장
            channel.listen('UserJoined', (data) => {
                this.handleUserJoined(data);
            });

            // 사용자 퇴장
            channel.listen('UserLeft', (data) => {
                this.handleUserLeft(data);
            });

            // 타이핑 상태
            channel.listen('UserTyping', (data) => {
                this.handleUserTyping(data);
            });

            this.log(`Subscribed to room channel: ${channelName}`);

        } catch (error) {
            this.log(`Failed to subscribe to room channel: ${channelName}`, error);
            this.triggerCallback('onError', error);
        }
    }

    /**
     * 프레즌스 채널 구독 (온라인 사용자 목록)
     */
    subscribeToPresenceChannel(roomId) {
        if (!this.echo) return;

        const channelName = `chat-presence.${roomId}`;

        try {
            const channel = this.echo.join(channelName);
            this.channels.set(channelName, channel);

            // 온라인 사용자 목록 업데이트
            channel.here((users) => {
                this.log('Online users', users);
                this.triggerCallback('onPresenceUpdate', { type: 'here', users });
            });

            // 사용자 입장
            channel.joining((user) => {
                this.log('User joining', user);
                this.triggerCallback('onPresenceUpdate', { type: 'joining', user });
            });

            // 사용자 퇴장
            channel.leaving((user) => {
                this.log('User leaving', user);
                this.triggerCallback('onPresenceUpdate', { type: 'leaving', user });
            });

            this.log(`Subscribed to presence channel: ${channelName}`);

        } catch (error) {
            this.log(`Failed to subscribe to presence channel: ${channelName}`, error);
        }
    }

    /**
     * 사용자 개인 채널 구독
     */
    subscribeToUserChannel(userUuid) {
        if (!this.echo) return;

        const channelName = `chat-user.${userUuid}`;

        try {
            const channel = this.echo.private(channelName);
            this.channels.set(channelName, channel);

            // 개인 알림 수신
            channel.listen('UserNotification', (data) => {
                this.handleUserNotification(data);
            });

            // 초대 알림
            channel.listen('RoomInvite', (data) => {
                this.handleRoomInvite(data);
            });

            this.log(`Subscribed to user channel: ${channelName}`);

        } catch (error) {
            this.log(`Failed to subscribe to user channel: ${channelName}`, error);
        }
    }

    /**
     * 방 채널 구독 해제
     */
    unsubscribeFromRoom(roomId) {
        const channels = [
            `chat-room.${roomId}`,
            `chat-presence.${roomId}`
        ];

        channels.forEach(channelName => {
            const channel = this.channels.get(channelName);
            if (channel) {
                try {
                    this.echo.leave(channelName);
                    this.channels.delete(channelName);
                    this.log(`Unsubscribed from channel: ${channelName}`);
                } catch (error) {
                    this.log(`Failed to unsubscribe from channel: ${channelName}`, error);
                }
            }
        });
    }

    /**
     * 타이핑 시작
     */
    startTyping() {
        if (!this.currentRoom || !this.currentUser) return;

        // 타이핑 이벤트 전송
        this.sendTypingEvent('start');

        // 자동 종료 타이머 설정
        if (this.typingTimer) {
            clearTimeout(this.typingTimer);
        }

        this.typingTimer = setTimeout(() => {
            this.stopTyping();
        }, this.typingTimeout);
    }

    /**
     * 타이핑 종료
     */
    stopTyping() {
        if (!this.currentRoom || !this.currentUser) return;

        if (this.typingTimer) {
            clearTimeout(this.typingTimer);
            this.typingTimer = null;
        }

        // 타이핑 종료 이벤트 전송
        this.sendTypingEvent('stop');
    }

    /**
     * 타이핑 이벤트 전송
     */
    sendTypingEvent(action) {
        // Livewire 이벤트로 전송
        if (window.Livewire) {
            if (action === 'start') {
                window.Livewire.emit('userStartTyping', {
                    room_id: this.currentRoom,
                    user_uuid: this.currentUser.uuid,
                    user_name: this.currentUser.name
                });
            } else {
                window.Livewire.emit('userStopTyping', {
                    room_id: this.currentRoom,
                    user_uuid: this.currentUser.uuid
                });
            }
        }
    }

    /**
     * 이벤트 핸들러들
     */
    handleNewMessage(data) {
        this.log('New message received', data);
        this.triggerCallback('onMessage', data);

        // 브라우저 알림 (권한이 있는 경우)
        this.showNotification(data);
    }

    handleMessageUpdated(data) {
        this.log('Message updated', data);
        this.triggerCallback('onMessageUpdated', data);
    }

    handleUserJoined(data) {
        this.log('User joined', data);
        this.triggerCallback('onUserJoined', data);
    }

    handleUserLeft(data) {
        this.log('User left', data);
        this.triggerCallback('onUserLeft', data);
    }

    handleUserTyping(data) {
        this.log('User typing', data);
        this.triggerCallback('onUserTyping', data);
    }

    handleUserNotification(data) {
        this.log('User notification', data);
        this.showNotification(data);
    }

    handleRoomInvite(data) {
        this.log('Room invite', data);
        this.showNotification(data, 'invite');
    }

    /**
     * 페이지 포커스 이벤트
     */
    handlePageFocus() {
        this.log('Page focused');
        // 읽음 처리 등
        if (window.Livewire && this.currentRoom) {
            window.Livewire.emit('markAsRead');
        }
    }

    handlePageBlur() {
        this.log('Page blurred');
        // 타이핑 상태 정리
        this.stopTyping();
    }

    /**
     * 브라우저 알림 표시
     */
    showNotification(data, type = 'message') {
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        // 현재 페이지가 포커스된 경우 알림 표시 안함
        if (!document.hidden) {
            return;
        }

        let title, body, icon;

        switch (type) {
            case 'message':
                title = data.message?.sender_name || '새 메시지';
                body = data.message?.content || '';
                icon = '/images/chat-icon.png';
                break;
            case 'invite':
                title = '채팅방 초대';
                body = data.message || '';
                icon = '/images/invite-icon.png';
                break;
            default:
                title = '채팅 알림';
                body = data.message || '';
                icon = '/images/notification-icon.png';
        }

        const notification = new Notification(title, {
            body: body,
            icon: icon,
            tag: `chat-${type}-${Date.now()}`,
            requireInteraction: false
        });

        // 3초 후 자동 닫기
        setTimeout(() => {
            notification.close();
        }, 3000);

        // 클릭 시 채팅방으로 이동
        notification.onclick = () => {
            window.focus();
            notification.close();

            if (type === 'message' && data.room_id && data.room_id !== this.currentRoom) {
                window.location.href = `/chat/room/${data.room_id}`;
            }
        };
    }

    /**
     * 콜백 등록
     */
    on(event, callback) {
        if (this.callbacks[event]) {
            this.callbacks[event].push(callback);
        }
    }

    /**
     * 콜백 제거
     */
    off(event, callback) {
        if (this.callbacks[event]) {
            const index = this.callbacks[event].indexOf(callback);
            if (index > -1) {
                this.callbacks[event].splice(index, 1);
            }
        }
    }

    /**
     * 콜백 실행
     */
    triggerCallback(event, data) {
        if (this.callbacks[event]) {
            this.callbacks[event].forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    this.log(`Callback error for ${event}`, error);
                }
            });
        }
    }

    /**
     * JWT 토큰 가져오기
     */
    getJwtToken() {
        // localStorage에서 토큰 가져오기
        return localStorage.getItem('jwt_token') ||
               sessionStorage.getItem('jwt_token') ||
               document.querySelector('meta[name="jwt-token"]')?.content;
    }

    /**
     * 연결 해제
     */
    disconnect() {
        this.log('Disconnecting...');

        // 모든 채널 구독 해제
        this.channels.forEach((channel, channelName) => {
            try {
                this.echo.leave(channelName);
            } catch (error) {
                this.log(`Error leaving channel ${channelName}`, error);
            }
        });

        this.channels.clear();
        this.currentRoom = null;

        // 타이핑 타이머 정리
        if (this.typingTimer) {
            clearTimeout(this.typingTimer);
            this.typingTimer = null;
        }
    }

    /**
     * 디버그 로그
     */
    log(message, data = null) {
        if (this.options.debug) {
            console.log(`[Jiny Chat] ${message}`, data);
        }
    }

    /**
     * 연결 상태 확인
     */
    isConnected() {
        return this.echo?.connector?.pusher?.connection?.state === 'connected';
    }

    /**
     * 재연결
     */
    reconnect() {
        this.log('Reconnecting...');

        if (this.echo?.connector?.pusher) {
            this.echo.connector.pusher.connect();
        }
    }
}

// 전역 변수로 내보내기
window.JinyChatRealtime = JinyChatRealtime;