# Satispay payment (0.0.1-beta)

Satispay plugin for Shopware 6 ecommerce
- The plugin is not currently in a production ready state;

## Requirements

- Shopware 6
- Satispay payment method is allowed only for EURO transactions. 

## Installation

### Via composer
Use the composer require command to add it to your ecommerce

```bash
composer require satispay/shopware6-plugin
```

Then you have to install and activate it
```bash
bin/console plugin:refresh
bin/console plugin:install Satispay
bin/console plugin:activate Satispay
```
After installation, a new [rule](https://docs.shopware.com/en/shopware-6-en/settings/rules) will be added called **Currency Euro**. This rule is set as [availability rule](https://docs.shopware.com/en/shopware-6-en/settings/Paymentmethods#availability-rule) for Satispay payment method.

## Configuration

### Activate Satispay Token

1. Log in as admin user
2. Access Settings > System > Plugins
   
   ![Settings System PLugins](docs/assets/settings-system-plugins.png)
   
3. Select config button for Satispay payment plugin to access its settings 
   
   ![Settings System PLugins](docs/assets/settings-system-plugins-config.png)
   
4. Select if you want to use sandbox mode through the checkbox Sandbox (if active is blue and will run on sanbdox, not otherwise)

   ![Satispay Settings](docs/assets/satispay-settings.png)

5. Insert your activation code created from your satispay account dashboard (Negozi Online -> Crea codice di attivazione)
  
   ![Satispay Token](docs/assets/satispay-business.png)

6. Click Save button to save current settings

   ![Satispay Save](docs/assets/satispay-settings-save.png)

7. Click Activate button

   ![Satispay Activate](docs/assets/satispay-settings-activate.png)

In case of success, you see in the Currently activated code your activation code.
In case of error, you'll receive an error code for Satispay support to identify the issue.

If you **empty the token activation** and click **activate**, 
it will **delete current activation values** and you will need a new token to **activate your connection** to satispay.

   ![Satispay Error](docs/assets/satispay-error.png)

### Enable Satispay Payment method
1. Select the corresponding sales channel where you want to activate satispay payment method
2. Open the dropdown Payment methods from Payment and shipping settings and select Satispay payment

   ![Satispay Payment](docs/assets/set-satispay-payment-method.png)

6. Click Save button to save current settings

### Extra settings (optional)
Extra settings are available in the admin panel in Settings > Shop > Payment.

![Satispay Extra Settings Edit](docs/assets/satispay-extra-settings-edit.png)

To view or change them select the action button and click edit.

![Satispay Extra Settings](docs/assets/satispay-extra-settings.png)

### Refund payment

1. Open the order you want to create the refund for from the orders grid. 
   
   _**Only the orders that have the payment status paid, refunded or refunded partially can be refunded.**_
2. Select the **Satispay Tab**  _visible only if the order was paid with satispay_.

![Satispay Order Detail](docs/assets/select-order.png)

3. Click **Make a Refund** button
4. Insert the amount to refund and confirm clicking **Perform refund** button

![Satispay Refund Modal](docs/assets/satispay-refund-modal.png)

![Satispay Refund Success](docs/assets/satispay-refund-success.png)

In case of error, you'll receive an error code for Satispay support to identify the issue.

![Satispay Refund Error](docs/assets/satispay-refund-error.png)

## Payment Flow

![Satispay Payment Flow](docs/assets/shopware-satispay-payment-flow.png)

* In 6.1 the payment status after the get payment status will stay in **OPEN** instead of **IN PROGRESS**.

## Tested on
- 6.1
- 6.2
- 6.3

## Contributing
Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License
This project uses the [MIT LICENSE](LICENSE)
