<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_files', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->unsignedBigInteger('message_id');
            $table->string('room_uuid');
            $table->string('uploader_uuid');
            $table->string('original_name');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type'); // image, document, video, audio, etc.
            $table->string('mime_type');
            $table->bigInteger('file_size');
            $table->string('storage_path'); // /0x/0/0x format
            $table->json('metadata')->nullable(); // additional file info
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('chat_messages')->onDelete('cascade');
            $table->index(['room_uuid', 'file_type']);
            $table->index(['uploader_uuid']);
            $table->index(['is_deleted', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_files');
    }
};