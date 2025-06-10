jQuery(document).ready(function ($) {
    const weekDays = {
        0: 'sunday',
        1: 'monday',
        2: 'tuesday',
        3: 'wednesday',
        4: 'thursday',
        5: 'friday',
        6: 'saturday'
    };
    const dateToday = new Date();

    $(document).on('focus', '.dropoff-date, .pickup-date', function () {
        $(this).datepicker({
            dateFormat: "mm/dd/yy",
            minDate: dateToday,
        });
    });
    $(document).on('focus', '.dropoff-time, .pickup-time', function () {
        $(this).timepicker({
            step: 10
        });
    });
    $('select[multiple="multiple"]').select2();

    const createDelivery = (button, force = false) => {
        let data = {
            action: 'create_delivery',
            order_id: uber.order_id,
            item_id: button.closest('.shipping').data('order_item_id'),
            force: force
        };

        if (button.closest('.create-delivery').find('.requires_id').is(':checked'))
            data.requires_id = true;
        if (button.closest('.create-delivery').find('.requires_signature').is(':checked'))
            data.requires_signature = true;
        if (button.closest('.create-delivery').find('.ignore_quote').is(':checked'))
            data.ignore_quote = true;
        if (button.closest('.create-delivery').find('.pickup-date').val() !== '')
            data.pickup_date = button.closest('.create-delivery').find('.pickup-date').val();
        if (button.closest('.create-delivery').find('.pickup-time').val() !== '')
            data.pickup_time = button.closest('.create-delivery').find('.pickup-time').val();

        $.ajax({
            url: uber.ajax_url,
            type: 'POST',
            data: data,
            dataType: 'json',
            beforeSend: (xhr) => {
                button.text('Creating...');
            },
            success: (data) => {
                if (data.status === 'success') {
                    if (data?.message) {
                        Swal.fire({
                            icon: 'question',
                            text: data.message,
                            showDenyButton: false,
                            showCancelButton: true,
                            confirmButtonText: 'Create',
                            // denyButtonText: `Cancel`,
                        }).then((result) => {
                            console.log(result);
                            if (result.isConfirmed) {
                                createDelivery(button, true);
                            }
                        })
                    } else {
                        Swal.fire({
                            html: `<p><img height="100px" src="${data.icon}"></p>
                        <h3>Delivery created!</h3>`,
                            // icon: 'success',
                            confirmButtonText: 'Ok'
                        });
                        $('#woocommerce-order-items').find('.inside').empty();
                        $('#woocommerce-order-items').find('.inside').append(data.html);

                        // Update notes.
                        if (data.notes_html) {
                            $('ul.order_notes').empty();
                            $('ul.order_notes').append($(data.notes_html).find('li'));
                        }
                        // $('.wc-order-data-row-toggle .save-action').trigger('click');
                    }
                } else {
                    Swal.fire({
                        title: 'Error',
                        html: data.message,
                        icon: 'error',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'New delivery request'
                    }).then((result) => {
                        if (result.value) {
                            button.closest('.create-delivery').find('.ignore_quote').prop('checked', true);
                            button.trigger('click');
                        }
                    })
                }
            },
            complete: () => {
                button.text('Create Delivery');
            }
        });
    }

    $(document).on('click', '.uber.delivery-request', function (e) {
        e.preventDefault();
        createDelivery($(this));
    });
    $('.uber_day_enable').on('click', function () {
        const button = $(this);

        if (!button.is(':checked')) {
            button.next().html('');
        } else {
            $.ajax({
                url: uber.ajax_url,
                type: 'POST',
                data: {
                    action: 'working_hours',
                    day: button.data('day'),
                    is_checked: button.is(':checked')
                },
                dataType: 'json',
                success: (data) => {
                    if (data.status === 'success') {
                        button.next().html(data.html);
                    }
                },
                error: () => {
                    Swal.fire({
                        title: 'Error',
                        text: 'Server error. Please contact support.',
                        icon: 'error',
                        timer: 1500
                    });
                }
            });
        }
    });
    $(document).on('click', '.schedule-switcher div', function () {
        $(this).closest('.schedule-switcher').find('.active').removeClass('active');
        $(this).addClass('active');
        if ($(this).hasClass('asap')) {
            $('.pickup-date').val('');
            $('.pickup-time').val('');
            $('.pickup-datetime').hide();
        } else {
            $('.pickup-datetime').show();
        }
    });
    $(document).on('click', '.uber.cancel-delivery', function (e) {
        e.preventDefault();
        const button = $(this);
        let data = {
            action: 'cancel_delivery',
            delivery_id: button.data('delivery_id'),
            item_id: button.data('item_id'),
            order_id: uber.order_id
        };
        $.ajax({
            url: uber.ajax_url,
            type: 'POST',
            data: data,
            dataType: 'json',
            beforeSend: (xhr) => {
                button.text('Cancelling...');
            },
            success: (data) => {
                if (data.status === 'success') {
                    Swal.fire({
                        text: 'Delivery cancelled!',
                        icon: 'success',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    $('#woocommerce-order-items').find('.inside').empty();
                    $('#woocommerce-order-items').find('.inside').append(data.html);

                    // Update notes.
                    if (data.notes_html) {
                        $('ul.order_notes').empty();
                        $('ul.order_notes').append($(data.notes_html).find('li'));
                    }
                    // $('.wc-order-data-row-toggle .save-action').trigger('click');
                } else {
                    Swal.fire({
                        title: 'Error',
                        html: data.message,
                        icon: 'error',
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            },
            complete: () => {
                button.text('Cancel Delivery');
            }
        });
    });
    // const uber_fields = [
    // 	'#uber_sandbox_key',
    // 	'#uber_production_key',
    // 	'#uber_customer_id',
    // 	'#uber_sandbox_developer_id',
    // 	'#uber_sandbox_account_id',
    // 	'#uber_production_developer_id',
    // 	'#uber_production_account_id',
    // 	'#uber_webhook_signature_secret'
    // ],
    // deliv_fields = [
    // 	'#deliv_api_key'
    // ];
    // let provider = $('#delivery_provider').val();
    // if ( provider === 'uber' ) {
    // 	deliv_fields.forEach( (item, index) => {
    // 		$(item).closest('tr').hide();
    // 	});
    // 	uber_fields.forEach( (item, index) => {
    // 		$(item).closest('tr').show();
    // 	});
    // } else if ( provider === 'deliv' ){
    // 	uber_fields.forEach( (item, index) => {
    // 		$(item).closest('tr').hide();
    // 	});
    // 	deliv_fields.forEach( (item, index) => {
    // 		$(item).closest('tr').show();
    // 	});
    // }
    // $('#delivery_provider').on('change', function(){
    // 	provider 		 = $(this).val();
    // 	if ( provider === 'uber' ) {
    // 		deliv_fields.forEach( (item, index) => {
    // 			$(item).closest('tr').hide();
    // 		});
    // 		uber_fields.forEach( (item, index) => {
    // 			$(item).closest('tr').show();
    // 		});
    // 	} else if( provider === 'deliv' ) {
    // 		uber_fields.forEach( (item, index) => {
    // 			$(item).closest('tr').hide();
    // 		});
    // 		deliv_fields.forEach( (item, index) => {
    // 			$(item).closest('tr').show();
    // 		});
    // 	}
    // });
});
