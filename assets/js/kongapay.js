jQuery( function( $ ) {

    window.onload = wcKongaPayGatewayFormHandler();

    function wcKongaPayGatewayFormHandler() {
        KPG.setup({
            "hash": kongapay_params.hash,
            "amount": kongapay_params.amount,
            "description": kongapay_params.description,
            "email": kongapay_params.email,
            "merchantId": kongapay_params.merchant_id,
            "reference": kongapay_params.reference,
            "firstname" : kongapay_params.first_name,
            "lastname" : kongapay_params.last_name,
            "phone" : kongapay_params.phone,
            "metadata": kongapay_params.metadata,
            "enableFrame": kongapay_params.enable_frame,
            "callback" : kongapay_params.callback_url,
            "selectedChannelIdentifier" : "",
            "customerId" : kongapay_params.customer_id,
        });

        return false;
    }
} );