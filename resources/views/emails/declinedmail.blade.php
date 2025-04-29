<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        /* Add custom classes and styles that you want inlined here */
    </style>
</head>

<body class="bg-light">
    <div class="container">
        <div class="card my-10">
            <div class="card-body">
                <h1 class="h3 mb-2">Denial Of Leave Application</h1>
                <h5>Dear {{ $leave->user->name }},</h5>
                <hr>
                <div class="space-y-3">
                    <p>
                        This email is in response to your request for a leave of absence beginning
                        {{ date_format(date_create($leave->date_start), 'l') }}
                        {{ date_format(date_create($leave->date_start), 'jS') }}
                        {{ date_format(date_create($leave->date_start), 'M') }}
                        {{ date_format(date_create($leave->date_start), 'Y') }} through
                        {{ date_format(date_create($leave->date_end), 'l') }}
                        {{ date_format(date_create($leave->date_end), 'jS') }}
                        {{ date_format(date_create($leave->date_end), 'M') }}
                        {{ date_format(date_create($leave->date_end), 'Y') }} for {{ $leave->reason }}.
                    </p>
                    <p>
                        Although we make every effort to accommodate employees with a need for time off, unfortunately,
                        your leave request is not approved due to {{ $leave->remarks }} </p>
                    <p>
                        If you feel that this decision is made in error or that extenuating circumstances warrant
                        further review of your request, please feel free to contact me with more information surrounding
                        your need for leave.
                    </p>
                        <span>Sincerely,</span><br>
                        <span>{{ $name }}</span><br>
                        <span>{{$leave->dept->name}} {{$position }}</span>
                    </p>
                </div>
                <hr>
            </div>
        </div>
    </div>
</body>

</html>









