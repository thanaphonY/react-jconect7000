<div class="app-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">
                        <?= isset($category) ? 'Edit Category' : 'Add Category' ?>
                    </h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?= base_url('device_categories') ?>">Device Categories</a></li>
                        <li class="breadcrumb-item active"><?= isset($category) ? 'Edit' : 'Add' ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Form Card -->
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-layer-group"></i>
                            <?= isset($category) ? 'Edit Device Category' : 'Add New Device Category' ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="categoryForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="name" 
                                               name="name" 
                                               value="<?= isset($category) ? htmlspecialchars($category->name) : set_value('name') ?>"
                                               required>
                                        <?= form_error('name', '<small class="text-danger">', '</small>') ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="icon" class="form-label">Icon</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i id="iconPreview" class="fas fa-server"></i></span>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="icon" 
                                                   name="icon" 
                                                   value="<?= isset($category) ? htmlspecialchars($category->icon) : set_value('icon', 'fa-server') ?>"
                                                   placeholder="fa-server">
                                        </div>
                                        <small class="form-text text-muted">FontAwesome icon class (e.g., fa-server, fa-bolt, fa-thermometer-half)</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="color" class="form-label">Color</label>
                                        <div class="input-group">
                                            <input type="color" 
                                                   class="form-control form-control-color" 
                                                   id="colorPicker" 
                                                   value="<?= isset($category) ? htmlspecialchars($category->color) : '#007bff' ?>"
                                                   title="Choose color">
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="color" 
                                                   name="color" 
                                                   value="<?= isset($category) ? htmlspecialchars($category->color) : set_value('color', '#007bff') ?>"
                                                   placeholder="#007bff">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="is_active" class="form-label">Status</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   id="is_active" 
                                                   name="is_active" 
                                                   value="1"
                                                   <?= (isset($category) && $category->is_active) || set_checkbox('is_active', '1') ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_active">
                                                Active
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" 
                                          id="description" 
                                          name="description" 
                                          rows="3" 
                                          placeholder="Enter category description..."><?= isset($category) ? htmlspecialchars($category->description) : set_value('description') ?></textarea>
                            </div>

                            <!-- Preview Section -->
                            <div class="mb-3">
                                <label class="form-label">Preview</label>
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <div class="category-preview" style="background-color: <?= isset($category) ? $category->color : '#007bff' ?>; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                                <i id="previewIcon" class="<?= isset($category) ? $category->icon : 'fa-server' ?> text-white fa-lg"></i>
                                            </div>
                                            <div>
                                                <h6 id="previewName" class="mb-1"><?= isset($category) ? $category->name : 'Category Name' ?></h6>
                                                <p id="previewDesc" class="text-muted mb-0"><?= isset($category) ? $category->description : 'Category description will appear here' ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="<?= base_url('device_categories') ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    <?= isset($category) ? 'Update Category' : 'Create Category' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('name');
    const iconInput = document.getElementById('icon');
    const colorPicker = document.getElementById('colorPicker');
    const colorInput = document.getElementById('color');
    const descriptionInput = document.getElementById('description');
    
    const previewName = document.getElementById('previewName');
    const previewIcon = document.getElementById('previewIcon');
    const previewDesc = document.getElementById('previewDesc');
    const categoryPreview = document.querySelector('.category-preview');

    // Update preview when inputs change
    function updatePreview() {
        previewName.textContent = nameInput.value || 'Category Name';
        previewIcon.className = iconInput.value || 'fa-server';
        previewDesc.textContent = descriptionInput.value || 'Category description will appear here';
        categoryPreview.style.backgroundColor = colorInput.value || '#007bff';
    }

    // Event listeners
    nameInput.addEventListener('input', updatePreview);
    iconInput.addEventListener('input', updatePreview);
    descriptionInput.addEventListener('input', updatePreview);
    
    colorPicker.addEventListener('input', function() {
        colorInput.value = this.value;
        updatePreview();
    });
    
    colorInput.addEventListener('input', function() {
        colorPicker.value = this.value;
        updatePreview();
    });

    // Initial preview update
    updatePreview();
});
</script>

<style>
.category-preview {
    transition: all 0.3s ease;
}

.category-preview:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.form-control-color {
    width: 60px;
    height: 38px;
}
</style> 