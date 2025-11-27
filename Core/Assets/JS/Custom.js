/* ============================
   Utilidades: spinner y toasts
   ============================ */

/**
 * Controla el estado visual de acciones que muestran un spinner.
 * @param {string} animation - 'add' para activar, 'remove' para desactivar
 * @param {boolean|null} result - resultado opcional para mostrar toast final
 */
function animateSpinner(animation, result = null) {
    if (animation === 'add') {
        // Bloquear botones para evitar múltiples envíos
        $('button.btn-spin-action').attr('disabled', true);
        $('a.btn-spin-action').addClass('disabled').attr('aria-disabled', 'true');

        // Mostrar toast spinner (setToast es una dependencia externa)
        try {
            setToast('', 'spinner', '', 0);
        } catch (e) {
            // Si setToast no existe, no interrumpir la ejecución
            console && console.warn && console.warn('setToast no disponible:', e);
        }
        return;
    }

    if (animation === 'remove') {
        // Restaurar botones
        $('button.btn-spin-action').removeAttr('disabled');
        $('a.btn-spin-action').removeClass('disabled').attr('aria-disabled', 'false');

        // Eliminar toasts relacionados si existen
        $('#messages-toasts .toast-spinner, #messages-toasts .toast-completed').remove();

        // Si no se pasa resultado, terminar aquí
        if (result === null) {
            return;
        }

        // Mostrar toast según resultado
        try {
            if (result) {
                setToast('', 'completed', '', 3000);
            } else {
                setToast('', 'danger', '', 0);
            }
        } catch (e) {
            console && console.warn && console.warn('setToast no disponible:', e);
        }
    }
}

/* ============================
   Modal dinámico de confirmación
   ============================ */

/**
 * Crea y muestra un modal de confirmación que envía un formulario.
 * @param {string} viewName - sufijo del id del formulario (form{viewName})
 * @param {string} action - valor a asignar en input[name="action"]
 * @param {string} title - título del modal (HTML seguro asumido)
 * @param {string} message - cuerpo del modal (HTML seguro asumido)
 * @param {string} cancel - texto del botón cancelar
 * @param {string} confirm - texto del botón confirmar
 */
function confirmAction(viewName, action, title, message, cancel, confirm) {
    // Eliminar modal previo si existe para evitar duplicados
    const existing = document.getElementById('dynamicConfirmActionModal');
    if (existing) {
        existing.remove();
    }

    // Construir HTML del modal (se asume que title/message vienen del servidor y son seguros)
    const modalHTML = `
    <div class="modal fade" id="dynamicConfirmActionModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="dynamicConfirmActionModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="dynamicConfirmActionModalLabel">${title}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            ${message}
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-spin-action" data-bs-dismiss="modal">${cancel}</button>
            <button type="button" id="saveDynamicConfirmActionModalBtn" class="btn btn-danger btn-spin-action">${confirm}</button>
          </div>
        </div>
      </div>
    </div>
    `;

    // Insertar el modal en el body de forma segura
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Inicializar y mostrar el modal con Bootstrap 5
    const modalEl = document.getElementById('dynamicConfirmActionModal');
    if (!modalEl) {
        return;
    }
    const bsModal = new bootstrap.Modal(modalEl);
    bsModal.show();

    // Manejar click en el botón de confirmación
    const saveBtn = document.getElementById('saveDynamicConfirmActionModalBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            try {
                const form = document.getElementById('form' + viewName);
                if (!form) {
                    // Si no existe el formulario, cerrar modal y salir silenciosamente
                    bsModal.hide();
                    return;
                }

                const actionInput = form.querySelector('input[name="action"]');
                if (actionInput) {
                    actionInput.value = action;
                }

                // Enviar el formulario
                form.submit();
            } catch (e) {
                // No propagar errores al usuario desde aquí
                console && console.warn && console.warn('Error al procesar confirmAction:', e);
            } finally {
                // Cerrar el modal
                bsModal.hide();
            }
        });
    }

    // Al cerrarse el modal, eliminarlo del DOM para limpieza
    modalEl.addEventListener('hidden.bs.modal', function () {
        const node = document.getElementById('dynamicConfirmActionModal');
        if (node) {
            node.remove();
        }
    });
}

/* ============================
   Helpers: pasar datos del formulario padre al modal
   ============================ */

/**
 * Añade inputs ocultos al contenedor del modal con datos del formulario origen.
 * @param {string} modal - id del elemento modal (sin '#')
 * @param {HTMLFormElement} form - referencia al formulario origen
 */
function setModalParentForm(modal, form) {
    try {
        const container = document.getElementById(modal);
        if (!container) {
            return;
        }

        // Si el formulario tiene un campo 'code', añadirlo como hidden al contenedor del modal
        if (form && form.code) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'code';
            input.value = form.code.value;
            // Añadir al padre del modal (mantener comportamiento original)
            $('#' + modal).parent().append(input);
            return;
        }

        // Si el formulario maneja múltiples códigos (codes[]), recoger los checkboxes marcados
        if (form && form.elements && form.elements['codes[]']) {
            const codes = [];
            const checkboxes = document.querySelectorAll('input[name="codes[]"]:checked');
            checkboxes.forEach((cb) => {
                codes.push(cb.value);
            });

            // Añadir un input hidden por cada código al contenedor del modal
            codes.forEach((code) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'codes[]';
                input.value = code;
                $('#' + modal).parent().append(input);
            });

            // Log para depuración
            console && console.log && console.log('codes added to modal:', codes);
        }
    } catch (e) {
        console && console.warn && console.warn('setModalParentForm error:', e);
    }
}

/* ============================
   Inicialización DOM al cargar la página
   ============================ */

$(document).ready(function () {
    // Filas clicables: soporta click izquierdo y click medio (abrir en nueva pestaña)
    $('.clickableRow').on('mousedown', function (event) {
        const which = event.which;
        if (which !== 1 && which !== 2) {
            return;
        }

        const $this = $(this);
        const href = $this.attr('data-href');
        const target = $this.attr('data-bs-target');

        if (typeof href !== 'undefined' && href !== false && href !== '') {
            if (typeof target !== 'undefined' && target === '_blank') {
                window.open(href);
            } else if (which === 2) {
                // Click medio: abrir en nueva ventana
                window.open(href);
            } else {
                // Navegar en el contexto padre
                try {
                    parent.document.location = href;
                } catch (e) {
                    // Fallback si no se puede acceder a parent
                    window.location.href = href;
                }
            }
        }
    });

    // Elementos que cancelan la acción: evitar que el evento burbujee a la fila padre
    $('.cancelClickable').on('mousedown', function (event) {
        event.preventDefault();
        event.stopPropagation();
    });

    // Corrección para submenús en dropdowns: evitar que el click cierre el menú padre
    $(document).on('click', 'nav .dropdown-submenu', function (e) {
        e.stopPropagation();
    });

    // Enfocar automáticamente el primer elemento con [autofocus] dentro de modales mostrados
    $(document).on('shown.bs.modal', '.modal', function () {
        const $modal = $(this);
        const $auto = $modal.find('[autofocus]').first();
        if ($auto && $auto.length) {
            $auto.focus();
        }
    });
});