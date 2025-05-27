<div class="nk-content ">
    <div class="container-fluid">
        <div class="nk-content-inner">
            <div class="nk-content-body">
                <div class="nk-block-head nk-block-head-sm">
                    <div class="nk-block-between">
                        <div class="nk-block-head-content">
                            <h3 class="nk-block-title page-title">Settings</h3>
                        </div>
                    </div>
                </div>
                <div class="nk-block nk-block-lg">
                    <div class="card card-bordered card-stretch">
                        <ul class="nav nav-tabs nav-tabs-mb-icon nav-tabs-card" role="tablist">
                            <li class="nav-item" role="presentation">
                            </li>

                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active show" id="site" role="tabpanel">
                                <div class="card-inner pt-0">
                                    {{-- <form action="#" class="gy-3 form-settings">
                                        <div class="row g-3 align-center">
                                            <div class="col-lg-5">
                                                <div class="form-group"><label class="form-label" for="comp-name">Site
                                                        Name</label><span class="form-note">Specify the name of your
                                                        Site.</span></div>
                                            </div>
                                            <div class="col-lg-7">
                                                <div class="form-group">
                                                    <div class="form-control-wrap">
                                                        <input wire:model="name" type="text"
                                                            class="form-control name" value="{{ $name }}"
                                                            name="name" id="default-04"
                                                            placeholder="Enter site name">
                                                    </div>
                                                    @error('name')
                                                        <div class="form-note text-danger mt-1">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 align-center">
                                            <div class="col-lg-5">
                                                <div class="form-group"><label class="form-label" for="comp-email">Site
                                                        Email</label><span class="form-note">Specify the email address
                                                        of your
                                                        Site.</span></div>
                                            </div>
                                            <div class="col-lg-7">
                                                <div class="form-group">
                                                    <div class="form-control-wrap">
                                                        <input wire:model="email" type="email"
                                                            class="form-control email" name="email"
                                                            value="{{ $email }}"
                                                            placeholder="Enter site email address">
                                                    </div>
                                                    @error('email')
                                                        <div class="form-note text-danger mt-1">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 align-center">
                                            <div class="col-lg-5">
                                                <div class="form-group"><label class="form-label"
                                                        for="comp-copyright">Site Copyright</label><span
                                                        class="form-note">Copyright information of your
                                                        Site.</span>
                                                </div>
                                            </div>
                                            <div class="col-lg-7">
                                                <div class="form-group">
                                                    <div class="form-control-wrap"><input type="text"
                                                            class="form-control" id="comp-copyright"
                                                            value="© 2022, DashLite. All Rights Reserved.">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 align-center">
                                            <div class="col-lg-5">
                                                <div class="form-group"><label class="form-label">Main
                                                        Website</label><span class="form-note">Specify the
                                                        URL if your
                                                        main website is external.</span></div>
                                            </div>
                                            <div class="col-lg-7">
                                                <div class="form-group">
                                                    <div class="form-control-wrap"><input type="text"
                                                            class="form-control" name="site-url"
                                                            value="https://www.softnio.com"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 align-center">
                                            <div class="col-lg-5">
                                                <div class="form-group"><label class="form-label"
                                                        for="site-off">Maintanance Mode</label><span
                                                        class="form-note">Enable to make website make
                                                        offline.</span>
                                                </div>
                                            </div>
                                            <div class="col-lg-7">
                                                <div class="form-group">
                                                    <div class="custom-control custom-switch"><input type="checkbox"
                                                            class="custom-control-input" name="reg-public"
                                                            id="site-off"><label class="custom-control-label"
                                                            for="site-off">Offline</label></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-lg-7">
                                                <div class="form-group mt-2"><button
                                                        wire:submit.prevent='updateCompany'type="submit"
                                                        class="btn btn-lg btn-primary">Update</button>
                                                </div>
                                            </div>
                                        </div>
                                    </form> --}}
                                    <form wire:submit="updateSiteSettings" class="gy-3 form-settings">
                                        <div class="row g-3 align-center">
                                            <div class="col-lg-5">
                                                <div class="form-group"><label class="form-label" for="comp-name">Site
                                                        Name</label><span class="form-note">Specify the name of your
                                                        Site.</span></div>
                                            </div>
                                            <div class="col-lg-7">
                                                <div class="form-group">
                                                    <div class="form-control-wrap">
                                                        <input wire:model="name" type="text"
                                                            class="form-control name" name="name" id="default-04"
                                                            placeholder="Site Name" value="{{ $settings->$name ?? '' }}">
                                                    </div>
                                                    @error('name')
                                                        <div class="form-note text-danger mt-1">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 align-center">
                                            <div class="col-lg-5">
                                                <div class="form-group"><label class="form-label" for="comp-email">Site
                                                        Email</label><span class="form-note">Specify the email address
                                                        of your
                                                        Site.</span></div>

                                            </div>
                                            <div class="col-lg-7">
                                                <div class="form-group">
                                                    <div class="form-control-wrap">
                                                        <input wire:model="email" type="email"
                                                            class="form-control email" name="email"
                                                            value="{{ $settings->$email ?? '' }}"
                                                            placeholder="doV0C@example.com" id="default-05">
                                                    </div>
                                                    @error('email')
                                                        <div class="form-note text-danger mt-1">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 align-center">
                                            <div class="col-lg-5">
                                                <div class="form-group"><label class="form-label"
                                                        for="comp-copyright">Site Copyright</label><span
                                                        class="form-note">Copyright information of your
                                                        Site.</span>
                                                </div>
                                            </div>
                                            <div class="col-lg-7">
                                                <div class="form-group">
                                                    <div class="form-control-wrap"><input wire:model="copyright"
                                                            type="text" class="form-control" id="comp-copyright"
                                                            placeholder="{{ $settings->copyright ?? '' }}">
                                                    </div>
                                                    @error('copyright');
                                                        <div class="form-note text-danger mt-1">{{ $message }}</div>
                                                    @enderror

                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 align-center">
                                            <div class="col-lg-5">
                                                <div class="form-group"><label class="form-label" for="comp-logo">Site
                                                        Logo</label><span class="form-note">Upload your site
                                                        logo.</span>
                                                </div>
                                                <div class="col-lg-7">
                                                    <div class="form-group">
                                                        <div class="form-control-wrap">
                                                            <div class="custom-file">

                                                                <input type="file" wire:model='logo'
                                                                    class="custom-file-input" id="customFile">
                                                                <label class="custom-file-label" for="customFile">Choose
                                                                    file</label>
                                                            </div>
                                                            @if ($logo)
                                                                Photo Preview:
                                                                <img src="{{ $logo->temporaryUrl() }}">
                                                            @endif
                                                        </div>
                                                        <div wire:loading wire:target="photo">Uploading...</div>

                                                        @error('logo')
                                                            <div class="form-note text-danger mt-1">{{ $message }}
                                                            </div>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-3 align-center">
                                            <div class="col-lg-5">
                                                <div class="form-group">
                                                    <label class="form-label" for="site-off">Maintenance Mode</label>
                                                    <span class="form-note">Enable to make the website offline.</span>
                                                </div>
                                            </div>
                                            <div class="col-lg-7">
                                                <div class="form-group">
                                                    <div class="custom-control custom-switch">
                                                        <input wire:model="maintenance_mode" type="checkbox"
                                                            class="custom-control-input" name="reg-public"
                                                            id="site-off"
                                                            @if ($maintenance_mode) checked @endif>
                                                        <label class="custom-control-label"
                                                            for="site-off">Offline</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row float-left">
                       <div class="col-lg-7">
    <div class="form-group mt-2">
        <button wire:submit='updateSiteSettings' type="submit"
            class="btn btn-md btn-primary {{ $user->user_type !== 'admin' ? 'disabled' : '' }}"
            id="update-site"
            {{ $user->user_type !== 'admin' ? 'disabled' : '' }}>
            Update
        </button>
    </div>
</div>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
