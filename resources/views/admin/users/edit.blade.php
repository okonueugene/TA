@extends('layouts.admin')

@section('content')
    <div class="nk-content ">
        <div class="container-fluid">
            <div class="nk-content-inner">
                <div class="nk-content-body">
                    <div class="nk-block-head nk-block-head-sm">
                        <div class="nk-block-between">
                            <div class="nk-block-head-content">
                                <h3 class="nk-block-title page-title">Edit User</h3>
                            </div>
                            <div class="nk-block-head-content">
                                <div class="toggle-wrap nk-block-tools-toggle"><a href="#"
                                        class="btn btn-icon btn-trigger toggle-expand me-n1" data-target="more-options"><em
                                            class="icon ni ni-more-v"></em></a>
                                    <div class="toggle-expand-content" data-content="more-options">
                                        <ul class="nk-block-tools g-3">
                                            <li><a href="{{ route('admin.users.index') }}"
                                                    class="btn btn-white btn-outline-light"><em
                                                        class="icon ni ni-arrow-left"></em><span>Back</span></a>

                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="nk-block nk-block-lg">
                        <div class="card card-preview">
                            <div class="card-inner">
                                <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <div class="row gy-4">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="name">Name</label>
                                                <div class="form-control-wrap">
                                                    <input type="text" class="form-control" id="name" name="name"
                                                        value="{{ $user->name }}">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="email">Email</label>
                                                <div class="form-control-wrap">
                                                    <input type="email" class="form-control" id="email" name="email"
                                                        value="{{ $user->email }}">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="password">Password</label>
                                                <div class="form-control-wrap">
                                                    <input type="password" class="form-control" id="password"
                                                        name="password">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-group">
                                                <button type="submit" class="btn btn-lg btn-primary">Save</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
