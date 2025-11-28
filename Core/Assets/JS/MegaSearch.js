/* ============================
   Convertir resultados JSON en filas de tabla
   ============================ */
/**
 * Transforma una lista JSON en un array de cadenas <tr> con celdas <td>.
 * Cada elemento del JSON debe contener un campo 'url' para el enlace de la fila.
 * @param {Array<Object>} json
 * @returns {Array<string>}
 */
function json2tr(json) {
    const items = [];

    $.each(json, function (_idx, row) {
        let tds = "";

        $.each(row, function (key, value) {
            if (key === "url") {
                // la URL se usa para el enlace de la fila; no es una celda
                return;
            }

            if (value === null) {
                tds += "<td>-</td>";
                return;
            }

            if ($.isNumeric(value) && Number(value) < 0) {
                tds += "<td class='text-danger'>" + value + "</td>";
                return;
            }

            const text = String(value);
            if (text.length > 40) {
                tds += "<td>" + text.substring(0, 40) + "...</td>";
            } else {
                tds += "<td>" + text + "</td>";
            }
        });

        const href = row.url || "#";
        items.push("<tr class='clickableRow' data-href='" + href + "'>" + tds + "</tr>");
    });

    return items;
}

/* ============================
   Asignar comportamiento clicable a filas
   ============================ */
/**
 * Añade manejadores de clic a filas con clase .clickableRow para navegar.
 */
function reloadClickableRow() {
    $(".clickableRow").mousedown(function (event) {
        if (event.which === 1) {
            const $row = $(this);
            const href = $row.attr("data-href");
            const target = $row.attr("data-bs-target");

            if (typeof href !== typeof undefined && href !== false) {
                if (typeof target !== typeof undefined && target === "_blank") {
                    window.open(href);
                } else {
                    parent.document.location = href;
                }
            }
        }
    });
}

/* ============================
   Búsqueda y render de una sola sección
   ============================ */
/**
 * Realiza la búsqueda en una URL y pinta pestaña y tabla si hay resultados.
 * @param {string} url
 */
function searchOnSection(url) {
    $.getJSON(url, function (json) {
        $.each(json, function (key, val) {
            const rows = json2tr(val.results);

            if (rows.length > 0) {
                // Crear pestaña con icono y contador
                $("#v-pills-tab").append(
                    "<a class='nav-link' id='v-pills-" + key + "-tab' data-bs-toggle='pill' href='#v-pills-" + key + "' role='tab' aria-controls='v-pills-" + key + "' aria-expanded='true'>" +
                    "<span class='badge bg-secondary float-end'>" + rows.length + "</span>" +
                    "<i class='" + val.icon + " fa-fw'></i> " + val.title +
                    "</a>"
                );

                // Construir cabecera de tabla
                let tableHTML = "<thead><tr>";
                $.each(val.columns, function (_i, colTitle) {
                    tableHTML += "<th>" + colTitle + "</th>";
                });
                tableHTML += "</tr></thead>";

                // Añadir filas
                $.each(rows, function (_i, trHTML) {
                    tableHTML += trHTML;
                });

                // Contenedor de contenido de pestaña
                $("#v-pills-tabContent").append(
                    "<div class='tab-pane fade' id='v-pills-" + key + "' role='tabpanel' aria-labelledby='v-pills-" + key + "-tab'>" +
                    "<div class='card shadow'><div class='table-responsive'>" +
                    "<table class='table table-striped table-hover mb-0'>" + tableHTML + "</table>" +
                    "</div></div></div>"
                );

                // Mostrar primera pestaña
                $("#v-pills-tab a:first").tab("show");

                // Activar filas clicables
                reloadClickableRow();

                // Ocultar mensaje de "sin datos"
                $("#no-data-msg").hide();
            }
        });
    });
}

/* ============================
   Búsqueda secuencial en múltiples secciones
   ============================ */
/**
 * Consulta varias URLs en cadena para no sobrecargar el servidor.
 * @param {Object<string,string>} sections - objeto { nombreSeccion: url }
 */
function searchOnSections(sections) {
    const urls = Object.values(sections);
    let index = 0;

    function searchNext() {
        if (index >= urls.length) {
            return;
        }

        const currentUrl = urls[index];
        index++;

        $.getJSON(currentUrl, function (json) {
            $.each(json, function (key, val) {
                const rows = json2tr(val.results);

                if (rows.length > 0) {
                    // Pestaña compacta con icono y contador
                    $("#v-pills-tab").append(
                        "<a class='nav-link text-nowrap' id='v-pills-" + key + "-tab' data-bs-toggle='pill' href='#v-pills-" + key + "' role='tab' aria-controls='v-pills-" + key + "' aria-expanded='true'>" +
                        "<i class='" + val.icon + " fa-fw me-1 d-none d-lg-inline-block'></i>" +
                        "<span class='d-inline d-lg-inline'>" + val.title + "</span>" +
                        "<span class='badge bg-secondary ms-1 mt-lg-1 mb-lg-1 float-lg-end'>" + rows.length + "</span>" +
                        "</a>"
                    );

                    // Cabecera de columnas
                    let tableHTML = "<thead><tr>";
                    $.each(val.columns, function (_i, colTitle) {
                        tableHTML += "<th>" + colTitle + "</th>";
                    });
                    tableHTML += "</tr></thead>";

                    // Filas
                    $.each(rows, function (_i, trHTML) {
                        tableHTML += trHTML;
                    });

                    // Contenido de pestaña
                    $("#v-pills-tabContent").append(
                        "<div class='tab-pane fade' id='v-pills-" + key + "' role='tabpanel' aria-labelledby='v-pills-" + key + "-tab'>" +
                        "<div class='card shadow'><div class='table-responsive'>" +
                        "<table class='table table-striped table-hover mb-0'>" + tableHTML + "</table>" +
                        "</div></div></div>"
                    );

                    // Mostrar primera pestaña si no hay ninguna activa
                    $("#v-pills-tab a:first").tab("show");

                    // Hacer filas navegables
                    reloadClickableRow();

                    // Ocultar mensaje sin datos
                    $("#no-data-msg").hide();
                }
            });
        }).always(function () {
            // Continuar con la siguiente URL pase lo que pase
            searchNext();
        });
    }

    searchNext();
}