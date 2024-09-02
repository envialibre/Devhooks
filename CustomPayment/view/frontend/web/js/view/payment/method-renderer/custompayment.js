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
      this.api_base_url = urlBuilder.build('custompayment/payment/cliente'); // Use urlBuilder to generate the base URL
      this.api_token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIyIiwianRpIjoiNGJhZDU1M2JhYmQ5NzYwNDQzZmYzZWVhYTJhZDgwZTNlZjdmNzY1N2Q5NDYyYTI5NmM2YWIwMjA1NTlhZDQ1NTk4MTgxN2IwNTFmYTA5M2IiLCJpYXQiOjE3MjQ0MTg5NTAuMTgyMjA5LCJuYmYiOjE3MjQ0MTg5NTAuMTgyMjE4LCJleHAiOjE3ODc0OTA5NTAuMTczMDk5LCJzdWIiOiIxMjMiLCJzY29wZXMiOlsiY2xpZW50ZS10YXJqZXRhcyIsImNsaWVudGUtdHJhbnNhY2Npb25lcyIsImNsaWVudGUtY2xpZW50ZXMiLCJjbGllbnRlLXN1c2NyaXBjaW9uZXMiLCJjbGllbnRlLXBsYW5lcyIsImNsaWVudGUtYW50aWZyYXVkZSIsImNsaWVudGUtd2ViaG9va3MiLCJjbGllbnRlLWNvbmNpbGlhY2lvbiIsImNsaWVudGUtdnRleCJdfQ.JlpM3jVY9ofjyyiSrLdFymTJXUxgo6_n0v-FulmLrhWQL9F-1M7v1ZT9K0TYvJIrPERAha-QpfwsNOQt49n2ertUYk5qXAw5FJKAKkbmx9pvlhqZEYi6YPVEN91gGpV3S4mXCK4BGaQSgx6aoSc6zVqaEab0fGpFiV65ecn9G8HYWv4Wfnbmk364jF-ZFBD68i4BGd5bICWYFAomXXCjrlW7uTwVq67BnSXjkwOpzTp2uN9GCF59V_SX87lexhCJ-cNnRDOigPnaYZqBNlDLoetxkRnxHMyM4lXAzjnS3Qei0GbCYFE_etTfNzKEO7JoOdieFUOrRfdbSA3J8RcA3A6J7psn75_4UGLW6M91agCnKgmOqymBOn9COR8mi78S9MJZyS_4C6ePPJXTl7scSAZkz1eysXD93Fi9-MBLLbYRpVxEk0lJOgZYJ1TrmoQWLmvYNtKyp8xGRFLOu_7tX4qHDTY_ZXi5Y8CCfNYeb_5a3KbtQxQKI67UaN4L9bu9Bnc_Hdzn4gwlB_zcsVWPFZhwDLww0ckAmV6oXVUS0lrPWeErfaOFay-WQEQyHRLmCn4nwVIqFt6Xuzx9WV3uDZUeZO4OJ85ASiRXOit8bQO81gn2nQRXSOPx_Bf-BWvAQb0dMO3JwuDjRqu-KBMt6L1Wu3qmWZmhht9o7oO5Ql4';

      this.uuid = generateUUID();
    },

    placeOrder: function () {
      var self = this;
      if (this.validateFields()) {
        // First, get or create the customer by email
        this.createOrGetCustomerByEmail(function (clienteId) {
          self.cliente_id = clienteId; // Set the returned customer ID
          // Check if the card already exists
          self.checkIfCardExists(function (existingCard) {
            if (existingCard) {
              // Card exists, update the CVV
              self.updateCardCvv(existingCard.token, function (updatedToken) {
                self.processPayment(updatedToken);
              });
            } else {
              // Card does not exist, create a new one
              self.createCard(function (newCardToken) {
                self.processPayment(newCardToken);
              });
            }
          });
        });
      } else {
        console.error("Validation failed: Please fill in all credit card details.");
        alert("Please fill in all credit card details.");
      }
    },

    processPayment: function (token) {
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
        monto: quote.totals().grand_total.toFixed(2),
        moneda: quote.totals().quote_currency_code,
        metodo_pago: "tarjeta",
        tarjeta: {
          token: token,
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
          fecha_creacion: new Date().toISOString(),
          fecha_entrega: "2019-08-24T14:15:22Z", // Adjust if you have a specific date
          monto_articulos: quote.totals().grand_total,
          monto_envio: quote.totals().shipping_amount,
          total_monto: quote.totals().grand_total,
        },
        cliente: {
          id: self.cliente_id,
        },
      };

      console.log("Sending payment data:", paymentData);

      $.ajax({
        url: urlBuilder.build('custompayment/payment/cargo'), // Use urlBuilder to generate the URL
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify(paymentData),
        success: function (response) {
          console.log("Payment processed successfully:", response);
          if (response.status === "success") {
            console.log("Payment successful", response);
            // Proceed with placing the order if needed
          } else {
            console.error("Payment failed:", response.message);
          }
        },
        error: function (error) {
          console.error("Error processing payment:", error);
        },
      });
    },

    createOrGetCustomerByEmail: function (callback) {
      var self = this;
      var email =
        quote.billingAddress() && quote.billingAddress().email
          ? quote.billingAddress().email
          : quote.shippingAddress() && quote.shippingAddress().email
          ? quote.shippingAddress().email
          : customer.customerData && customer.customerData.email
          ? customer.customerData.email
          : quote.guestEmail || "string";

      // First, attempt to get the customer by email
      $.ajax({
        url: this.buildUrl(`email/${email}`), // Use urlBuilder to generate the URL
        type: "GET",
        success: function (response) {
          if (response.status === "success" && response.data && response.data.cliente) {
            console.log("Customer found:", response.data.cliente);
            callback(response.data.cliente.id);
          } else {
            console.log("Customer not found, creating a new one...");
            self.createCustomer(email, callback);
          }
        },
        error: function (error) {
          console.error("Error fetching customer by email:", error);
          if (error.responseJSON && error.responseJSON.status === "fail" && error.responseJSON.http_code === 404) {
            console.log("Customer not found, creating a new one...");
            self.createCustomer(email, callback);
          } else {
            console.error("An unexpected error occurred:", error);
          }
        },
      });
    },

    createCustomer: function (email, callback) {
      var customerData = {
        id_externo: this.uuid, // Use the generated UUID
        nombre: quote.shippingAddress().firstname || "string",
        apellido_paterno: quote.shippingAddress().lastname || "string",
        email: email,
        telefono: {
          numero: quote.shippingAddress().telephone || "5566778899",
        },
        direccion: {
          linea1: quote.shippingAddress().street[0] || "string",
          cp: quote.shippingAddress().postcode || "111",
          telefono: {
            numero: quote.shippingAddress().telephone || "5566778899",
          },
        },
        creacion_externa: new Date().toISOString(),
      };

      console.log("Sending customer data:", customerData);

      $.ajax({
        url: this.buildUrl(""), // Use the base URL for POST request
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify(customerData),
        success: function (response) {
          console.log("Customer created successfully:", response);
          if (response.status === "success") {
            callback(response.data.cliente.id);
          } else {
            console.error("Customer creation failed:", response.message);
          }
        },
        error: function (error) {
          console.error("Error creating customer:", error);
        },
      });
    },

    checkIfCardExists: function (callback) {
      var self = this;
      var url = this.buildUrl(`${self.cliente_id}/tarjeta`);

      $.ajax({
        url: url,
        type: "GET",
        headers: {
          Authorization: "Bearer " + self.api_token, // Keep the Authorization header for tarjeta requests
        },
        success: function (response) {
          console.log("Fetched existing cards:", response);
          if (
            response.status === "success" &&
            response.data.tarjetas.data.length > 0
          ) {
            var existingCard = response.data.tarjetas.data.find(function (
              card
            ) {
              return (
                card.pan ===
                self
                  .creditCardNumber()
                  .replace(/(\d{6})(\d{6})(\d{4})/, "$1******$3")
              );
            });
            callback(existingCard);
          } else {
            callback(null);
          }
        },
        error: function (error) {
          console.error("Error fetching existing cards:", error);
          callback(null);
        },
      });
    },

    updateCardCvv: function (token, callback) {
      var self = this;
      var url = this.buildUrl(`tarjeta/${token}`);
      var updateData = {
        cvv2: self.creditCardCvc(),
      };

      $.ajax({
        url: url,
        type: "PUT",
        headers: {
          Authorization: "Bearer " + self.api_token, // Keep the Authorization header for tarjeta requests
        },
        contentType: "application/json",
        data: JSON.stringify(updateData),
        success: function (response) {
          console.log("Card CVV updated successfully:", response);
          if (response.status === "success") {
            callback(response.data.tarjeta.token);
          } else {
            console.error("Failed to update card CVV:", response.message);
            callback(null);
          }
        },
        error: function (error) {
          console.error("Error updating card CVV:", error);
          callback(null);
        },
      });
    },

    createCard: function (callback) {
      var self = this;
      var cardData = {
        nombre: self.creditCardName(),
        pan: self.creditCardNumber(),
        expiracion_mes: self.creditCardExpiry().split("/")[0],
        expiracion_anio: self.creditCardExpiry().split("/")[1],
        cvv2: self.creditCardCvc(),
        cliente_id: self.cliente_id,
        default: true,
        cargo_unico: true,
      };

      $.ajax({
        url: this.buildUrl("tarjeta"),
        type: "POST",
        headers: {
          Authorization: "Bearer " + self.api_token, // Keep the Authorization header for tarjeta requests
        },
        contentType: "application/json",
        data: JSON.stringify(cardData),
        success: function (response) {
          console.log("Card created successfully:", response);
          if (response.status === "success") {
            callback(response.data.tarjeta.token);
          } else {
            console.error("Failed to create card:", response.message);
            callback(null);
          }
        },
        error: function (error) {
          console.error("Error creating card:", error);
          callback(null);
        },
      });
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

    buildUrl: function (endpoint) {
      return `${this.api_base_url}/${endpoint}`;
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
