<?php
if ($this->db->field_exists('site_node_id', 'devices') && $this->db->table_exists('site_nodes')) {
    $this->load->model('Site_model');
    $siteTree = $this->Site_model->tree();
    $siteOptions = site_select_location_options($siteTree);
    $selectedNode = isset($device->site_node_id) ? (int) $device->site_node_id : 0;
    $selectedPaths = array_column($siteOptions, 'id');
    if ($selectedNode && !in_array($selectedNode, $selectedPaths, true)) {
        $row = $this->db->select('id, name, node_type')->get_where('site_nodes', array('id' => $selectedNode))->row();
        if ($row) {
            $siteOptions[] = array(
                'id' => (int) $row->id,
                'path' => $row->name . ' (' . $row->node_type . ')',
                'type' => $row->node_type,
                'name' => $row->name,
            );
        }
    }
?>
<div class="mb-2">
  <label class="form-label mb-1">Site node</label>
  <select class="form-control site-node-select2" name="site_node_id" id="site_node_id_select"
    data-placeholder="Search by district or path…">
    <option value=""></option>
    <?php foreach ($siteOptions as $opt) { ?>
      <option value="<?php echo (int) $opt['id']; ?>" <?php echo $selectedNode === (int) $opt['id'] ? 'selected' : ''; ?>>
        <?php echo html_escape($opt['path']); ?>
      </option>
    <?php } ?>
  </select>
  <div class="form-text"><?php echo count($siteOptions); ?> locations · type to search</div>
</div>
<script src="<?php echo iaBase(); ?>assets/js/site-node-select.js"></script>
<?php } ?>
