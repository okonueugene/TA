@extends('layouts.admin')

@section('content')
    <div class="nk-content ">
        <div class="container-fluid">
            <div class="nk-content-inner">
                <div class="nk-content-body">
                    <div class="nk-block-head nk-block-head-sm">
                        <div class="nk-block-between">
                            <div class="nk-block-head-content">
                                <h3 class="nk-block-title page-title">Employees</h3>
                            </div>
                            <div class="nk-block-head-content">
                                <div class="toggle-wrap nk-block-tools-toggle">
                                    <a href="#" class="btn btn-icon btn-trigger toggle-expand me-n1"
                                        data-target="more-options"><em class="icon ni ni-more-v"></em>
                                    </a>
                                    <div class="toggle-expand-content" data-content="more-options">
                                        <ul class="nk-block-tools g-3">
                                            <li class="nk-block-tools-opt">
                                                <a href="javascript:void(0)"
                                                    class="btn btn-primary btn-sm  float-end mx-2 modal-button mt-2"
                                                    data-href="{{ url('/admin/employees/create') }}">
                                                    <em class="icon ni ni-plus"></em>
                                                    <span>Add Employee</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="nk-block">
                        <div class="card card-bordered card-stretch">
                            <table class="cell-border stripe row-border hover" id="page_table" style="width:100%">
                                <thead>
                                    <tr class="nk-tb-item nk-tb-head">
                                        <th class="nk-tb-col nk-tb-col-check">
                                            <div class="custom-control custom-control-sm custom-checkbox notext">
                                                <input type="checkbox" class="custom-control-input" id="uid">
                                                <label class="custom-control-label" for="uid"></label>
                                            </div>
                                        </th>
                                        <th class="nk-tb-col"><span>Name</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Gender</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Occupation</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Phone</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Residence</span></th>
                                        <th class="nk-tb-col tb-col-md"><span>Team</span></th>
                                        <th class="nk-tb-col nk-tb-col-tools text-end">
                                            <span class="sub-text">Actions</span>
                                        </th>
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
            var url = "{{ url('admin/employees') }}";

            var columns = [{
                    data: 'pin',
                    name: 'pin'
                },
                {
                    data: 'empname',
                    name: 'empname'
                },
                {
                    data: 'empgender',
                    name: 'empgender'
                },
                {
                    data: 'empoccupation',
                    name: 'empoccupation'
                },
                {
                    data: 'empphone',
                    name: 'empphone'
                },
                {
                    data: 'empresidence',
                    name: 'empresidence'
                },
                {
                    data: 'team',
                    name: 'team'
                },
                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false
                }
            ];

            var filters = {};

            var page_table = __initializePageTable(url, columns, filters);
        });
    </script>
@endpush
