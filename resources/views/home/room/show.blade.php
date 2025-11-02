{{-- 채팅방 메시지 화면 --}}
@extends('jiny-site::layouts.home')

{{-- 스타일 분리 --}}
@includeIf('jiny-chat::home.room.style')

@section('content')
    <div class="container-fluid">
        {{-- 채팅방 정보 헤더 --}}
        {{-- @includeIf('jiny-chat::home.room.header') --}}
        @livewire('jiny-chat::chat-header', ['roomId' => $room->id])

        <div class="row">
            {{-- 메시지 영역 --}}
            <div class="col-lg-9">
                <div class="chat-container card">
                    {{-- 메시지 목록 영역 --}}
                    @livewire('jiny-chat::chat-messages', ['roomId' => $room->id])

                    {{-- 메시지 입력 영역 --}}
                    <div class="chat-input-area">
                        @livewire('jiny-chat::chat-write', ['roomId' => $room->id])
                    </div>
                </div>
            </div>

            {{-- 참여자 목록 --}}
            <div class="col-lg-3">
                {{-- @includeIf('jiny-chat::home.room.user') --}}
                @livewire('jiny-chat::chat-participants', ['roomId' => $room->id])
            </div>
        </div>
    </div>

@endsection
