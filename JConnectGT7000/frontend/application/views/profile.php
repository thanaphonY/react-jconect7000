<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6">
        <h3 class="mb-0">
          <i class="bi bi-person-circle me-2"></i>
          <?php echo lang('profile'); ?>
        </h3>
        <p class="text-muted mb-0"><?php echo lang('manage_your_profile_information'); ?></p>
      </div>
      <div class="col-sm-6">
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb justify-content-sm-end mb-0">
            <li class="breadcrumb-item"><a href="<?php echo base_url(); ?>"><i class="bi bi-house"></i> <?php echo lang('home'); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo lang('profile'); ?></li>
          </ol>
        </nav>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
  <div class="container-fluid">
    <form method="post" enctype="multipart/form-data" action="<?php echo base_url() . 'user/addEditProfile' ?>">
      <div class="row g-4">
        <!-- Profile Picture Card -->
        <div class="col-lg-4 col-md-12">
          <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-primary text-white">
              <div class="d-flex align-items-center">
                <i class="bi bi-camera me-2"></i>
                <h5 class="card-title mb-0"><?php echo lang('profile_picture'); ?></h5>
              </div>
            </div>
            <div class="card-body text-center p-4">
              <div class="position-relative mb-4">
                <div class="profile-picture-container">
                  <?php
                  if (file_exists('assets/images/' . $user_data[0]->profile_pic) && isset($user_data[0]->profile_pic)) {
                    $profile_pic = $user_data[0]->profile_pic;
                  } else {
                    $profile_pic = 'user.png';
                  } ?>
                  <img class="profile-image" 
                       src="<?php echo iaBase(); ?>/assets/images/<?php echo isset($profile_pic) ? $profile_pic : 'user.png'; ?>" 
                       alt="User profile picture"
                       id="profile-preview">
                  <div class="profile-overlay">
                    <i class="bi bi-camera-fill"></i>
                  </div>
                </div>
              </div>
              
              <div class="mb-3">
                                 <label for="fileUpload" class="btn btn-primary btn-lg w-100">
                   <i class="bi bi-upload me-2"></i>
                   <?php echo lang('change_picture'); ?>
                 </label>
                <input id="fileUpload" class="d-none" name="profile_pic" type="file" accept="image/*" />
                <input type="hidden" name="fileOld" value="<?php echo isset($user_data[0]->profile_pic) ? $user_data[0]->profile_pic : ''; ?>" />
              </div>
              
              <div class="text-muted small">
                <i class="bi bi-info-circle me-1"></i>
                <?php echo lang('supported_formats'); ?>: JPG, PNG, GIF
              </div>
            </div>
          </div>
        </div>

        <!-- Personal Information Card -->
        <div class="col-lg-8 col-md-12">
          <div class="card shadow-sm border-0">
            <div class="card-header bg-info text-white">
              <div class="d-flex align-items-center">
                <i class="bi bi-person-badge me-2"></i>
                <h5 class="card-title mb-0"><?php echo lang('personal_information'); ?></h5>
              </div>
            </div>
                        <div class="card-body p-4">
              <div class="row g-3">
                <!-- Name Field -->
                <div class="col-md-6">
                  <label for="name" class="form-label fw-semibold">
                    <i class="bi bi-person me-1"></i>
                    <?php echo lang('name'); ?>
                  </label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           value="<?php echo (isset($user_data[0]->name) ? $user_data[0]->name : ''); ?>" 
                           required>
                    <div class="invalid-feedback">
                      <?php echo lang('please_enter_your_name'); ?>
                    </div>
                  </div>
                </div>

                <!-- Email Field -->
                <div class="col-md-6">
                  <label for="email" class="form-label fw-semibold">
                    <i class="bi bi-envelope me-1"></i>
                    <?php echo lang('email'); ?>
                  </label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" 
                           class="form-control" 
                           id="email" 
                           name="email" 
                           value="<?php echo (isset($user_data[0]->email) ? $user_data[0]->email : ''); ?>" 
                           required>
                    <div class="invalid-feedback">
                      <?php echo lang('please_enter_valid_email'); ?>
                    </div>
                  </div>
                </div>

                <!-- User Type Display (Read-only) -->
                <div class="col-md-6">
                  <label class="form-label fw-semibold">
                    <i class="bi bi-shield me-1"></i>
                    <?php echo lang('user_type'); ?>
                  </label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-shield"></i></span>
                    <input type="text" 
                           class="form-control bg-light" 
                           value="<?php echo ucfirst(isset($user_data[0]->user_type) ? $user_data[0]->user_type : ''); ?>" 
                           readonly>
                  </div>
                </div>

                <!-- Status Display (Read-only) -->
                <div class="col-md-6">
                  <label class="form-label fw-semibold">
                    <i class="bi bi-circle-fill me-1"></i>
                    <?php echo lang('status'); ?>
                  </label>
                  <div class="input-group">
                    <span class="input-group-text">
                      <i class="bi bi-circle-fill text-<?php echo (isset($user_data[0]->status) && $user_data[0]->status == 'active') ? 'success' : 'warning'; ?>"></i>
                    </span>
                    <input type="text" 
                           class="form-control bg-light" 
                           value="<?php echo ucfirst(isset($user_data[0]->status) ? $user_data[0]->status : ''); ?>" 
                           readonly>
                  </div>
                </div>
              </div>

              <!-- Change Password Section -->
              <div class="mt-4">
                <div class="d-flex align-items-center mb-3">
                  <i class="bi bi-lock me-2 text-primary"></i>
                  <h6 class="mb-0 fw-semibold"><?php echo lang('ChangePassword'); ?></h6>
                </div>
                
                <div class="row g-3">
                  <!-- Current Password -->
                  <div class="col-md-12">
                    <label for="currentpassword" class="form-label fw-semibold">
                      <i class="bi bi-key me-1"></i>
                      <?php echo lang('current_password'); ?>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="bi bi-key"></i></span>
                      <input type="password" 
                             class="form-control" 
                             name="currentpassword" 
                             id="currentpassword"
                             placeholder="<?php echo lang('enter_current_password'); ?>">
                      <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>
                  </div>

                  <!-- New Password -->
                  <div class="col-md-6">
                    <label for="password" class="form-label fw-semibold">
                      <i class="bi bi-lock me-1"></i>
                      <?php echo lang('NewPassword'); ?>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="bi bi-lock"></i></span>
                      <input type="password" 
                             class="form-control" 
                             name="password" 
                             id="password"
                             placeholder="<?php echo lang('enter_new_password'); ?>">
                      <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>
                    <div class="password-strength mt-2" id="passwordStrength"></div>
                  </div>

                  <!-- Confirm New Password -->
                  <div class="col-md-6">
                    <label for="password" class="form-label fw-semibold">
                      <i class="bi bi-lock-fill me-1"></i>
                      <?php echo lang('confirm_new_password'); ?>
                    </label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                      <input type="password" 
                             class="form-control" 
                             id="confirm_password" 
                             name="confirmPassword"
                             placeholder="<?php echo lang('confirm_new_password'); ?>"
                             onkeyup="validatePasswordMatch()">
                      <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                        <i class="bi bi-eye"></i>
                      </button>
                    </div>
                    <div id="passwordMatchMessage" class="mt-2"></div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Card Footer -->
            <div class="card-footer bg-light">
              <input type="hidden" name="ia_users_id" value="<?php echo isset($user_data[0]->ia_users_id) ? $user_data[0]->ia_users_id : ''; ?>">
              <input type="hidden" name="user_type" value="<?php echo isset($user_data[0]->user_type) ? $user_data[0]->user_type : ''; ?>">
              
              <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                  <i class="bi bi-info-circle me-1"></i>
                  <?php echo lang('changes_will_be_logged'); ?>
                </div>
                <button class="btn btn-success btn-lg" id="saveformbutton" type="submit">
                  <i class="bi bi-check-circle me-2"></i>
                  <?php echo lang('Save'); ?>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Custom CSS -->
<style>
.profile-picture-container {
  position: relative;
  display: inline-block;
  border-radius: 50%;
  overflow: hidden;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  transition: all 0.3s ease;
}

.profile-picture-container:hover {
  transform: scale(1.05);
  box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.profile-image {
  width: 200px;
  height: 200px;
  object-fit: cover;
  border-radius: 50%;
  border: 4px solid #fff;
}

.profile-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.3s ease;
  border-radius: 50%;
}

.profile-picture-container:hover .profile-overlay {
  opacity: 1;
}

.profile-overlay i {
  color: white;
  font-size: 2rem;
}

.password-strength {
  height: 4px;
  border-radius: 2px;
  transition: all 0.3s ease;
}

.password-strength.weak {
  background-color: #dc3545;
}

.password-strength.medium {
  background-color: #ffc107;
}

.password-strength.strong {
  background-color: #198754;
}

.card {
  transition: all 0.3s ease;
}

.card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0,0,0,0.1) !important;
}

.input-group-text {
  background-color: #f8f9fa;
  border-color: #dee2e6;
}

.form-control:focus {
  border-color: #0d6efd;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
}

/* Dark mode support */
[data-bs-theme="dark"] .form-control {
  background-color: #2b3035;
  border-color: #495057;
  color: #e9ecef;
}

[data-bs-theme="dark"] .form-control:focus {
  background-color: #2b3035;
  border-color: #0d6efd;
  color: #e9ecef;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

[data-bs-theme="dark"] .form-control::placeholder {
  color: #6c757d;
}

[data-bs-theme="dark"] .input-group-text {
  background-color: #495057;
  border-color: #495057;
  color: #e9ecef;
}

[data-bs-theme="dark"] .form-control.bg-light {
  background-color: #343a40 !important;
  color: #e9ecef;
}

[data-bs-theme="dark"] .card {
  background-color: #212529;
  border-color: #495057;
}

[data-bs-theme="dark"] .card-header {
  border-bottom-color: #495057;
}

[data-bs-theme="dark"] .card-footer {
  background-color: #343a40 !important;
  border-top-color: #495057;
}

[data-bs-theme="dark"] .text-muted {
  color: #adb5bd !important;
}

[data-bs-theme="dark"] .btn-outline-secondary {
  border-color: #6c757d;
  color: #e9ecef;
}

[data-bs-theme="dark"] .btn-outline-secondary:hover {
  background-color: #6c757d;
  border-color: #6c757d;
  color: #fff;
}



.btn {
  transition: all 0.3s ease;
}

.btn:hover {
  transform: translateY(-1px);
}
</style>

<!-- JavaScript -->
<script>
// Simple form validation
document.addEventListener('DOMContentLoaded', function() {
  const form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', function(event) {
      // Allow form to submit normally
      console.log('Form submitting...');
    });
  }
});

// Profile picture preview
document.getElementById('fileUpload').addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('profile-preview').src = e.target.result;
    };
    reader.readAsDataURL(file);
  }
});

// Password visibility toggles
function togglePasswordVisibility(inputId, buttonId) {
  const input = document.getElementById(inputId);
  const button = document.getElementById(buttonId);
  const icon = button.querySelector('i');
  
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.remove('bi-eye');
    icon.classList.add('bi-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.remove('bi-eye-slash');
    icon.classList.add('bi-eye');
  }
}

document.getElementById('toggleCurrentPassword').addEventListener('click', () => {
  togglePasswordVisibility('currentpassword', 'toggleCurrentPassword');
});

document.getElementById('toggleNewPassword').addEventListener('click', () => {
  togglePasswordVisibility('password', 'toggleNewPassword');
});

document.getElementById('toggleConfirmPassword').addEventListener('click', () => {
  togglePasswordVisibility('confirm_password', 'toggleConfirmPassword');
});

// Password strength indicator
document.getElementById('password').addEventListener('input', function() {
  const password = this.value;
  const strengthDiv = document.getElementById('passwordStrength');
  
  let strength = 0;
  if (password.length >= 8) strength++;
  if (/[a-z]/.test(password)) strength++;
  if (/[A-Z]/.test(password)) strength++;
  if (/[0-9]/.test(password)) strength++;
  if (/[^A-Za-z0-9]/.test(password)) strength++;
  
  strengthDiv.className = 'password-strength';
  if (strength < 3) {
    strengthDiv.classList.add('weak');
  } else if (strength < 5) {
    strengthDiv.classList.add('medium');
  } else {
    strengthDiv.classList.add('strong');
  }
});

// Password match validation
function validatePasswordMatch() {
  const password = document.getElementById('password').value;
  const confirmPassword = document.getElementById('confirm_password').value;
  const messageDiv = document.getElementById('passwordMatchMessage');
  
  if (confirmPassword === '') {
    messageDiv.innerHTML = '';
    return;
  }
  
  if (password === confirmPassword) {
    messageDiv.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Passwords match</span>';
  } else {
    messageDiv.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Passwords do not match</span>';
  }
}

// Password validation and form submission
document.getElementById('saveformbutton').addEventListener('click', function(e) {
  const password = document.getElementById('password').value;
  const confirmPassword = document.getElementById('confirm_password').value;
  
  // Check if passwords match only if password is entered
  if (password && password !== confirmPassword) {
    e.preventDefault();
    alert('Passwords do not match!');
    return false;
  }
  
  // Show loading state
  this.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Saving...';
  this.disabled = true;
  
  // Submit the form
  this.closest('form').submit();
});
</script>