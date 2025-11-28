/* ============================
   Contador para controlar llamadas en inputs de texto
   ============================ */
let waitDatalistCounter = 0;

/* ============================
   Obtener valor del campo padre según su tipo
   ============================ */
function getValueTypeParent(parent) {
    if (parent.is('select')) {
        return parent.find('option:selected').val();
    }
    if (parent.attr('type') === 'checkbox' && parent.prop("checked")) {
        return parent.val();
    }
    if (parent.attr('type') === 'radio') {
        return parent.find(':checked').val();
    }
    if (parent.is('input') || parent.is('textarea')) {
        return parent.val();
    }
    return '';
}

/* ============================
   Cargar opciones en el datalist desde servidor
   ============================ */
function widgetSelectGetData(input, parent) {
    const datalist = $('#' + input.attr('list'));
    datalist.html('');

    const data = {
        action: 'datalist',
        activetab: input.closest('form').find('input[name="activetab"]').val(),
        field: input.attr("data-field"),
        fieldcode: input.attr("data-fieldcode"),
        fieldfilter: input.attr("data-fieldfilter"),
        fieldtitle: input.attr("data-fieldtitle"),
        required: input.attr('required') === 'required' ? 1 : 0,
        source: input.attr("data-source"),
        term: getValueTypeParent(parent)
    };

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        dataType: "json",
        success: function (results) {
            datalist.html('');
            results.forEach(function (element) {
                datalist.append('<option value="' + element.key + '">' + element.value + '</option>');
            });
            input.change();
        },
        error: function (msg) {
            alert(msg.status + " " + msg.responseText);
        }
    });
}

/* ============================
   Inicialización al cargar la página
   ============================ */
$(document).ready(function () {
    $('.parentDatalist').each(function () {
        const parentStr = $(this).attr('parent');
        if (!parentStr || parentStr === 'undefined' || parentStr === false) {
            return;
        }

        const input = $(this);
        const parent = input.closest('form').find('[name="' + parentStr + '"]');

        if (parent.is('select') || ['color', 'datetime-local', 'date', 'time'].includes(parent.attr('type'))) {
            parent.change(function () {
                widgetSelectGetData(input, parent);
            });
        } else if (parent.attr('type') === 'hidden') {
            const hiddenInput = document.querySelector("[name='" + parentStr + "']");
            hiddenInput.addEventListener('change', function () {
                widgetSelectGetData(input, parent);
            });

            let previousValue = hiddenInput.value;
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
                waitDatalistCounter++;
                const waitNum = waitDatalistCounter;
                await new Promise(r => setTimeout(r, 500));
                if (waitNum < waitDatalistCounter) {
                    return false;
                }
                widgetSelectGetData(input, parent);
            });
        }

        if (parent.length > 0) {
            widgetSelectGetData(input, parent);
        }
    });
});
