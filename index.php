<?php
define('APP_ACCESS', true);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
startSecureSession();

// If already logged in, go to dashboard
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'admin': redirect('admin/dashboard.php'); break;
        case 'teacher': redirect('teachers/dashboard.php'); break;
        case 'student': redirect('students/dashboard.php'); break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>School Portal - Empowering Education, Inspiring Futures</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --primary: #2563eb;
      --secondary: #7c3aed;
      --success: #059669;
      --warning: #f59e0b;
      --info: #0ea5e9;
      --orange: #ea580c;
    }

    body {
      font-family: 'Poppins', sans-serif;
      overflow-x: hidden;
      background: #f8fafc;
      color: #1e293b;
    }

    /* Navbar */
    .navbar {
      background: white;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      padding: 1rem 0;
      position: fixed;
      width: 100%;
      top: 0;
      z-index: 1000;
    }

    .navbar-brand {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--primary) !important;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .school-logo {
      width: 50px;
      height: 50px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: white;
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .nav-link {
      color: #64748b !important;
      font-weight: 600;
      margin: 0 0.5rem;
      transition: all 0.3s ease;
      padding: 0.5rem 1rem !important;
      border-radius: 8px;
    }

    .nav-link:hover {
      color: var(--primary) !important;
      background: rgba(37, 99, 235, 0.05);
    }

    .btn-login {
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      padding: 0.6rem 1.5rem;
      border-radius: 10px;
      font-weight: 600;
      border: none;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
      color: white;
    }

    /* Hero Section */
    .hero-section {
      padding: 140px 0 80px;
      background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%);
      position: relative;
      overflow: hidden;
    }

    .hero-section::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -10%;
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, rgba(37, 99, 235, 0.1) 0%, transparent 70%);
      border-radius: 50%;
    }

    .hero-section::after {
      content: '';
      position: absolute;
      bottom: -30%;
      left: -5%;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(124, 58, 237, 0.1) 0%, transparent 70%);
      border-radius: 50%;
    }

    .hero-content {
      position: relative;
      z-index: 2;
    }

    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1.25rem;
      background: white;
      border: 2px solid #dbeafe;
      border-radius: 50px;
      color: var(--primary);
      font-weight: 600;
      font-size: 0.9rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);
    }

    .hero-title {
      font-size: 3.5rem;
      font-weight: 800;
      line-height: 1.2;
      margin-bottom: 1.5rem;
      color: #0f172a;
    }

    .hero-title .highlight {
      color: var(--primary);
      position: relative;
    }

    .hero-title .highlight::after {
      content: '';
      position: absolute;
      bottom: 5px;
      left: 0;
      right: 0;
      height: 12px;
      background: rgba(37, 99, 235, 0.2);
      z-index: -1;
      border-radius: 4px;
    }

    .hero-description {
      font-size: 1.15rem;
      color: #475569;
      line-height: 1.8;
      margin-bottom: 2.5rem;
      max-width: 550px;
    }

    .hero-buttons {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 3rem;
    }

    .btn-primary-lg {
      padding: 0.9rem 2rem;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      border: none;
      border-radius: 10px;
      font-weight: 600;
      font-size: 1rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
    }

    .btn-primary-lg:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(37, 99, 235, 0.4);
      color: white;
    }

    .btn-secondary-lg {
      padding: 0.9rem 2rem;
      background: white;
      color: var(--primary);
      border: 2px solid var(--primary);
      border-radius: 10px;
      font-weight: 600;
      font-size: 1rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.3s ease;
    }

    .btn-secondary-lg:hover {
      background: var(--primary);
      color: white;
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
    }

    .hero-stats {
      display: flex;
      gap: 2.5rem;
      flex-wrap: wrap;
    }

    .stat-box {
      text-align: left;
    }

    .stat-number {
      font-size: 2rem;
      font-weight: 800;
      color: var(--primary);
      display: block;
      line-height: 1;
    }

    .stat-label {
      font-size: 0.9rem;
      color: #64748b;
      font-weight: 500;
      margin-top: 0.25rem;
    }

    .hero-image {
      position: relative;
      z-index: 2;
    }

    .image-card {
      background: white;
      border-radius: 20px;
      padding: 2rem;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
      border: 1px solid #e2e8f0;
    }

    .subject-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .subject-box {
      background: linear-gradient(135deg, #f8fafc, #f1f5f9);
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 1.25rem;
      text-align: center;
      transition: all 0.3s ease;
    }

    .subject-box:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .subject-icon {
      width: 50px;
      height: 50px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 0.75rem;
      color: white;
      font-size: 1.5rem;
    }

    .subject-box.math .subject-icon {
      background: linear-gradient(135deg, #ec4899, #f43f5e);
    }

    .subject-box.science .subject-icon {
      background: linear-gradient(135deg, #10b981, #059669);
    }

    .subject-box.english .subject-icon {
      background: linear-gradient(135deg, #f59e0b, #ea580c);
    }

    .subject-box h5 {
      font-size: 0.95rem;
      font-weight: 700;
      color: #1e293b;
      margin: 0;
    }

    /* Features Section */
    .features-section {
      padding: 80px 0;
      background: white;
    }

    .section-header {
      text-align: center;
      margin-bottom: 4rem;
    }

    .section-badge {
      display: inline-block;
      padding: 0.5rem 1.25rem;
      background: #eff6ff;
      border-radius: 50px;
      color: var(--primary);
      font-weight: 700;
      font-size: 0.85rem;
      margin-bottom: 1rem;
    }

    .section-title {
      font-size: 2.5rem;
      font-weight: 800;
      color: #0f172a;
      margin-bottom: 1rem;
    }

    .section-description {
      font-size: 1.1rem;
      color: #64748b;
      max-width: 650px;
      margin: 0 auto;
      line-height: 1.7;
    }

    .feature-card {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 2rem;
      text-align: center;
      transition: all 0.3s ease;
      height: 100%;
    }

    .feature-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
      border-color: var(--primary);
    }

    .feature-icon {
      width: 70px;
      height: 70px;
      background: linear-gradient(135deg, #eff6ff, #dbeafe);
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      font-size: 2rem;
      color: var(--primary);
    }

    .feature-card h4 {
      font-size: 1.25rem;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 0.75rem;
    }

    .feature-card p {
      color: #64748b;
      line-height: 1.6;
      margin: 0;
    }

    /* Academic Programs */
    .programs-section {
      padding: 80px 0;
      background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
    }

    .program-card {
      background: white;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
      height: 100%;
    }

    .program-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    }

    .program-header {
      padding: 2rem;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: white;
      text-align: center;
    }

    .program-header.elementary {
      background: linear-gradient(135deg, #f59e0b, #ea580c);
    }

    .program-header.middle {
      background: linear-gradient(135deg, #10b981, #059669);
    }

    .program-header.high {
      background: linear-gradient(135deg, #ec4899, #f43f5e);
    }

    .program-icon {
      font-size: 3rem;
      margin-bottom: 1rem;
    }

    .program-header h3 {
      font-size: 1.5rem;
      font-weight: 700;
      margin: 0;
    }

    .program-body {
      padding: 2rem;
    }

    .program-body ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .program-body li {
      padding: 0.75rem 0;
      border-bottom: 1px solid #f1f5f9;
      color: #475569;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .program-body li:last-child {
      border-bottom: none;
    }

    .program-body li i {
      color: var(--success);
      font-size: 1.1rem;
    }

    /* Stats Banner */
    .stats-banner {
      padding: 60px 0;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      position: relative;
      overflow: hidden;
    }

    .stats-banner::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      opacity: 0.5;
    }

    .stat-item {
      text-align: center;
      position: relative;
      z-index: 1;
    }

    .stat-value {
      font-size: 3rem;
      font-weight: 800;
      color: white;
      display: block;
      margin-bottom: 0.5rem;
    }

    .stat-text {
      font-size: 1rem;
      color: rgba(255, 255, 255, 0.9);
      font-weight: 500;
    }

    /* Testimonials */
    .testimonials-section {
      padding: 80px 0;
      background: white;
    }

    .testimonial-card {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 2rem;
      height: 100%;
      transition: all 0.3s ease;
    }

    .testimonial-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
    }

    .testimonial-stars {
      color: #f59e0b;
      font-size: 1.1rem;
      margin-bottom: 1rem;
    }

    .testimonial-text {
      color: #475569;
      font-size: 1rem;
      line-height: 1.7;
      margin-bottom: 1.5rem;
      font-style: italic;
    }

    .testimonial-author {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .author-avatar {
      width: 50px;
      height: 50px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 1.1rem;
    }

    .author-info h5 {
      font-size: 1rem;
      font-weight: 700;
      color: #1e293b;
      margin: 0 0 0.25rem 0;
    }

    .author-info p {
      font-size: 0.85rem;
      color: #64748b;
      margin: 0;
    }

    /* CTA Section */
    .cta-section {
      padding: 80px 0;
      background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%);
    }

    .cta-card {
      background: white;
      border-radius: 24px;
      padding: 4rem 3rem;
      text-align: center;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
      max-width: 900px;
      margin: 0 auto;
    }

    .cta-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 2.5rem;
      margin: 0 auto 2rem;
      box-shadow: 0 10px 30px rgba(37, 99, 235, 0.3);
    }

    .cta-title {
      font-size: 2.5rem;
      font-weight: 800;
      color: #0f172a;
      margin-bottom: 1rem;
    }

    .cta-description {
      font-size: 1.1rem;
      color: #64748b;
      margin-bottom: 2.5rem;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }

    /* Footer */
    .footer {
      background: #0f172a;
      padding: 4rem 0 2rem;
      color: white;
    }

    .footer-brand {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .footer-logo {
      width: 45px;
      height: 45px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
    }

    .footer-text {
      color: #94a3b8;
      margin-bottom: 2rem;
      line-height: 1.7;
    }

    .footer-links h5 {
      color: white;
      font-weight: 700;
      margin-bottom: 1.5rem;
      font-size: 1.1rem;
    }

    .footer-links a {
      display: block;
      color: #94a3b8;
      text-decoration: none;
      margin-bottom: 0.75rem;
      transition: all 0.3s ease;
    }

    .footer-links a:hover {
      color: var(--primary);
      padding-left: 5px;
    }

    .footer-bottom {
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      margin-top: 3rem;
      padding-top: 2rem;
      text-align: center;
      color: #64748b;
    }

    /* Responsive */
    @media (max-width: 992px) {
      .hero-title {
        font-size: 2.75rem;
      }

      .section-title {
        font-size: 2rem;
      }

      .cta-title {
        font-size: 2rem;
      }
    }

    @media (max-width: 768px) {
      .hero-title {
        font-size: 2.25rem;
      }

      .hero-buttons {
        flex-direction: column;
        width: 100%;
      }

      .btn-primary-lg,
      .btn-secondary-lg {
        width: 100%;
        justify-content: center;
      }

      .subject-grid {
        grid-template-columns: 1fr;
      }

      .stat-value {
        font-size: 2.5rem;
      }

      .cta-card {
        padding: 3rem 2rem;
      }
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <nav class="navbar">
    <div class="container">
      <div class="d-flex align-items-center justify-content-between w-100">
        <a class="navbar-brand" href="#">
          <div class="school-logo">
            <i class="fas fa-graduation-cap"></i>
          </div>
          <span>School Portal</span>
        </a>
        <div class="d-flex align-items-center gap-2">
          <a href="#features" class="nav-link d-none d-md-inline">Programs</a>
          <a href="#about" class="nav-link d-none d-md-inline">About</a>
          <a href="#testimonials" class="nav-link d-none d-md-inline">Reviews</a>
          <a href="login.php" class="btn-login">
            <i class="fas fa-sign-in-alt me-1"></i>
            Portal Login
          </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero-section">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6">
          <div class="hero-content">
            <div class="hero-badge">
              <i class="fas fa-award"></i>
              Excellence in Education Since 2010
            </div>
            <h1 class="hero-title">
              Shaping <span class="highlight">Tomorrow's Leaders</span> Today
            </h1>
            <p class="hero-description">
              Welcome to our comprehensive learning management system where students excel, teachers inspire, and parents stay connected. Join our thriving academic community.
            </p>
            <div class="hero-buttons">
              <a href="login.php" class="btn-primary-lg">
                <i class="fas fa-sign-in-alt"></i>
                Student Login
              </a>
              <a href="register.php" class="btn-secondary-lg">
                <i class="fas fa-user-plus"></i>
                Enroll Now
              </a>
            </div>
            <div class="hero-stats">
              <div class="stat-box">
                <span class="stat-number">500+</span>
                <span class="stat-label">Active Students</span>
              </div>
              <div class="stat-box">
                <span class="stat-number">50+</span>
                <span class="stat-label">Expert Teachers</span>
              </div>
              <div class="stat-box">
                <span class="stat-number">15+</span>
                <span class="stat-label">Years Excellence</span>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6 d-none d-lg-block">
          <div class="hero-image">
            <div class="image-card">
              <h4 style="font-weight: 700; color: #1e293b; margin-bottom: 1.5rem; text-align: center;">
                <i class="fas fa-book-open" style="color: var(--primary);"></i>
                Our Core Subjects
              </h4>
              <div class="subject-grid">
                <div class="subject-box math">
                  <div class="subject-icon">
                    <i class="fas fa-calculator"></i>
                  </div>
                  <h5>Mathematics</h5>
                </div>
                <div class="subject-box science">
                  <div class="subject-icon">
                    <i class="fas fa-flask"></i>
                  </div>
                  <h5>Science</h5>
                </div>
                <div class="subject-box english">
                  <div class="subject-icon">
                    <i class="fas fa-book"></i>
                  </div>
                  <h5>English</h5>
                </div>
                <div class="subject-box">
                  <div class="subject-icon">
                    <i class="fas fa-globe"></i>
                  </div>
                  <h5>Social Studies</h5>
                </div>
              </div>
              <div style="background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 12px; padding: 1.5rem; text-align: center; margin-top: 1rem;">
                <i class="fas fa-certificate" style="font-size: 2rem; color: var(--primary); margin-bottom: 0.5rem;"></i>
                <p style="margin: 0; color: #1e293b; font-weight: 600; font-size: 0.95rem;">Accredited Curriculum</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Features Section -->
  <section class="features-section" id="features">
    <div class="container">
      <div class="section-header">
        <span class="section-badge">Why Choose Us</span>
        <h2 class="section-title">Comprehensive Learning Experience</h2>
        <p class="section-description">
          Our portal provides everything students, teachers, and parents need for academic success in one convenient platform.
        </p>
      </div>

      <div class="row g-4">
        <div class="col-lg-4 col-md-6">
          <div class="feature-card">
            <div class="feature-icon">
              <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <h4>Expert Faculty</h4>
            <p>Learn from qualified and passionate educators dedicated to your success</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="feature-card">
            <div class="feature-icon">
              <i class="fas fa-laptop-code"></i>
            </div>
            <h4>Digital Learning</h4>
            <p>Access lessons, assignments, and resources anytime, anywhere</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="feature-card">
            <div class="feature-icon">
              <i class="fas fa-chart-line"></i>
            </div>
            <h4>Progress Tracking</h4>
            <p>Monitor academic performance with detailed reports and analytics</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="feature-card">
            <div class="feature-icon">
              <i class="fas fa-users"></i>
            </div>
            <h4>Parent Portal</h4>
            <p>Stay informed with real-time updates on your child's progress</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="feature-card">
            <div class="feature-icon">
              <i class="fas fa-trophy"></i>
            </div>
            <h4>Extracurriculars</h4>
            <p>Engage in clubs, sports, and activities to enrich your school experience</p>
          </div>
        </div>
        <div class="col-lg-4 col-md-6">
          <div class="feature-card">
            <div class="feature-icon">
              <i class="fas fa-headset"></i>
            </div>
            <h4>24/7 Support</h4>
            <p>Get assistance whenever you need it from our dedicated support team</p>
          </div>
        </div>
      </div>  
    </div>
  </section>  
</body>
</html>
