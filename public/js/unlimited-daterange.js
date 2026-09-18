(function (window, $) {
    function parseDateRangeValue(value) {
        const text = String(value || '').trim();
        if (!text || typeof moment !== 'function') {
            return null;
        }

        const parts = text.split(/\s*[~–]\s*|\s+-\s+/);
        if (parts.length !== 2) {
            return null;
        }

        const start = moment(parts[0].trim(), ['DD-MM-YYYY', 'D-M-YYYY'], true);
        const end = moment(parts[1].trim(), ['DD-MM-YYYY', 'D-M-YYYY'], true);
        if (!start.isValid() || !end.isValid()) {
            return null;
        }

        return { start: start.startOf('day'), end: end.startOf('day') };
    }

    window.bindUnlimitedDateRange = function (selector, extraOptions) {
        const $el = $(selector);
        if (!$el.length || typeof $el.daterangepicker !== 'function') {
            return $el;
        }

        const parsed = parseDateRangeValue($el.val());
        const options = $.extend(true, {
            autoUpdateInput: false,
            locale: {
                format: 'DD-MM-YYYY',
                separator: ' - ',
                applyLabel: 'Terapkan',
                cancelLabel: 'Batal',
                fromLabel: 'Dari',
                toLabel: 'Ke',
                customRangeLabel: 'Kustom',
                daysOfWeek: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
                monthNames: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
                firstDay: 0,
            },
            maxDate: moment(),
        }, extraOptions || {});

        if (parsed) {
            options.startDate = parsed.start;
            options.endDate = parsed.end.isAfter(moment()) ? moment() : parsed.end;
        }

        $el.daterangepicker(options);
        $el.off('apply.daterangepicker hide.daterangepicker cancel.daterangepicker');
        $el.on('apply.daterangepicker', function (ev, picker) {
            $(this).val(
                picker.startDate.format('DD-MM-YYYY') + ' - ' + picker.endDate.format('DD-MM-YYYY')
            );
        });
        $el.on('cancel.daterangepicker', function () {
            $(this).val('');
        });

        return $el;
    };
})(window, jQuery);
