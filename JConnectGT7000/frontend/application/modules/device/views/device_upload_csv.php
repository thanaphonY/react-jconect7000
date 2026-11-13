<!--begin::App Content Header-->
<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col">
                <h4>Automatic Add</h4>

            </div>
        </div>
    </div>
    <!--end::App Content Header-->

    <!--begin::App Content-->
    <div class="app-content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-md-6">

                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">📥 Upload Device CSV</h5>
                        </div>
                        <div class="card-body">
                            <form action="<?= base_url('device/import_csv') ?>" method="post" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="csv_file" class="form-label">Select CSV File</label>
                                    <input type="file" class="form-control" name="csv_file" id="csv_file" accept=".csv" required>
                                </div>
                                <button type="submit" class="btn btn-success w-100">Upload & Import</button>
                            </form>
                        </div>
                    </div>

                    <div class="mt-3 alert alert-info">
                        <strong>📌 CSV Format Example:</strong><br>
                        <code>name,serial_number,dev_eui,app_key,location,description,application_server</code>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <!--end::App Content-->