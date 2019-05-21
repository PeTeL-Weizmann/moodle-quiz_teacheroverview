define(['jquery', 'quiz_teacheroverview/d3', 'quiz_teacheroverview/nvd3amd', 'core/str'], function ($, d3, nvd3amd, str) {
    return {
        init: function (block3j, block4j) {

            // Reset table filter if user clicks on filter status close button.
            $("#filtervalue").click(resetFilter);

            // Function resets table and filter status.
            function resetFilter() {
                $('#attempts tbody tr').not('.emptyrow').show();
                var allResults = str.get_string('all_results', 'quiz_teacheroverview');
                allResults.done(function (val) {
                    $("#filtervalue").removeClass('filtered').html(val)
                }); // Reset filter status.
                window.lastindex = -1; // Reset last clicked bar.
            }

            // SG - block 3 - bar chart.
            nv.addGraph(function () {
                var chart3 = nv.models
                    .discreteBarChart()
                    .x(function (d) {
                        return d.label;
                    })
                    .y(function (d) {
                        return d.value;
                    })
                    .staggerLabels(false)
                    .showValues(false)
                    .duration(250)
                    .showLegend(false);
                chart3.color(['#8F6FC2', '#7f3e53', '#260A55', '#260A55', '#260A55', '#260A55', '#260A55']);
                chart3.showYAxis(false);
                chart3.yAxis.tickFormat(d3.format('d'));

                d3.select('#block3 svg').datum(JSON.parse(block3j)).call(chart3);

                nv.utils.windowResize(chart3.update);

                // SG - filtering attempts table by barchart.
                chart3.discretebar.dispatch.on('elementClick', function (e) {
                    var inProgressFilterStringPr = str.get_string('stateinprogress', 'quiz');
                    var notStartedFilterStringPr = str.get_string('notstarted', 'quiz_teacheroverview');
                    $.when(inProgressFilterStringPr, notStartedFilterStringPr).then(function (inProgressFilterString, notStartedFilterString) {
                        var range = e.data.label.split(' - ');

                        var minrange = Math.min(...range);
                        var maxrange = Math.max(...range);

                        var searchParams = new URLSearchParams(window.location.search);
                        var param = searchParams.get('display');

                        var gradecell = 'td.teacheroverviewgrades';
                        var statecell = 'td.teacheroverviewstate';
                        if (window.lastindex === e.index) {
                            resetFilter(); // Reset table view if user clicks the same bar again.
                        } else {
                            $('#attempts tbody tr').not('.emptyrow').each(function () {
                                var grade = $(this).find(gradecell).text();
                                var state = $(this).find(statecell).text();
                                $(this).show(); // Reset table view - show all rows.
                                $(this).toggle(); // Hide every row.
                                if (parseInt(grade) >= minrange && parseInt(grade) < maxrange) {
                                    $(this).toggle(); // Show row that fits the condition.
                                }
                                if ((parseInt(maxrange) === 10 && parseInt(grade) === 10) || (parseInt(maxrange) === 100 && parseInt(grade) === 100)) {
                                    $(this).toggle(); // Special conditions for 10 and 100 grades.
                                }
                                if (e.index === 0 && grade.includes('-') && state.includes(inProgressFilterString)) {
                                    $(this).toggle(); // Special conditions for 'not submitted'.
                                }
                            });

                            window.lastindex = e.index; // Remember last clicked bar.
                            var filteredResults = str.get_string('filtered_results', 'quiz_teacheroverview', {'label': e.data.label, 'value': e.data.value});
                            filteredResults.done(function (val) {
                                $("#filtervalue").addClass('filtered').html(val)
                            }); // Set filter status before table.
                            $("HTML, BODY").animate({scrollTop: $("#attempts").offset().top}, 800); // Scroll to the results table body.
                        };

                    });
                });

                return chart3;
            });

            // SG - block 4 - chart pie.
            nv.addGraph(function () {
                var chart4 = nv.models
                    .pieChart()
                    .x(function (d) {
                        return d.label;
                    })
                    .y(function (d) {
                        return d.value;
                    })
                    .showLegend(false)
                    .showLabels(false) // Display pie labels.
                    .labelThreshold(0.05) // Configure the minimum slice size for labels to show up.
                    .labelType('percent') // Configure what type of data to show in the label. Can be "key", "value" or "percent".
                    .donut(true) // Turn on Donut mode. Makes pie chart look tasty!
                    .donutRatio(0.5) // Configure how big you want the donut hole size to be.
                    .color(['#260A55', '#8F6FC2', '#A864A8']);

                chart4.valueFormat(d3.format('d')); // Set only decimal value.

                d3.select('#block4 svg').datum(JSON.parse(block4j)).transition().duration(350).call(chart4);

                nv.utils.windowResize(chart4.update);

                // SG - filtering attempts table by piechart.
                chart4.pie.dispatch.on('elementClick', function (e) {
                    var inProgressFilterStringPr = str.get_string('stateinprogress', 'quiz');
                    var notStartedFilterStringPr = str.get_string('notstarted', 'quiz_teacheroverview');
                    $.when(inProgressFilterStringPr, notStartedFilterStringPr).then(function (inProgressFilterString, notStartedFilterString) {
                        var searchParams = new URLSearchParams(window.location.search);
                        var param = searchParams.get('display')

                        var gradecell = 'td.teacheroverviewgrades';
                        var statecell = 'td.teacheroverviewstate';

                        if (window.lastindex === e.index) {
                            resetFilter(); // Reset table view if user clicks the same bar again.
                        } else {
                            $('#attempts tbody tr').not('.emptyrow').each(function () {
                                var grade = $(this).find(gradecell).text();
                                var state = $(this).find(statecell).text();
                                $(this).show(); // Reset table view - show all rows.
                                $(this).toggle(); // Hide every row.
                                if (e.index === 0 && !grade.includes('-')) {
                                    $(this).toggle(); // Special conditions for 'submitted'.
                                }
                                if (e.index === 1 && grade.includes('-') && state.includes(inProgressFilterString)) {
                                    $(this).toggle(); // Special conditions for 'not submitted'.
                                }
                                if (e.index === 2 && grade.includes('-') && state.includes(notStartedFilterString)) {
                                    $(this).toggle(); // Special conditions for 'not started'.
                                }
                            })
                            window.lastindex = e.index; // Remember last clicked bar.
                            var filteredResults = str.get_string('filtered_results', 'quiz_teacheroverview', {'label': e.data.label, 'value': e.data.value});
                            filteredResults.done(function (val) {
                                $("#filtervalue").addClass('filtered').html(val)
                            }); // Set filter status before table.
                            $("HTML, BODY").animate({scrollTop: $("#attempts").offset().top}, 800); // Scroll to the results table body.
                        };
                    });
                });

                return chart4;
            });

            // SG - filtering attempts table by grades.
            $('.quiz_teacheroverview_dashboard_block2 .question_details-element').click(function (e) {
                var searchParams = new URLSearchParams(window.location.search);
                var displayparam = searchParams.get('display');

                var gradecell = 'td.teacheroverviewqsgrade' + parseInt(e.target.innerHTML);
                if ($(this).hasClass('green')) {
                    var qstatus = gradecell + ' span.' + 'user-correct';
                    var qstatusstr = 'filtered_results_succeeded_by_questions';
                } else {
                    var qstatus = gradecell + ' span.' + 'incorrect, ' + gradecell + ' span.' + 'user-partiallycorrect, ' + gradecell + ' span.' + 'notanswered';
                    var qstatusstr = 'filtered_results_failed_by_questions';
                }

                if (displayparam === 'full') {     // Filter only at full display mode (when grades are present).
                    $('#attempts tbody tr').not('.emptyrow').each(function () {
                        var ca = $(this).find($(qstatus));
                        $(this).show();         // Reset table view - show all rows.
                        $(this).toggle();       // Hide every row.
                        if (ca.length > 0) {
                            $(this).toggle();   // Show users filtered by grade.
                        }
                    });

                    var filteredResults = str.get_string(qstatusstr, 'quiz_teacheroverview', e.target.innerHTML);
                    filteredResults.done(function (val) {
                        $("#filtervalue").addClass('filtered').html(val)
                    }); // Set filter status before table.
                    $("HTML, BODY").animate({scrollTop: $("#attempts").offset().top}, 800); // Scroll to the results table body.
                } else {
                    searchParams.set('display', 'full');
                    searchParams.set('q', e.target.innerHTML);
                    window.location.href = window.location.origin + window.location.pathname + '?' + searchParams.toString(); // Go to full display mode to filter by grades.
                }
            });

            // Go to full display mode to filter by grades.
            var searchParams = new URLSearchParams(window.location.search);
            var qparam = searchParams.get('q');
            var displayparam = searchParams.get('display');
            if (qparam && displayparam === 'full') {
                var t = $('.quiz_teacheroverview_dashboard_block2 .question_details-element#dq' + qparam).trigger("click");
            }

        } // init end
    };
});
