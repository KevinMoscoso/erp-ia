/* ============================
   Helper: preparar datos para autocomplete
   ============================ */
function listFilterAutocompleteGetData(formId, formData, term) {
    try {
        const rawForm = $("form[id=" + formId + "]").serializeArray();
        rawForm.forEach(function (input) {
            formData[input.name] = input.value;
        });
        formData["action"] = "autocomplete";
        formData["term"] = term;
    } catch (e) {
        if (console && console.warn) {
            console.warn('listFilterAutocompleteGetData error:', e);
        }
    }
    return formData;
}

/* ============================
   Inicialización de autocomplete en filtros de listas
   ============================ */
$(document).ready(function () {
    try {
        $(".filter-autocomplete").each(function () {
            const $field = $(this);

            // Recoger atributos de configuración del campo
            const data = {
                field: $field.attr("data-field"),
                fieldcode: $field.attr("data-fieldcode"),
                fieldtitle: $field.attr("data-fieldtitle"),
                name: $field.attr("data-name"),
                source: $field.attr("data-source")
            };

            // Identificar el formulario al que pertenece
            const formId = $field.closest("form").attr("id");

            // Activar jQuery UI Autocomplete
            $field.autocomplete({
                source: function (request, response) {
                    $.ajax({
                        method: "POST",
                        url: window.location.href,
                        data: listFilterAutocompleteGetData(formId, $.extend({}, data), request.term),
                        dataType: "json",
                        success: function (results) {
                            try {
                                const values = [];
                                results.forEach(function (element) {
                                    if (element.key === null || element.key === element.value) {
                                        values.push(element);
                                    } else {
                                        values.push({
                                            key: element.key,
                                            value: element.key + " | " + element.value
                                        });
                                    }
                                });
                                response(values);
                            } catch (e) {
                                if (console && console.warn) {
                                    console.warn('autocomplete success handler error:', e);
                                }
                                response([]);
                            }
                        },
                        error: function (xhr) {
                            alert(xhr.status + " " + xhr.responseText);
                        }
                    });
                },
                select: function (event, ui) {
                    try {
                        $("form[id=" + formId + "] input[name=" + data.name + "]").val(ui.item.key);

                        if (ui.item.key !== null) {
                            const parts = String(ui.item.value).split(" | ");
                            ui.item.value = (parts.length > 1) ? parts[1] : parts[0];
                        }

                        $(this).form().submit();
                    } catch (e) {
                        if (console && console.warn) {
                            console.warn('autocomplete select handler error:', e);
                        }
                    }
                }
            });
        });
    } catch (e) {
        if (console && console.warn) {
            console.warn('ListFilterAutocomplete init error:', e);
        }
    }
});