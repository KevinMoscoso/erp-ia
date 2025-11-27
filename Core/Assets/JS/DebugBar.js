/* ============================
   Utilidades para la DebugBar
   ============================ */

/**
 * Desactiva cualquier botón activo de la debugbar y oculta las secciones de detalle.
 * Mantiene compatibilidad con los selectores públicos esperados por el sistema.
 * @returns {boolean} siempre devuelve false
 */
function hideAllDebugBar() {
    try {
        // Quitar la clase active de los enlaces activos dentro de la debugbar
        var $activeLinks = $(".debugbar ul li a.active");
        if ($activeLinks && $activeLinks.length) {
            $activeLinks.removeClass('active');
        }

        // Ocultar todas las secciones de la debugbar con animación rápida
        var $sections = $(".debugbar-section");
        if ($sections && $sections.length) {
            $sections.hide('fast');
        }
    } catch (e) {
        // No propagar errores; registrar en consola si está disponible
        if (console && console.warn) {
            console.warn('hideAllDebugBar error:', e);
        }
    }

    return false;
}

/**
 * Muestra la sección de la debugbar identificada por key y marca su botón como activo.
 * Llama a hideAllDebugBar() para limpiar el estado previo.
 * @param {string} key - sufijo que identifica botón y sección (ej. "Errors")
 * @returns {boolean} siempre devuelve false
 */
function showDebugBarSection(key) {
    try {
        // Limpiar estado previo
        hideAllDebugBar();

        // Construir selectores esperados por el sistema
        var btnSelector = "#debugbarBtn" + key;
        var sectionSelector = "#debugbarSection" + key;

        // Activar el botón correspondiente si existe
        var $btn = $(btnSelector);
        if ($btn && $btn.length) {
            $btn.addClass('active');
        }

        // Mostrar la sección correspondiente si existe
        var $section = $(sectionSelector);
        if ($section && $section.length) {
            $section.show('fast');
        }
    } catch (e) {
        if (console && console.warn) {
            console.warn('showDebugBarSection error:', e);
        }
    }

    return false;
}