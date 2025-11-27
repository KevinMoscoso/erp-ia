/* ============================
   EditProducto: cálculos y ordenación de imágenes
   ============================ */

$(document).ready(function () {
    /* ----------------------------
       Recalcular precio según coste/margen
       ---------------------------- */

    // Cuando cambia el campo de coste, recalcular precio si el margen es positivo
    $('.calc-cost').on('change', function () {
        try {
            const coste = parseFloat($(this).val());
            const margenField = this.form ? this.form.margen : null;
            const margen = margenField ? parseFloat(margenField.value) : NaN;

            if (!isNaN(coste) && !isNaN(margen) && margen > 0) {
                const precio = coste * (100 + margen) / 100;
                $(this.form.precio).val(precio);
            }
        } catch (e) {
            if (console && console.warn) {
                console.warn('calc-cost handler error:', e);
            }
        }
    });

    // Cuando cambia el margen, recalcular precio usando el coste del formulario
    $('.calc-margin').on('change', function () {
        try {
            const margen = parseFloat($(this).val());
            const costeField = this.form ? this.form.coste : null;
            const coste = costeField ? parseFloat(costeField.value) : NaN;

            if (!isNaN(coste) && !isNaN(margen) && margen > 0) {
                const precio = coste * (100 + margen) / 100;
                $(this.form.precio).val(precio);
            }
        } catch (e) {
            if (console && console.warn) {
                console.warn('calc-margin handler error:', e);
            }
        }
    });

    // Si el precio se modifica manualmente, resetear el margen a 0
    $('.calc-price').on('change', function () {
        try {
            if (this.form && this.form.margen) {
                $(this.form.margen).val(0);
            }
        } catch (e) {
            if (console && console.warn) {
                console.warn('calc-price handler error:', e);
            }
        }
    });

    /* ----------------------------
       Inicializar sortable para #images-container
       ---------------------------- */

    if ($.fn && $.fn.sortable) {
        $('#images-container').sortable({
            cursor: 'move',
            tolerance: 'pointer',
            opacity: 0.65,
            stop: function (event, ui) {
                try {
                    // Obtener orden actual a partir de los hijos del contenedor
                    const children = Array.from(event.target.children || []);
                    const orden = children.map((el) => {
                        if (el && el.dataset && el.dataset.imageId) {
                            return el.dataset.imageId;
                        }
                        // fallback a atributo data-image-id
                        return el ? el.getAttribute('data-image-id') : null;
                    }).filter(Boolean);

                    if (orden.length === 0) {
                        return;
                    }

                    // Construir URL actual y añadir parámetro action=sort-images
                    const url = new URL(window.location.href);
                    url.searchParams.append('action', 'sort-images');

                    // Enviar orden al servidor vía POST
                    $.ajax({
                        method: 'POST',
                        url: url.toString(),
                        data: { orden: orden },
                        dataType: 'json',
                        success: function (data) {
                            if (!data || data.status !== 'ok') {
                                const msg = data && data.message ? data.message : 'Error al ordenar imágenes';
                                alert(msg);
                            }
                        },
                        error: function (xhr) {
                            const status = xhr && xhr.status ? xhr.status : 'Error';
                            const resp = xhr && xhr.responseText ? xhr.responseText : '';
                            alert(status + ' ' + resp);
                        }
                    });
                } catch (e) {
                    if (console && console.warn) {
                        console.warn('images-container sortable stop error:', e);
                    }
                }
            }
        });
    } else {
        if (console && console.warn) {
            console.warn('jQuery UI sortable no está cargado: #images-container no será ordenable.');
        }
    }
});