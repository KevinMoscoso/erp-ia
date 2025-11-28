// Construye la tabla de resultados con las subcuentas
function widgetSubaccountDraw(id, data) {
    const $list = $("#list_" + id);
    if (!Array.isArray(data) || data.length === 0) {
        $list.empty();
        return;
    }

    const rows = data.map(item => {
        const saldoNum = parseFloat(item.saldo || 0);
        const saldoTxt = saldoNum.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        const saldoCss = saldoNum < 0 ? " text-danger" : "";

        return `
            <tr class="clickableRow" onclick="widgetSubaccountSelect('${id}', '${item.codsubcuenta}')">
                <td class="text-center">
                    <a href="${item.url}" target="_blank" onclick="event.stopPropagation();">
                        <i class="fa-solid fa-external-link-alt fa-fw"></i>
                    </a>
                </td>
                <td><b>${item.codsubcuenta}</b></td>
                <td>${item.descripcion}</td>
                <td class="text-end${saldoCss}">${saldoTxt}</td>
            </tr>`;
    }).join("");

    $list.html(rows);
}

// Lanza la búsqueda de subcuentas al servidor
function widgetSubaccountSearch(id) {
    const $list = $("#list_" + id);
    $list.empty();

    const $input = $("#" + id);
    const payload = {
        action: "widget-subcuenta-search",
        active_tab: $input.closest("form").find("input[name='activetab']").val(),
        col_name: $input.attr("name"),
        query: $("#modal_" + id + "_q").val(),
        codejercicio: $("#modal_" + id + "_ej").val(),
        sort: $("#modal_" + id + "_s").val()
    };

    $.post({
        url: window.location.href,
        data: payload,
        dataType: "json"
    })
    .done(results => widgetSubaccountDraw(id, results))
    .fail(xhr => alert(xhr.status + " " + xhr.responseText));
}

// Detecta la tecla Enter en el buscador del modal
function widgetSubaccountSearchKp(id, event) {
    if (event.key === "Enter") {
        event.preventDefault();
        widgetSubaccountSearch(id);
    }
}

// Selecciona la subcuenta y actualiza el campo principal
function widgetSubaccountSelect(id, value) {
    $("#" + id).val(value);
    $("#modal_" + id).modal("hide");
    $("#modal_span_" + id).text(value);
}
