<?php if ( $item->get_method_id() === 'uber' ):
	$delivery_id = $item->get_meta( 'Delivery id' );
	?>
	<h3>Delivery tools</h3>
	<div class="create-delivery">
			<label><input type="checkbox" name="requires_id" class="requires_id" checked disabled> Requires ID</label>
			<label><input type="checkbox" name="requires_signature" class="requires_signature" checked disabled> Requires Signature</label>
			<label><input type="checkbox" name="ignore_quote" class="ignore_quote"> Ignore quote</label>
			<div class="schedule-switcher">
				<div class="asap active">ASAP</div>
				<div class="schedule">Schedule</div>
			</div>
			<div class="datetime pickup-datetime" style="display: none;">
				<input class="pickup-date" type="text" placeholder="Pickup Date">
				<input class="pickup-time" type="text" placeholder="Pickup Time">
			</div>
		<div class="delivery-submit">
			<button type="submit" class="uber button save_order button-primary delivery-request<?php if ( $delivery_id ) echo ' exists'; ?>">Create Delivery</button>
			<?php if ( $delivery_id ): ?>
				<button type="submit" class="uber button save_order button-primary cancel-delivery" data-delivery_id="<?php echo esc_attr($delivery_id); ?>" data-item_id="<?php echo esc_attr($item->get_id()); ?>">Cancel Delivery</button>
<!--                <a class="button postmates-dashboard" target="_blank" href="https://partner.postmates.com/dashboard/home/deliveries/--><?php //echo $delivery_id; ?><!--">-->
<!--                    <img src="--><?php //echo WC_Postmates\Postmates::$small_icon; ?><!--">-->
<!--                    <span>See Order in Postmates</span>-->
<!--                </a>-->
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>