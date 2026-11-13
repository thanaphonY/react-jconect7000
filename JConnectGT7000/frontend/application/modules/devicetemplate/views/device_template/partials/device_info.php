 <!-- Device Information -->
 <div class="card mt-3">
   <div class="card-header bg-secondary">
     <h5 class="mb-0 text-white"><?php echo lang('device_information') ?></h5>
   </div>
   <div class="card-body">
     <div class="mb-3">
       <label class="form-label"><?php echo lang('device_information') ?></label>
       <div id="device-info-fields"></div>
       <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addDeviceInfoField()"><?php echo lang('add_field') ?> </button>
       <input type="hidden" name="device_information" id="device_information_json">
     </div>
   </div>
 </div>

<script>
let deviceInfoData = <?= isset($template) && $template->device_information ? json_encode(json_decode($template->device_information), JSON_UNESCAPED_UNICODE) : '{"Brand": "", "Model": "", "Manufacturer": "", "Serial Number": "", "Firmware Version": "", "Hardware Version": ""}' ?>;

function renderDeviceInfoFields() {
  const container = document.getElementById('device-info-fields');
  container.innerHTML = '';

  Object.entries(deviceInfoData).forEach(([key, value], index) => {
    container.innerHTML += `
      <div class="row mb-2" data-index="${index}">
        <div class="col-md-5">
          <input type="text" class="form-control" placeholder="Key" value="${key}" onchange="updateDeviceInfoKey(${index}, this.value)">
        </div>
        <div class="col-md-5">
          <input type="text" class="form-control" placeholder="Value" value="${value}" onchange="updateDeviceInfoValue(${index}, this.value)">
        </div>
        <div class="col-md-2">
          <button type="button" class="btn btn-danger btn-sm" onclick="removeDeviceInfoField(${index})"> <?php echo lang('remove') ?></button>
        </div>
      </div>
    `;
  });

  updateDeviceInfoJSON();
}

function updateDeviceInfoKey(index, newKey) {
  const oldKey = Object.keys(deviceInfoData)[index];
  const value = deviceInfoData[oldKey];
  delete deviceInfoData[oldKey];
  deviceInfoData[newKey] = value;
  renderDeviceInfoFields();
}

function updateDeviceInfoValue(index, newValue) {
  const key = Object.keys(deviceInfoData)[index];
  deviceInfoData[key] = newValue;
  updateDeviceInfoJSON();
}

function removeDeviceInfoField(index) {
  const key = Object.keys(deviceInfoData)[index];
  delete deviceInfoData[key];
  renderDeviceInfoFields();
}

function addDeviceInfoField() {
  let newKey = "key" + (Object.keys(deviceInfoData).length + 1);
  while (deviceInfoData[newKey]) newKey += "_";
  deviceInfoData[newKey] = "";
  renderDeviceInfoFields();
}

function updateDeviceInfoJSON() {
  document.getElementById('device_information_json').value = JSON.stringify(deviceInfoData);
}

// Auto-populate common device info fields
function populateCommonDeviceInfo() {
  const categorySelect = document.getElementById('category_id');
  const dcimCategorySelect = document.getElementById('dcim_category');
  const dcimSubCategorySelect = document.getElementById('dcim_sub_category');
  const category = dcimSubCategorySelect
    ? `${dcimCategorySelect?.value || ''} / ${dcimSubCategorySelect.value}`
    : (categorySelect?.options[categorySelect.selectedIndex]?.text || '');

  if (dcimCategorySelect && dcimSubCategorySelect && Array.isArray(window.dcimTaxonomy)) {
    const taxonomyEntry = window.dcimTaxonomy.find(function (entry) {
      return entry.category === dcimCategorySelect.value;
    });
    if (taxonomyEntry) {
      deviceInfoData.domain = taxonomyEntry.domain;
      deviceInfoData.pue_role = taxonomyEntry.pue_role;
      deviceInfoData.dcim_category = dcimCategorySelect.value;
      deviceInfoData.dcim_sub_category = dcimSubCategorySelect.value;
    }
  }
  
  // Set common fields based on category
  if (category.toLowerCase().includes('ups')) {
    if (!deviceInfoData['Device Type']) deviceInfoData['Device Type'] = 'UPS';
    if (!deviceInfoData['Power Rating']) deviceInfoData['Power Rating'] = '';
    if (!deviceInfoData['Battery Type']) deviceInfoData['Battery Type'] = '';
  } else if (category.toLowerCase().includes('temperature') || category.toLowerCase().includes('humidity')) {
    if (!deviceInfoData['Device Type']) deviceInfoData['Device Type'] = 'Sensor';
    if (!deviceInfoData['Measurement Range']) deviceInfoData['Measurement Range'] = '';
    if (!deviceInfoData['Accuracy']) deviceInfoData['Accuracy'] = '';
  } else if (category.toLowerCase().includes('power')) {
    if (!deviceInfoData['Device Type']) deviceInfoData['Device Type'] = 'Power Meter';
    if (!deviceInfoData['Voltage Rating']) deviceInfoData['Voltage Rating'] = '';
    if (!deviceInfoData['Current Rating']) deviceInfoData['Current Rating'] = '';
  }
  
  renderDeviceInfoFields();
}

document.addEventListener('DOMContentLoaded', function() {
  renderDeviceInfoFields();
  
  // Listen for category changes
  const categorySelect = document.getElementById('category_id');
  const dcimCategorySelect = document.getElementById('dcim_category');
  const dcimSubCategorySelect = document.getElementById('dcim_sub_category');
  if (categorySelect) categorySelect.addEventListener('change', populateCommonDeviceInfo);
  if (dcimCategorySelect) dcimCategorySelect.addEventListener('change', populateCommonDeviceInfo);
  if (dcimSubCategorySelect) {
    dcimSubCategorySelect.addEventListener('change', populateCommonDeviceInfo);
    dcimSubCategorySelect.addEventListener('dcim-category-changed', populateCommonDeviceInfo);
    populateCommonDeviceInfo();
  }
});
</script>
