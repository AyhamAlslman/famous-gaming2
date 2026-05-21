<?php
include 'includes/config.php';
$page_title = t('about_page_title');
include 'includes/header.php';
?>
<section class="hero page-hero arena-page-hero about-page-hero">
    <div class="container">
        <span class="ticket-label">FAMOUS GAMING 2026</span>
        <h1><?php echo t('about_hero_title'); ?></h1>
        <p><?php echo t('about_hero_text'); ?></p>
    </div>
</section>

<section class="content arena-page-content about-page-content">
    <div class="container">
        <div class="home-section-heading arena-section-heading">
            <span class="ticket-label"><?php echo t('about_section_title'); ?></span>
            <h2><?php echo t('home_intro_title'); ?></h2>
        </div>

        <div class="about-main-container">
            <div class="about-intro-card">
                <p class="about-intro">
                    <?php echo t('about_intro'); ?>
                </p>
            </div>

            <div class="about-copy-block">
                <h3 class="about-section-title"><?php echo t('about_mission_title'); ?></h3>
                <p class="about-text">
                    <?php echo t('about_mission_text'); ?>
                </p>
            </div>

            <div class="about-copy-block">
                <h3 class="about-section-title"><?php echo t('about_why_title'); ?></h3>
                <ul class="about-features-list">
                    <li class="about-feature-item">
                        <strong><?php echo t('about_latest_consoles'); ?></strong> <?php echo t('about_latest_consoles_text'); ?>
                    </li>
                    <li class="about-feature-item">
                        <strong><?php echo t('about_4k'); ?></strong> <?php echo t('about_4k_text'); ?>
                    </li>
                    <li class="about-feature-item">
                        <strong><?php echo t('about_internet'); ?></strong> <?php echo t('about_internet_text'); ?>
                    </li>
                    <li class="about-feature-item">
                        <strong><?php echo t('about_climate'); ?></strong> <?php echo t('about_climate_text'); ?>
                    </li>
                    <li class="about-feature-item">
                        <strong><?php echo t('about_refreshments'); ?></strong> <?php echo t('about_refreshments_text'); ?>
                    </li>
                    <li class="about-feature-item">
                        <strong><?php echo t('about_pricing'); ?></strong> <?php echo t('about_pricing_text'); ?>
                    </li>
                </ul>
            </div>

            <div class="about-copy-block about-values-section">
                <h3 class="about-section-title"><?php echo t('about_values_title'); ?></h3>
                <div class="row g-3 about-values-grid">
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="about-value-card h-100">
                            <h4 class="about-value-title"><?php echo t('about_value_quality'); ?></h4>
                            <p class="about-value-text"><?php echo t('about_value_quality_text'); ?></p>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="about-value-card h-100">
                            <h4 class="about-value-title"><?php echo t('about_value_service'); ?></h4>
                            <p class="about-value-text"><?php echo t('about_value_service_text'); ?></p>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-md-4">
                        <div class="about-value-card h-100">
                            <h4 class="about-value-title"><?php echo t('about_value_innovation'); ?></h4>
                            <p class="about-value-text"><?php echo t('about_value_innovation_text'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="about-cta-container">
                <a href="booking.php" class="btn"><?php echo t('about_cta'); ?></a>
            </div>
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
