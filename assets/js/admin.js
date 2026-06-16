;(($) => {
  // Declare masAdmin variable
  var masAdmin = window.masAdmin || {}

  // Inicialización
  $(document).ready(() => {
    initDatePickers()
    initTimePickers()
    initModals()
    initFormValidation()
    initAjaxActions()
  })

  // Inicializar date pickers
  function initDatePickers() {
    if ($.fn.datepicker) {
      $(".mas-datepicker").datepicker({
        dateFormat: "yy-mm-dd",
        minDate: 0,
        firstDay: 1,
      })
    }
  }

  // Inicializar time pickers
  function initTimePickers() {
    // Los navegadores modernos soportan input type="time" nativamente
    // Aquí se puede agregar un polyfill si es necesario
  }

  // Inicializar modales
  function initModals() {
    // Abrir modal
    $(document).on("click", "[data-modal-open]", function (e) {
      e.preventDefault()
      var modalId = $(this).data("modal-open")
      $("#" + modalId).fadeIn(200)
    })

    // Cerrar modal
    $(document).on("click", "[data-modal-close], .mas-modal-overlay", function (e) {
      if (e.target === this) {
        $(this).closest(".mas-modal-overlay").fadeOut(200)
      }
    })

    // Cerrar con ESC
    $(document).on("keydown", (e) => {
      if (e.key === "Escape") {
        $(".mas-modal-overlay").fadeOut(200)
      }
    })
  }

  // Validación de formularios
  function initFormValidation() {
    // Validar RUT chileno
    $(".mas-rut-input").on("blur", function () {
      var rut = $(this).val()
      if (rut && !validateRUT(rut)) {
        $(this).addClass("error")
        showError($(this), "RUT inválido")
      } else {
        $(this).removeClass("error")
        hideError($(this))
      }
    })

    // Validar email
    $(".mas-email-input").on("blur", function () {
      var email = $(this).val()
      if (email && !validateEmail(email)) {
        $(this).addClass("error")
        showError($(this), "Email inválido")
      } else {
        $(this).removeClass("error")
        hideError($(this))
      }
    })

    // Validar teléfono
    $(".mas-phone-input").on("blur", function () {
      var phone = $(this).val()
      if (phone && !validatePhone(phone)) {
        $(this).addClass("error")
        showError($(this), "Teléfono inválido")
      } else {
        $(this).removeClass("error")
        hideError($(this))
      }
    })
  }

  // Acciones AJAX
  function initAjaxActions() {
    // Actualizar estado de cita
    $(document).on("click", ".mas-update-status", function (e) {
      e.preventDefault()

      var $btn = $(this)
      var appointmentId = $btn.data("appointment-id")
      var newStatus = $btn.data("status")

      if (!confirm("¿Está seguro de cambiar el estado de esta cita?")) {
        return
      }

      $btn.prop("disabled", true).append(' <span class="mas-loading"></span>')

      $.ajax({
        url: masAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "mas_update_appointment_status",
          nonce: masAdmin.nonce,
          appointment_id: appointmentId,
          status: newStatus,
        },
        success: (response) => {
          if (response.success) {
            showNotice("Estado actualizado exitosamente", "success")
            location.reload()
          } else {
            showNotice(response.data.message || "Error al actualizar", "error")
          }
        },
        error: () => {
          showNotice("Error de conexión", "error")
        },
        complete: () => {
          $btn.prop("disabled", false).find(".mas-loading").remove()
        },
      })
    })

    // Eliminar registro
    $(document).on("click", ".mas-delete-item", function (e) {
      e.preventDefault()

      if (!confirm("¿Está seguro de eliminar este registro? Esta acción no se puede deshacer.")) {
        return
      }

      var $btn = $(this)
      var itemType = $btn.data("type")
      var itemId = $btn.data("id")

      $btn.prop("disabled", true)

      $.ajax({
        url: masAdmin.ajaxUrl,
        type: "POST",
        data: {
          action: "mas_delete_" + itemType,
          nonce: masAdmin.nonce,
          id: itemId,
        },
        success: (response) => {
          if (response.success) {
            showNotice("Registro eliminado exitosamente", "success")
            $btn.closest("tr").fadeOut(300, function () {
              $(this).remove()
            })
          } else {
            showNotice(response.data.message || "Error al eliminar", "error")
            $btn.prop("disabled", false)
          }
        },
        error: () => {
          showNotice("Error de conexión", "error")
          $btn.prop("disabled", false)
        },
      })
    })
  }

  // Funciones de validación
  function validateRUT(rut) {
    // Limpiar formato
    rut = rut.replace(/\./g, "").replace(/-/g, "")

    if (rut.length < 2) return false

    var body = rut.slice(0, -1)
    var dv = rut.slice(-1).toUpperCase()

    // Calcular dígito verificador
    var suma = 0
    var multiplo = 2

    for (var i = body.length - 1; i >= 0; i--) {
      suma += Number.parseInt(body.charAt(i)) * multiplo
      multiplo = multiplo < 7 ? multiplo + 1 : 2
    }

    var dvEsperado = 11 - (suma % 11)
    dvEsperado = dvEsperado === 11 ? "0" : dvEsperado === 10 ? "K" : dvEsperado.toString()

    return dv === dvEsperado
  }

  function validateEmail(email) {
    var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return re.test(email)
  }

  function validatePhone(phone) {
    var re = /^[\d\s+\-$$$$]+$/
    return re.test(phone) && phone.replace(/\D/g, "").length >= 8
  }

  // Mostrar/ocultar errores
  function showError($field, message) {
    hideError($field)
    $field.after('<span class="mas-field-error">' + message + "</span>")
  }

  function hideError($field) {
    $field.next(".mas-field-error").remove()
  }

  // Mostrar notificaciones
  function showNotice(message, type) {
    type = type || "info"

    var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + "</p></div>")

    $(".wrap > h1").after($notice)

    setTimeout(() => {
      $notice.fadeOut(300, function () {
        $(this).remove()
      })
    }, 5000)
  }
})(window.jQuery)
