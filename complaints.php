<?php
include 'includes/config.php';
$page_title = 'Feedback - FAMOUS GAMING';
include 'includes/header.php';

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = mysqli_real_escape_string($conn, $_POST['customer_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    if (!empty($customer_name) && !empty($message)) {
        $insert_query = "INSERT INTO complaints (customer_name, phone, message)
                        VALUES ('$customer_name', '$phone', '$message')";

        if (mysqli_query($conn, $insert_query)) {
            $success_msg = 'Thank you for your feedback! We appreciate your input and will review it carefully.';
        } else {
            $error_msg = 'Error submitting feedback. Please try again.';
        }
    } else {
        $error_msg = 'Please fill in all required fields.';
    }
}
?>

<section class="hero">
    <div class="container">
        <h1>Your Feedback Matters</h1>
        <p>Share your experience, suggestions, or complaints with us</p>
    </div>
</section>

<section class="content">
    <div class="container">
        <?php if ($success_msg): ?>
            <div class="message success">
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="message error">
                <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="feedback-main-container">
            <div class="feedback-intro">
                <p class="feedback-intro-text">
                    We value your opinion! Whether you have a suggestion, complaint, or compliment,
                    we want to hear from you. Your feedback helps us improve our services.
                </p>
            </div>

            <form method="POST" action="" class="form-container">
                <div class="form-group">
                    <label class="form-label">Your Name *</label>
                    <input type="text" name="customer_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone Number (Optional)</label>
                    <input type="tel" name="phone" class="form-control" placeholder="07XXXXXXXX">
                    <small class="booking-time-hint form-text">Provide your phone if you'd like us to contact you</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Your Message *</label>
                    <textarea name="message" class="form-control" required rows="6"
                              placeholder="Share your feedback, suggestions, or complaints here..."></textarea>
                </div>

                <button type="submit" class="btn feedback-submit-btn w-100">
                    Submit Feedback
                </button>
            </form>
        </div>

        <div class="feedback-categories-container">
            <h3 class="feedback-categories-title">We're Here to Listen</h3>

            <div class="row g-3 feedback-categories-grid">
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feedback-category-card h-100">
                        <div class="feedback-category-icon">💬</div>
                        <h4 class="feedback-category-title">Suggestions</h4>
                        <p class="feedback-category-text">
                            Have ideas to improve our service? We'd love to hear them!
                        </p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feedback-category-card h-100">
                        <div class="feedback-category-icon">⚠️</div>
                        <h4 class="feedback-category-title">Complaints</h4>
                        <p class="feedback-category-text">
                            Experienced an issue? Let us know so we can make it right.
                        </p>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-lg-4">
                    <div class="feedback-category-card h-100">
                        <div class="feedback-category-icon">⭐</div>
                        <h4 class="feedback-category-title">Compliments</h4>
                        <p class="feedback-category-text">
                            Had a great experience? Share your positive feedback!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
mysqli_close($conn);
include 'includes/footer.php';
?>
