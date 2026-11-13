
function renderValueBox2Preview(container, config) {
    container.innerHTML = `
      <div class="text-center">
        <div class="fs-5 mb-1">${config.label || '--'}</div>
        <div class="fs-5 fw-bold">
          -- <span>${config.unit1 || ''}</span>
          &nbsp;
        ${config.spector}
          &nbsp;
          -- <span>${config.unit2 || ''}</span>
        </div>

      </div>
    `;
  }

  