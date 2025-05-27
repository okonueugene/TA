<div class="modal-dialog modal-dialog-scrollable" role="document">
    <div class="modal-content">
        <!-- Modal Header -->
        <div class="modal-header">
            <h5 class="modal-title">Add Employee</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <!-- Modal Body -->
        <div class="modal-body">
            <form action="{{ url('/admin/employees') }}" method="POST" class="form">
                @csrf
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Name</label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="empname">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Gender</label>
                        <div class="form-control-wrap">
                            <select class="form-control" name="empgender">
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Occupation</label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="empoccupation">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Phone</label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="empphone">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Residence</label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="empresidence">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Team</label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="team">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Status</label>
                        <div class="form-control-wrap">
                            <select class="form-control" name="status">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Account Number</label>
                        <div class="form-control-wrap">
                            <input type="text" class="form-control" name="acc_no">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-md btn-primary">Save</button>
                        <button type="button" class="btn btn-md btn-secondary float-end"
                            data-bs-dismiss="modal">Close</button>

                    </div>

                </div>
            </div>
                
        </div>
        </form>
    </div>
</div>
