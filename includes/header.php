<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'FAMOUS GAMING'; ?></title>

    <!-- Bootstrap CSS (Local) -->
    <link rel="stylesheet" href="css/bootstrap.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css?v=3.4">

    <link rel="icon" type="image/svg+xml" href="images/logo-mark.svg">
    <link rel="icon" type="image/png" href="images/favicon.png">
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand logo" href="index.php">
                <span class="logo-mark" aria-hidden="true">
                    <svg class="logo-controller" viewBox="0 0 84 84" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="controllerStroke" x1="17" y1="22" x2="66" y2="56" gradientUnits="userSpaceOnUse">
                                <stop offset="0" stop-color="#86B7FF"/>
                                <stop offset="0.55" stop-color="#968FFF"/>
                                <stop offset="1" stop-color="#62D8FF"/>
                            </linearGradient>
                            <linearGradient id="controllerGlow" x1="22" y1="24" x2="61" y2="52" gradientUnits="userSpaceOnUse">
                                <stop offset="0" stop-color="#A9C8FF"/>
                                <stop offset="0.5" stop-color="#B59FFF"/>
                                <stop offset="1" stop-color="#7FE4FF"/>
                            </linearGradient>
                        </defs>
                        <path class="controller-core" d="M25.173 24.5H58.827C63.198 24.5 67.134 27.079 68.881 31.085L71.989 38.213C74.87 44.817 70.029 52.25 62.823 52.25H58.998C56.116 52.25 53.441 50.767 51.918 48.328L49.997 45.251C49.149 43.892 47.661 43.065 46.059 43.065H37.941C36.339 43.065 34.851 43.892 34.003 45.251L32.082 48.328C30.559 50.767 27.884 52.25 25.002 52.25H21.177C13.971 52.25 9.13 44.817 12.011 38.213L15.119 31.085C16.866 27.079 20.802 24.5 25.173 24.5Z"/>
                        <path class="controller-highlight" d="M28 28.5H56"/>
                        <path class="controller-detail" d="M27.8 36.3H33.2V30.9H37V36.3H42.4V40.1H37V45.5H33.2V40.1H27.8V36.3Z"/>
                        <path class="controller-detail" d="M43.8 35.7H46.8"/>
                        <circle class="controller-orb" cx="51.8" cy="34.2" r="2.2"/>
                        <circle class="controller-orb" cx="57.8" cy="39.2" r="2.2"/>
                        <circle class="controller-orb" cx="45.8" cy="39.2" r="2.2"/>
                        <circle class="controller-orb" cx="51.8" cy="44.2" r="2.2"/>
                        <circle class="controller-thumb" cx="41.4" cy="39.8" r="3.25"/>
                    </svg>
                </span>
                <span class="logo-copy">
                    <span class="logo-word logo-word-famous">FAMOUS</span>
                    <span class="logo-word logo-word-gaming">GAMING</span>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto nav-menu">
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'about.php' ? 'active' : ''; ?>" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'services.php' ? 'active' : ''; ?>" href="services.php">Services</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'store.php' ? 'active' : ''; ?>" href="store.php">Store</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'booking.php' ? 'active' : ''; ?>" href="booking.php">Book Now</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'complaints.php' ? 'active' : ''; ?>" href="complaints.php">Feedback</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'contact.php' ? 'active' : ''; ?>" href="contact.php">Contact</a></li>
                </ul>
            </div>
        </div>
    </nav>
