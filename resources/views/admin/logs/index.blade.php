@extends('layouts.admin')

@section('content')
    <div class="nk-content ">
        <div class="container-fluid">
            <div class="nk-content-inner">
                <div class="nk-content-body">
                    <div class="nk-block-head nk-block-head-sm">
                        <div class="nk-block-between">
                            <div class="nk-block-head-content">
                                <h3 class="nk-block-title page-title">Activity Logs</h3>
                            </div>
                        </div>
                    </div>
                    <div class="nk-block">
                        <div class="card card-bordered card-stretch">
                            <table class="cell-border stripe row-border hover" id="page_table" style="width:100%">
                                <thead>
                                    <tr class="nk-tb-item nk-tb-head">
                                        <th class="nk-tb-col">Log ID</th>
                                        <th class="nk-tb-col tb-col-mb">Date & Time</th>
                                        <th class="nk-tb-col tb-col-mb">Action Summary</th>
                                        <th class="nk-tb-col tb-col-mb">Action By</th>
                                        <th class="nk-tb-col tb-col-mb">Subject Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        $(document).ready(function() {

            //initialize datatable
            var url = "{{ url('admin/logs') }}";

            var columns = [{
                    data: 'log_id', // Maps to the 'log_id' addColumn in the controller
                    name: 'log_id',
                    orderable: false, // Often, log IDs are not sorted meaningfully by users
                    searchable: false // If you don't want to search by ID directly
                },
                {
                    data: 'created_at', // Maps to the formatted 'created_at' editColumn
                    name: 'created_at'
                },
                {
                    data: 'action_summary', // Maps to the 'action_summary' addColumn
                    name: 'action_summary'
                },
                {
                    data: 'action_by', // Maps to the 'action_by' addColumn
                    name: 'action_by'
                },
                {
                    data: 'subject_details', // Maps to the 'subject_details' addColumn
                    name: 'subject_details'
                }
            ];

            var filters = {};

            var page_table = __initializePageTable(url, columns, filters);
        });
    </script>
@endpush
