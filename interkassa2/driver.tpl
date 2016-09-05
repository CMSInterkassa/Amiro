%%include_language "_local/eshop/pay_drivers/interkassa2/driver.lng"%%

<!--#set var="settings_form" value="

    <tr>
        <td>%%ik_co_id%%:</td>
        <td><input type="text" name="ik_co_id" class="field" value="##ik_co_id##" size="40"></td>
    </tr>
        <tr>
        <td>%%secret_key%%:</td>
        <td><input type="text" name="secret_key" class="field" value="##secret_key##" size="40"></td>
    </tr>
        <tr>
        <td>%%test_key%%:</td>
        <td><input type="text" name="test_key" class="field" value="##test_key##" size="40"></td>
    </tr>

"-->

<!--#set var="checkout_form" value="
    <form name="paymentformpayanyway" action="##process_url##" method="POST">
    <input type="hidden" name="amount" value="##amount##">
    <input type="hidden" name="description" value="##description##">
    <input type="hidden" name="order" value="##order##">
    ##hiddens##
    ##if(_button_html=="1")##
    ##button##
    ##else##
    <input type="submit" name="sbmt" class="btn" value="      %%button_caption%%      " ##disabled##>
    ##endif##
    </form>
"-->

<!--#set var="pay_form" value="
    <form name="payment"
       method="post" action="https://sci.interkassa.com/" accept-charset="UTF-8">
    <input type="hidden" name="ik_co_id" value="##ik_co_id##">
    <input type="hidden" name="ik_am" value="##amount##">
    <input type="hidden" name="ik_desc" value="##order_id##">
    <input type="hidden" name="ik_pm_no" value="##order_id##">
    <input type="hidden" name="ik_cur" value="##currency##">
    <input type="hidden" name="ik_sign" value="##ik_sign##">
    <input type="hidden" name="ik_fal_u" value="##cancel##">
    <input type="hidden" name="ik_pnd_u" value="##cancel##">
    <input type="hidden" name="ik_suc_u" value="##return##">
    <input type="hidden" name="ik_ia_u" value="##callback##">
    </form>
    <script type="text/javascript">
            document.payment.submit();
    </script> 
"-->
