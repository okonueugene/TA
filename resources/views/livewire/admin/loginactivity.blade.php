@extends('layouts.admin')

@section('content')
    <div class="nk-content ">
        <div class="container-fluid">
            <div class="card-inner card-inner-lg">
                <div class="nk-block-head nk-block-head-lg">
                    <div class="nk-block-between">
                        <div class="nk-block-head-content">
                            <h4 class="nk-block-title">Login Activity</h4>
                            <div class="nk-block-des">
                                <p>Here is your last {{ count($activities) }} login activities log. <span
                                        class="text-soft"><em class="icon ni ni-info"></em></span></p>
                            </div>
                        </div>
                        <div class="nk-block-head-content align-self-start d-lg-none"><a href="#"
                                class="toggle btn btn-icon btn-trigger mt-n1" data-target="userAside"><em
                                    class="icon ni ni-menu-alt-r"></em></a></div>
                    </div>
                </div>
                <div class="nk-block card">
                    <table class="table table-ulogs">
                        <thead class="table-light">
                            <tr>
                                <th class="tb-col-os"><span class="overline-title">Browser <span class="d-sm-none">/
                                            IP</span></span></th>
                                <th class="tb-col-ip"><span class="overline-title">IP</span></th>
                                <th class="tb-col-time"><span class="overline-title">Time</span></th>
                                <th class="tb-col-time"><span class="overline-title">Status</span></th>
                                <th class="tb-col-time"><span class="overline-title">Logout</span></th>
                                <th class="tb-col-action"><span class="overline-title">&nbsp;</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($activities as $activity)
                                <tr>
                                    <td class="tb-col-os">{{ $activity->user_agent }}</td>
                                    <td class="tb-col-ip"><?php
                                    $curl = curl_init();
                                    
                                    curl_setopt_array($curl, [
                                        CURLOPT_URL => 'https://spott.p.rapidapi.com/places/ip/' . $activity->ip_address,
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_FOLLOWLOCATION => true,
                                        CURLOPT_ENCODING => '',
                                        CURLOPT_MAXREDIRS => 10,
                                        CURLOPT_TIMEOUT => 30,
                                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                        CURLOPT_CUSTOMREQUEST => 'GET',
                                        CURLOPT_HTTPHEADER => ['X-RapidAPI-Host: spott.p.rapidapi.com', 'X-RapidAPI-Key: c755dded1fmsh02ea59ef06f4bb5p18757ajsn6dc14b75bd01'],
                                    ]);
                                    
                                    $response = curl_exec($curl);
                                    $err = curl_error($curl);
                                    
                                    curl_close($curl);
                                    
                                    if ($err) {
                                        echo 'cURL Error #:' . $err;
                                    } else {
                                        $y = explode(',', $response);
                                        $country = explode(':', $y[3])[1];
                                        $title = str_replace(['\'', '"', ',', ';', '<', '>'], ' ', $country);
                                        echo $title;
                                    } ?>
                                    </td>
                                    <td class="tb-col-time"><span class="sub-text"><?php $t = new DateTime($activity->login_at, new DateTimeZone('UTC'));
                                    echo $t->setTimeZone(new DateTimeZone('Africa/Nairobi'))->format('Y-m-d H:i:s'); ?></span></td>
                                    <td class="tb-col-time"><span class="sub-text">
                                            @if ($activity->login_successful == 1)
                                            <span class="badge bg-success text-white">Logged-In</span>@else<span
                                                    class="badge bg-danger text-white">LogIn-Failed</span>
                                        </span>
                            @endif
                            </td>
                            <td class="tb-col-time"><span class="sub-text"><?php $t = new DateTime($activity->logout_at, new DateTimeZone('UTC'));
                            echo $t->setTimeZone(new DateTimeZone('Africa/Nairobi'))->format('Y-m-d H:i:s'); ?></span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
