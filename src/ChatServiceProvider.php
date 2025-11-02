<?php

namespace Jiny\Chat;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use Livewire\Livewire;

class ChatServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/home.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Load test routes (개발 환경에서만)
        if (app()->environment(['local', 'testing'])) {
            $this->loadRoutesFrom(__DIR__.'/../routes/test.php');
        }

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'jiny-chat');

        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/chat.php' => config_path('chat.php'),
            __DIR__.'/../config/broadcasting.php' => config_path('chat-broadcasting.php'),
        ], 'config');

        // Publish views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/jiny-chat'),
        ], 'views');

        // Publish assets
        $this->publishes([
            __DIR__.'/../resources/js' => public_path('vendor/jiny-chat/js'),
        ], 'assets');

        // Register Livewire components
        $this->registerLivewireComponents();

        // Register broadcasting channels
        $this->registerBroadcastingChannels();

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Jiny\Chat\Console\Commands\CleanupOrphanedChatFiles::class,
            ]);
        }

        // Register global helper functions
        $this->registerGlobalHelpers();
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(__DIR__.'/../config/chat.php', 'chat');
        $this->mergeConfigFrom(__DIR__.'/../config/broadcasting.php', 'chat.broadcasting');

        // Register services
        $this->app->singleton('chat', function ($app) {
            return new \Jiny\Chat\Services\ChatService();
        });

        // Register broadcasting auth
        $this->app->singleton('chat.channel.auth', function ($app) {
            return new \Jiny\Chat\Broadcasting\ChatChannelAuth();
        });
    }

    /**
     * Register Livewire components
     */
    protected function registerLivewireComponents(): void
    {
        Livewire::component('jiny-chat::chat-participants', \Jiny\Chat\Http\Livewire\ChatParticipants::class);
        Livewire::component('jiny-chat::chat-header', \Jiny\Chat\Http\Livewire\ChatHeader::class);
        Livewire::component('jiny-chat::chat-messages', \Jiny\Chat\Http\Livewire\ChatMessages::class);
        Livewire::component('jiny-chat::chat-write', \Jiny\Chat\Http\Livewire\ChatWrite::class);
        Livewire::component('jiny-chat::chat-message', \Jiny\Chat\Http\Livewire\ChatMessage::class);
        Livewire::component('jiny-chat::chat-list', \Jiny\Chat\Http\Livewire\ChatList::class);
    }

    /**
     * Register broadcasting channels for chat
     */
    protected function registerBroadcastingChannels(): void
    {
        // 브로드캐스팅이 활성화된 경우에만 채널 등록
        if (config('chat.broadcasting.default') === 'null') {
            return;
        }

        $auth = app('chat.channel.auth');

        // 채팅방 프라이빗 채널
        Broadcast::channel('chat-room.{roomId}', function ($user, $roomId) use ($auth) {
            return $auth->chatRoom(request(), $roomId);
        });

        // 사용자 개인 프라이빗 채널
        Broadcast::channel('chat-user.{userUuid}', function ($user, $userUuid) use ($auth) {
            return $auth->chatUser(request(), $userUuid);
        });

        // 채팅방 프레즌스 채널 (온라인 사용자 목록)
        Broadcast::channel('chat-presence.{roomId}', function ($user, $roomId) use ($auth) {
            return $auth->chatPresence(request(), $roomId);
        });

        // 타이핑 상태 채널
        Broadcast::channel('chat-typing.{roomId}', function ($user, $roomId) use ($auth) {
            return $auth->chatTyping(request(), $roomId);
        });
    }

    /**
     * Register global helper functions for chat
     */
    protected function registerGlobalHelpers(): void
    {
        // 헬퍼 파일 로드
        $helpersFile = __DIR__ . '/helpers.php';
        if (file_exists($helpersFile)) {
            require_once $helpersFile;
        }
    }
}
