{*
* Team Paylike
*
*  @author     Team Paylike
*  @copyright  Team Paylike
*  @license    MIT license: https://opensource.org/licenses/MIT
*}
<script type="text/javascript">
$(document).ready(function() {
	var appendEl;
	appendEl = $('select[name=id_order_state]').parents('form').after($('<div/>'));
	$("#paylike").appendTo(appendEl);
});
</script>
<div id="paylike" class="row" style="margin-top:5%;">
	<div class="panel">
		<form action="{$link->getAdminLink('AdminOrders', false)|escape:'htmlall':'UTF-8'}&id_order={$id_order|escape:'htmlall':'UTF-8'}&vieworder&token={$order_token|escape:'htmlall':'UTF-8'}" method="post">
			<fieldset {if $ps_version < 1.5}style="width: 400px;"{/if}>
				<legend class="panel-heading"><img src="../img/admin/money.gif" alt="" />{l s='Paylike Payment Refund' mod='paylike'}</legend>
				<div class="form-group margin-form">
					<input class="form-group" name="paylike_amount_to_refund" placeholder="{l s='Amount to refund' mod='paylike'}" type="text"/>
					<input class="pull-right btn btn-default" name="SubmitPaylikeRefund" type="submit" class="btn btn-primary" value="{l s='Process Refund' mod='paylike'}"/>
				</div>
			</fieldset>
		</form>
	</div>
</div>