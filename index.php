<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FixMate | We Fix, You Relax</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
      background: #ffffff;
      color: #1a1a1a;
      line-height: 1.6;
      scroll-behavior: smooth;
    }

    /* Navigation Bar */
    .navbar {
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(10px);
      padding: 20px 80px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
      position: sticky;
      top: 0;
      z-index: 1000;
      border-bottom: 1px solid rgba(139, 111, 71, 0.1);
    }

    .navbar .logo-section {
      display: flex;
      align-items: center;
      gap: 14px;
    }

    .navbar .logo-section img {
      height: 42px;
      width: auto;
    }

    .navbar .logo-text {
      font-size: 24px;
      color: #8b6f47;
      font-weight: 700;
      letter-spacing: -0.5px;
    }

    .navbar ul {
      list-style: none;
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .navbar ul li a {
      color: #4a4a4a;
      text-decoration: none;
      font-weight: 500;
      padding: 10px 20px;
      border-radius: 6px;
      transition: all 0.2s ease;
      font-size: 15px;
    }

    .navbar ul li a:hover {
      color: #8b6f47;
      background-color: rgba(139, 111, 71, 0.05);
    }

    .navbar ul li a.signup-btn {
      background: linear-gradient(135deg, #8b6f47, #a0826d);
      color: white;
      font-weight: 600;
      margin-left: 8px;
    }

    .navbar ul li a.signup-btn:hover {
      background: linear-gradient(135deg, #765e3a, #8b6f47);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(139, 111, 71, 0.25);
    }

    /* Hero Section */
    .hero {
      background: linear-gradient(135deg, #8b6f47 0%, #a0826d 100%);
      padding: 100px 80px;
      position: relative;
      overflow: hidden;
    }

    .hero::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: 
        radial-gradient(circle at 10% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 90% 80%, rgba(255, 255, 255, 0.08) 0%, transparent 50%);
      pointer-events: none;
    }

    .hero-container {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 80px;
      align-items: center;
      position: relative;
      z-index: 1;
    }

    .hero-content h1 {
      font-size: 56px;
      font-weight: 800;
      color: white;
      margin-bottom: 16px;
      line-height: 1.1;
      letter-spacing: -1px;
    }

    .hero-content .tagline {
      font-size: 28px;
      font-weight: 600;
      color: #fef3e2;
      margin-bottom: 24px;
      font-style: italic;
    }

    .hero-content p {
      font-size: 18px;
      color: rgba(255, 255, 255, 0.95);
      margin-bottom: 40px;
      line-height: 1.7;
    }

    .hero-buttons {
      display: flex;
      gap: 16px;
    }

    .btn {
      padding: 14px 32px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 16px;
      transition: all 0.3s ease;
      display: inline-block;
      cursor: pointer;
    }

    .btn-primary {
      background: white;
      color: #8b6f47;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }

    .btn-secondary {
      background: transparent;
      color: white;
      border: 2px solid white;
    }

    .btn-secondary:hover {
      background: white;
      color: #8b6f47;
    }

    .hero-image {
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .hero-image img {
      width: 100%;
      max-width: 450px;
      height: auto;
      filter: drop-shadow(0 20px 40px rgba(0, 0, 0, 0.3));
    }

    /* About Section */
    .about {
      padding: 100px 80px;
      background: #fafafa;
    }

    .about-container {
      max-width: 1000px;
      margin: 0 auto;
      text-align: center;
    }

    .section-label {
      display: inline-block;
      padding: 6px 16px;
      background: rgba(139, 111, 71, 0.1);
      color: #8b6f47;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 20px;
    }

    .about h2 {
      font-size: 42px;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 24px;
      letter-spacing: -0.5px;
    }

    .about p {
      font-size: 18px;
      color: #4a4a4a;
      line-height: 1.8;
      margin-bottom: 20px;
    }

    .about strong {
      color: #8b6f47;
      font-weight: 700;
    }

    /* Services Section */
    .services {
      padding: 100px 80px;
      background: white;
    }

    .services-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .services-header {
      text-align: center;
      margin-bottom: 60px;
    }

    .services h2 {
      font-size: 42px;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 16px;
      letter-spacing: -0.5px;
    }

    .services-subtitle {
      font-size: 18px;
      color: #666;
      max-width: 600px;
      margin: 0 auto;
    }

    .service-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 30px;
    }

    .service-card {
      background: white;
      border: 1px solid #e8e8e8;
      border-radius: 12px;
      padding: 40px 32px;
      transition: all 0.3s ease;
      position: relative;
    }

    .service-card::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, #8b6f47, #a0826d);
      border-radius: 12px 12px 0 0;
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .service-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 12px 40px rgba(139, 111, 71, 0.15);
      border-color: #8b6f47;
    }

    .service-card:hover::before {
      opacity: 1;
    }

    .service-number {
      display: inline-block;
      width: 36px;
      height: 36px;
      background: linear-gradient(135deg, #8b6f47, #a0826d);
      color: white;
      border-radius: 8px;
      text-align: center;
      line-height: 36px;
      font-weight: 700;
      font-size: 16px;
      margin-bottom: 20px;
    }

    .service-card h3 {
      font-size: 22px;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 12px;
      letter-spacing: -0.3px;
    }

    .service-card p {
      font-size: 16px;
      color: #666;
      line-height: 1.7;
    }

    /* Footer */
    footer {
      background: #1a1a1a;
      color: #ffffff;
      padding: 50px 80px;
      text-align: center;
    }

    .footer-content {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .footer-logo {
      font-size: 24px;
      font-weight: 700;
      color: #a0826d;
      margin-bottom: 16px;
    }

    .footer-contact {
      display: flex;
      justify-content: center;
      gap: 40px;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }

    .footer-contact a {
      color: #a0826d;
      text-decoration: none;
      font-weight: 500;
      transition: color 0.2s;
    }

    .footer-contact a:hover {
      color: #b8956f;
    }

    .footer-bottom {
      padding-top: 24px;
      border-top: 1px solid rgba(160, 130, 109, 0.2);
      color: #999;
      font-size: 14px;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
      .navbar {
        padding: 16px 40px;
      }

      .hero {
        padding: 80px 40px;
      }

      .hero-container {
        grid-template-columns: 1fr;
        gap: 50px;
        text-align: center;
      }

      .hero-content h1 {
        font-size: 44px;
      }

      .hero-buttons {
        justify-content: center;
      }

      .service-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .about, .services {
        padding: 80px 40px;
      }
    }

    @media (max-width: 768px) {
      .navbar {
        padding: 16px 24px;
        flex-direction: column;
        gap: 16px;
      }

      .hero {
        padding: 60px 24px;
      }

      .hero-content h1 {
        font-size: 36px;
      }

      .hero-content .tagline {
        font-size: 22px;
      }

      .hero-content p {
        font-size: 16px;
      }

      .hero-buttons {
        flex-direction: column;
      }

      .btn {
        width: 100%;
      }

      .about, .services {
        padding: 60px 24px;
      }

      .about h2, .services h2 {
        font-size: 32px;
      }

      .service-grid {
        grid-template-columns: 1fr;
      }

      footer {
        padding: 40px 24px;
      }

      .footer-contact {
        flex-direction: column;
        gap: 16px;
      }
    }
  </style>
</head>
<body>

  <!-- Navigation Bar -->
  <nav class="navbar">
    <div class="logo-section">
      <img src="image/logo.png" alt="FixMate Logo">
      
    </div>
    <ul>
      <li><a href="index.php">Home</a></li>
      <li><a href="login.php">Login</a></li>
      <li><a href="signup.php" class="signup-btn">Sign Up</a></li>
    </ul>
  </nav>

  <!-- Hero Section -->
  <section class="hero">
    <div class="hero-container">
      <div class="hero-content">
        <h1>Welcome to FixMate</h1>
        <p>We created FixMate to help students, professors, and staff easily report issues, track repairs, and keep the campus running smoothly. Our system supports both quick problem reporting and scheduled preventive maintenance — making campus life cleaner, safer, and more efficient.</p>
        <div class="hero-buttons">
          <a href="signup.php" class="btn btn-primary">Get Started Today</a>
          <a href="#about" class="btn btn-secondary">Learn More</a>
        </div>
      </div>
      <div class="hero-image">
        <img src="image/logo.png" alt="FixMate Logo">
      </div>
    </div>
  </section>

  <!-- About Section -->
  

  <!-- Services Section -->
  <section id="services" class="services">
    <div class="services-container">
      <div class="services-header">
        <span class="section-label">Our Services</span>
        <h2>Comprehensive Maintenance Solutions</h2>
        <p class="services-subtitle">Everything you need to maintain and manage your campus facilities efficiently</p>
      </div>
      <div class="service-grid">
        <div class="service-card">
          <div class="service-number">01</div>
          <h3>Instant Problem Reporting</h3>
          <p>Easily report any issue online. Just fill out a short form, and our team will handle it right away.</p>
        </div>
        
        <div class="service-card">
          <div class="service-number">02</div>
          <h3>Maintenance Dashboard</h3>
          <p>View all repair requests in one place. Track progress, see updates, and manage tasks with ease.</p>
        </div>
        
        <div class="service-card">
          <div class="service-number">03</div>
          <h3>Smart Notifications</h3>
          <p>Stay informed with instant alerts for new reports and completed repairs.</p>
        </div>
        
        <div class="service-card">
          <div class="service-number">04</div>
          <h3>Preventive Maintenance</h3>
          <p>Keep equipment in good condition with regular check-ups to avoid sudden breakdowns.</p>
        </div>
        
        <div class="service-card">
          <div class="service-number">05</div>
          <h3>Progress Tracking</h3>
          <p>Follow each repair from start to finish and make sure everything stays on schedule.</p>
        </div>
        
        <div class="service-card">
          <div class="service-number">06</div>
          <h3>Complete Record Management</h3>
          <p>All reports and repair histories are safely stored for quick and easy access anytime.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer>
    <div class="footer-content">
      <div class="footer-logo">FixMate</div>
      <div class="footer-contact">
        <a href="mailto:support@fixmate.com">support@fixmate.com</a>
        <a href="tel:+66234567890">+66 234 567 890</a>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2025 FixMate. We Fix, You Relax. All rights reserved.</p>
      </div>
    </div>
  </footer>

</body>
</html>