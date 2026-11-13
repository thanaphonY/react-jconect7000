 <!-- Metrics Module Device Template -->
 <div class="card mt-3">
   <div class="card-header bg-success">
     <h5 class="mb-0 text-white"><?php echo lang('metrics_definition') ?></h5>
   </div>
   <div class="card-body">
     <div class="mb-3">
       <label class="form-label"><?php echo lang('metrics') ?></label>
       <div id="metrics-container"></div>
       <button type="button" class="btn btn-outline-primary mt-2" onclick="addMetric()"><?php echo lang('add_metrics') ?> </button>
       <input type="hidden" name="metrics" id="metrics_json">
     </div>
   </div>
 </div>

 <script>
   let metrics = [];

   const protocolFields = {
     modbus_tcp: ['address', 'type'],
     modbus_rtu: ['address', 'type'],
     snmp: ['oid', 'type'],
     mqtt: ['topic', 'json_path'],
     bacnet: ['object_id', 'property_id']
   };

   function renderMetrics() {
     const container = document.getElementById('metrics-container');
     const globalProtocol = document.getElementById('protocol')?.value || 'modbus_tcp';
     container.innerHTML = '';

     metrics.forEach((m, i) => {
       m.protocol = globalProtocol;
       const dynamicFields = protocolFields[globalProtocol] || [];

       let html = `

  <div class="row mb-2">
    <div class="col-md-3">
      <input class="form-control" placeholder="Trigger Operator" value="${m.trigger?.operator || ''}" onchange="updateMetricTrigger(${i}, 'operator', this.value)">
    </div>
    <div class="col-md-3">
      <input class="form-control" placeholder="Trigger Value" value="${m.trigger?.value || ''}" onchange="updateMetricTrigger(${i}, 'value', this.value)">
    </div>
    <div class="col-md-3">
      <input class="form-control" placeholder="Duration (e.g. 5m)" value="${m.trigger?.duration || ''}" onchange="updateMetricTrigger(${i}, 'duration', this.value)">
    </div>
    <div class="col-md-3">
      <input class="form-control" placeholder="Severity" value="${m.trigger?.severity || ''}" onchange="updateMetricTrigger(${i}, 'severity', this.value)">
    </div>
  </div>
  <div class="row mb-2">
    <div class="col-12">
      <input class="form-control" placeholder="Alarm Message" value="${m.trigger?.message || ''}" onchange="updateMetricTrigger(${i}, 'message', this.value)">
    </div>
  </div>

      <div class="border rounded p-3 mb-3" data-index="${i}">
        <div class="row mb-2">
          <div class="col-md-4"><input class="form-control" placeholder="Key" value="${m.key || ''}" onchange="updateMetric(${i}, 'key', this.value)"></div>
          <div class="col-md-4"><input class="form-control" placeholder="Label" value="${m.label || ''}" onchange="updateMetric(${i}, 'label', this.value)"></div>
          <div class="col-md-4"><input class="form-control" placeholder="Unit" value="${m.unit || ''}" onchange="updateMetric(${i}, 'unit', this.value)"></div>
        </div>

        <div class="row mb-2">
          <div class="col-md-4">
            <input class="form-control" placeholder="Datatype (int/float)" value="${m.datatype || ''}" onchange="updateMetric(${i}, 'datatype', this.value)">
          </div>
          <div class="col-md-4">
            <input class="form-control" placeholder="Scale" value="${m.scale || 1}" onchange="updateMetric(${i}, 'scale', parseFloat(this.value))">
          </div>
        </div>
    `;

       if (
         (globalProtocol === 'modbus_tcp' || globalProtocol === 'modbus_rtu') ||
         (globalProtocol === 'snmp' && m.type !== 'trap') ||
         globalProtocol === 'bacnet'
       ) {
         html += `
        <div class="row mb-2">
          <div class="col-md-4">
            <input class="form-control metric-pull-rate" placeholder="Pull rate (ms)" value="${m.pull_rate || 10000}" onchange="updateMetric(${i}, 'pull_rate', parseInt(this.value))">
          </div>
        </div>
      `;
       }

       dynamicFields.forEach(field => {
         html += `
        <div class="row mb-2">
          <div class="col-md-6">
            <input class="form-control" placeholder="${field}" value="${m[field] || ''}" onchange="updateMetric(${i}, '${field}', this.value)">
          </div>
        </div>
      `;
       });



       html += `
        <div class="text-end">
          <button type="button" class="btn btn-danger btn-sm" onclick="removeMetric(${i})">Remove</button>
        </div>
      </div>
    `;

       container.innerHTML += html;
     });

     updateMetricsJSON();
   }

   function updateMetric(index, field, value) {
     metrics[index][field] = value;
     updateMetricsJSON();
   }

   function removeMetric(index) {
     metrics.splice(index, 1);
     renderMetrics();
   }

   function addMetric() {
     const globalProtocol = document.getElementById('protocol')?.value || 'modbus_tcp';
     const defaultFields = {
       modbus_tcp: {
         address: '',
         type: 'holding_register'
       },
       modbus_rtu: {
         address: '',
         type: 'holding_register'
       },
       snmp: {
         oid: '',
         type: 'snmp_get'
       },
       mqtt: {
         topic: '',
         json_path: ''
       },
       bacnet: {
         object_id: '',
         property_id: ''
       }
     };

     const newMetric = {
       key: '',
       label: '',
       unit: '',
       protocol: globalProtocol,
       datatype: 'int',
       scale: 1,
       pull_rate: (globalProtocol === 'mqtt' || globalProtocol === 'snmp') ? 0 : 10000,
       ...defaultFields[globalProtocol]
     };

     metrics.push(newMetric);
     renderMetrics();
   }

   function updateMetricsJSON() {
     document.getElementById('metrics_json').value = JSON.stringify(metrics, null, 2);
   }

   document.addEventListener('DOMContentLoaded', () => {
     metrics = <?= isset($template) && $template->metrics ? json_encode(json_decode($template->metrics), JSON_UNESCAPED_UNICODE) : '[]' ?>;
     renderMetrics();
     document.getElementById('protocol').addEventListener('change', () => {
       renderMetrics();
     });
   });

   function updateMetricTrigger(index, field, value) {
     if (!metrics[index].trigger) {
       metrics[index].trigger = {};
     }
     metrics[index].trigger[field] = value;
     saveMetrics();
   }
 </script>

