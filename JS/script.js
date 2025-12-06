function displayToast(message, type = 'success') {
    let icon_html = '';
    
    if (type === 'success') {
        icon_html = '<i class="fa-solid fa-check-circle" style="color: #8A9A5B;"></i>';
    } 
    else {
        icon_html = '<i class="fa-solid fa-triangle-exclamation" style="color: #A64B2A;"></i>';
    }

    let $toast = $(`
        <div class="toast ${type}">
            ${icon_html}
            <span>${message}</span>
        </div>
    `);

    $('body').append($toast)
    setTimeout(() => {
        $toast.addClass('fade-out');
        setTimeout(() => {
            $toast.remove();
        }, 500);
    }, 3000);
}

function displayConfirmationToast(message, onConfirm, onCancel) {
    let $toast = $(`
        <div class="toast confirmation">
            <i class="fa-solid fa-circle-question" style="color: #4A90E2;"></i>
            <span>${message}</span>
            <div class="toast-buttons">
                <button class="confirm-button"><i class="fa-solid fa-check"></i></button>
                <button class="cancel-button"><i class="fa-solid fa-times"></i></button>
            </div>
        </div>
    `);

    $toast.find('.confirm-button').on('click', function() {
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
        $toast.remove();
    });

    $toast.find('.cancel-button').on('click', function() {
        if (typeof onCancel === 'function') {
            onCancel();
        }
        $toast.remove();
    });

    $('body').append($toast);
}

$(document).on('click', '.quantity-button .add-button, .quantity-button .minus-button', function() {
    const $button = $(this);
    const $input = $button.siblings('input');
    let value = parseInt($input.val(), 10);

    if ($button.find('.fa-plus').length > 0) {
        if (value < 99) {
            value++;
        }
    } else if ($button.find('.fa-minus').length > 0) {
        if (value > 1) {
            value--;
        }
    }
    $input.val(value);
});

$(document).on('change', '.quantity-button input', function() {
    let value = parseInt($(this).val(), 10);
    if (isNaN(value) || value < 1) {
        $(this).val(1);
    } else if (value > 99) {
        $(this).val(99);
    }
});

$(document).ready(function() {
    
    $('input[name="method-type"]').change(function() {
        
        $('.payment-card').removeClass('active');
        $(this).closest('.payment-card').addClass('active');

        $('.payment-details').slideUp(200);

        let selectedValue = $(this).val();

        if (selectedValue === 'e-wallet') {
            $('#e-wallet-details').slideDown(300);
        } 
        else if (selectedValue === 'card') {
            $('#card-details').slideDown(300);
        }
        else if (selectedValue === 'fpx') {
            $('#fpx-details').slideDown(300);
        }
    });

    $('input[name="ewallet_provider"]').change(function(){
    });
});