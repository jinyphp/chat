{{-- 채팅방 생성 페이지 --}}
@extends('jiny-site::layouts.home')

@section('content')
<div class="container-fluid py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">

            {{-- 헤더 --}}
            <div class="text-center mb-5">
                <h2 class="fw-bold text-primary">
                    <i class="fas fa-plus-circle"></i>
                    새 채팅방 만들기
                </h2>
                <p class="text-muted">새로운 채팅방을 생성하여 다른 사용자들과 소통하세요</p>
            </div>

            {{-- 오류 메시지 --}}
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <strong><i class="fas fa-exclamation-triangle"></i> 오류가 발생했습니다:</strong>
                    <ul class="list-unstyled mt-2 mb-0">
                        @foreach ($errors->all() as $error)
                            <li>• {{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- 성공 메시지 --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- 에러 메시지 --}}
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- 메인 폼 카드 --}}
            <div class="card shadow-sm border-0">
                <div class="card-body p-5">
                    <form method="POST" action="{{ route('home.chat.rooms.store') }}">
                        @csrf

                        {{-- 기본 정보 --}}
                        <div class="mb-5">
                            <h3 class="h5 fw-semibold mb-4 text-primary">
                                <i class="fas fa-info-circle"></i> 기본 정보
                            </h3>

                            {{-- 채팅방 제목 --}}
                            <div class="mb-4">
                                <label for="title" class="form-label fw-semibold">
                                    채팅방 제목 <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       name="title"
                                       id="title"
                                       value="{{ old('title') }}"
                                       required
                                       maxlength="255"
                                       placeholder="채팅방 제목을 입력하세요"
                                       class="form-control form-control-lg @error('title') is-invalid @enderror">
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">채팅방을 식별할 수 있는 제목을 입력하세요.</div>
                            </div>

                            {{-- 채팅방 슬러그 --}}
                            <div class="mb-4">
                                <label for="slug" class="form-label fw-semibold">
                                    채팅방 슬러그 (URL)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="fas fa-link text-muted"></i>
                                    </span>
                                    <input type="text"
                                           name="slug"
                                           id="slug"
                                           value="{{ old('slug') }}"
                                           maxlength="255"
                                           placeholder="영문-소문자-하이픈"
                                           pattern="^[a-z0-9-]+$"
                                           class="form-control @error('slug') is-invalid @enderror">
                                    @error('slug')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-text">
                                    <small class="text-muted">
                                        영문 소문자, 숫자, 하이픈(-)만 사용 가능합니다.
                                        비워두면 제목을 기반으로 자동 생성됩니다.
                                    </small>
                                </div>
                            </div>

                            {{-- 설명 --}}
                            <div class="mb-4">
                                <label for="description" class="form-label fw-semibold">
                                    설명
                                </label>
                                <textarea name="description"
                                          id="description"
                                          rows="3"
                                          maxlength="1000"
                                          placeholder="채팅방에 대한 설명을 입력하세요 (선택사항)"
                                          class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">채팅방의 목적이나 규칙을 설명해주세요.</div>
                            </div>
                        </div>

                        {{-- 채팅방 타입 --}}
                        <div class="mb-5">
                            <h3 class="h5 fw-semibold mb-4 text-primary">
                                <i class="fas fa-cog"></i> 채팅방 타입
                            </h3>

                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="form-check p-3 border rounded">
                                        <input id="type_public"
                                               name="type"
                                               type="radio"
                                               value="public"
                                               {{ old('type', 'public') === 'public' ? 'checked' : '' }}
                                               class="form-check-input">
                                        <label for="type_public" class="form-check-label">
                                            <div class="fw-semibold">
                                                <i class="fas fa-globe text-success"></i> 공개 채팅방
                                            </div>
                                            <div class="text-muted small">누구나 검색하고 참여할 수 있습니다.</div>
                                        </label>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-check p-3 border rounded">
                                        <input id="type_private"
                                               name="type"
                                               type="radio"
                                               value="private"
                                               {{ old('type') === 'private' ? 'checked' : '' }}
                                               class="form-check-input">
                                        <label for="type_private" class="form-check-label">
                                            <div class="fw-semibold">
                                                <i class="fas fa-lock text-warning"></i> 비공개 채팅방
                                            </div>
                                            <div class="text-muted small">초대를 통해서만 참여할 수 있습니다.</div>
                                        </label>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-check p-3 border rounded">
                                        <input id="type_group"
                                               name="type"
                                               type="radio"
                                               value="group"
                                               {{ old('type') === 'group' ? 'checked' : '' }}
                                               class="form-check-input">
                                        <label for="type_group" class="form-check-label">
                                            <div class="fw-semibold">
                                                <i class="fas fa-users text-info"></i> 그룹 채팅방
                                            </div>
                                            <div class="text-muted small">소규모 그룹을 위한 채팅방입니다.</div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 접근 설정 --}}
                        <div class="mb-5">
                            <h3 class="h5 fw-semibold mb-4 text-primary">
                                <i class="fas fa-shield-alt"></i> 접근 설정
                            </h3>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check p-3 border rounded h-100">
                                        <input id="is_public"
                                               name="is_public"
                                               type="checkbox"
                                               value="1"
                                               {{ old('is_public', '1') ? 'checked' : '' }}
                                               class="form-check-input">
                                        <label for="is_public" class="form-check-label">
                                            <div class="fw-semibold">
                                                <i class="fas fa-search text-primary"></i> 검색 가능
                                            </div>
                                            <div class="text-muted small">다른 사용자가 채팅방을 검색할 수 있습니다.</div>
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-check p-3 border rounded h-100">
                                        <input id="allow_join"
                                               name="allow_join"
                                               type="checkbox"
                                               value="1"
                                               {{ old('allow_join', '1') ? 'checked' : '' }}
                                               class="form-check-input">
                                        <label for="allow_join" class="form-check-label">
                                            <div class="fw-semibold">
                                                <i class="fas fa-door-open text-success"></i> 자유 참여 허용
                                            </div>
                                            <div class="text-muted small">승인 없이 자유롭게 참여할 수 있습니다.</div>
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-check p-3 border rounded h-100">
                                        <input id="allow_invite"
                                               name="allow_invite"
                                               type="checkbox"
                                               value="1"
                                               {{ old('allow_invite', '1') ? 'checked' : '' }}
                                               class="form-check-input">
                                        <label for="allow_invite" class="form-check-label">
                                            <div class="fw-semibold">
                                                <i class="fas fa-user-plus text-info"></i> 초대 허용
                                            </div>
                                            <div class="text-muted small">참여자가 다른 사용자를 초대할 수 있습니다.</div>
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="p-3 border rounded h-100">
                                        <label for="password" class="form-label fw-semibold mb-2">
                                            <i class="fas fa-key text-warning"></i> 비밀번호 (선택사항)
                                        </label>
                                        <input type="password"
                                               name="password"
                                               id="password"
                                               minlength="4"
                                               placeholder="채팅방 비밀번호"
                                               class="form-control @error('password') is-invalid @enderror">
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text small">참여 시 비밀번호 입력이 필요합니다.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 제한 설정 --}}
                        <div class="mb-5">
                            <h3 class="h5 fw-semibold mb-4 text-primary">
                                <i class="fas fa-users-cog"></i> 제한 설정
                            </h3>

                            <div class="row">
                                <div class="col-md-6">
                                    <label for="max_participants" class="form-label fw-semibold">
                                        최대 참여자 수
                                    </label>
                                    <input type="number"
                                           name="max_participants"
                                           id="max_participants"
                                           value="{{ old('max_participants') }}"
                                           min="0"
                                           max="1000"
                                           placeholder="0 (무제한)"
                                           class="form-control @error('max_participants') is-invalid @enderror">
                                    @error('max_participants')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">0 또는 비워두면 무제한, 2-1000명 범위에서 제한 가능</div>
                                </div>
                            </div>
                        </div>

                        {{-- 버튼 --}}
                        <div class="d-flex gap-3 justify-content-end">
                            <a href="{{ route('home.chat.rooms.index') }}"
                               class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-arrow-left"></i> 취소
                            </a>
                            <button type="submit"
                                    class="btn btn-primary btn-lg">
                                <i class="fas fa-plus"></i> 채팅방 만들기
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- 도움말 --}}
            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="fas fa-info-circle"></i>
                    채팅방 생성 후 설정을 변경하거나 삭제할 수 있습니다.
                </small>
            </div>

        </div>
    </div>
</div>
@endsection