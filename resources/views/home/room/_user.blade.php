<div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-users me-2"></i>
                            참여자 ({{ $participants->count() }})
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        @foreach ($participants as $participant)
                            <div class="participant-item">
                                <div
                                    class="participant-avatar {{ $participant->role === 'owner' ? 'bg-danger' : ($participant->role === 'admin' ? 'bg-warning' : 'bg-secondary') }}">
                                    {{ getAvatarText($participant->name ?? 'Unknown User') }}
                                </div>
                                <div class="participant-info">
                                    <p class="participant-name">{{ $participant->name ?? 'Unknown User' }}</p>
                                    <p class="participant-role role-{{ $participant->role }}">
                                        @switch($participant->role)
                                            @case('owner')
                                                <i class="fas fa-crown me-1"></i>방장
                                            @break

                                            @case('admin')
                                                <i class="fas fa-shield-alt me-1"></i>관리자
                                            @break

                                            @default
                                                <i class="fas fa-user me-1"></i>멤버
                                        @endswitch
                                    </p>
                                </div>
                                @if ($participant->last_seen_at)
                                    <small class="text-muted">
                                        {{ \Carbon\Carbon::parse($participant->last_seen_at)->diffForHumans() }}
                                    </small>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
