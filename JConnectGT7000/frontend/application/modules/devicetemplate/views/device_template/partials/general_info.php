<div class="card card-primary">
          <div class="card-header">
            <h5 class="mb-0"><?php echo lang('general_information') ?> </h5>
          </div>
          <div class="card-body">
            <label for="image" class="form-label"><?php echo lang('image') ?></label>
            
            <?php if (!empty($template->image)): ?>
              <div class="mt-2">
                <img src="<?= base_url('uploads/device_templates/' . $template->image) ?>" alt="Current Image" style="max-width: 200px;">
              </div>
            <?php endif; ?>
            <input type="file" class="form-control" name="image" id="image" accept="image/*">
            <div class="mb-3">
              <label for="name" class="form-label"> <?php echo lang('template_name') ?></label>
              <input type="text" class="form-control" name="name" id="name" required
                value="<?= isset($template) ? htmlspecialchars($template->name) : '' ?>">
            </div>

            <div class="mb-3">
              <label for="description" class="form-label"><?php echo lang('description') ?></label>
              <textarea class="form-control" name="description" id="description" rows="3"><?= isset($template) ? htmlspecialchars($template->description) : '' ?></textarea>
            </div>

            <div class="mb-3">
              <label for="protocol" class="form-label"><?php echo lang('protocol') ?></label>
              <select class="form-select" name="protocol" id="protocol" required>
                <option value=""><?php echo lang('select_protocol') ?></option>
                <?php
                $protocols = [
                  'modbus_tcp', 'modbus_rtu', 'modbus_router',
                  'snmp', 'mqtt',
                  'bacnet_ip', 'bacnet_mstp', 'bacnet_router',
                  'restful', 'webhook', 'lorawan',
                  'gateway_endpoint', 'visual', 'rtsp'
                ];
                foreach ($protocols as $p) {
                  $selected = isset($template) && $template->protocol === $p ? 'selected' : '';
                  echo "<option value='$p' $selected>" . strtoupper(str_replace('_', ' ', $p)) . "</option>";
                }
                ?>
              </select>
            </div>

            <?php
              $taxonomy = isset($dcim_taxonomy) && is_array($dcim_taxonomy) ? $dcim_taxonomy : [];
              $defaultCategory = isset($selected_dcim_category)
                ? $selected_dcim_category
                : ($taxonomy[0]['category'] ?? '');
              $postedCategory = set_value('dcim_category', $defaultCategory);
              $selectedTaxonomy = $taxonomy[0] ?? ['subs' => []];
              foreach ($taxonomy as $taxonomyEntry) {
                  if ($taxonomyEntry['category'] === $postedCategory) {
                    $selectedTaxonomy = $taxonomyEntry;
                    break;
                  }
              }
              $defaultSubCategory = isset($selected_dcim_sub_category)
                ? $selected_dcim_sub_category
                : ($selectedTaxonomy['subs'][0] ?? '');
              $postedSubCategory = set_value('dcim_sub_category', $defaultSubCategory);
            ?>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="dcim_category" class="form-label">Category</label>
                  <select class="form-select" name="dcim_category" id="dcim_category" required>
                    <?php foreach ($taxonomy as $taxonomyEntry): ?>
                      <option value="<?= htmlspecialchars($taxonomyEntry['category']) ?>"
                              <?= $taxonomyEntry['category'] === $postedCategory ? 'selected' : '' ?>>
                        <?= htmlspecialchars($taxonomyEntry['category']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="dcim_sub_category" class="form-label">Sub-Category</label>
                  <select class="form-select" name="dcim_sub_category" id="dcim_sub_category" required>
                    <?php foreach ($selectedTaxonomy['subs'] as $subCategory): ?>
                      <option value="<?= htmlspecialchars($subCategory) ?>"
                              <?= $subCategory === $postedSubCategory ? 'selected' : '' ?>>
                        <?= htmlspecialchars($subCategory) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text">
                    Will be saved as <code id="dcim-category-label"></code>
                  </div>
                </div>
              </div>

              <script>
              (function () {
                const taxonomy = <?= json_encode($taxonomy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                window.dcimTaxonomy = taxonomy;
                const categorySelect = document.getElementById('dcim_category');
                const subCategorySelect = document.getElementById('dcim_sub_category');
                const categoryLabel = document.getElementById('dcim-category-label');

                function selectedTaxonomy() {
                  return taxonomy.find(function (entry) {
                    return entry.category === categorySelect.value;
                  }) || taxonomy[0] || { subs: [] };
                }

                function updateLabel() {
                  categoryLabel.textContent = categorySelect.value + ' / ' + subCategorySelect.value;
                }

                function populateSubCategories(keepCurrent) {
                  const current = keepCurrent ? subCategorySelect.value : '';
                  const entry = selectedTaxonomy();
                  subCategorySelect.innerHTML = '';
                  entry.subs.forEach(function (name) {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    if (name === current) option.selected = true;
                    subCategorySelect.appendChild(option);
                  });
                  updateLabel();
                  subCategorySelect.dispatchEvent(new Event('dcim-category-changed'));
                }

                categorySelect.addEventListener('change', function () {
                  populateSubCategories(false);
                });
                subCategorySelect.addEventListener('change', updateLabel);
                updateLabel();
              })();
              </script>
          </div>
        </div>
