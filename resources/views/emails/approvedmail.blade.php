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
                <h1 class="h3 mb-2">Approval Of Leave Application</h1>
                <h5>Hello {{$leave->user->name}},</h5>
                <hr>
                <div class="space-y-3">
                    <p>
                        We have received your leave request running between {{ date_format(date_create($leave->date_start), 'l') }}
                        {{ date_format(date_create($leave->date_start), 'jS') }}
                        {{ date_format(date_create($leave->date_start), 'M') }}
                        {{ date_format(date_create($leave->date_start), 'Y') }} and
                        {{ date_format(date_create($leave->date_end), 'l') }}
                        {{ date_format(date_create($leave->date_end), 'jS') }}
                        {{ date_format(date_create($leave->date_end), 'M') }}
                        {{ date_format(date_create($leave->date_end), 'Y') }}.
                    </p>
                    <p>
                        The sum total of {{ $leave->nodays }} working days.
                    </p>
                    <p>
                        I am happy to inform you that as of  {{ date_format(date_create(date('Y/m/d')), 'l') }}
                        {{ date_format(date_create(date('Y/m/d')), 'jS') }}
                        {{ date_format(date_create(date('Y/m/d')), 'M') }}
                        {{ date_format(date_create(date('Y/m/d')), 'Y') }}, your leave request is approved.
                    </p>
                    <p>
                        Per your request, this time off will be marked as {{ $leave->type->name }}.</p>
                    <p>
                        <span>Regards,</span><br>
                        <span>{{$name}}</span><br>
                        <span>{{$leave->dept->name}} {{$position }}</span>
                    </p>
                </div>
                <hr>
            </div>
        </div>
    </div>
</body>

</html>
