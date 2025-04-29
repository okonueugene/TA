<div class="nk-content ">
    <div class="container-fluid">
        <div class="nk-content-inner">
            <div class="nk-content-body">
                <div class="card card-bordered">
                    <div class="card-inner card-inner-lg">
                        <div class="nk-block-head nk-block-head-lg">
                            <div class="nk-block-between">
                                <div class="nk-block-head-content">
                                    <h4 class="nk-block-title">Personal Information</h4>
                                    <div class="nk-block-des">
                                        <p>Basic info, about you.</p>
                                    </div>
                                </div>
                                <div class="nk-block-head-content align-self-start d-lg-none"><a href="#"
                                        class="toggle btn btn-icon btn-trigger mt-n1" data-target="userAside"><em
                                            class="icon ni ni-menu-alt-r"></em></a></div>
                            </div>
                        </div>
                        <div class="nk-block">
                            <div class="nk-data data-list">
                                <div class="data-head">
                                    <h6 class="overline-title">Basics</h6>
                                </div>
                                <div class="data-item" data-bs-toggle="modal" data-bs-target="#profile-edit">
                                    <div class="data-col data-col-end"><span class="data-more"><em
                                                class="icon ni ni-forward-ios"></em></span></div>
                                </div>
                                <div class="data-item" data-bs-toggle="modal" data-bs-target="#profile-edit">
                                    <div class="data-col"><span class="data-label">Full Name</span><span
                                            class="data-value">{{ $user->name }}
                                        </span></div>
                                    <div class="data-col data-col-end"><span class="data-more"><em
                                                class="icon ni ni-forward-ios"></em></span></div>
                                </div>
                                <div class="data-item" data-bs-toggle="modal" data-bs-target="#profile-edit">
                                    <div class="data-col"><span class="data-label">Department</span><span
                                            class="data-value">{{ $user->dept ? $user->dept->name : 'N/A' }}
                                    </div>
                                    <div class="data-col data-col-end"><span class="data-more"><em
                                                class="icon ni ni-forward-ios"></em></span></div>
                                </div>
                                <div class="data-item">
                                    <div class="data-col"><span class="data-label">Email</span><span
                                            class="data-value">{{ $user->email }}</span></div>
                                    <div class="data-col data-col-end"><span class="data-more disable"><em
                                                class="icon ni ni-forward-ios"></em></span></div>
                                </div>
                                <div class="data-item" data-bs-toggle="modal" data-bs-target="#profile-edit">
                                    <div class="data-col"><span class="data-label">Gender</span><span
                                            class="data-value text-soft">{{ ucfirst($user->gender ?? 'N/A') }}
                                        </span></div>
                                    <div class="data-col data-col-end"><span class="data-more"><em
                                                class="icon ni ni-forward-ios"></em></span></div>
                                </div>
                                <div class="data-item" data-bs-toggle="modal" data-bs-target="#profile-edit">
                                    <div class="data-col"><span class="data-label">Position</span><span
                                            class="data-value">{{ ucfirst($user->user_type) }}
                                        </span></div>
                                    <div class="data-col data-col-end"><span class="data-more"><em
                                                class="icon ni ni-forward-ios"></em></span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
