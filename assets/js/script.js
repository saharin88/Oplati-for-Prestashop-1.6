$(document).ready(function () {

    let olatiObj = $('#oplati'),
        checkStatusTimeout = parseInt(olatiObj.data('timeout'));

    let checkStatus = function () {
        $.ajax({
            url: '//' + location.host + '/index.php?fc=module&module=oplati&controller=payment&ajax=1',
            dataType: 'json',
            cache: false,
            success: function (resp) {

                console.log(resp);

                if (resp.success) {
                    if (resp.data.status === 0) {
                        setTimeout(checkStatus, checkStatusTimeout);
                    } else if (resp.data.status === 1) {
                        olatiObj.html('<p>' + resp.message + '</p>');
                        setTimeout(function () {
                            location.href = '/index.php?fc=module&module=oplati&controller=payment&action=success';
                        }, 3000);
                    } else {
                        olatiObj.html('<p>' + resp.message + '</p><p><button id="rePayment" class="btn btn-default">' + repeat_payment + '</button> <a class="button2" href="/index.php?fc=module&module=oplati&controller=payment&action=cancel">' + cancel_payment + '</a></p>');
                    }
                } else {
                    console.log(resp.message);
                }
            },
            error: function () {
                olatiObj.html('System error');
            }
        });
    };

    setTimeout(checkStatus, checkStatusTimeout);


    $('body').on('click', 'button#rePayment', function (e) {

        $('body').trigger('processStart');

        $.ajax({
            url: '//' + location.host + '/index.php?fc=module&module=oplati&controller=payment&ajax=1&action=rePayment',
            dataType: 'json',
            cache: false,
            success: function (resp) {

                $('body').trigger('processStop');

                if (resp.success) {
                    olatiObj.html($(resp.data).html());
                    setTimeout(checkStatus, checkStatusTimeout);
                } else {
                    alert('Error');
                    console.log(resp.message);
                }
            },
            error: function () {
                $('body').trigger('processStop');
                olatiObj.html('System error');
            }
        });


    });


});