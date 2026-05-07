jQuery(document).ready(function ($) {
    function calculateBMI() {
        const weight = parseFloat($('#weight').val()); // adjust ID to match your form
        const heightCm = parseFloat($('#height_cm').val()); // adjust if different
        const bmiField = $('#bmi'); // adjust ID to match your form
        console.log(weight);

        if (!isNaN(weight) && !isNaN(heightCm) && heightCm > 0) {
            const heightM = heightCm / 100;
            const bmi = weight / (heightM * heightM);
            bmiField.val(bmi.toFixed(1));
        } else {
            bmiField.val('');
        }
    }

    $('#weight, #height_cm').on('input change', calculateBMI);
    calculateBMI(); // run once on load in case fields are pre-filled
});
