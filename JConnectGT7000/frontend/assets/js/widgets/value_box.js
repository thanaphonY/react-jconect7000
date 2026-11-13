
function ensureValueBoxPreviewStyles() {
  if (document.getElementById('value-box-preview-styles')) return;
  const s = document.createElement('style');
  s.id = 'value-box-preview-styles';
  s.textContent = `
    .widget-value-box { display:flex; flex-direction:column; justify-content:center; align-items:center; }
    .widget-value-box.has-icon { flex-direction:row; align-items:center; justify-content:center; text-align:left; gap:0.5rem; }
    .widget-value-box.has-icon .widget-value-box-body { flex:0 1 auto; }
    .widget-value-box-icon { font-size:1.8rem; line-height:1; }
    .widget-value-box-icon.effect-spin { animation: vb-icon-spin 2s linear infinite; }
    .widget-value-box-icon.effect-pulse { animation: vb-icon-pulse 1.2s ease-in-out infinite; }
    .widget-value-box-icon.effect-blink { animation: vb-icon-blink 1s step-start infinite; }
    @keyframes vb-icon-spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
    @keyframes vb-icon-pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.12);opacity:.85} }
    @keyframes vb-icon-blink { 0%,100%{opacity:1} 50%{opacity:.25} }
  `;
  document.head.appendChild(s);
}

function renderValueBoxPreview(container, config) {
  ensureValueBoxPreviewStyles();
  const showIcon = config.show_icon === '1' || config.show_icon === 1;
  const iconClass = (config.icon || 'bi-circle').replace(/^bi-?/, '');
  const maps = Array.isArray(config.value_maps) ? config.value_maps : [];
  const firstMap = maps[0] || {};
  const effect = firstMap.icon_effect && firstMap.icon_effect !== 'none' ? ` effect-${firstMap.icon_effect}` : '';
  const iconColor = firstMap.icon_color ? ` style="color:${firstMap.icon_color}"` : '';

  const linkAttrs = config.link_url ? ` style="min-height:80px; cursor:pointer;" title="${String(config.link_url).replace(/"/g, '&quot;')}"` : ' style="min-height:80px;"';
  container.innerHTML = `
    <div class="widget-value-box ${showIcon ? 'has-icon' : ''} text-center"${linkAttrs}>
      ${showIcon ? `<div class="widget-value-box-icon bi bi-${iconClass}${effect}"${iconColor} aria-hidden="true"></div>` : ''}
      <div class="widget-value-box-body">
        <div class="small text-secondary mb-1">${config.label || '--'}</div>
        <div class="fs-5 fw-bold">
          ${firstMap.label || '--'}${config.unit && !firstMap.label ? ` <span class="text-muted">${config.unit}</span>` : ''}
        </div>
      </div>
    </div>
  `;
}
