jQuery(document).ready(function($) {
    //#approve-payment-button 
    $('#woocommerce-order-actions .button.wc-reload').click(afb_approve_payment);
    $(document).on("click", "#approve-payment-button", afb_approve_payment )
    
    function afb_approve_payment(e){
        
        let action_value = $("#woocommerce-order-actions select").val()
        console.log("clciked",action_value )
        
        if( $(this).attr("id") != "approve-payment-button" ){
             if(action_value != "approve_payment" ){
               return;
            }
        }
       
        
        e.preventDefault();

        var orderID = approvePayment.order_id;
        var nonce = approvePayment.nonce;
        var restUrl = approvePayment.rest_url;
        
        
         console.log("Approve Data", orderID, nonce,  restUrl)

        $.ajax({
            url: restUrl,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            },
            data: {
                order_id: orderID
            },
            
            success: function(response) {
                alert('Payment approved successfully.');
                console.log("res", response )
                location.reload();
            },
            error: function(response) {
                alert('Error: '+response.responseText);
                console.log("err: ", response)
            }
        });
    }
});