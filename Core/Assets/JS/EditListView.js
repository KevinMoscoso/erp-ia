/* ============================
   Variables de texto (internacionalizables)
   ============================ */

var editListViewDeleteCancel = "";
var editListViewDeleteConfirm = "";
var editListViewDeleteMessage = "";
var editListViewDeleteTitle = "";

/* ============================
   Modal de confirmación para eliminar elementos
   ============================ */

/**
 * Muestra un modal dinámico para confirmar la eliminación en una EditListView.
 * Mantiene los selectores e IDs esperados por el sistema.
 * @param {string} viewName - sufijo del formulario (form{viewName})
 * @returns {boolean}
 */
function editListViewDelete(viewName) {
    try {
        // Si ya existe, eliminar el modal previo para evitar duplicados
        const prev = document.getElementById('dynamicEditListViewDeleteModal');
        if (prev) {
            prev.remove();
        }

        // Construir el HTML del modal (se asume que los textos provienen del servidor)
        const modalHTML = `
        <div class="modal fade" id="dynamicEditListViewDeleteModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="dynamicEditListViewDeleteModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="dynamicEditListViewDeleteModalLabel">${editListViewDeleteTitle}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                ${editListViewDeleteMessage}
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-spin-action" data-bs-dismiss="modal">${editListViewDeleteCancel}</button>
                <button type="button" id="saveDynamicEditListViewDeleteModalBtn" class="btn btn-danger btn-spin-action">${editListViewDeleteConfirm}</button>
              </div>
            </div>
          </div>
        </div>
        `;

        // Insertar el modal en el body de forma segura
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Inicializar y mostrar el modal con Bootstrap 5
        const modalEl = document.getElementById('dynamicEditListViewDeleteModal');
        if (!modalEl) {
            return false;
        }
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();

        // Asociar acción al botón de confirmación
        const saveBtn = document.getElementById('saveDynamicEditListViewDeleteModalBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                try {
                    // Ejecutar la acción de eliminar en el formulario correspondiente
                    editListViewSetAction(viewName, "delete");
                } catch (e) {
                    if (console && console.warn) {
                        console.warn('editListViewDelete: error al ejecutar la acción', e);
                    }
                } finally {
                    // Cerrar el modal
                    bsModal.hide();
                }
            });
        }

        // Al cerrarse, limpiar el DOM eliminando el modal
        modalEl.addEventListener('hidden.bs.modal', function () {
            const node = document.getElementById('dynamicEditListViewDeleteModal');
            if (node) {
                node.remove();
            }
        });
    } catch (e) {
        if (console && console.warn) {
            console.warn('editListViewDelete error:', e);
        }
    }

    return false;
}

/* ============================
   Helpers para asignar acciones y offsets en formularios
   ============================ */

/**
 * Asigna el valor de action en el formulario correspondiente y lo envía.
 * @param {string} viewName
 * @param {string} value
 */
function editListViewSetAction(viewName, value) {
    try {
        $("#form" + viewName + " :input[name=\"action\"]").val(value);
        $("#form" + viewName).submit();
    } catch (e) {
        if (console && console.warn) {
            console.warn('editListViewSetAction error:', e);
        }
    }
}

/**
 * Ajusta el offset para paginación en el formulario específico y lo envía.
 * @param {string} viewName
 * @param {number|string} value
 */
function editListViewSetOffset(viewName, value) {
    try {
        // Limpiar action en el formulario principal
        $("#form" + viewName + " :input[name=\"action\"]").val('');
        // Asignar offset y enviar el formulario de paginación
        $("#form" + viewName + "Offset :input[name=\"offset\"]").val(value);
        $("#form" + viewName + "Offset").submit();
    } catch (e) {
        if (console && console.warn) {
            console.warn('editListViewSetOffset error:', e);
        }
    }
}

/* ============================
   Inicialización al cargar la página
   ============================ */

$(document).ready(function () {
    try {
        const selected = document.getElementById('EditListViewSelected');
        if (selected !== null) {
            // Desplazar la vista hacia el elemento seleccionado
            selected.scrollIntoView();
        }
    } catch (e) {
        if (console && console.warn) {
            console.warn('EditListView init error:', e);
        }
    }
});