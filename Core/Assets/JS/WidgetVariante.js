// Función para recortar descripciones largas
function shortenText(text, maxLength = 300) {
    if (!text) return '';
    return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
}

// Función para determinar la clase CSS según valor
function getCssClass(value) {
    if (value < 0) return ' text-danger';
    if (value === 0) return ' text-warning';
    return '';
}

// Construir filas de la tabla con la información de cada variante
function widgetVarianteDraw(id, items) {
    const $list = $("#list_" + id);
    if (!Array.isArray(items) || items.length === 0) {
        $list.empty();
        return;
    }

    const rows = items.map(item => {
        const descripcion = shortenText(item.descripcion);
        const priceCss = getCssClass(item.precio);
        const stockCss = getCssClass(item.stock);

        return `
            <tr class="clickableRow" onclick="widgetVarianteSelect('${id}', '${item.match}')">
                <td class="text-center">
                    <a href="${item.url}" target="_blank" onclick="event.stopPropagation();">
                        <i class="fa-solid fa-external-link-alt fa-fw"></i>
                    </a>
                </td>
                <td><b>${item.referencia}</b> ${descripcion}</td>
                <td class="text-end text-nowrap${priceCss}">${item.precio_str}</td>
                <td class="text-end text-nowrap${stockCss}">${item.stock_str}</td>
            </tr>`;
    }).join("");

    $list.html(rows);
}

// Buscar variantes en el servidor y mostrar resultados
function widgetVarianteSearch(id) {
    $("#list_" + id).empty();

    const $input = $("#" + id);
    const payload = {
        action: 'widget-variante-search',
        active_tab: $input.closest('form').find("input[name='activetab']").val(),
        col_name: $input.attr("name"),
        query: $("#modal_" + id + "_q").val(),
        codfabricante: $("#modal_" + id + "_fab").val(),
        codfamilia: $("#modal_" + id + "_fam").val(),
        sort: $("#modal_" + id + "_s").val()
    };

    $.ajax({
        type: "POST",
        url: window.location.href,
        data: payload,
        dataType: "json"
    })
    .done(results => widgetVarianteDraw(id, results))
    .fail(xhr => alert(xhr.status + " " + xhr.responseText));
}

// Ejecutar búsqueda al presionar Enter
function widgetVarianteSearchKp(id, event) {
    if (event.key === "Enter") {
        event.preventDefault();
        widgetVarianteSearch(id);
    }
}

// Seleccionar variante y actualizar campo principal
function widgetVarianteSelect(id, value) {
    $("#" + id).val(value);
    $("#modal_" + id).modal("hide");
    $("#modal_span_" + id).text(value);
}