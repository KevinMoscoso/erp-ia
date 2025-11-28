/* ============================
   Control de frecuencia para inputs de texto
   ============================ */
let waitSelectCounter = 0;

/* ============================
   Obtener valor del campo padre según su tipo
   ============================ */
function getValueTypeParent(parent) {
    if (parent.is('select')) {
        return parent.find('option:selected').val();
    } else if (parent.attr('type') === 'checkbox' && parent.prop('checked')) {
        return parent.val();
    } else if (parent.attr('type') === 'radio') {
        return parent.find(':checked').val();
    } else if (parent.is('input') || parent.is('textarea')) {
        return parent.val();
    }
    return '';
}

/* ============================
   Cargar opciones en el select dependiente desde servidor
   ============================ */
function widgetSelectGetData(select, parent) {
    select.html('');

    const payload = {
        action: 'select',
        activetab: select.closest('form').find('input[name="activetab"]').val(),
        field: select.attr('data-field'),
        fieldcode: select.attr('data-fieldcode'),
        fieldfilter: select.attr('data-fieldfilter'),
        fieldtitle: select.attr('data-fieldtitle'),
        required: select.attr('required') === 'required' ? 1 : 0,
        source: select.attr('data-source'),
        term: getValueTypeParent(parent)
    };

    $.ajax({
        method: 'POST',
        url: window.location.href,
        data: payload,
        dataType: 'json',
        success: function (results) {
            select.html('');
            results.forEach(function (element) {
                const isSelected = (element.key == select.attr('value')) ? 'selected' : '';
                const key = (element.key == null) ? '' : element.key;
                select.append('<option value="' + key + '" ' + isSelected + '>' + element.value + '</option>');
            });
            select.change();
        },
        error: function (xhr) {
            alert(xhr.status + ' ' + xhr.responseText);
        }
    });
}

/* ============================
   Inicialización al cargar la página
   ============================ */
$(document).ready(function () {
    // Activar select2 en selects marcados
    $('select.select2').select2({
        width: 'style',
        theme: 'bootstrap-5'
    });

    // Configurar selects dependientes de un campo padre
    $('.parentSelect').each(function () {
        const parentStr = $(this).attr('parent');
        if (parentStr === 'undefined' || parentStr === false || parentStr === '') {
            return;
        }

        const select = $(this);
        const parent = select.closest('form').find('[name="' + parentStr + '"]');

        if (parent.is('select') || ['color', 'datetime-local', 'date', 'time'].includes(parent.attr('type'))) {
            parent.change(function () {
                widgetSelectGetData(select, parent);
            });
        } else if (parent.attr('type') === 'hidden') {
            const hiddenInput = document.querySelector("[name='" + parentStr + "']");
            hiddenInput.addEventListener('change', function () {
                widgetSelectGetData(select, parent);
            });

            let previousValue = hiddenInput.value;

            // Observar cambios en el valor del input oculto
            const observer = new MutationObserver((mutations) => {
                mutations.forEach(mutation => {
                    if (
                        mutation.type === 'attributes' &&
                        mutation.attributeName === 'value' &&
                        hiddenInput.value !== previousValue
                    ) {
                        previousValue = hiddenInput.value;
                        hiddenInput.dispatchEvent(new Event('change'));
                    }
                });
            });
            observer.observe(hiddenInput, { attributes: true });
        } else if (parent.is('input') || parent.is('textarea')) {
            parent.keyup(async function () {
                // Debounce: procesar solo la última pulsación
                waitSelectCounter++;
                const waitNum = waitSelectCounter;
                await new Promise(r => setTimeout(r, 500));
                if (waitNum < waitSelectCounter) {
                    return false;
                }
                widgetSelectGetData(select, parent);
            });
        }

        // Carga inicial si el padre existe
        if (parent.length > 0) {
            widgetSelectGetData(select, parent);
        }
    });
});
