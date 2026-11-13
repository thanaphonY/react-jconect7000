<!--begin::App Content Header-->
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col">
        <h5><?php echo lang('notification_manager'); ?></h5>
        <nav aria-label="breadcrumb">
         <!-- <ol class="breadcrumb">
            <li class="breadcrumb-item active" aria-current="page"><?php echo lang('notification_manager'); ?></li>
          </ol>-->
        </nav>
      </div>
      <div class="col text-end">

            <h5 class="card-title mb-0"><?php echo lang('notification_channels'); ?></h5>
            <button type="button" class="btn btn-primary" onclick="openAddModal()">
              <i class="bi bi-plus-circle"></i> Add Channel
            </button>
        
      </div>
    </div>
  </div>
</div>
<!--end::App Content Header-->

<!--begin::App Content-->
<div class="app-content">
  <div class="container-fluid">
    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <a class="nav-link active" href="<?= base_url('notification_manager') ?>"><?= lang('notification_channels_tab') ?></a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="<?= base_url('notification_manager/health_checks') ?>"><?= lang('health_check') ?></a>
      </li>
    </ul>
    
    <!-- Dashboard Overview Cards -->
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card bg-primary text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h4 class="mb-0"><?= count($channels) ?></h4>
                <small>Total Channels</small>
              </div>
              <div class="align-self-center">
                <i class="bi bi-bell fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-success text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h4 class="mb-0"><?= count(array_filter($channels, function($c) { return $c->is_active == 't'; })) ?></h4>
                <small>Active Channels</small>
              </div>
              <div class="align-self-center">
                <i class="bi bi-check-circle fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-info text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h4 class="mb-0"><?= $total_devices ?? 0 ?></h4>
                <small>Connected Devices</small>
              </div>
              <div class="align-self-center">
                <i class="bi bi-cpu fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card bg-warning text-white">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div>
                <h4 class="mb-0"><?= $total_alarms ?? 0 ?></h4>
                <small>Today's Alarms</small>
              </div>
              <div class="align-self-center">
                <i class="bi bi-exclamation-triangle fs-1"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Enhanced Channels Table -->
    <div class="row">
      <div class="col-12">
        <div class="card">
          
          <div class="card-body table-responsive">
            <table class="table table-striped table-bordered" id="channelsTable">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th><?php echo lang('name'); ?></th>
                  <th><?php echo lang('type'); ?></th>
                  <th><?php echo lang('status'); ?></th>
                  <th>Connected Devices</th>
                  <th><?php echo lang('action'); ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($channels as $i => $c): ?>
                  <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                      <strong><?= $c->name ?></strong>
                      <?php if ($c->description): ?>
                        <br><small class="text-muted"><?= $c->description ?></small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="badge bg-info">
                        <i class="bi bi-<?= getChannelIcon($c->type) ?>"></i>
                        <?= strtoupper($c->type) ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge <?= $c->is_active == 't' ? 'bg-success' : 'bg-secondary' ?>">
                        <i class="bi bi-<?= $c->is_active == 't' ? 'check-circle' : 'x-circle' ?>"></i>
                        <?= $c->is_active=='t' ? 'Active' : 'Inactive' ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge bg-primary"><?= getConnectedDevices($c->id) ?? 0 ?> devices</span>
                      <button class="btn btn-sm btn-outline-info ms-1" onclick="showDeviceList(<?= $c->id ?>)">
                        <i class="bi bi-eye"></i>
                      </button>
                    </td>
                    <td>
                      <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-warning" 
                                onclick="openEditModal(<?= $c->id ?>)" title="Edit">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-info" 
                                onclick="testChannel(<?= $c->id ?>)" title="Test Channel">
                          <i class="bi bi-play-circle"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" 
                                onclick="showMessageHistory(<?= $c->id ?>)" title="Message History">
                          <i class="bi bi-clock-history"></i>
                        </button>
                        <a href="<?= base_url('notification_manager/delete/' . $c->id) ?>" 
                           class="btn btn-sm btn-danger" 
                           onclick="return confirmDelete('<?= $c->name ?>')" title="Delete">
                          <i class="bi bi-trash"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!--end::App Content-->

<!-- Device List Modal -->
<div class="modal fade" id="deviceListModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Connected Devices</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="deviceListContent">
        <!-- Content will be loaded here -->
      </div>
    </div>
  </div>
</div>

<!-- Message History Modal -->
<div class="modal fade" id="messageHistoryModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Message History</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="messageHistoryContent">
        <!-- Content will be loaded here -->
      </div>
    </div>
  </div>
</div>

<!-- Test Channel Modal -->
<div class="modal fade" id="testChannelModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Test Channel</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="testChannelForm">
          <div class="mb-3">
            <label class="form-label">Test Message</label>
            <textarea class="form-control" id="testMessage" rows="3" 
                      placeholder="Enter test message...">Test notification from JConnect System</textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Message Template Preview</label>
            <div class="alert alert-info" id="templatePreview">
              <!-- Template preview will be shown here -->
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="sendTestMessage()">Send Test</button>
      </div>
    </div>
  </div>
</div>

<!-- Add/Edit Channel Modal -->
<div class="modal fade" id="channelModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="channelModalTitle">Add Channel</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="channelForm">
          <input type="hidden" id="channelId" name="id">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Name *</label>
                <input type="text" class="form-control" id="channelName" name="name" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Type *</label>
                                        <select class="form-select" id="channelType" name="type" required onchange="updateConfigFields()">
                            <option value="">Select Type</option>
                            <option value="email">Email</option>
                            <option value="line_oa">LINE Official Account</option>
                            <option value="telegram">Telegram</option>

                        </select>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea class="form-control" id="channelDescription" name="description" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Message Template</label>
            <textarea class="form-control" id="channelTemplate" name="message_template" rows="3" 
                      placeholder="Available placeholders: {DEVICE_NAME}, {METRIC_NAME}, {VALUE}, {UNIT}, {LOCATION}, {TIMESTAMP}, {SEVERITY}"></textarea>
          </div>
          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="channelActive" name="is_active" value="1" checked>
              <label class="form-check-label">Active</label>
            </div>
          </div>
          <div id="configFields">
            <!-- Dynamic config fields will be loaded here -->
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveChannel()">Save Channel</button>
      </div>
    </div>
  </div>
</div>

<script>
// Initialize DataTable
$(document).ready(function() {
    $('#channelsTable').DataTable({
        responsive: true,
        order: [[0, 'asc']]
    });
});

// Helper function to get channel icon
function getChannelIcon(type) {
    const icons = {
        'email': 'envelope',
        'line_oa': 'chat-dots',
        'telegram': 'telegram',
        'webhook': 'link'
    };
    return icons[type] || 'bell';
}

// Show device list for a channel
function showDeviceList(channelId) {
    $.get('<?= base_url("notification_manager/get_devices/") ?>' + channelId, function(data) {
        $('#deviceListContent').html(data);
        $('#deviceListModal').modal('show');
    });
}

// Show message history for a channel
function showMessageHistory(channelId) {
    $.get('<?= base_url("notification_manager/get_message_history/") ?>' + channelId, function(data) {
        $('#messageHistoryContent').html(data);
        $('#messageHistoryModal').modal('show');
    });
}

// Test channel functionality
function testChannel(channelId) {
    $('#testChannelModal').data('channel-id', channelId);
    $('#testChannelModal').modal('show');
    // Load channel template preview
    $.get('<?= base_url("notification_manager/get_channel_template/") ?>' + channelId, function(data) {
        $('#templatePreview').html(data);
    });
}

// Send test message
function sendTestMessage() {
    const message = $('#testMessage').val();
    const channelId = $('#testChannelModal').data('channel-id');
    
    if (!channelId) {
        showNotification('Error: Channel ID not found. Please try again.', 'danger');
        return;
    }
    
    $.post('<?= base_url("notification_manager/send_test/") ?>' + channelId, {
        message: message
    })
    .done(function(response) {
        if (response.success) {
            showNotification('Test message sent successfully!', 'success');
            $('#testChannelModal').modal('hide');
        } else {
            showNotification('Failed to send test message: ' + (response.error || 'Unknown error'), 'danger');
        }
    })
    .fail(function(xhr, status, error) {
        console.error('AJAX Error:', status, error);
        showNotification('Error sending test message. Please try again.', 'danger');
    });
}

// Open Add Channel Modal
function openAddModal() {
    $('#channelModalTitle').text('Add Channel');
    $('#channelForm')[0].reset();
    $('#channelId').val('');
    $('#configFields').html('');
    
    // Enable Type field when adding new channel
    $('#channelType').prop('disabled', false);
    
    $('#channelModal').modal('show');
}

// Open Edit Channel Modal
function openEditModal(channelId) {
    $('#channelModalTitle').text('Edit Channel');
    
    // Load channel data
    $.get('<?= base_url("notification_manager/get_channel_data/") ?>' + channelId)
        .done(function(data) {
            try {
                // Handle both string and object responses
                const channel = typeof data === 'string' ? JSON.parse(data) : data;
                
                $('#channelId').val(channel.id);
                $('#channelName').val(channel.name);
                $('#channelType').val(channel.type);
                $('#channelDescription').val(channel.description);
                $('#channelTemplate').val(channel.message_template);
                $('#channelActive').prop('checked', channel.is_active === 't');
                
                // Update config fields first
                updateConfigFields();
                
                // Disable Type field when editing (after config fields are loaded)
                $('#channelType').prop('disabled', true);
                
                // Load config values if they exist
                if (channel.config) {
                    try {
                        const config = JSON.parse(channel.config);
                        // Map legacy keys to new keys for email channel
                        if(channel.type === 'email') {
                            if(config['recipient'] && !config['to']) config['to'] = config['recipient'];
                            if(config['username'] && !config['smtp_user']) config['smtp_user'] = config['username'];
                            if(config['password'] && !config['smtp_pass']) config['smtp_pass'] = config['password'];
                        }
                        setTimeout(() => {
                            Object.keys(config).forEach(key => {
                                const field = $(`[name="config[${key}]"]`);
                                if (field.length > 0) {
                                    field.val(config[key]);
                                } else {
                                    console.log('Field not found:', `config[${key}]`);
                                }
                            });
                        }, 100);
                    } catch (configError) {
                        console.error('Error parsing config:', configError);
                    }
                }
                
                $('#channelModal').modal('show');
            } catch (parseError) {
                console.error('Error parsing channel data:', parseError);
                showNotification('Error loading channel data. Please try again.', 'danger');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            showNotification('Error loading channel data. Please try again.', 'danger');
        });
}

// Update config fields based on channel type
function updateConfigFields() {
    const type = $('#channelType').val();
    let html = '';
    
    switch(type) {
        case 'email':
            html = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" name="config[smtp_host]" placeholder="smtp.gmail.com">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">SMTP Port</label>
                            <input type="number" class="form-control" name="config[smtp_port]" placeholder="587">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">SMTP Secure</label>
                            <select class="form-select" name="config[smtp_secure]">
                                <option value="false">No (STARTTLS)</option>
                                <option value="true">Yes (SSL/TLS)</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">SMTP Username</label>
                            <input type="email" class="form-control" name="config[smtp_user]" placeholder="your@email.com">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">SMTP Password</label>
                            <input type="password" class="form-control" name="config[smtp_pass]">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">From Email (Sender)</label>
                            <input type="email" class="form-control" name="config[from]" placeholder="sender@email.com">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Email Subject</label>
                            <input type="text" class="form-control" name="config[subject]" placeholder="Alarm Notification" value="Alarm Notification">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Recipient Email</label>
                    <textarea class="form-control" name="config[to]" rows="3" placeholder="recipient1@email.com&#10;recipient2@email.com&#10;recipient3@email.com"></textarea>
                    <small class="form-text text-muted">ใส่หลาย email โดยใช้เครื่องหมายจุลภาค (,) หรือขึ้นบรรทัดใหม่ เช่น: user1@example.com, user2@example.com</small>
                </div>
            `;
            break;

        case 'line_oa':
            html = `
                <div class="mb-3">
                    <label class="form-label">Access Token</label>
                    <input type="text" class="form-control" name="config[access_token]" placeholder="Your LINE channel access token">
                </div>
                <div class="mb-3">
                    <label class="form-label">To (User/Group ID)</label>
                    <input type="text" class="form-control" name="config[to]" placeholder="Target user ID or group ID">
                </div>
            `;
            break;
        case 'webhook':
            html = `
                <div class="mb-3">
                    <label class="form-label">Webhook URL</label>
                    <input type="url" class="form-control" name="config[url]" placeholder="https://your-webhook-url.com">
                </div>
            `;
            break;
        case 'telegram':
            html = `
                <div class="mb-3">
                    <label class="form-label">Bot Token</label>
                    <input type="text" class="form-control" name="config[bot_token]" placeholder="Your bot token">
                </div>
                <div class="mb-3">
                    <label class="form-label">Chat ID</label>
                    <input type="text" class="form-control" name="config[chat_id]" placeholder="Target chat ID">
                </div>
            `;
            break;
    }
    
    $('#configFields').html(html);
}

// Validate and format email addresses
function validateEmails(emailString) {
    if (!emailString) return { isValid: true, emails: [] };
    
    // Split by comma and newline, then clean up
    const emails = emailString
        .split(/[,\n]/)
        .map(email => email.trim())
        .filter(email => email);
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    const invalidEmails = emails.filter(email => !emailRegex.test(email));
    
    return {
        isValid: invalidEmails.length === 0,
        emails: emails,
        invalidEmails: invalidEmails
    };
}

// Save channel (Add or Edit)
function saveChannel() {
    // Enable type field before submit to ensure value is sent
    $('#channelType').prop('disabled', false);
    const formData = new FormData($('#channelForm')[0]);
    const channelId = $('#channelId').val();
    
    // Validate emails if it's an email channel
    const channelType = $('#channelType').val();
    if (channelType === 'email') {
        const recipientField = $('[name="config[to]"]');
        const emailValidation = validateEmails(recipientField.val());
        
        if (!emailValidation.isValid) {
            showNotification('Invalid email format: ' + emailValidation.invalidEmails.join(', '), 'warning');
            return;
        }
        
        // Update form data with cleaned email list
        formData.set('config[to]', emailValidation.emails.join(', '));
    }
    
    const url = channelId ? 
        '<?= base_url("notification_manager/update_channel/") ?>' + channelId :
        '<?= base_url("notification_manager/create_channel") ?>';
    
    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            console.log('Save response:', response);
            if (response.success) {
                showNotification(channelId ? 'Channel updated successfully!' : 'Channel created successfully!', 'success');
                $('#channelModal').modal('hide');
                location.reload(); // Reload page to show updated data
            } else {
                showNotification('Error: ' + (response.error || 'Unknown error'), 'danger');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
            console.error('Response Text:', xhr.responseText);
            showNotification('An error occurred. Please try again. Check console for details.', 'danger');
        }
    });
}

// Custom confirmation for delete
function confirmDelete(channelName) {
    return confirm('Are you sure you want to delete the channel "' + channelName + '"? This action cannot be undone.');
}
</script> 