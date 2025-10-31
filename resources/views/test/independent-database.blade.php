<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>독립 채팅방 데이터베이스 테스트</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8" x-data="chatRoomTest()">
        <h1 class="text-3xl font-bold mb-8 text-center">독립 채팅방 데이터베이스 테스트</h1>

        <!-- 알림 메시지 -->
        <div x-show="message" x-text="message"
             :class="messageType === 'error' ? 'bg-red-100 border-red-400 text-red-700' : 'bg-green-100 border-green-400 text-green-700'"
             class="border px-4 py-3 rounded mb-4" x-transition></div>

        <!-- 탭 메뉴 -->
        <div class="mb-6">
            <nav class="flex space-x-8" aria-label="Tabs">
                <button @click="activeTab = 'create'"
                        :class="activeTab === 'create' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                    채팅방 생성
                </button>
                <button @click="activeTab = 'test'"
                        :class="activeTab === 'test' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                    메시지 테스트
                </button>
                <button @click="activeTab = 'stats'"
                        :class="activeTab === 'stats' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                    통계 및 관리
                </button>
                <button @click="activeTab = 'databases'"
                        :class="activeTab === 'databases' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        class="whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                    데이터베이스 목록
                </button>
            </nav>
        </div>

        <!-- 채팅방 생성 탭 -->
        <div x-show="activeTab === 'create'" class="space-y-6">
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">새 채팅방 생성</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">채팅방 제목</label>
                        <input type="text" x-model="newRoom.title"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="테스트 채팅방">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">방장 UUID</label>
                        <input type="text" x-model="newRoom.owner_uuid"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="test-user-001">
                    </div>
                    <button @click="createRoom()" :disabled="loading"
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                        <span x-show="!loading">채팅방 생성</span>
                        <span x-show="loading">생성 중...</span>
                    </button>
                </div>
            </div>

            <!-- 기존 채팅방 목록 -->
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">기존 채팅방 목록</h2>
                <div class="space-y-2">
                    @foreach($rooms as $room)
                    <div class="flex items-center justify-between p-3 border rounded-lg">
                        <div>
                            <h3 class="font-medium">{{ $room->title }}</h3>
                            <p class="text-sm text-gray-500">코드: {{ $room->code }} | UUID: {{ $room->uuid }}</p>
                        </div>
                        <div class="flex space-x-2">
                            <button @click="selectedRoom = '{{ $room->code }}'; activeTab = 'test'"
                                    class="px-3 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                테스트
                            </button>
                            <button @click="testConnection('{{ $room->code }}')"
                                    class="px-3 py-1 text-xs bg-green-100 text-green-800 rounded">
                                연결 테스트
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- 메시지 테스트 탭 -->
        <div x-show="activeTab === 'test'" class="space-y-6">
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">메시지 테스트</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">채팅방 코드</label>
                        <input type="text" x-model="selectedRoom"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="채팅방 코드 입력">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">메시지 내용</label>
                        <input type="text" x-model="testMessage.content"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="테스트 메시지">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">발신자 UUID</label>
                        <input type="text" x-model="testMessage.sender_uuid"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="test-user-001">
                    </div>
                    <div class="flex space-x-2">
                        <button @click="sendMessage()" :disabled="loading || !selectedRoom"
                                class="flex-1 flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50">
                            메시지 전송
                        </button>
                        <button @click="loadMessages()" :disabled="loading || !selectedRoom"
                                class="flex-1 flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                            메시지 조회
                        </button>
                    </div>
                </div>

                <!-- 메시지 목록 -->
                <div x-show="messages.length > 0" class="mt-6">
                    <h3 class="text-lg font-medium mb-3">메시지 목록</h3>
                    <div class="max-h-64 overflow-y-auto space-y-2">
                        <template x-for="message in messages" :key="message.id">
                            <div class="p-3 border rounded-lg">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium" x-text="message.sender_name || message.sender_uuid"></p>
                                        <p class="text-gray-700" x-text="message.content"></p>
                                    </div>
                                    <span class="text-xs text-gray-500" x-text="message.created_at"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- 통계 및 관리 탭 -->
        <div x-show="activeTab === 'stats'" class="space-y-6">
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">채팅방 통계 및 관리</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">채팅방 코드</label>
                        <input type="text" x-model="selectedRoom"
                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="채팅방 코드 입력">
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <button @click="loadStats()" :disabled="loading || !selectedRoom"
                                class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 disabled:opacity-50">
                            통계 조회
                        </button>
                        <button @click="backupDatabase()" :disabled="loading || !selectedRoom"
                                class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 disabled:opacity-50">
                            백업
                        </button>
                        <button @click="optimizeDatabase()" :disabled="loading || !selectedRoom"
                                class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 disabled:opacity-50">
                            최적화
                        </button>
                    </div>
                </div>

                <!-- 통계 정보 -->
                <div x-show="stats" class="mt-6">
                    <h3 class="text-lg font-medium mb-3">통계 정보</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 border rounded-lg">
                            <h4 class="font-medium text-gray-700">데이터베이스 크기</h4>
                            <p class="text-2xl font-bold text-blue-600" x-text="stats.database_info?.size_human || 'N/A'"></p>
                        </div>
                        <div class="p-4 border rounded-lg">
                            <h4 class="font-medium text-gray-700">피크 시간</h4>
                            <p class="text-lg" x-text="stats.stats?.peak_hours?.peak_hour + '시' || 'N/A'"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 데이터베이스 목록 탭 -->
        <div x-show="activeTab === 'databases'" class="space-y-6">
            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">모든 독립 데이터베이스</h2>
                    <button @click="loadAllDatabases()" :disabled="loading"
                            class="py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
                        새로고침
                    </button>
                </div>

                <div x-show="databases.length > 0" class="space-y-2">
                    <template x-for="db in databases" :key="db.room_code">
                        <div class="flex items-center justify-between p-3 border rounded-lg">
                            <div>
                                <h3 class="font-medium" x-text="db.room_code"></h3>
                                <p class="text-sm text-gray-500">
                                    크기: <span x-text="db.size_human"></span> |
                                    수정일: <span x-text="db.modified_at"></span>
                                </p>
                            </div>
                            <div class="flex space-x-2">
                                <button @click="selectedRoom = db.room_code; activeTab = 'test'"
                                        class="px-3 py-1 text-xs bg-blue-100 text-blue-800 rounded">
                                    테스트
                                </button>
                                <button @click="testConnection(db.room_code)"
                                        class="px-3 py-1 text-xs bg-green-100 text-green-800 rounded">
                                    연결 테스트
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div x-show="databases.length === 0 && !loading" class="text-center py-8 text-gray-500">
                    독립 데이터베이스가 없습니다.
                </div>
            </div>
        </div>
    </div>

    <script>
        function chatRoomTest() {
            return {
                activeTab: 'create',
                loading: false,
                message: '',
                messageType: 'success',
                selectedRoom: '',

                newRoom: {
                    title: '',
                    owner_uuid: 'test-user-001'
                },

                testMessage: {
                    content: '',
                    sender_uuid: 'test-user-001'
                },

                messages: [],
                stats: null,
                databases: [],

                init() {
                    this.loadAllDatabases();
                },

                async createRoom() {
                    this.loading = true;
                    try {
                        const response = await fetch('/chat/test/create-room', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify(this.newRoom)
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.showMessage(data.message, 'success');
                            this.selectedRoom = data.room.code;
                            this.newRoom.title = '';
                            location.reload(); // 페이지 새로고침
                        } else {
                            this.showMessage(data.error, 'error');
                        }
                    } catch (error) {
                        this.showMessage('요청 실패: ' + error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async sendMessage() {
                    if (!this.selectedRoom || !this.testMessage.content) return;

                    this.loading = true;
                    try {
                        const response = await fetch(`/chat/test/${this.selectedRoom}/send-message`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify(this.testMessage)
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.showMessage('메시지 전송 성공', 'success');
                            this.testMessage.content = '';
                            this.loadMessages();
                        } else {
                            this.showMessage(data.error, 'error');
                        }
                    } catch (error) {
                        this.showMessage('요청 실패: ' + error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async loadMessages() {
                    if (!this.selectedRoom) return;

                    this.loading = true;
                    try {
                        const response = await fetch(`/chat/test/${this.selectedRoom}/messages`);
                        const data = await response.json();

                        if (data.success) {
                            this.messages = data.messages;
                            this.showMessage(`${data.total_count}개 메시지 로드됨`, 'success');
                        } else {
                            this.showMessage(data.error, 'error');
                        }
                    } catch (error) {
                        this.showMessage('요청 실패: ' + error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async loadStats() {
                    if (!this.selectedRoom) return;

                    this.loading = true;
                    try {
                        const response = await fetch(`/chat/test/${this.selectedRoom}/stats`);
                        const data = await response.json();

                        if (data.success) {
                            this.stats = data;
                            this.showMessage('통계 정보 로드됨', 'success');
                        } else {
                            this.showMessage(data.error, 'error');
                        }
                    } catch (error) {
                        this.showMessage('요청 실패: ' + error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async testConnection(roomCode) {
                    this.loading = true;
                    try {
                        const response = await fetch(`/chat/test/${roomCode}/test-connection`);
                        const data = await response.json();

                        if (data.success) {
                            this.showMessage(`연결 성공 - 메시지 수: ${data.data.message_count}`, 'success');
                        } else {
                            this.showMessage('연결 실패: ' + data.data.error, 'error');
                        }
                    } catch (error) {
                        this.showMessage('요청 실패: ' + error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async loadAllDatabases() {
                    this.loading = true;
                    try {
                        const response = await fetch('/chat/test/databases');
                        const data = await response.json();

                        if (data.success) {
                            this.databases = data.databases;
                        } else {
                            this.showMessage(data.error, 'error');
                        }
                    } catch (error) {
                        this.showMessage('요청 실패: ' + error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async backupDatabase() {
                    if (!this.selectedRoom) return;

                    this.loading = true;
                    try {
                        const response = await fetch(`/chat/test/${this.selectedRoom}/backup`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });
                        const data = await response.json();

                        if (data.success) {
                            this.showMessage('백업 완료', 'success');
                        } else {
                            this.showMessage(data.error, 'error');
                        }
                    } catch (error) {
                        this.showMessage('요청 실패: ' + error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async optimizeDatabase() {
                    if (!this.selectedRoom) return;

                    this.loading = true;
                    try {
                        const response = await fetch(`/chat/test/${this.selectedRoom}/optimize`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });
                        const data = await response.json();

                        if (data.success) {
                            this.showMessage(`최적화 완료 - ${data.size_reduced} 절약됨`, 'success');
                            this.loadStats();
                        } else {
                            this.showMessage(data.error, 'error');
                        }
                    } catch (error) {
                        this.showMessage('요청 실패: ' + error.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                showMessage(text, type = 'success') {
                    this.message = text;
                    this.messageType = type;
                    setTimeout(() => {
                        this.message = '';
                    }, 5000);
                }
            }
        }
    </script>
</body>
</html>