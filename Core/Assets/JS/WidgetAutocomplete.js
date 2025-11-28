/* ============================
   Helper: preparar datos para autocomplete del widget
   ============================ */
function widgetAutocompleteGetData(formId, formData, term) {
    try {
        const rawForm = $("form[id=" + formId + "]").serializeArray();
        rawForm.forEach(function (input) {
            formData[input.name] = input.value;
        });
        formData["action"] = "autocomplete";
        formData["term"] = term;
    } catch (e) {
        if (console && console.warn) {
            console.warn('widgetAutocompleteGetData error:', e);
        }
    }
    return formData;
}

/* ============================
   Inicialización del widget de autocompletado
   ============================ */
$(document).ready(function () {
    try {
        $(".widget-autocomplete").each(function () {
            const $field = $(this);

            // Configuración tomada de atributos del campo
            const data = {
                field: $field.attr("data-field"),
                fieldcode: $field.attr("data-fieldcode"),
                fieldfilter: $field.attr("data-fieldfilter"),
                fieldtitle: $field.attr("data-fieldtitle"),
                source: $field.attr("data-source"),
                strict: $field.attr("data-strict")
            };

            // Obtener el id del formulario contenedor
            const formId = $field.closest("form").attr("id");

            // Activar jQuery UI Autocomplete con las reglas del proyecto
            $field.autocomplete({
                source: function (request, response) {
                    $.ajax({
                        method: "POST",
                        url: window.location.href,
                        data: widgetAutocompleteGetData(formId, $.extend({}, data), request.term),
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
                                    console.warn('widget-autocomplete success handler error:', e);
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
                        if (ui.item.key !== null) {
                            $("form[id=" + formId + "] input[name=" + data.field + "]").val(ui.item.key);

                            const parts = String(ui.item.value).split(" | ");
                            ui.item.value = (parts.length > 1) ? parts[1] : parts[0];
                        }
                    } catch (e) {
                        if (console && console.warn) {
                            console.warn('widget-autocomplete select handler error:', e);
                        }
                    }
                },
                open: function () {
                    // Asegurar que el desplegable se muestre por encima
                    try {
                        $(this).autocomplete('widget').css('z-index', 1500);
                        return false;
                    } catch (e) {
                        if (console && console.warn) {
                            console.warn('widget-autocomplete open handler error:', e);
                        }
                    }
                }
            });

            // Modo no estricto: actualizar el valor del input mientras escribe
            $field.on("keyup", function (event) {
                try {
                    if (data.strict === "0" && event.key !== "Enter") {
                        $("form[id=" + formId + "] input[name=" + data.field + "]").val(event.target.value);
                    }
                } catch (e) {
                    if (console && console.warn) {
                        console.warn('widget-autocomplete keyup handler error:', e);
                    }
                }
            });
        });
    } catch (e) {
        if (console && console.warn) {
            console.warn('WidgetAutocomplete init error:', e);
        }
    }
});