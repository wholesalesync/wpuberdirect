jQuery(document).ready(function($){
	get_deliveries('pending');
	$('.change-status').on('click', function(e){
		e.preventDefault();
		$('.change-status.active').removeClass('active');
		$(this).addClass('active');
		const status = $(this).data('status');
		get_deliveries(status);
	});
	function get_deliveries(status) {
		$.ajax({
			url: deliveries.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'get_deliveries',
				status: status,
				customer_id: deliveries.customer_id,
				api_key: deliveries.api_key,
				security: deliveries.nonce
			},
			beforeSend: (xhr) => {
				$('#result img').show();
			},
			success: (data) => {
				console.log(data);
				$('#result tbody').html(data.data.html);
			},
			complete: (data) => {
				$('#result img').hide();
			},
			error: (data) => {
				console.log(data);
			}
		});
	}
	$('#result').on('click', '.cancel-delivery', function(e){
		e.preventDefault();
		const button 	  = $(this),
			  delivery_id = button.data('id');

		$.ajax({
			url: deliveries.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'dash_cancel_delivery',
				delivery_id: delivery_id,
				security: deliveries.nonce
			},
			beforeSend: (xhr) => {
				button.text('Cancelling...');
			},
			success: (data) => {
				if ( data.status === 'success' ) {
					button.closest('tr').remove();
				} else {
					Swal.fire({
						title: 'Error',
						text: data.message,
						icon: 'error',
						timer: 1500
					});
				}
			},
			complete: (data) => {
				button.text('Cancel');
			},
			error: (data) => {
				console.log(data);
			}
		});
	});
});