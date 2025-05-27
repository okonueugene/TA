<div class="modal-dialog modal-dialog-scrollable" role="document">
    <div class="modal-content">
        <!-- Modal Header -->
        <div class="modal-header">
            <h5 class="modal-title">Edit Employee</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <!-- Modal Body -->
        <div class="modal-body">
            <form action="{{ url('/admin/employees/' . $employee->id) }}" method="POST" class="form">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="pin">Pin</label>
                            <input type="text" class="form-control" id="pin" name="pin" value="{{ $employee->pin }}" readonly>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="empname">Name</label>
                            <input type="text" class="form-control" id="empname" name="empname"
                                value="{{ $employee->empname }}">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="empgender">Gender</label>
                            <input type="text" class="form-control" id="empgender" name="empgender"
                                value="{{ ucfirst($employee->empgender) }}" readonly>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="empoccupation">Occupation</label>
                            <input type="text" class="form-control" id="empoccupation" name="empoccupation"
                                value="{{ ucfirst($employee->empoccupation) }}">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="empphone">Phone</label>
                            <input type="text" class="form-control" id="empphone" name="empphone"
                                value="{{ $employee->empphone }}">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="empresidence">Residence</label>
                            <input type="text" class="form-control" id="empresidence" name="empresidence"
                                value="{{ ucwords($employee->empresidence) }}">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="team">Team</label>
                            <input type="text" class="form-control" id="team" name="team"
                                value="{{ $employee->team }}">
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="active" {{ $employee->status == 1 ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $employee->status == 0 ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group">
                            <label for="acc_no">Account Number</label>
                            <input type="text" class="form-control" id="acc_no" name="acc_no"
                                value="{{ $employee->acc_no }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary submit-btn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>