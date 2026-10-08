# WhatsApp

WhatsApp is not connected.

`WHATSAPP_PROVIDER`, `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_VERIFY_TOKEN`, and `WHATSAPP_APP_SECRET` are reserved in the environment and `config/services.php`. The application does not receive webhooks or send messages.

The later provider interface is expected to verify webhook signatures, resolve an organization integration, and create service requests through application services. Those services do not exist yet.
