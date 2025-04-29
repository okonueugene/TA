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
                <h1 class="h3 mb-2">Leave Application</h1>
                <h5>Hello Sir/Madam,</h5>
                <hr>
                <div class="space-y-3">
                    <p>I'm writing to ask for {{ $leave->leave_type_id == 1 ? 'an' : 'a' }}
                        {{ $leave->type->name }}.
                    </p>
                    <p>
                        I'd like to take my leave between {{ date_format(date_create($leave->date_start), 'l') }}
                        {{ date_format(date_create($leave->date_start), 'jS') }}
                        {{ date_format(date_create($leave->date_start), 'M') }}
                        {{ date_format(date_create($leave->date_start), 'Y') }} and
                        {{ date_format(date_create($leave->date_end), 'l') }}
                        {{ date_format(date_create($leave->date_end), 'jS') }}
                        {{ date_format(date_create($leave->date_end), 'M') }}
                        {{ date_format(date_create($leave->date_end), 'Y') }}.
                    </p>
                    <p>
                        I'll be away for {{ $leave->nodays }} working days, which is in accordance with the site's
                        {{ $leave->type->name }} policy.
                    </p>
                    <p>
                        I have discussed my absence with my team to cover for me during this time.
                    </p>
                    <p>
                        Thank you for considering the above dates for my leave.
                    </p>
                    <p>
                        <span>Regards,</span><br>
                        <span>{{ $leave->user->name }}</span>
                    </p>
                </div>
                <hr>
            </div>
        </div>
    </div>
</body>

</html>
