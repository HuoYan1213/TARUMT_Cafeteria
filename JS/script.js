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

        const selectedValue = $(this).val();
        const targetDetailsId = '#' + selectedValue + '-details';

        $('.payment-details').not(targetDetailsId).slideUp(200);

        if (selectedValue && selectedValue !== 'counter') {
            $(targetDetailsId).slideDown(300);
        }
    });

    $('input[name="ewallet_provider"]').change(function(){
    });
});

function formatCardExpiry(input) {
    let value = input.value.replace(/\D/g, '');
    let month = '';
    let year = '';

    if (value.length > 0) {
        month = value.substring(0, 2);
    }
    if (value.length > 2) {
        year = value.substring(2, 4);
    }

    // Auto-add slash
    if (value.length > 2) {
        input.value = month + ' / ' + year;
    } else {
        input.value = month;
    }

    // Validation
    if (value.length >= 4) {
        const currentYear = new Date().getFullYear() % 100;
        const currentMonth = new Date().getMonth() + 1;
        const inputMonth = parseInt(month, 10);
        const inputYear = parseInt(year, 10);

        if (
            inputMonth < 1 || inputMonth > 12 ||
            inputYear < currentYear ||
            (inputYear === currentYear && inputMonth < currentMonth)
        ) {
            input.classList.add('input-error');
        } else {
            input.classList.remove('input-error');
        }
    } else {
        input.classList.remove('input-error');
    }
}