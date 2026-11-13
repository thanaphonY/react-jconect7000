
function renderRationPreview(container, config) {
    container.innerHTML = `
       <div class="text-center">
   <div class="small mb-1">${config.label || '--'}</div>
   <div class="fs-2 fw-bold ratio-value">
     --
   </div>
   <div class="small ">${config.unit1 || '--'}/${config.unit2 || '--'}</div>
 </div>
 <hr class="my-3">
 <div class="row text-center">
   <div class="col-sm-6">
     <div class="small text-secondary">${config.label1 || '--'}</div>
     <div class="value text-secondary value1">--</div>
     <div class="small text-secondary">${config.unit1 || '--'}</div>
   </div>
   <div class="col-sm-6">
     <div class="small text-secondary">${config.label2 || '--'}</div>
     <div class="value text-secondary value2">--</div>
     <div class="small text-secondary">${config.unit2 || '--'}</div>
   </div>

 </div>

 <style type="text/css">
.widget-value-box {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  height: 100%;
  overflow: hidden;
  text-align: center;
}


.widget-value-box  .value {
  font-size: clamp(1.5rem, 3vw, 2.5rem);
  font-weight: bold;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.widget-value-box .unit {
  font-size: 1rem;
}

.widget-value-box .time {
  font-size: 0.75rem;
  
  white-space: nowrap;
  text-overflow: ellipsis;
  overflow: hidden;
  max-height: 1.2em;
  flex-shrink: 1;
}
</style>

    `;
  }

  