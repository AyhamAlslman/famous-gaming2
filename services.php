<?php
include 'includes/config.php';
$page_title = t('services_page_title');
include 'includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1><?php echo t('services_hero_title'); ?></h1>
        <p><?php echo t('services_hero_text'); ?></p>
    </div>
</section>

<section class="content">
    <div class="container">
        <div class="services-amenities-section">
            <h2 class="section-title"><?php echo t('services_section_title'); ?></h2>

            <div class="row g-4 services-grid">
                <div class="col-12 col-md-6 col-lg-4">
                    <div
                        class="service-card service-detail-card h-100"
                        role="button"
                        tabindex="0"
                        aria-label="<?php echo htmlspecialchars(t('services_gaming_title'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-card-modal-trigger
                        data-card-title="<?php echo htmlspecialchars(t('services_gaming_title'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-card-type="<?php echo htmlspecialchars(t('services_gaming_type'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-card-description="<?php echo htmlspecialchars(t('services_gaming_desc'), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <div class="service-image" data-card-media>
                            <img src="images/service-gaming.png" alt="<?php echo htmlspecialchars(t('services_gaming_type'), ENT_QUOTES, 'UTF-8'); ?>" class="img-fluid">
                        </div>
                        <div class="service-card-content">
                            <h3><?php echo t('services_gaming_title'); ?></h3>
                            <ul>
                                <li data-card-detail-item><?php echo t('services_gaming_item_1'); ?></li>
                                <li data-card-detail-item><?php echo t('services_gaming_item_2'); ?></li>
                                <li data-card-detail-item><?php echo t('services_gaming_item_3'); ?></li>
                                <li data-card-detail-item><?php echo t('services_gaming_item_4'); ?></li>
                                <li data-card-detail-item><?php echo t('services_gaming_item_5'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div
                        class="service-card service-detail-card h-100"
                        role="button"
                        tabindex="0"
                        aria-label="<?php echo htmlspecialchars(t('services_hospitality_title'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-card-modal-trigger
                        data-card-title="<?php echo htmlspecialchars(t('services_hospitality_title'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-card-type="<?php echo htmlspecialchars(t('services_hospitality_type'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-card-description="<?php echo htmlspecialchars(t('services_hospitality_desc'), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <div class="service-image" data-card-media>
                            <img src="images/service-food.png" alt="<?php echo htmlspecialchars(t('services_hospitality_type'), ENT_QUOTES, 'UTF-8'); ?>" class="img-fluid">
                        </div>
                        <div class="service-card-content">
                            <h3><?php echo t('services_hospitality_title'); ?></h3>
                            <ul>
                                <li data-card-detail-item><?php echo t('services_hospitality_item_1'); ?></li>
                                <li data-card-detail-item><?php echo t('services_hospitality_item_2'); ?></li>
                                <li data-card-detail-item><?php echo t('services_hospitality_item_3'); ?></li>
                                <li data-card-detail-item><?php echo t('services_hospitality_item_4'); ?></li>
                                <li data-card-detail-item><?php echo t('services_hospitality_item_5'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div
                        class="service-card service-detail-card h-100"
                        role="button"
                        tabindex="0"
                        aria-label="<?php echo htmlspecialchars(t('services_events_title'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-card-modal-trigger
                        data-card-title="<?php echo htmlspecialchars(t('services_events_title'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-card-type="<?php echo htmlspecialchars(t('services_events_type'), ENT_QUOTES, 'UTF-8'); ?>"
                        data-card-description="<?php echo htmlspecialchars(t('services_events_desc'), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <div class="service-image" data-card-media>
                            <img src="images/service-event.png" alt="<?php echo htmlspecialchars(t('services_events_type'), ENT_QUOTES, 'UTF-8'); ?>" class="img-fluid">
                        </div>
                        <div class="service-card-content">
                            <h3><?php echo t('services_events_title'); ?></h3>
                            <ul>
                                <li data-card-detail-item><?php echo t('services_events_item_1'); ?></li>
                                <li data-card-detail-item><?php echo t('services_events_item_2'); ?></li>
                                <li data-card-detail-item><?php echo t('services_events_item_3'); ?></li>
                                <li data-card-detail-item><?php echo t('services_events_item_4'); ?></li>
                                <li data-card-detail-item><?php echo t('services_events_item_5'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="why-choose-us-container">
            <h2 class="section-title why-choose-us-title"><?php echo t('services_why_title'); ?></h2>
            <div class="row g-4 why-choose-us-grid">
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="why-choose-us-item">
                        <div class="why-choose-us-icon">⚡</div>
                        <h4 class="why-choose-us-heading"><?php echo t('services_why_1_title'); ?></h4>
                        <p class="why-choose-us-description"><?php echo t('services_why_1_text'); ?></p>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="why-choose-us-item">
                        <div class="why-choose-us-icon">🎯</div>
                        <h4 class="why-choose-us-heading"><?php echo t('services_why_2_title'); ?></h4>
                        <p class="why-choose-us-description"><?php echo t('services_why_2_text'); ?></p>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="why-choose-us-item">
                        <div class="why-choose-us-icon">🛋️</div>
                        <h4 class="why-choose-us-heading"><?php echo t('services_why_3_title'); ?></h4>
                        <p class="why-choose-us-description"><?php echo t('services_why_3_text'); ?></p>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="why-choose-us-item">
                        <div class="why-choose-us-icon">🏆</div>
                        <h4 class="why-choose-us-heading"><?php echo t('services_why_4_title'); ?></h4>
                        <p class="why-choose-us-description"><?php echo t('services_why_4_text'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="services-cta-container">
            <a href="booking.php" class="btn services-cta-btn"><?php echo t('services_cta'); ?></a>
        </div>
    </div>
</section>

<div class="card-detail-modal" id="servicesCardDetailModal" hidden>
    <div class="card-detail-backdrop" data-card-modal-close></div>
    <div class="card-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="servicesCardDetailModalTitle">
        <button type="button" class="card-detail-close" aria-label="<?php echo htmlspecialchars(t('services_close_details'), ENT_QUOTES, 'UTF-8'); ?>" data-card-modal-close>&times;</button>
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
