/* ============================
   Búsqueda de archivos en el widget de librería
   ============================ */
function widgetLibrarySearch(id) {
    // indicador de carga
    $("#list_" + id).html("<div class='col-12 text-center pt-5 pb-5'><i class='fa-solid fa-circle-notch fa-4x fa-spin'></i></div>");

    const $inputHidden = $("div#" + id + " input.input-hidden");
    const payload = {
        action: "widget-library-search",
        active_tab: $inputHidden.closest("form").find('input[name="activetab"]').val(),
        col_name: $inputHidden.attr("name"),
        widget_id: id,
        query: $("#modal_" + id + "_q").val(),
        sort: $("#modal_" + id + "_s").val()
    };

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: payload,
        dataType: "json",
        success: function (results) {
            $('div#list_' + id).html(results.html);
        },
        error: function (xhr) {
            alert(xhr.status + " " + xhr.responseText);
        }
    });
}

/* ============================
   Búsqueda al pulsar Enter en el input
   ============================ */
function widgetLibrarySearchKp(id, event) {
    if (event.key === "Enter") {
        event.preventDefault();
        widgetLibrarySearch(id);
    }
}

/* ============================
   Selección de archivo dentro del widget
   ============================ */
function widgetLibrarySelect(id, id_file, filename) {
    $("div#" + id + " input.input-hidden").val(id_file);
    $("div#" + id + " span.file-name").text(filename);

    const $list = $('div#list_' + id);
    $list.find('div.file').removeClass('border-primary');
    $list.find('div[data-idfile="' + id_file + '"]').addClass('border-primary');

    $("#modal_" + id).modal("hide");
}

/* ============================
   Subida de archivo y actualización de la lista
   ============================ */
function widgetLibraryUpload(id, file) {
    const $inputHidden = $("div#" + id + " input.input-hidden");

    const data = new FormData();
    data.append("action", "widget-library-upload");
    data.append("active_tab", $inputHidden.closest("form").find('input[name="activetab"]').val());
    data.append("col_name", $inputHidden.attr("name"));
    data.append("widget_id", id);
    data.append("file", file);

    $.ajax({
        method: "POST",
        url: window.location.href,
        data: data,
        dataType: "json",
        processData: false,
        contentType: false,
        success: function (results) {
            $('div#list_' + id).html(results.html);

            // si solamente hay un resultado, lo seleccionamos automáticamente
            if (results.records === 1) {
                widgetLibrarySelect(id, results.new_file, results.new_filename);
            }
        },
        error: function (xhr) {
            alert(xhr.status + " " + xhr.responseText);
        }
    });
}