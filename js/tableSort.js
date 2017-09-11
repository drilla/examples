    /** сортировка отправлений */
    (function () {
        var $tableDirect  = $('table.schedule-table').first();
        var $tablePassing = $('.passing table.schedule-table');
        if (!$tableDirect.length) {
            return;
        }

        var $sortControls = $tableDirect.find('.sortable-col[data-sort-parameter]');

        /* вернет отсортированный по parameter объект $rows в порядке order = asc | desc
        вернет null если сортировка не имела эффекта, и порядок элементов не изменился */
        var sort = function ($rows, parameter, order) {

            var hasChanges = false;
            var aValue, bValue;

            $rows.sort(function (a, b) {

                if (parameter === 'sort-price') {
                    /* поскольку цена состоит из двух - минимум и максимум, сортировку по возрастанию проводим по минимальной
                     сортировка по убыванию не нужна, но она есть и будет по максимальной границе */
                    if (order === 'asc') {
                        aValue = $(a).data(parameter + '-min');
                        bValue = $(b).data(parameter + '-min');
                    } else {
                        aValue = $(a).data(parameter + '-max');
                        bValue = $(b).data(parameter + '-max');
                    }
                } else {
                    aValue = $(a).data(parameter);
                    bValue = $(b).data(parameter);
                }

                /* nulls last! */
                if (aValue === undefined) {
                    switch (order) {
                        case 'asc' : aValue = Number.MAX_VALUE; break;
                        case 'desc' : aValue = Number.MIN_VALUE; break;
                    }
                }

                if (bValue === undefined) {
                    switch (order) {
                        case 'asc' : bValue = Number.MAX_VALUE; break;
                        case 'desc' : bValue = Number.MIN_VALUE; break;
                    }
                }

                if (aValue == bValue) return 0;

                hasChanges = true;
                switch (order) {
                    case 'asc' : return (aValue < bValue) ? -1 : 1;
                    case 'desc' : return (aValue < bValue) ? 1 : -1;
                }
            });

            if (!hasChanges) return null;

            return $rows;
        };

        var getActiveSortParam = $tableDirect.find('.sortable-col.active').data('sortParameter');

        /** сортирует указанную таблицу */
        var sortTable = function ($table, sortParameter, order) {
            var $departureRows  = $table.find('tbody tr.schedule-departure-row');
            var $sortedDepartureRows;
            var dropDownInfoRows = {};
            var $newRowsSet = [];
            var $fixedRows = $table.find('tbody tr[data-best-position]');

            $departureRows.each(function() {
                var self        = $(this);
                var departureId = self.data('departureId');

                /**
                 * в случае с продажными направлениями, имеющими в городе старта и городе прибытия по 2 остановки,
                 * departure_id не будет уникальным. Использовать надо уникальный ключ
                 */
                var uniqueKey = self.data('uniqueKey');

                dropDownInfoRows[uniqueKey] = $table.find('tr[data-unique-key="' + uniqueKey + '"]:not(.schedule-departure-row)');
            });

            $sortedDepartureRows = sort($departureRows, sortParameter, order);

            if ($sortedDepartureRows !== null) {
                /* вынимаем из таблицы элементы */
                $sortedDepartureRows.detach();

                for (var item in dropDownInfoRows) {
                    dropDownInfoRows[item].detach();
                }

                $fixedRows.detach();

                /* добавляем не подлежащие сортировке элементы */
                $fixedRows.each(function () {
                    var bestPosition = $(this).data('bestPosition');

                    /* если не можем поместить элемент туда куда он хочет - пихаем в конец */
                    var position = $sortedDepartureRows.length > bestPosition ? bestPosition : $sortedDepartureRows.length;

                    $sortedDepartureRows.splice(position, 0, this);
                });

                /* необходимо прикрепить выпадушки на нужные места */
                $sortedDepartureRows.each(function () {
                    $newRowsSet.push($(this));

                    /* пытаемся найти  и добавить выпадушку */
                    var uniqueKey      = $(this).data('uniqueKey');
                    if ((dropDownInfoRows[uniqueKey] != undefined)) {
                        dropDownInfoRows[uniqueKey].each(function() {
                            $newRowsSet.push($(this));
                        });
                    }
                });

                /* вставляем в таблицу элементы в новом порядке */
                $table.find('tbody').append($newRowsSet);
            } else {
                /* если порядок не изменился не трогаем таблицу */
            }
        };

        $sortControls.click(function ($event) {
            var $sortControl = $(this);
            var sortParameter = $sortControl.data('sortParameter');
            var isActive = $sortControl.hasClass('active');
            var order;

            $event.stopImmediatePropagation();

            if (isActive) {
                order = $sortControl.hasClass('asc') ? 'desc': 'asc';
            } else {
                order = $sortControl.hasClass('asc') ? 'asc' : 'desc';
            }

            $sortControl.removeClass('asc desc').addClass(order);

            sortTable($tableDirect, sortParameter, order);
            sortTable($tablePassing, sortParameter, order);

            $sortControls.removeClass('active');
            $sortControls.filter('[data-sort-parameter="'+ $sortControl.data('sortParameter') +'"]').addClass('active');
        });
    }());
