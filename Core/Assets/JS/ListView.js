/* ============================
   Textos públicos (i18n desde PHP)
   ============================ */
var listViewDeleteCancel = "";
var listViewDeleteConfirm = "";
var listViewDeleteMessage = "";
var listViewDeleteTitle = "";

/* ============================
   Selección masiva de checkboxes en la lista
   ============================ */
function listViewCheckboxes(viewName) {
    try {
        var checked = $("#form" + viewName + " .listActionCB").prop("checked");
        $("#form" + viewName + " .listAction").each(function () {
            $(this).prop("checked", checked);
        });
    } catch (e) {
        if (console && console.warn) {
            console.warn('listViewCheckboxes error:', e);
        }
    }
}

/* ============================
   Modal de confirmación para eliminar registros
   ============================ */
function listViewDelete(viewName) {
    try {
        // Eliminar cualquier modal previo con el mismo ID para evitar duplicados
        const previous = document.getElementById('dynamicListViewDeleteModal');
        if (previous) {
            previous.remove();
        }

        // Construir el modal con Bootstrap 5
        const modalHTML = `
        <div class="modal fade" id="dynamicListViewDeleteModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="dynamicListViewDeleteModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="dynamicConfirmActionModalLabel">${listViewDeleteTitle}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                ${listViewDeleteMessage}
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-spin-action" data-bs-dismiss="modal">${listViewDeleteCancel}</button>
                <button type="button" id="saveDynamicListViewDeleteModalBtn" class="btn btn-danger btn-spin-action">${listViewDeleteConfirm}</button>
              </div>
            </div>
          </div>
        </div>`;

        // Insertar en el body y mostrar
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        const modalEl = document.getElementById('dynamicListViewDeleteModal');
        if (!modalEl) {
            return false;
        }
        const bsModal = new bootstrap.Modal(modalEl);
        bsModal.show();

        // Acción al confirmar
        const confirmBtn = document.getElementById('saveDynamicListViewDeleteModalBtn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                try {
                    listViewSetAction(viewName, "delete");
                } catch (e) {
                    if (console && console.warn) {
                        console.warn('listViewDelete confirm handler error:', e);
                    }
                } finally {
                    bsModal.hide();
                }
            });
        }

        // Limpiar el DOM al cerrarse el modal
        modalEl.addEventListener('hidden.bs.modal', function () {
            const node = document.getElementById('dynamicListViewDeleteModal');
            if (node) {
                node.remove();
            }
        });
    } catch (e) {
        if (console && console.warn) {
            console.warn('listViewDelete error:', e);
        }
    }

    return false;
}

/* ============================
   Mostrar/Ocultar enlaces externos en pestañas
   ============================ */
function listViewOpenTab(viewName) {
    try {
        $("#form" + viewName + " .toggle-ext-link").each(function () {
            const $el = $(this);
            if ($el.hasClass("d-none")) {
                $el.removeClass("d-none");
            } else {
                $el.addClass("d-none");
            }
        });
    } catch (e) {
        if (console && console.warn) {
            console.warn('listViewOpenTab error:', e);
        }
    }
}

/* ============================
   Imprimir/Exportar la lista en nueva pestaña
   ============================ */
function listViewPrintAction(viewName, option) {
    try {
        $("#form" + viewName).attr("target", "_blank");
        $("#form" + viewName + " :input[name=\"action\"]").val('export');
        $("#form" + viewName).append('<input type="hidden" name="option" value="' + option + '"/>');
        $("#form" + viewName).submit();
        $("#form" + viewName + " :input[name=\"action\"]").val('');
        $("#form" + viewName).attr("target", "");
        animateSpinner('remove');
    } catch (e) {
        if (console && console.warn) {
            console.warn('listViewPrintAction error:', e);
        }
    }
}

/* ============================
   Acciones y envíos del formulario de lista
   ============================ */
function listViewSetAction(viewName, value) {
    try {
        $("#form" + viewName + " :input[name=\"action\"]").val(value);
        $("#form" + viewName).submit();
    } catch (e) {
        if (console && console.warn) {
            console.warn('listViewSetAction error:', e);
        }
    }
}

function listViewSetLoadFilter(viewName, value) {
    try {
        $("#form" + viewName + " :input[name=\"loadfilter\"]").val(value);
        $("#form" + viewName).submit();
    } catch (e) {
        if (console && console.warn) {
            console.warn('listViewSetLoadFilter error:', e);
        }
    }
}

function listViewSetOffset(viewName, value) {
    try {
        $("#form" + viewName + " :input[name=\"action\"]").val('');
        $("#form" + viewName + " :input[name=\"offset\"]").val(value);
        $("#form" + viewName).submit();
    } catch (e) {
        if (console && console.warn) {
            console.warn('listViewSetOffset error:', e);
        }
    }
}

function listViewSetOrder(viewName, value) {
    try {
        $("#form" + viewName + " :input[name=\"action\"]").val('');
        $("#form" + viewName + " :input[name=\"order\"]").val(value);
        $("#form" + viewName).submit();
    } catch (e) {
        if (console && console.warn) {
            console.warn('listViewSetOrder error:', e);
        }
    }
}

/* ============================
   Mostrar/Ocultar bloque de filtros
   ============================ */
function listViewShowFilters(viewName) {
    try {
        $("#form" + viewName + "Filters").toggle(500);
    } catch (e) {
        if (console && console.warn) {
            console.warn('listViewShowFilters error:', e);
        }
    }
}

/* ============================
   Interacciones con filas y evitar Enter
   ============================ */
$(document).ready(function () {
    try {
        // Navegación por filas clicables
        $(".clickableListRow").mousedown(function (event) {
            if (event.which === 1 || event.which === 2) {
                var href = $(this).attr("data-href");
                var target = $(this).attr("data-bs-target");

                if (typeof href !== typeof undefined && href !== false) {
                    if (typeof target !== typeof undefined && target === "_blank") {
                        window.open($(this).attr("data-href"));
                    } else if (event.which === 2) {
                        // Alternar visibilidad de enlaces externos en toda la lista
                        $(".toggle-ext-link").each(function () {
                            const $el = $(this);
                            if ($el.hasClass("d-none")) {
                                $el.removeClass("d-none");
                            } else {
                                $el.addClass("d-none");
                            }
                        });
                    } else {
                        parent.document.location = $(this).attr("data-href");
                    }
                }
            }
        });

        // Evitar envío con Enter en campos concretos
        $(".noEnterKey").keypress(function (e) {
            return !(e.which == 13 || e.keyCode == 13);
        });
    } catch (e) {
        if (console && console.warn) {
            console.warn('ListView init error:', e);
        }
    }
});