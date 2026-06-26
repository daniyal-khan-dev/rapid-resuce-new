(function () {
    "use strict";

    // HTML escape
    function esc(s) {
        return String(s || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    // Ambulance type labels
    var AMB_TYPES = {
        1: "Basic Life Support",
        2: "Advanced Life Support",
        3: "Critical Care",
        4: "Neonatal",
        5: "Bariatric",
    };

    // Build ambulance slide HTML
    function buildAmbulanceSlide(a) {
        var features = [];
        try {
            features = JSON.parse(a.card_features);
        } catch (ex) {}
        if (!Array.isArray(features)) {
            features = String(a.card_features || "").split(",").map(function (f) {
                return f.trim();
            }).filter(Boolean);
        }
        features = features.slice(0, 4);

        var imgHtml = a.card_image
            ? '<img src="/assets/user/img/fleet/' +
              esc(a.card_image) +
              '" alt="' +
              esc(a.card_title || a.vehicle_number) +
              '">'
            : '<img src="/assets/user/img/other/ambulance.png" alt="' +
              esc(a.vehicle_number) +
              '">';

        var featHtml = "";
        if (features.length) {
            featHtml = '<div class="rr-fleet-card__badge">';
            features.forEach(function (f) {
                featHtml +=
                    '<span><i class="fas fa-check-circle me-1" style="color:var(--rr-primary)"></i>' +
                    esc(f) +
                    "</span>";
            });
            featHtml += "</div>";
        }

        var rating = parseFloat(a.card_rating || 0);
        var ratingHtml = "";
        if (rating) {
            ratingHtml = '<div class="rr-fleet-card__rating">';
            for (var i = 1; i <= 5; i++) {
                if (i <= Math.floor(rating))
                    ratingHtml += '<i class="fas fa-star"></i>';
                else if (i - rating < 1)
                    ratingHtml += '<i class="fas fa-star-half-alt"></i>';
                else ratingHtml += '<i class="far fa-star"></i>';
            }
            ratingHtml +=
                '<span class="text-muted">' +
                rating +
                (a.card_trips ? " &middot; " + a.card_trips + " trips" : "") +
                "</span></div>";
        }

        var typeLabel = AMB_TYPES[String(a.type)] || "Type " + a.type;

        return (
            '<div class="rr-slide" data-ambulance-id="' +
            a.id +
            '">' +
            '<div class="rr-fleet-card">' +
            '<div class="rr-fleet-card__img">' +
            imgHtml +
            "</div>" +
            '<div class="rr-fleet-card__body">' +
            '<span class="rr-fleet-card__type">' +
            esc(typeLabel) +
            "</span>" +
            "<h4>" +
            esc(a.card_title || a.vehicle_number) +
            "</h4>" +
            "<p>" +
            esc(
                a.card_description ||
                    "Fully equipped unit ready for any emergency.",
            ) +
            "</p>" +
            featHtml +
            ratingHtml +
            "</div>" +
            "</div>" +
            "</div>"
        );
    }

    // Build testimonial slide HTML
    function buildTestimonialSlide(t) {
        var stars = "";
        for (var i = 1; i <= 5; i++) {
            stars +=
                i <= t.rating
                    ? '<i class="fas fa-star"></i>'
                    : '<i class="far fa-star"></i>';
        }
        return (
            '<div class="rr-slide" data-testimonial-id="' +
            t.id +
            '">' +
            '<div class="rr-review-card">' +
            '<div class="rr-review-stars">' +
            stars +
            "</div>" +
            '<p class="rr-review-text">&ldquo;' +
            esc(t.content) +
            "&rdquo;</p>" +
            '<div class="rr-reviewer">' +
            '<div class="rr-reviewer-avatar">' +
            esc(
                String(t.name || "?")
                    .charAt(0)
                    .toUpperCase(),
            ) +
            "</div>" +
            "<div>" +
            '<div class="rr-reviewer-name">' +
            esc(t.name) +
            "</div>" +
            '<div class="rr-reviewer-role">' +
            esc(t.role || "") +
            "</div>" +
            "</div>" +
            "</div>" +
            "</div>" +
            "</div>"
        );
    }

    //  Rebuild fleet slider from fresh server data
    function rebuildFleetSlider(items) {
        var section = document.getElementById("ambulances");
        if (!section) return;
        var container = section.querySelector(".container");
        if (!container) return;

        var oldWrap = document.getElementById("fleetSlider");
        var oldCtrl = oldWrap ? oldWrap.nextElementSibling : null;
        if (
            oldCtrl &&
            oldCtrl.classList &&
            oldCtrl.classList.contains("rr-slider-controls")
        )
            oldCtrl.remove();
        if (oldWrap) oldWrap.remove();

        container
            .querySelectorAll(".col-12.text-center")
            .forEach(function (el) {
                el.remove();
            });

        if (window.rrSliders) delete window.rrSliders["fleetSlider"];

        if (!items || !items.length) {
            container.insertAdjacentHTML(
                "beforeend",
                '<div class="col-12 text-center py-4" style="color:var(--rr-text-light)">No Ambulances listed yet.</div>',
            );
            return;
        }

        var wrapDiv = document.createElement("div");
        wrapDiv.className = "rr-slider-wrap";
        wrapDiv.id = "fleetSlider";
        var trackDiv = document.createElement("div");
        trackDiv.className = "rr-slider-track";
        trackDiv.innerHTML = items.map(buildAmbulanceSlide).join("");
        wrapDiv.appendChild(trackDiv);
        container.appendChild(wrapDiv);

        var ctrlDiv = document.createElement("div");
        ctrlDiv.className = "rr-slider-controls";
        ctrlDiv.innerHTML =
            '<button class="rr-slider-nav prev" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>' +
            '<div class="rr-slider-dots" id="fleetDots"></div>' +
            '<button class="rr-slider-nav next" aria-label="Next"><i class="fas fa-chevron-right"></i></button>';
        container.appendChild(ctrlDiv);

        if (typeof rrInitSlider === "function") rrInitSlider("fleetSlider");
    }

    // Rebuild reviews slider from fresh server data
    function rebuildReviewsSlider(items) {
        var section = document.getElementById("testimonials");
        if (!section) return;
        var container = section.querySelector(".container");
        if (!container) return;

        var oldWrap = document.getElementById("reviewsSlider");
        var oldCtrl = oldWrap ? oldWrap.nextElementSibling : null;
        if (
            oldCtrl &&
            oldCtrl.classList &&
            oldCtrl.classList.contains("rr-slider-controls")
        )
            oldCtrl.remove();
        if (oldWrap) oldWrap.remove();

        container
            .querySelectorAll(".col-12.text-center")
            .forEach(function (el) {
                el.remove();
            });

        if (window.rrSliders) delete window.rrSliders["reviewsSlider"];

        if (!items || !items.length) {
            container.insertAdjacentHTML(
                "beforeend",
                '<div class="col-12 text-center py-4" style="color:var(--rr-text-light)">No Reviews listed yet.</div>',
            );
            return;
        }

        var wrapDiv = document.createElement("div");
        wrapDiv.className = "rr-slider-wrap";
        wrapDiv.id = "reviewsSlider";
        var trackDiv = document.createElement("div");
        trackDiv.className = "rr-slider-track";
        trackDiv.innerHTML = items.map(buildTestimonialSlide).join("");
        wrapDiv.appendChild(trackDiv);
        container.appendChild(wrapDiv);

        var ctrlDiv = document.createElement("div");
        ctrlDiv.className = "rr-slider-controls";
        ctrlDiv.innerHTML =
            '<button class="rr-slider-nav prev" aria-label="Previous"><i class="fas fa-chevron-left"></i></button>' +
            '<div class="rr-slider-dots" id="reviewsDots"></div>' +
            '<button class="rr-slider-nav next" aria-label="Next"><i class="fas fa-chevron-right"></i></button>';
        container.appendChild(ctrlDiv);

        if (typeof rrInitSlider === "function") rrInitSlider("reviewsSlider");
    }

    // Services handler
    function handleService(e) {
        var grid = document.getElementById("servicesGrid");
        if (!grid) return;
        var d = e.data;

        if (e.action === "added") {
            if (d.status != 1) return;
            var emptyEl = grid.querySelector(".col-12");
            if (emptyEl) emptyEl.remove();
            grid.insertAdjacentHTML(
                "beforeend",
                '<div class="col-md-6 col-lg-4" data-service-id="' +
                    d.id +
                    '">' +
                    '<div class="rr-card">' +
                    '<div class="rr-card__icon"><i class="' +
                    esc(d.icon) +
                    '"></i></div>' +
                    "<h4>" +
                    esc(d.title) +
                    "</h4>" +
                    "<p>" +
                    esc(d.description) +
                    "</p>" +
                    "</div>" +
                    "</div>",
            );
        } else if (e.action === "updated") {
            var existing = grid.querySelector(
                '[data-service-id="' + d.id + '"]',
            );
            if (d.status == 1) {
                var newHtml =
                    '<div class="col-md-6 col-lg-4" data-service-id="' +
                    d.id +
                    '">' +
                    '<div class="rr-card">' +
                    '<div class="rr-card__icon"><i class="' +
                    esc(d.icon) +
                    '"></i></div>' +
                    "<h4>" +
                    esc(d.title) +
                    "</h4>" +
                    "<p>" +
                    esc(d.description) +
                    "</p>" +
                    "</div>" +
                    "</div>";
                if (existing) {
                    existing.outerHTML = newHtml;
                } else {
                    var emptyEl2 = grid.querySelector(".col-12");
                    if (emptyEl2) emptyEl2.remove();
                    grid.insertAdjacentHTML("beforeend", newHtml);
                }
            } else {
                if (existing) {
                    existing.remove();
                    if (!grid.querySelector("[data-service-id]"))
                        grid.innerHTML =
                            '<div class="col-12 text-center py-4" style="color:var(--rr-text-light)">No services listed yet.</div>';
                }
            }
        } else if (e.action === "deleted") {
            var el = grid.querySelector('[data-service-id="' + d.id + '"]');
            if (el) {
                el.remove();
                if (!grid.querySelector("[data-service-id]"))
                    grid.innerHTML =
                        '<div class="col-12 text-center py-4" style="color:var(--rr-text-light)">No services listed yet.</div>';
            }
        }
    }

    // Ambulances handler
    function handleAmbulance() {
        fetch(window.routesrtAmbulances)
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                rebuildFleetSlider(data.items || []);
            })
            .catch(function () {});
    }

    // Testimonials handler 
    function handleTestimonial() {
        fetch(window.routes.rtTestimonials)
            .then(function (r) {
                return r.json();
            })
            .then(function (data) {
                rebuildReviewsSlider(data.items || []);
            })
            .catch(function () {});
    }

    // FAQs handler
    function handleFaq(e) {
        var accordion = document.getElementById("faqAccordion");
        if (!accordion) return;
        var d = e.data;

        if (e.action === "added") {
            if (d.status != 1) return;
            var emptyItem = accordion.querySelector(
                ".accordion-item:not([data-faq-id])",
            );
            if (emptyItem) emptyItem.remove();
            var count = accordion.querySelectorAll("[data-faq-id]").length;
            var num = String(count + 1).padStart(2, "0");
            var headId = "faqHead_rt_" + d.id;
            var bodyId = "faqBody_rt_" + d.id;
            accordion.insertAdjacentHTML(
                "beforeend",
                '<div class="accordion-item rr-accordion__item" data-faq-id="' +
                    d.id +
                    '">' +
                    '<h2 class="accordion-header" id="' +
                    headId +
                    '">' +
                    '<button class="accordion-button rr-accordion__btn collapsed" type="button" ' +
                    'data-bs-toggle="collapse" data-bs-target="#' +
                    bodyId +
                    '" ' +
                    'aria-expanded="false" aria-controls="' +
                    bodyId +
                    '">' +
                    '<span class="rr-accordion__num">' +
                    num +
                    "</span>" +
                    esc(d.question) +
                    "</button>" +
                    "</h2>" +
                    '<div id="' +
                    bodyId +
                    '" class="accordion-collapse collapse" ' +
                    'aria-labelledby="' +
                    headId +
                    '" data-bs-parent="#faqAccordion">' +
                    '<div class="accordion-body rr-accordion__body">' +
                    esc(d.answer) +
                    "</div>" +
                    "</div>" +
                    "</div>",
            );
        } else if (e.action === "updated") {
            var el = accordion.querySelector('[data-faq-id="' + d.id + '"]');
            if (el) {
                if (d.status == 1) {
                    var btn = el.querySelector(".rr-accordion__btn");
                    var body = el.querySelector(".accordion-body");
                    if (btn) btn.lastChild.textContent = " " + d.question;
                    if (body) body.textContent = d.answer;
                } else {
                    el.remove();
                    if (!accordion.querySelector("[data-faq-id]"))
                        accordion.innerHTML =
                            '<div class="accordion-item rr-accordion__item"><p style="padding:16px;color:var(--rr-text-light)">No FAQs available yet.</p></div>';
                }
            } else if (d.status == 1) {
                var count2 = accordion.querySelectorAll("[data-faq-id]").length;
                var num2 = String(count2 + 1).padStart(2, "0");
                var headId2 = "faqHead_rt_" + d.id;
                var bodyId2 = "faqBody_rt_" + d.id;
                accordion.insertAdjacentHTML(
                    "beforeend",
                    '<div class="accordion-item rr-accordion__item" data-faq-id="' +
                        d.id +
                        '">' +
                        '<h2 class="accordion-header" id="' +
                        headId2 +
                        '">' +
                        '<button class="accordion-button rr-accordion__btn collapsed" type="button" ' +
                        'data-bs-toggle="collapse" data-bs-target="#' +
                        bodyId2 +
                        '" ' +
                        'aria-expanded="false" aria-controls="' +
                        bodyId2 +
                        '">' +
                        '<span class="rr-accordion__num">' +
                        num2 +
                        "</span>" +
                        esc(d.question) +
                        "</button>" +
                        "</h2>" +
                        '<div id="' +
                        bodyId2 +
                        '" class="accordion-collapse collapse" ' +
                        'aria-labelledby="' +
                        headId2 +
                        '" data-bs-parent="#faqAccordion">' +
                        '<div class="accordion-body rr-accordion__body">' +
                        esc(d.answer) +
                        "</div>" +
                        "</div>" +
                        "</div>",
                );
            }
        } else if (e.action === "deleted") {
            var el2 = accordion.querySelector('[data-faq-id="' + d.id + '"]');
            if (el2) {
                el2.remove();
                if (!accordion.querySelector("[data-faq-id]"))
                    accordion.innerHTML =
                        '<div class="accordion-item rr-accordion__item"><p style="padding:16px;color:var(--rr-text-light)">No FAQs available yet.</p></div>';
            }
        }
    }

    // Branches handler 
    function handleBranch(e) {
        var wrap = document.getElementById("branchesWrap");
        if (!wrap) return;
        var d = e.data;

        function buildBranch(b) {
            var emailRow = b.email
                ? '<div class="rr-branch__row">'
                  + '<i class="fas fa-envelope"></i>'
                  + '<span>Email:</span>'
                  + esc(b.email)
                  + '</div>'
                : '';
            return (
                '<div class="rr-branch" data-branch-id="' +
                b.id +
                '">' +
                '<h5><i class="fas fa-building"></i> ' +
                esc(b.name) +
                "</h5>" +
                '<div class="rr-branch__row">' +
                '<i class="fas fa-map-marker-alt"></i>' +
                "<span>Address:</span>" +
                esc(b.address) +
                "</div>" +
                '<div class="rr-branch__row">' +
                '<i class="fas fa-phone-alt"></i>' +
                "<span>Telephone:</span>" +
                esc(b.phone) +
                "</div>" +
                emailRow +
                "</div>"
            );
        }

        if (e.action === "added") {
            var emptyBranch = wrap.querySelector(
                ".rr-branch:not([data-branch-id])",
            );
            if (emptyBranch) emptyBranch.remove();
            wrap.insertAdjacentHTML("beforeend", buildBranch(d));
        } else if (e.action === "updated") {
            var el = wrap.querySelector('[data-branch-id="' + d.id + '"]');
            if (el) {
                el.outerHTML = buildBranch(d);
            } else {
                var emptyBranch2 = wrap.querySelector(
                    ".rr-branch:not([data-branch-id])",
                );
                if (emptyBranch2) emptyBranch2.remove();
                wrap.insertAdjacentHTML("beforeend", buildBranch(d));
            }
        } else if (e.action === "deleted") {
            var el2 = wrap.querySelector('[data-branch-id="' + d.id + '"]');
            if (el2) {
                el2.remove();
                if (!wrap.querySelector("[data-branch-id]"))
                    wrap.innerHTML =
                        '<div class="rr-branch" style="text-align:center;color:var(--rr-text-light);"><p>No branch locations available.</p></div>';
            }
        }
    }

    // Connect and subscribe
    document.addEventListener("DOMContentLoaded", function () {
        if (typeof Pusher === "undefined" || !window._rrReverb) return;
        var cfg = window._rrReverb;
        try {
            var pusher = new Pusher(cfg.key, {
                wsHost: cfg.wsHost,
                wsPort: cfg.wsPort,
                wssPort: cfg.wssPort,
                forceTLS: cfg.forceTLS,
                enabledTransports: cfg.enabledTransports,
                cluster: "mt1",
                disableStats: true,
            });

            var ch = pusher.subscribe("content.updates");

            ch.bind("content.updated", function (e) {
                switch (e.module) {
                    case "service": handleService(e); break;
                    case "ambulance": handleAmbulance(); break;
                    case "testimonial": handleTestimonial(); break;
                    case "faq": handleFaq(e); break;
                    case "branch": handleBranch(e); break;
                }
            });

            pusher.connection.bind("connected", function () {
                console.log(
                    "[Reverb] User connected — content.updates channel",
                );
            });
            pusher.connection.bind("error", function (err) {
                console.warn("[Reverb] Content channel error:", err);
            });
        } catch (err) {
            console.warn("[Reverb] Content channel setup failed:", err.message);
        }
    });
})();
