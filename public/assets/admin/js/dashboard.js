(function () {
    var allLabels = window.dashboard.visitorLabels;
    var allData = window.dashboard.visitorData;
    var currentRange = 30;
    var zoomOffset = 0;
    var ctx = document.getElementById("visitorChart").getContext("2d");
    var gradient = ctx.createLinearGradient(0, 0, 0, 220);
    gradient.addColorStop(0, "rgba(215,44,66,0.30)");
    gradient.addColorStop(1, "rgba(215,44,66,0.00)");

    var chart = new Chart(ctx, {
        type: "line",
        data: {
            labels: [],
            datasets: [
                {
                    label: "Visitors",
                    data: [],
                    borderColor: "rgba(215,44,66,0.85)",
                    backgroundColor: gradient,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: "rgba(215,44,66,1)",
                    tension: 0.35,
                    fill: true,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: "index",
                intersect: false,
            },
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    backgroundColor: "rgba(14,23,40,0.95)",
                    titleColor: "#f1f5f9",
                    bodyColor: "#94a3b8",
                    borderColor: "rgba(255,255,255,0.08)",
                    borderWidth: 1,
                    padding: 10,
                    callbacks: {
                        title: function (items) {
                            return formatLabel(items[0].label);
                        },
                        label: function (item) {
                            return (
                                " " +
                                item.raw +
                                " visitor" +
                                (item.raw !== 1 ? "s" : "")
                            );
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: {
                        color: "rgba(255,255,255,0.04)",
                    },
                    ticks: {
                        color: "rgba(255,255,255,0.35)",
                        font: {
                            size: 11,
                        },
                        maxTicksLimit: 10,
                        callback: function (val, idx) {
                            return formatShort(this.getLabelForValue(val));
                        },
                    },
                },
                y: {
                    grid: {
                        color: "rgba(255,255,255,0.05)",
                    },
                    ticks: {
                        color: "rgba(255,255,255,0.35)",
                        font: {
                            size: 11,
                        },
                        stepSize: 1,
                    },
                    beginAtZero: true,
                },
            },
        },
    });

    function formatLabel(dateStr) {
        var d = new Date(dateStr);
        return d.toLocaleDateString("en-GB", {
            day: "2-digit",
            month: "short",
            year: "numeric",
        });
    }

    function formatShort(dateStr) {
        var d = new Date(dateStr);
        return d.toLocaleDateString("en-GB", {
            day: "2-digit",
            month: "short",
        });
    }

    function applyWindow() {
        var total = allLabels.length;
        var window = Math.min(currentRange, total);
        /* zoomOffset shifts start; clamp so we never go out of bounds */
        var maxOffset = total - window;
        zoomOffset = Math.max(0, Math.min(zoomOffset, maxOffset));

        var start = total - window - zoomOffset;
        var end = total - zoomOffset;
        chart.data.labels = allLabels.slice(start, end);
        chart.data.datasets[0].data = allData.slice(start, end);
        chart.update("none");

        var subtitle = document.getElementById("chartSubtitle");
        if (subtitle) {
            subtitle.textContent = window + " day" + (window !== 1 ? "s" : "") + (zoomOffset > 0 ? " (shifted)" : "");
        }
    }

    window.setChartRange = function (days) {
        currentRange = days;
        zoomOffset = 0;
        document.querySelectorAll(".chart-range-btn").forEach(function (b) {
            b.classList.remove("chart-range-btn--active");
        });
        var map = {
            7: "btn7",
            30: "btn30",
            90: "btn90",
        };
        var btn = document.getElementById(map[days]);
        if (btn) btn.classList.add("chart-range-btn--active");
        applyWindow();
    };

    window.zoomChart = function (dir) {
        /* dir = 1 → zoom in (fewer days), -1 → zoom out (more days) */
        var step = dir === 1 ? -7 : 7;
        currentRange = Math.max(7, Math.min(90, currentRange + step));
        document.querySelectorAll(".chart-range-btn").forEach(function (b) {
            b.classList.remove("chart-range-btn--active");
        });
        applyWindow();
    };

    window.resetZoom = function () {
        setChartRange(30);
    };

    applyWindow();
})();
