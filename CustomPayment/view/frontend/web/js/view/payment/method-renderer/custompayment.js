define([
  "Magento_Checkout/js/view/payment/default",
  "ko",
  "jquery",
  "Magento_Checkout/js/model/quote",
  "Magento_Customer/js/model/customer",
  "Magento_Checkout/js/model/payment/additional-validators",
  "Magento_Checkout/js/model/payment-service",
  "Magento_Checkout/js/model/payment/method-list",
  "mage/url"
], function (Component, ko, $, quote, customer, additionalValidators, paymentService, paymentMethodList, urlBuilder) {
  "use strict";

  return Component.extend({
    defaults: {
      template: "Devhooks_CustomPayment/payment/customtemplate",
      creditCardName: "",
      creditCardNumber: "",
      creditCardExpiry: "",
      creditCardCvc: "",
    },

    initialize: function () {
      this._super();
      console.log("Initializing custompayment.js");

      this.creditCardName = ko.observable(this.creditCardName);
      this.creditCardNumber = ko.observable(this.creditCardNumber);
      this.creditCardExpiry = ko.observable(this.creditCardExpiry);
      this.creditCardCvc = ko.observable(this.creditCardCvc);

      // Fetching the configuration values
      this.api_base_url = urlBuilder.build('custompayment/payment/cliente');
      this.uuid = generateUUID();
    },

    placeOrder: function () {
      var self = this;
      if (this.validateFields()) {
        var paymentData = this.preparePaymentData();

        console.log("Sending payment data:", paymentData);

        $.ajax({
          url: this.api_base_url,
          type: "POST",
          contentType: "application/json",
          data: JSON.stringify(paymentData),
          success: function (response) {
            console.log("Payment processed successfully:", response);
            if (response.success) {
              console.log("Payment successful", response);
              // Proceed with placing the order if needed
            } else {
              console.error("Payment failed:", response.message);
              alert("Payment failed: " + response.message);
            }
          },
          error: function (error) {
            console.error("Error processing payment:", error);
            alert("An error occurred while processing the payment.");
          },
        });
      } else {
        console.error("Validation failed: Please fill in all credit card details.");
        alert("Please fill in all credit card details.");
      }
    },

    preparePaymentData: function () {
      var self = this;

      // Extracting product information from the quote
      var items = quote.getItems();
      var articulos = items.map(function (item) {
        return {
          id_pedido: item.item_id || "string",
          nombre_producto: item.name || "string",
          sku: item.sku || "string",
          cantidad: item.qty || 1,
          precio_unitario: item.price || 0,
          precio_total: item.row_total || 0,
        };
      });

      var paymentData = {
        action: "processPayment",
        cliente: {
          email:
            quote.billingAddress() && quote.billingAddress().email
              ? quote.billingAddress().email
              : quote.shippingAddress() && quote.shippingAddress().email
              ? quote.shippingAddress().email
              : customer.customerData && customer.customerData.email
              ? customer.customerData.email
              : quote.guestEmail || "string",
          nombre: quote.shippingAddress().firstname || "string",
          apellido_paterno: quote.shippingAddress().lastname || "string",
          telefono: quote.shippingAddress().telephone || "5566778899",
          direccion: {
            linea1: quote.shippingAddress().street[0] || "string",
            cp: quote.shippingAddress().postcode || "111",
          },
        },
        tarjeta: {
          nombre: self.creditCardName(),
          pan: self.creditCardNumber(),
          expiracion_mes: self.creditCardExpiry().split("/")[0],
          expiracion_anio: self.creditCardExpiry().split("/")[1],
          cvv2: self.creditCardCvc(),
        },
        pedido: {
          id_externo: self.uuid, // Use the generated UUID
          creacion: new Date().toISOString(),
          direccion_envio: {
            linea1: quote.shippingAddress().street[0] || "string",
            cp: quote.shippingAddress().postcode || "string",
            telefono: {
              numero: quote.shippingAddress().telephone || "string",
            },
            nombre: quote.shippingAddress().firstname || "string",
            apellido_paterno: quote.shippingAddress().lastname || "string",
          },
          peso: 0, // Set appropriate weight if needed
          articulos: articulos,
          total_articulos: articulos.length,
          monto_articulos: quote.totals().grand_total,
          monto_envio: quote.totals().shipping_amount,
          total_monto: quote.totals().grand_total,
        },
      };

      return paymentData;
    },

    validateFields: function () {
      console.log("Validating fields");
      if (
        !this.creditCardName() ||
        !this.creditCardNumber() ||
        !this.creditCardExpiry() ||
        !this.creditCardCvc()
      ) {
        console.error(
          "Validation failed: Please fill in all credit card details."
        );
        alert("Please fill in all credit card details.");
        return false;
      }
      return true;
    },
  });
});

function generateUUID() {
  var now = new Date().toISOString();
  now = now.replace(/[:.-]/g, "");
  var randomSegment = "xxxxxx".replace(/[x]/g, function () {
    return ((Math.random() * 16) | 0).toString(16);
  });
  var uuid = now + "-" + randomSegment;
  return uuid;
}
