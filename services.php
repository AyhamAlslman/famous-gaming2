<?php
include 'includes/config.php';
$page_title = 'Services - FAMOUS GAMING';
include 'includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Premium Services</h1>
        <p>Luxury Gaming Experience - Professional Hospitality - Exclusive Amenities</p>
    </div>
</section>

<section class="content">
    <div class="container">
        <div class="services-amenities-section">
            <h2 class="section-title">Exclusive Amenities</h2>

            <div class="row g-4 services-grid">
                <div class="col-12 col-md-6 col-lg-4">
                    <div
                        class="service-card service-detail-card h-100"
                        role="button"
                        tabindex="0"
                        aria-label="View details for Gaming Excellence"
                        data-card-modal-trigger
                        data-card-title="Gaming Excellence"
                        data-card-type="Gaming Experience"
                        data-card-description="A premium console setup built for competitive sessions, immersive visuals, and a polished in-room PlayStation experience."
                    >
                        <div class="service-image" data-card-media>
                            <img src="images/service-gaming.png" alt="Gaming Experience" class="img-fluid">
                        </div>
                        <div class="service-card-content">
                            <h3>Gaming Excellence</h3>
                            <ul>
                                <li data-card-detail-item>Latest PS4 &amp; PS5 Consoles</li>
                                <li data-card-detail-item>Premium Game Library</li>
                                <li data-card-detail-item>4K HDR Display Technology</li>
                                <li data-card-detail-item>Professional Controllers</li>
                                <li data-card-detail-item>VR Gaming Available</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div
                        class="service-card service-detail-card h-100"
                        role="button"
                        tabindex="0"
                        aria-label="View details for Premium Hospitality"
                        data-card-modal-trigger
                        data-card-title="Premium Hospitality"
                        data-card-type="Hospitality"
                        data-card-description="Comfort-driven lounge service designed to keep every gaming session smooth, social, and well supported."
                    >
                        <div class="service-image" data-card-media>
                            <img src="images/service-food.png" alt="Hospitality" class="img-fluid">
                        </div>
                        <div class="service-card-content">
                            <h3>Premium Hospitality</h3>
                            <ul>
                                <li data-card-detail-item>Gourmet Beverages</li>
                                <li data-card-detail-item>Specialty Coffee Selection</li>
                                <li data-card-detail-item>Premium Snacks</li>
                                <li data-card-detail-item>Fresh Pizza &amp; Sandwiches</li>
                                <li data-card-detail-item>In-Room Service</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div
                        class="service-card service-detail-card h-100"
                        role="button"
                        tabindex="0"
                        aria-label="View details for Private Events"
                        data-card-modal-trigger
                        data-card-title="Private Events"
                        data-card-type="Events"
                        data-card-description="Tailored event experiences for birthdays, tournaments, and group sessions inside a premium gaming atmosphere."
                    >
                        <div class="service-image" data-card-media>
                            <img src="images/service-event.png" alt="Events" class="img-fluid">
                        </div>
                        <div class="service-card-content">
                            <h3>Private Events</h3>
                            <ul>
                                <li data-card-detail-item>Luxury Birthday Packages</li>
                                <li data-card-detail-item>Professional Tournaments</li>
                                <li data-card-detail-item>Corporate Team Building</li>
                                <li data-card-detail-item>Bespoke Decorations</li>
                                <li data-card-detail-item>Exclusive Event Rates</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="why-choose-us-container">
            <h2 class="section-title why-choose-us-title">Why Choose Us</h2>
            <div class="row g-4 why-choose-us-grid">
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="why-choose-us-item">
                        <div class="why-choose-us-icon">⚡</div>
                        <h4 class="why-choose-us-heading">Ultra-Fast Connectivity</h4>
                        <p class="why-choose-us-description">Zero-latency fiber connection</p>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="why-choose-us-item">
                        <div class="why-choose-us-icon">🎯</div>
                        <h4 class="why-choose-us-heading">Curated Collection</h4>
                        <p class="why-choose-us-description">Weekly updated game library</p>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="why-choose-us-item">
                        <div class="why-choose-us-icon">🛋️</div>
                        <h4 class="why-choose-us-heading">Luxury Environment</h4>
                        <p class="why-choose-us-description">Designer furniture and acoustics</p>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="why-choose-us-item">
                        <div class="why-choose-us-icon">🏆</div>
                        <h4 class="why-choose-us-heading">Elite Competitions</h4>
                        <p class="why-choose-us-description">Professional tournament hosting</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="services-cta-container">
            <a href="booking.php" class="btn services-cta-btn">Reserve Your Experience</a>
        </div>
    </div>
</section>

<div class="card-detail-modal" id="servicesCardDetailModal" hidden>
    <div class="card-detail-backdrop" data-card-modal-close></div>
    <div class="card-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="servicesCardDetailModalTitle">
        <button type="button" class="card-detail-close" aria-label="Close details" data-card-modal-close>&times;</button>
        <div class="card-detail-layout">
            <div class="card-detail-media-shell" id="servicesCardDetailModalMedia"></div>
            <div class="card-detail-copy">
                <span class="card-detail-type" id="servicesCardDetailModalType"></span>
                <h3 class="card-detail-title" id="servicesCardDetailModalTitle"></h3>
                <p class="card-detail-description" id="servicesCardDetailModalDescription"></p>
                <ul class="card-detail-list" id="servicesCardDetailModalList"></ul>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('servicesCardDetailModal');
        const modalMedia = document.getElementById('servicesCardDetailModalMedia');
        const modalType = document.getElementById('servicesCardDetailModalType');
        const modalTitle = document.getElementById('servicesCardDetailModalTitle');
        const modalDescription = document.getElementById('servicesCardDetailModalDescription');
        const modalList = document.getElementById('servicesCardDetailModalList');
        const triggers = Array.from(document.querySelectorAll('.service-detail-card[data-card-modal-trigger]'));
        let lastTrigger = null;

        if (!modal || !modalMedia || !modalType || !modalTitle || !modalDescription || !modalList || !triggers.length) {
            return;
        }

        function openModal(card) {
            lastTrigger = card;
            modalMedia.innerHTML = '';
            modalList.innerHTML = '';

            const media = card.querySelector('[data-card-media]');
            const details = Array.from(card.querySelectorAll('[data-card-detail-item]'));

            if (media) {
                modalMedia.appendChild(media.cloneNode(true));
            }

            modalType.textContent = card.dataset.cardType || '';
            modalTitle.textContent = card.dataset.cardTitle || '';
            modalDescription.textContent = card.dataset.cardDescription || '';

            details.forEach(function (detail) {
                const item = document.createElement('li');
                item.textContent = detail.textContent.trim();
                modalList.appendChild(item);
            });

            modal.hidden = false;
            document.body.classList.add('card-detail-modal-open');
        }

        function closeModal() {
            if (modal.hidden) {
                return;
            }

            modal.hidden = true;
            document.body.classList.remove('card-detail-modal-open');

            if (lastTrigger) {
                lastTrigger.focus();
            }
        }

        triggers.forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                openModal(trigger);
            });

            trigger.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openModal(trigger);
                }
            });
        });

        modal.addEventListener('click', function (event) {
            if (event.target.closest('[data-card-modal-close]')) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    })();
</script>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
