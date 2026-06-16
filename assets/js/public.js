;(($) => {
  var currentStep = 1
  var totalSteps = 3
  var selectedSlot = null
  var masPublic = window.masPublic

  var selectedSlots = []
  var boxPricePerHour = 0

  var selectedBoxData = null // Declare selectedBoxData variable

  $(document).ready(() => {
    if ($("#mas-rental-wizard-form").length) {
      initBoxRentalWizard()
    } else {
      // Original initialization for non-wizard forms
      initStepNavigation()
      initDatePicker()
      initSlotSelection()
      initFormSubmission()
      initValidation()
      initBoxRentalForm()
    }
  })

  function initStepNavigation() {
    $(".mas-next-step").on("click", (e) => {
      e.preventDefault()

      if (validateCurrentStep()) {
        if (currentStep < totalSteps) {
          goToStep(currentStep + 1)
        }
      }
    })

    $(".mas-prev-step").on("click", (e) => {
      e.preventDefault()

      if (currentStep > 1) {
        goToStep(currentStep - 1)
      }
    })
  }

  function goToStep(step) {
    $('.mas-form-step[data-step="' + currentStep + '"]').removeClass("mas-step-active")
    $('.mas-step-dot[data-step="' + currentStep + '"]')
      .removeClass("mas-step-active")
      .addClass("mas-step-completed")

    currentStep = step
    $('.mas-form-step[data-step="' + currentStep + '"]').addClass("mas-step-active")
    $('.mas-step-dot[data-step="' + currentStep + '"]').addClass("mas-step-active")

    if (currentStep === 3) {
      updateSummary()
    }

    $("html, body").animate(
      {
        scrollTop: $(".mas-appointment-form-container").offset().top - 50,
      },
      300,
    )
  }

  function validateCurrentStep() {
    var isValid = true
    var $currentStepEl = $('.mas-form-step[data-step="' + currentStep + '"]')

    $currentStepEl.find(".mas-field-error").remove()
    $currentStepEl.find(".error").removeClass("error")

    $currentStepEl.find("[required]").each(function () {
      var $field = $(this)
      var value = $field.val().trim()

      if (!value) {
        isValid = false
        $field.addClass("error")
        $field.after('<span class="mas-field-error">Este campo es requerido</span>')
      } else {
        if ($field.attr("type") === "email" && !validateEmail(value)) {
          isValid = false
          $field.addClass("error")
          $field.after('<span class="mas-field-error">Email inválido</span>')
        }

        if ($field.hasClass("mas-phone-input") && !validatePhone(value)) {
          isValid = false
          $field.addClass("error")
          $field.after('<span class="mas-field-error">Teléfono inválido</span>')
        }

        if ($field.hasClass("mas-rut-input") && !validateRUT(value)) {
          isValid = false
          $field.addClass("error")
          $field.after('<span class="mas-field-error">RUT inválido</span>')
        }
      }
    })

    if (currentStep === 2 && !$("#appointment_time").val()) {
      isValid = false
      $("#mas-available-slots").after('<span class="mas-field-error">Debe seleccionar un horario</span>')
    }

    return isValid
  }

  function initDatePicker() {
    var $datePicker = $("#appointment_date")

    var today = new Date().toISOString().split("T")[0]
    $datePicker.attr("min", today)

    var maxDays = 30
    var maxDate = new Date()
    maxDate.setDate(maxDate.getDate() + maxDays)
    $datePicker.attr("max", maxDate.toISOString().split("T")[0])

    $datePicker.on("change", function () {
      var selectedDate = $(this).val()
      if (selectedDate) {
        loadAvailableSlots(selectedDate)
      }
    })
  }

  function loadAvailableSlots(date) {
    var $slotsContainer = $("#mas-available-slots")

    $slotsContainer.html(
      '<div class="mas-slots-loading"><span class="mas-loading-spinner"></span> Cargando horarios...</div>',
    )

    $.ajax({
      url: masPublic.ajaxUrl,
      type: "POST",
      data: {
        action: "mas_get_available_slots",
        nonce: masPublic.nonce,
        date: date,
      },
      success: (response) => {
        if (response.success && response.data.length > 0) {
          var slotsHtml = ""

          response.data.forEach((slot) => {
            slotsHtml +=
              '<button type="button" class="mas-slot-btn" data-time="' + slot.time + '">' + slot.formatted + "</button>"
          })

          $slotsContainer.html(slotsHtml)
        } else {
          $slotsContainer.html('<p class="mas-slots-placeholder">No hay horarios disponibles para esta fecha</p>')
        }
      },
      error: () => {
        $slotsContainer.html(
          '<p class="mas-slots-placeholder" style="color: #e74c3c;">Error al cargar horarios. Intente nuevamente.</p>',
        )
      },
    })
  }

  function initSlotSelection() {
    $(document).on("click", ".mas-slot-btn", function () {
      $(".mas-slot-btn").removeClass("mas-slot-selected")
      $(this).addClass("mas-slot-selected")

      selectedSlot = $(this).data("time")
      $("#appointment_time").val(selectedSlot)
    })
  }

  function updateSummary() {
    $("#summary_patient_name").text($("#patient_name").val())
    $("#summary_patient_email").text($("#patient_email").val())
    $("#summary_patient_phone").text($("#patient_phone").val())

    var date = $("#appointment_date").val()
    var dateObj = new Date(date + "T00:00:00")
    var formattedDate = dateObj.toLocaleDateString("es-CL", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    })
    $("#summary_appointment_date").text(formattedDate)

    $("#summary_appointment_time").text($("#appointment_time").val())
  }

  function initFormSubmission() {
    // The appointment form now has its own inline handler in public/appointment-form.php
    // This prevents double submission when clicking the submit button
  }

  function initValidation() {
    $(".mas-rut-input").on("input", function () {
      var value = $(this).val().replace(/\./g, "").replace(/-/g, "")
      if (value.length > 1) {
        var rut = value.slice(0, -1)
        var dv = value.slice(-1)
        $(this).val(rut.replace(/\B(?=(\d{3})+(?!\d))/g, ".") + "-" + dv)
      }
    })
  }

  function initBoxRentalForm() {
    $('input[name="box_id"], #rental_date').on("change", () => {
      var boxId = $('input[name="box_id"]:checked').val()
      var date = $("#rental_date").val()

      if (date && boxId) {
        loadAvailableSlotsForBox(date, boxId)
      }
    })

    $(document).on("click", ".mas-box-slot-btn", function () {
      var $btn = $(this)
      var time = $btn.data("time")

      if ($btn.hasClass("mas-slot-selected")) {
        $btn.removeClass("mas-slot-selected")
        selectedSlots = selectedSlots.filter((t) => t !== time)
      } else {
        $btn.addClass("mas-slot-selected")
        selectedSlots.push(time)
      }

      $("#selected_times").val(selectedSlots.join(","))
      updateRentalPrice()
    })

    $("#mas-rental-form").on("submit", function (e) {
      e.preventDefault()

      if (selectedSlots.length === 0) {
        alert("Debe seleccionar al menos un horario")
        return
      }

      var $form = $(this)
      var $submitBtn = $form.find('button[type="submit"]')

      $submitBtn.prop("disabled", true).html('<span class="mas-loading-spinner"></span> Procesando...')

      $.ajax({
        url: masPublic.ajaxUrl,
        type: "POST",
        data: {
          action: "mas_create_multiple_rentals",
          nonce: masPublic.nonce,
          box_id: $('input[name="box_id"]:checked').val(),
          professional_id: $('input[name="professional_id"]').val(),
          rental_date: $("#rental_date").val(),
          time_slots: selectedSlots.join(","),
          notes: $("#notes").val(),
        },
        success: (response) => {
          if (response.success && response.data.preference_id) {
            $form.hide()
            $("#payment-total-amount").text("$" + response.data.total_amount.toLocaleString("es-CL"))
            $("#mas-mercadopago-container").fadeIn(300)

            initMercadoPago(response.data.preference_id)
          } else {
            alert(response.data.message || "Error al crear los arriendos.")
            $submitBtn.prop("disabled", false).html("Confirmar Arriendo")
          }
        },
        error: () => {
          alert("Error de conexión. Intente nuevamente.")
          $submitBtn.prop("disabled", false).html("Confirmar Arriendo")
        },
      })
    })
  }

  function loadAvailableSlotsForBox(date, boxId) {
    var $slotsContainer = $("#mas-box-available-slots")

    $slotsContainer.html(
      '<div class="mas-slots-loading"><span class="mas-loading-spinner"></span> Cargando horarios...</div>',
    )

    selectedSlots = []
    $("#selected_times").val("")

    $.ajax({
      url: masPublic.ajaxUrl,
      type: "POST",
      data: {
        action: "mas_get_box_available_slots",
        nonce: masPublic.nonce,
        date: date,
        box_id: boxId,
      },
      success: (response) => {
        if (response.success && response.data.slots && response.data.slots.length > 0) {
          boxPricePerHour = Number.parseFloat(response.data.price_per_hour) || 0

          var slotsHtml = '<div class="mas-slots-grid">'

          response.data.slots.forEach((slot) => {
            slotsHtml +=
              '<button type="button" class="mas-box-slot-btn" data-time="' +
              slot.time +
              '">' +
              slot.formatted +
              "</button>"
          })

          slotsHtml += "</div>"
          slotsHtml += '<div class="mas-rental-price-info">'
          slotsHtml += "<p><strong>Precio por hora:</strong> $" + formatPrice(boxPricePerHour) + "</p>"
          slotsHtml += '<p><strong>Total seleccionado:</strong> <span id="rental_total_price">$0</span></p>'
          slotsHtml += "</div>"

          $slotsContainer.html(slotsHtml)
        } else {
          $slotsContainer.html('<p class="mas-slots-placeholder">No hay horarios disponibles para esta fecha</p>')
        }
      },
      error: () => {
        $slotsContainer.html(
          '<p class="mas-slots-placeholder" style="color: #e74c3c;">Error al cargar horarios. Intente nuevamente.</p>',
        )
      },
    })
  }

  function updateRentalPrice() {
    var totalHours = selectedSlots.length
    var totalPrice = totalHours * boxPricePerHour

    $("#rental_total_price").text("$" + formatPrice(totalPrice))
  }

  function formatPrice(price) {
    return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".")
  }

  function validateEmail(email) {
    var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return re.test(email)
  }

  function validatePhone(phone) {
    var re = /^[\d\s+\-()]+$/
    return re.test(phone) && phone.replace(/\D/g, "").length >= 8
  }

  function validateRUT(rut) {
    rut = rut.replace(/\./g, "").replace(/-/g, "")

    if (rut.length < 2) return false

    var body = rut.slice(0, -1)
    var dv = rut.slice(-1).toUpperCase()

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

  function initMercadoPago(preferenceId) {
    var mp = new window.MercadoPago(masPublic.mpPublicKey, {
      locale: "es-CL",
    })

    mp.bricks().create("wallet", "wallet_container", {
      initialization: {
        preferenceId: preferenceId,
      },
      customization: {
        texts: {
          valueProp: "smart_option",
        },
      },
    })
  }

  function initBoxRentalWizard() {
    var currentStep = 1
    var selectedBoxId = null
    var boxPricePerHour = 0

    // Box selection
    $(document).on("click", ".mas-box-card-modern", function () {
      $(".mas-box-card-modern").removeClass("selected")
      $(this).addClass("selected")

      selectedBoxId = $(this).data("box-id")
      boxPricePerHour = Number.parseFloat($(this).data("box-price"))

      // Reset selected slots and date when changing box
      selectedSlots = []
      $("#selected_times").val("")
      $("#rental_date").val("")
      $("#mas-box-available-slots").html(
        '<p class="mas-slots-placeholder">Seleccione una fecha para ver horarios disponibles</p>',
      )

      selectedBoxData = {
        id: selectedBoxId,
        name: $(this).find(".mas-box-card-title").text(),
        price: boxPricePerHour,
        image: $(this).find(".mas-box-card-image img").attr("src") || "",
      }

      $("#selected_box_id").val(selectedBoxId)

      // Auto advance to next step
      setTimeout(() => {
        goToWizardStep(2)
      }, 300)
    })

    // Date selection
    $("#rental_date").on("change", function () {
      var date = $(this).val()

      selectedSlots = []
      $("#selected_times").val("")
      $(".mas-wizard-next").prop("disabled", true)

      if (date && selectedBoxId) {
        loadAvailableSlotsForBox(date, selectedBoxId)
      }
    })

    // Time slot selection
    $(document).on("click", ".mas-box-slot-btn", function () {
      var $btn = $(this)
      var time = $btn.data("time")

      if ($btn.hasClass("mas-slot-selected")) {
        $btn.removeClass("mas-slot-selected")
        selectedSlots = selectedSlots.filter((t) => t !== time)
      } else {
        $btn.addClass("mas-slot-selected")
        selectedSlots.push(time)
      }

      $("#selected_times").val(selectedSlots.join(","))

      updateRentalPrice()

      // Enable/disable next button
      $(".mas-wizard-next").prop("disabled", selectedSlots.length === 0)
    })

    // Navigation buttons
    $(".mas-wizard-prev").on("click", () => {
      if (currentStep > 1) {
        if (currentStep === 2) {
          selectedBoxId = null
          selectedBoxData = null
          selectedSlots = []
          boxPricePerHour = 0
          $("#selected_box_id").val("")
          $("#selected_times").val("")
          $("#rental_date").val("")
          $(".mas-box-card-modern").removeClass("selected")
        }
        goToWizardStep(currentStep - 1)
      }
    })

    $(".mas-wizard-next").on("click", () => {
      if (currentStep < 3 && validateWizardStep(currentStep)) {
        goToWizardStep(currentStep + 1)
      }
    })

    // Form submission
    $("#mas-rental-wizard-form").on("submit", function (e) {
      e.preventDefault()

      if (selectedSlots.length === 0) {
        alert("Debe seleccionar al menos un horario")
        return
      }

      var $form = $(this)
      var $submitBtn = $(".mas-wizard-submit")

      $submitBtn.prop("disabled", true).html('<span class="mas-loading-spinner"></span> Procesando pago...')

      $.ajax({
        url: masPublic.ajaxUrl,
        type: "POST",
        data: {
          action: "mas_create_multiple_rentals",
          nonce: masPublic.nonce,
          box_id: selectedBoxId,
          professional_id: $('input[name="professional_id"]').val(),
          user_id: $('input[name="user_id"]').val(),
          rental_date: $("#rental_date").val(),
          time_slots: selectedSlots.join(","),
          notes: $("#notes").val(),
        },
        success: (response) => {
          if (response.success && response.data.preference_id) {
            console.log("[v0] Redirigiendo a MercadoPago con preference:", response.data.preference_id)
            // Redirect directly to MercadoPago
            var mp = new window.MercadoPago(masPublic.mpPublicKey, {
              locale: "es-CL",
            })

            // Get init_point and redirect
            if (response.data.init_point) {
              window.location.href = response.data.init_point
            } else {
              // Fallback: construct MercadoPago URL
              window.location.href =
                "https://www.mercadopago.cl/checkout/v1/redirect?pref_id=" + response.data.preference_id
            }
          } else {
            alert(response.data.message || "Error al crear los arriendos.")
            $submitBtn.prop("disabled", false).html("Confirmar y Pagar ✓")
          }
        },
        error: () => {
          alert("Error de conexión. Intente nuevamente.")
          $submitBtn.prop("disabled", false).html("Confirmar y Pagar ✓")
        },
      })
    })
  }

  function goToWizardStep(step) {
    // Hide current step
    $(".mas-wizard-content").removeClass("mas-wizard-content-active")
    $(".mas-wizard-step").removeClass("mas-wizard-step-active mas-wizard-step-completed")

    // Mark previous steps as completed
    for (var i = 1; i < step; i++) {
      $('.mas-wizard-step[data-step="' + i + '"]').addClass("mas-wizard-step-completed")
    }

    // Show new step
    $('.mas-wizard-content[data-step="' + step + '"]').addClass("mas-wizard-content-active")
    $('.mas-wizard-step[data-step="' + step + '"]').addClass("mas-wizard-step-active")

    currentStep = step

    // Update step 2 with selected box info
    if (step === 2 && selectedBoxData) {
      $("#selected-box-info").show()
      $("#selected-box-name").text(selectedBoxData.name)
      $("#selected-box-price").text("$" + formatPrice(selectedBoxData.price) + " / hora")
      if (selectedBoxData.image) {
        $("#selected-box-img").attr("src", selectedBoxData.image)
      }

      // Set min date to today
      var today = new Date().toISOString().split("T")[0]
      $("#rental_date").attr("min", today)

      $(".mas-wizard-next").prop("disabled", selectedSlots.length === 0)
    }

    // Update step 3 summary
    if (step === 3) {
      updateRentalSummary()
    }

    // Scroll to top
    $("html, body").animate(
      {
        scrollTop: $(".mas-rental-wizard-container").offset().top - 20,
      },
      300,
    )
  }

  function updateRentalSummary() {
    // Box info
    $("#summary-box-name").text(selectedBoxData.name)
    $("#summary-box-price").text("$" + formatPrice(selectedBoxData.price) + " / hora")
    if (selectedBoxData.image) {
      $("#summary-box-img").attr("src", selectedBoxData.image)
    }

    // Date
    var date = $("#rental_date").val()
    var dateObj = new Date(date + "T00:00:00")
    var formattedDate = dateObj.toLocaleDateString("es-CL", {
      weekday: "long",
      year: "numeric",
      month: "long",
      day: "numeric",
    })
    $("#summary-date").text(formattedDate)

    // Time slots
    var timesHtml = ""
    selectedSlots.forEach((time) => {
      timesHtml += '<span class="mas-summary-time-badge">' + time + "</span>"
    })
    $("#summary-times").html(timesHtml)

    var totalHours = selectedSlots.length
    var totalPrice = totalHours * boxPricePerHour
    $("#summary-total").text("$" + formatPrice(totalPrice))
  }

  function validateWizardStep(step) {
    var isValid = true
    var $currentStepEl = $('.mas-wizard-content[data-step="' + step + '"]')

    $currentStepEl.find(".mas-field-error").remove()
    $currentStepEl.find(".error").removeClass("error")

    $currentStepEl.find("[required]").each(function () {
      var $field = $(this)
      var value = $field.val().trim()

      if (!value) {
        isValid = false
        $field.addClass("error")
        $field.after('<span class="mas-field-error">Este campo es requerido</span>')
      } else {
        if ($field.attr("type") === "email" && !validateEmail(value)) {
          isValid = false
          $field.addClass("error")
          $field.after('<span class="mas-field-error">Email inválido</span>')
        }

        if ($field.hasClass("mas-phone-input") && !validatePhone(value)) {
          isValid = false
          $field.addClass("error")
          $field.after('<span class="mas-field-error">Teléfono inválido</span>')
        }

        if ($field.hasClass("mas-rut-input") && !validateRUT(value)) {
          isValid = false
          $field.addClass("error")
          $field.after('<span class="mas-field-error">RUT inválido</span>')
        }
      }
    })

    if (step === 2 && selectedSlots.length === 0) {
      isValid = false
      $(".mas-wizard-next").after('<span class="mas-field-error">Debe seleccionar al menos un horario</span>')
    }

    return isValid
  }
})(window.jQuery)
