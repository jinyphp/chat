<?php

namespace Jiny\Chat\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Jiny\Chat\ChatServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 데이터베이스 마이그레이션 실행
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // 추가 설정
        $this->setUpDatabase();
        $this->setUpAuthentication();
    }

    /**
     * 패키지 서비스 프로바이더 등록
     */
    protected function getPackageProviders($app)
    {
        return [
            ChatServiceProvider::class,
        ];
    }

    /**
     * 환경 설정
     */
    protected function defineEnvironment($app)
    {
        // 데이터베이스 설정
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // 채팅 설정
        $app['config']->set('chat.sharding.enabled', true);
        $app['config']->set('chat.sharding.shards', 16);
        $app['config']->set('chat.sharding.prefix', 'user_');

        // 브로드캐스팅 설정
        $app['config']->set('broadcasting.default', 'log');
        $app['config']->set('broadcasting.connections.log.driver', 'log');

        // 세션 설정
        $app['config']->set('session.driver', 'array');

        // 캐시 설정
        $app['config']->set('cache.default', 'array');
    }

    /**
     * 데이터베이스 설정
     */
    protected function setUpDatabase()
    {
        // 테스트용 사용자 테이블 생성 (샤딩 시뮬레이션)
        $this->app['db']->connection()->getSchemaBuilder()->create('user_001', function ($table) {
            $table->string('uuid')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // 테스트용 사용자 생성
        $this->createTestUser();
    }

    /**
     * 인증 설정
     */
    protected function setUpAuthentication()
    {
        // JWT Auth Mock 설정
        $this->app->bind('JwtAuth', function () {
            return new class {
                public function user($uuid = null) {
                    if ($uuid) {
                        return $this->getUserByUuid($uuid);
                    }
                    return $this->getCurrentUser();
                }

                public function getToken() {
                    return 'mock-jwt-token';
                }

                private function getCurrentUser() {
                    return (object) [
                        'uuid' => 'test-user-001',
                        'name' => 'Test User',
                        'email' => 'test@example.com',
                        'shard_id' => '001'
                    ];
                }

                private function getUserByUuid($uuid) {
                    return (object) [
                        'uuid' => $uuid,
                        'name' => 'Test User ' . substr($uuid, -3),
                        'email' => 'test' . substr($uuid, -3) . '@example.com',
                        'shard_id' => substr($uuid, -3)
                    ];
                }
            };
        });

        // Shard Mock 설정
        $this->app->bind('Shard', function () {
            return new class {
                public function user($uuid) {
                    return app('JwtAuth')->user($uuid);
                }

                public function connection($shardId) {
                    return app('db')->connection();
                }
            };
        });
    }

    /**
     * 테스트용 사용자 생성
     */
    protected function createTestUser()
    {
        $this->app['db']->connection()->table('user_001')->insert([
            'uuid' => 'test-user-001',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 인증된 사용자로 요청 실행
     */
    protected function actingAsJwtUser($user = null)
    {
        if (!$user) {
            $user = app('JwtAuth')->user();
        }

        return $this->withHeaders([
            'Authorization' => 'Bearer ' . app('JwtAuth')->getToken(),
        ]);
    }
}