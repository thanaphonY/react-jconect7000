<div class="app-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Device Categories</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= base_url('dashboard') ?>">Dashboard</a></li>
                        <li class="breadcrumb-item active">Device Categories</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="<?= base_url('device_categories/add') ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Category
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-layer-group fa-2x text-primary"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4 class="mb-1"><?= $total_categories ?></h4>
                                <p class="text-muted mb-0">Total Categories</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h4 class="mb-1"><?= $active_categories ?></h4>
                                <p class="text-muted mb-0">Active Categories</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categories Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Device Categories</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($categories)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No categories found</h5>
                                <p class="text-muted">Start by adding your first device category.</p>
                                <a href="<?= base_url('device_categories/add') ?>" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Category
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Icon</th>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="category-icon" style="background-color: <?= $category->color ?>; width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                                            <i class="<?= $category->icon ?> text-white"></i>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($category->name) ?></strong>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($category->description ?: '-') ?>
                                                </td>
                                                <td>
                                                    <?php if ($category->is_active): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= date('M d, Y', strtotime($category->created_at)) ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="<?= base_url('device_categories/edit/' . $category->id) ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="<?= base_url('device_categories/toggle_status/' . $category->id) ?>" 
                                                           class="btn btn-sm btn-outline-<?= $category->is_active ? 'warning' : 'success' ?>" 
                                                           title="<?= $category->is_active ? 'Deactivate' : 'Activate' ?>"
                                                           onclick="return confirm('Are you sure you want to <?= $category->is_active ? 'deactivate' : 'activate' ?> this category?')">
                                                            <i class="fas fa-<?= $category->is_active ? 'pause' : 'play' ?>"></i>
                                                        </a>
                                                        <a href="<?= base_url('device_categories/delete/' . $category->id) ?>" 
                                                           class="btn btn-sm btn-outline-danger" 
                                                           title="Delete"
                                                           onclick="return confirm('Are you sure you want to delete this category? This action cannot be undone.')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.category-icon {
    transition: transform 0.2s ease;
}

.category-icon:hover {
    transform: scale(1.1);
}

.btn-group .btn {
    margin-right: 2px;
}

.btn-group .btn:last-child {
    margin-right: 0;
}
</style> 