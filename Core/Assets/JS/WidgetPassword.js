/* ============================
   WidgetPassword.js
   Mostrar/Ocultar contraseñas en formularios y listas
   ============================ */
$(document).ready(function () {
    // Alternar visibilidad de contraseña en formularios al hacer clic en el botón de edición
    $(document).on('click', '.edit-psw', function () {
        const $input = $(this).closest('div').find('input[type="text"]');

        if ($input.hasClass('fs-psw')) {
            $input.removeClass('fs-psw');
        } else {
            $input.addClass('fs-psw');
        }
    });

    // Alternar visibilidad de contraseña en listas al pasar el ratón
    $(document).on('mouseenter mouseleave', '.list-psw', function () {
        const $psw = $(this).closest('div').find('.pass');

        if ($psw.hasClass('fs-psw')) {
            $psw.removeClass('fs-psw');
        } else {
            $psw.addClass('fs-psw');
        }
    });
});
