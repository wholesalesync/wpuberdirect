jQuery(document).ready(function ($) {
    const noticeTitle = 'Important Notice',
        noticeText = `${uber.verificationImg ? '<img id="id-verification-gif" src="' + uber.verificationImg + '" alt="ID verification">' : ''}
            <br>
            <span>${uber.verificationText}</span>
        `;
    if ($('input.shipping_method:checked').val().includes('uber')) {
        Swal.fire({
            title: noticeTitle,
            html: noticeText
        })
    }
    $(document.body).on('change', 'input.shipping_method', function (e) {
        const shippingMethod = $(this).val();
        if (shippingMethod.includes('uber')) {
            Swal.fire({
                title: noticeTitle,
                html: noticeText
            })
        }
    });
    const weekDays = {
        0: 'sunday',
        1: 'monday',
        2: 'tuesday',
        3: 'wednesday',
        4: 'thursday',
        5: 'friday',
        6: 'saturday'
    };
    $(document).on('click', '.schedule', function (e) {
        e.preventDefault();
        $.magnificPopup.open({
            items: {
                src: `<div class="white-popup">
					<p>Drop off Date/Time</p>
					<input class="pickup-date" type="text" placeholder="Date">
					<input class="pickup-time" type="text" placeholder="Time">
					<input type="hidden" name="chosen_date" id="chosen_date">
					<p><button class="button alt schedule-time">Schedule</button></p>
				</div>`,
                type: 'inline'
            }
        });
        let dateToday = new Date();
        $(document).find('.pickup-date').datepicker({
            dateFormat: "mm/dd/yy",
            minDate: dateToday,
            beforeShowDay: function(date) {
                weekDay = weekDays[date.getDay()];
                if (!merchant_schedule[weekDay].start && !merchant_schedule[weekDay].end) {
                    return [false, ''];
                } else {
                    return [true, ''];
                }
            },
            onSelect: (data) => {

                let chosenDate = new Date(data)
                weekDay = weekDays[chosenDate.getDay()];

                console.log(merchant_schedule[weekDay].start);
                let time_options = {'step': 10};
                if (!(merchant_schedule[weekDay].start === '12:00 am' && merchant_schedule[weekDay].start === merchant_schedule[weekDay].end)) {
                    time_options.minTime = merchant_schedule[weekDay].start;
                    time_options.maxTime = merchant_schedule[weekDay].end;
                }
                if ($(document).find('.ui-timepicker-wrapper').length > 0) {
                    $(document).find('.pickup-time').timepicker('remove');
                    $(document).find('.pickup-time').timepicker('hide');
                }
                $(document).find('.pickup-time').val('');
                $(document).find('.pickup-time').timepicker(time_options);
            }
        });
        // let time_options = {'step': 10};
        // if ( !(merchant_schedule[weekDay].start === '12:00 am' && merchant_schedule[weekDay].start === merchant_schedule[weekDay].end) ) {
        // 	time_options.minTime = merchant_schedule[weekDay].start;
        // 	time_options.maxTime = merchant_schedule[weekDay].end;
        // }

    });
    $(document).on('click', '.pickup-time', function () {
        $(this).timepicker();
    });
    $(document).on('click', '.schedule-switcher button', function (e) {
        e.preventDefault();
        $(this).closest('.schedule-switcher').find('.active').removeClass('active');
        $(this).addClass('active');
        if ($(this).hasClass('asap')) {
            if ($(this).next().text() !== 'Schedule') {
                $(this).next().text('Schedule');
                $.ajax({
                    url: uber.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'remove_datetime',
                        security: uber.nonce
                    },
                    success: (data) => {
                        $(document.body).trigger('update_checkout');
                    }
                });
            }
        }
    });
    $(document).on('click', '.schedule-time', function (e) {
        e.preventDefault();
        const button = $(this);
        $.ajax({
            url: uber.ajax_url,
            type: 'POST',
            data: {
                action: 'uber_schedule',
                date: button.closest('.white-popup').find('.pickup-date').val(),
                time: button.closest('.white-popup').find('.pickup-time').val(),
                address_1: $('#billing_address_1').val(),
                address_2: $('#billing_address_2').val(),
                city: $('#billing_city').val(),
                state: $('#billing_state').val(),
                postcode: $('#billing_postcode').val(),
                country: $('#billing_country').val(),
                security: uber.nonce
            },
            dataType: 'json',
            beforeSend: (xhr) => {
                button.text('Scheduling...');
            },
            success: (data) => {
                if (data.status === 'success') {
                    $(document).find('.schedule-switcher .schedule').text(data.human_date);
                    $('#schedule_datetime').val(data.datetime);
                    $(document.body).trigger('update_checkout');
                    $.magnificPopup.close();
                } else {
                    $.magnificPopup.close();
                    Swal.fire({
                        text: 'Error',
                        text: data.message,
                        icon: 'error',
                        showConfirmButton: false,
                        timer: 1500
                    });
                }
            },
            complete: () => {
                button.text('Schedule');
            }
        });
    });
});
