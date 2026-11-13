const MAP_UPLOAD_BASE = '/uploads/maps/';
const MAP_IMAGE_ACCEPT = '.jpg,.jpeg,.png,.bmp,.tif,.tiff,image/jpeg,image/png,image/bmp,image/x-ms-bmp,image/tiff';
const MAP_ALLOWED_IMAGE_TYPES = [
  'image/jpeg',
  'image/png',
  'image/bmp',
  'image/x-ms-bmp',
  'image/tiff'
];
const MAP_ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'bmp', 'tif', 'tiff'];

function validateMapImageFile(file) {
  if (!file) {
    return { ok: false, message: 'No file selected' };
  }

  const ext = (file.name.split('.').pop() || '').toLowerCase();
  const typeOk = !file.type || MAP_ALLOWED_IMAGE_TYPES.includes(file.type);
  const extOk = MAP_ALLOWED_IMAGE_EXTENSIONS.includes(ext);

  if (!typeOk && !extOk) {
    return { ok: false, message: 'Supported formats: JPEG, PNG, BMP, TIFF' };
  }

  return { ok: true };
}

function normalizeMapBackgroundPath(background) {
  if (background === null || background === undefined) return '';

  const value = String(background).trim();
  if (!value) return '';

  if (/^https?:\/\//i.test(value)) return value;

  let path = value.replace(/\\/g, '/');
  if (path.startsWith(MAP_UPLOAD_BASE)) {
    path = path.slice(MAP_UPLOAD_BASE.length);
  } else if (path.startsWith('uploads/maps/')) {
    path = path.slice('uploads/maps/'.length);
  }

  path = path.replace(/^\/+/, '');
  return path ? MAP_UPLOAD_BASE + path : '';
}

function getMapBackgroundFilename(background) {
  const path = normalizeMapBackgroundPath(background);
  if (!path) return '';
  return path.slice(MAP_UPLOAD_BASE.length);
}

function resolveMapImageUrl(background) {
  const path = normalizeMapBackgroundPath(background);
  if (!path) return '';
  if (/^https?:\/\//i.test(path)) return path;
  return path.startsWith('/') ? path : `/${path}`;
}

function appendMapBackgroundFallback(svg) {
  const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
  rect.setAttribute('width', '100%');
  rect.setAttribute('height', '100%');
  rect.setAttribute('fill', '#2b3035');

  const title = document.createElementNS('http://www.w3.org/2000/svg', 'text');
  title.setAttribute('x', '500');
  title.setAttribute('y', '285');
  title.setAttribute('text-anchor', 'middle');
  title.setAttribute('fill', '#adb5bd');
  title.setAttribute('font-size', '22');
  title.textContent = 'No map image';

  const hint = document.createElementNS('http://www.w3.org/2000/svg', 'text');
  hint.setAttribute('x', '500');
  hint.setAttribute('y', '320');
  hint.setAttribute('text-anchor', 'middle');
  hint.setAttribute('fill', '#6c757d');
  hint.setAttribute('font-size', '14');
  hint.textContent = 'Upload a map image above';

  svg.appendChild(rect);
  svg.appendChild(title);
  svg.appendChild(hint);
}

function appendMapBackground(svg, backgroundUrl) {
  const imageUrl = resolveMapImageUrl(backgroundUrl);
  if (!imageUrl || !getMapBackgroundFilename(backgroundUrl)) {
    appendMapBackgroundFallback(svg);
    return false;
  }

  const imgSvg = document.createElementNS('http://www.w3.org/2000/svg', 'image');
  imgSvg.setAttribute('href', imageUrl);
  imgSvg.setAttribute('height', '100%');
  imgSvg.setAttribute('width', '100%');
  imgSvg.setAttribute('x', '0');
  imgSvg.setAttribute('y', '0');
  imgSvg.setAttribute('preserveAspectRatio', 'xMidYMid meet');

  imgSvg.addEventListener('error', () => {
    if (imgSvg.parentNode === svg) {
      imgSvg.remove();
      appendMapBackgroundFallback(svg);
    }
  });

  svg.appendChild(imgSvg);
  return true;
}

function renderMapViewEditor(container, config) {
  container.innerHTML = `
      <div id="map-editor" class="position-relative w-100" style="height:500px; overflow:hidden; height:500px; align-items: center;display: flex;">
      <img id="map-image-preview" class="position-absolute img-fluid" style="max-height: 500px; z-index:1;">
      <svg id="map-edit-area" width="100%" height="400px" preserveAspectRatio="xMidYMid meet"  viewBox="0 0 1000 600" xmlns="http://www.w3.org/2000/svg"></svg>
    </div>
    <div id="map-config-panel" class="border p-3 bg-body text-body" style="display: none;">
      <h6>Edit Item</h6>

      <div class="mb-3 row">
          <label class="col-sm-2 col-form-label">Label</label>
        <div class="col-sm-4">
          <input type="text" id="input-label" class="form-control form-control-sm">
        </div>
        <label class="col-sm-2 col-form-label">Unit</label>
        <div class="col-sm-4">
          <input type="text" id="input-unit" class="form-control form-control-sm">
        </div>
      </div>

      <div class="mb-3 row">
          <label class="col-sm-2 col-form-label">Device</label>
        <div class="col-sm-4">
          <select id="input-device" class="form-select form-select-sm"></select>
        </div>

<label class="col-sm-2 col-form-label">Metric</label>
        <div class="col-sm-4">
          <select id="input-metric" class="form-select form-select-sm"></select>
        </div>
      </div>


      <div class="mb-3 row">
          <label class="col-sm-2 col-form-label">Show</label>
        <div class="col-sm-4">
          <select id="input-show" class="form-select form-select-sm">
            <option value="label">Label</option>
            <option value="value">Value</option>
            <option value="label+value">Label + Value</option>
            <option value="value+unit">Value + Unit</option>
            <option value="label+value+unit">Label + Value + Unit</option>
            <option value="point">Point</option>
          </select>
        </div>
          <label class="col-sm-2 col-form-label">URL</label>
        <div class="col-sm-4">
          <input type="text" id="input-url" class="form-control form-control-sm">
        </div>
      </div>

      <div class="d-flex justify-content-between">
        <button class="btn btn-sm btn-danger" onclick="deleteSelectedItem()">Delete</button>
        <button class="btn btn-sm btn-primary" onclick="applyItemChanges()">Apply</button>
      </div>
    </div>
    `;
}
