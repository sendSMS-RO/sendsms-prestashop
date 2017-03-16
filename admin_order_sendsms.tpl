<form id="sendsms_order_form" class="defaultForm form-horizontal" method="post" novalidate="">
    <div id="formSendSmsPanel" class="panel">
        <div class="panel-heading">
            Trimite SMS
        </div>
        {if isset($sendsms_msg)}
            <div class="alert {if ! $sendsms_error}alert-success{else}alert-danger{/if}">
                {$sendsms_msg}
            </div>
        {/if}
        <div class="form-wrapper">
            <div class="form-group">
                <label class="control-label col-lg-3 required">
                    Numar de telefon
                </label>
                <div class="col-lg-9">
                    <input type="text" name="sendsms_phone" id="sendsms_phone" value="" class="" size="40" required="required">
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-3 required">
                    Mesaj
                </label>
                <div class="col-lg-9">
                    <textarea name="sendsms_message" id="sendsms_message" rows="7" class="textarea-autosize" style="overflow: hidden; word-wrap: break-word; resize: none; height: 133px;"></textarea>
                </div>
            </div>
        </div>

        <div class="panel-footer">
            <button type="submit" value="1" id="sendsms_test_form_submit_btn" name="submitsendsms_order" class="button">
                <i class="process-icon-save"></i> Trimite
            </button>
        </div>
    </div>
</form>
