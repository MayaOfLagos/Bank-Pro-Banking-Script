<?php

$pageName  = "Home";

include_once("front/header.php");
require_once("include/userClass.php");
require_once("include/loginFunction.php");




?>
		<!-- Banner Section -->
		<div class="banner-section">
			<div class="main-slider-carousel owl-carousel owl-theme" style="height:620px;">

				<div class="slide" data-bg-image="<?= $web_url ?>/front/images/main-slider/intro-1.jpg" style="height:620px;">
					<div class="auto-container w-100">
						<div class="row clearfix">
							<!-- Content Column -->
							<div class="content-column col-lg-7 col-md-12 col-sm-12">
								<div class="inner-column">
									<h1> <span>Offering Financial Inclusion for all</span> </h1>
									<div class="text" style="color:#1F1B44;"><?= $pageTitle ?> continues to offer diverse financial products <br>through our many product lines.</div>
									<div class="btn-box">
										<a href="./signup/verify-registration.php" class="theme-btn btn-style-one"><span class="txt">Open Account Now</span></a>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				

			</div>


		</div>
		<!-- End Banner Section -->

		<!-- CTA Section Start -->
		<div class="cta-section" style="background:#1F1B44;">
			<div class="auto-container">
				<div class="row align-items-center">
					<div class="col-lg-7">
						<!-- CTA Content Start -->
						<div class="cta-content">
							<h3 class="title">For Enquires Or Support <span class="text-bold">Call us now!</span></h3>
							<p>We prodive a dedicated support 24/7 for any your question</p>
						</div>
						<!-- CTA Content End -->
					</div>
					<div class="col-lg-5">
						<!-- CTA Phone Number Start -->
						<div class="cta-phone text-lg-end text-strat">
							<h2 class="title">+<?= $page['url_tel'] ?></h2>
						</div>
						<!-- CTA Phone Number Start -->
					</div>
				</div>
			</div>
		</div>
		<!-- CTA Section End -->

		<!-- About Section -->
		<div class="about-section">
			<div class="auto-container">
				<div class="inner-container">
					<div class="row align-items-center clearfix">
						<!-- Image Column -->
						<div class="image-column col-lg-6">
							<div class="about-image">
								<div class="about-inner-image">
									<img src="<?= $web_url ?>/front/images/about/home-about.png" alt="about">
								</div>
							</div>
						</div>

						<!-- Content Column -->
						<div class="content-column col-lg-6 col-md-12 col-sm-12 mb-0">
							<div class="about-column">
								<div class="sec-title">
									<div class="title">about us</div>
									<h2> <span><?= $pageTitle ?></span> <br></h2>
								</div>
								<div class="text">
									<p>
										<?= $pageTitle ?> continues to serve the financial needs of individuals, farmers, businesses, and industries by offering the traditional banking products, as well as online, mobile and telephone banking products.
									</p>
								</div>
								<div class="signature">Winsthood</div>
							</div>
						</div>

					</div>
				</div>
			</div>
		</div>
		<!-- End About Section -->

		<!-- Services Section -->
		<div class="services-section">
			<div class="auto-container">
				<div class="sec-title">
					<div class="title">our services</div>
					<h2><span>Best Solutions</span> For Your Business</h2>
				</div>
				<div class="inner-container">
					<div class="row g-0">

						<!-- Service Block -->
						<div class="service-block col-lg-3 col-md-6 col-sm-12">
							<div class="inner-box">
								<div class="icon-box">
									<span class="icon ti-shield"></span>
								</div>
								<h5><a>Secure Transactions</a></h5>
								<div class="text">Very secured and supervised means to save, invest and transfer your funds at full guarantee.</div>
							</div>
						</div>

						<!-- Service Block -->
						<div class="service-block col-lg-3 col-md-6 col-sm-12">
							<div class="inner-box">
								<div class="icon-box">
									<span class="icon ti-home"></span>
								</div>
								<h5><a>Real Estate</a></h5>
								<div class="text">Invest in the Real Estate Industry, the Fastest and largest income generator. <?= $pageTitle ?> gives you this superior and unique edge.</div>
							</div>
						</div>

						<!-- Service Block -->
						<div class="service-block col-lg-3 col-md-6 col-sm-12">
							<div class="inner-box">
								<div class="icon-box">
									<span class="fab fa-bitcoin"></span>
								</div>
								<h5><a>Digital Assets</a></h5>
								<div class="text">Digital assets, like bitcoin, are an emerging asset class for investors.Our digital asset fund offers investors exposure to bitcoin and other assets.</div>
							</div>
						</div>

						<!-- Service Block -->
						<div class="service-block col-lg-3 col-md-6 col-sm-12">
							<div class="inner-box">
								<div class="icon-box">
									<span class="icon ti-money"></span>
								</div>
								<h5><a> Quick Loans</a></h5>
								<div class="text">We have great loan offers to help meet your needs with very low interest rates. Also available to every one regardless of your status.</div>
							</div>
						</div>

					</div>

				</div>
			</div>
		</div>
		<!-- End Services Section -->

		<!-- Project Section -->
		<div class="project-section">
			<div class="auto-container">

				<!-- Sec Title -->
				<div class="sec-title">
					<div class="clearfix">
						<div class="pull-left">
							<h2>See Our <span>Successful Strategies</span></h2>
						</div>
					</div>
				</div>

			</div>
			<div class="outer-container">
				<div class="row clearfix">

					<!-- Column -->
					<div class="column col-lg-6 col-md-12 col-sm-12">
						<div class="row clearfix">

							<!-- Inner Column -->
							<div class="inner-column col-lg-6 col-md-6 col-sm-12">
								<div class="gallery-block">
									<div class="inner-box">
										<div class="image">
											<img src="<?= $web_url ?>/front/images/gallery/2.jpg" alt="" />
											<div class="overlay-box">
												<div class="overlay-inner">
													<h3><a><?= $pageTitle ?></a></h3>
													<div class="designation">Banking & Financial</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>

							<!-- Inner Column -->
							<div class="inner-column col-lg-6 col-md-6 col-sm-12">
								<div class="gallery-block">
									<div class="inner-box">
										<div class="image">
											<img src="<?= $web_url ?>/front/images/gallery/3.jpg" alt="" />
											<div class="overlay-box">
												<div class="overlay-inner">
													<h3><a><?= $pageTitle ?></a></h3>
													<div class="designation">Business Consultation</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>

							<!-- Inner Column -->
							<div class="inner-column col-lg-12 col-md-12 col-sm-12">
								<div class="gallery-block">
									<div class="inner-box">
										<div class="image">
											<img src="<?= $web_url ?>/front/images/gallery/4.jpg" alt="" />
											<div class="overlay-box">
												<div class="overlay-inner">
													<h3><a><?= $pageTitle ?></a></h3>
													<div class="designation">Saving & Strategy</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>

						</div>
					</div>

					<!-- Column -->
					<div class="column col-lg-6 col-md-12 col-sm-12">
						<div class="gallery-block">
							<div class="inner-box">
								<div class="image">
									<img src="<?= $web_url ?>/front/images/gallery/1.jpg" alt="" />
									<div class="overlay-box">
										<div class="overlay-inner">
											<h3><a><?= $pageTitle ?></a></h3>
											<div class="designation">Home & Business Loan</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>
		<!-- End Project Section -->



		<!-- Sponsors Section -->
		<div class="sponsors-section">
    <div class="auto-container">

        <div class="carousel-outer">
            <!--Sponsors Slider-->
            <ul class="sponsors-carousel owl-carousel owl-theme">
                <li>
                    <div class="image-box"><a href="#"><img src="<?= $web_url ?>/front/images/clients/1.png" alt=""></a></div>
                </li>
                <li>
                    <div class="image-box"><a href="#"><img src="<?= $web_url ?>/front/images/clients/2.png" alt=""></a></div>
                </li>
                <li>
                    <div class="image-box"><a href="#"><img src="<?= $web_url ?>/front/images/clients/4.png" alt=""></a></div>
                </li>
                <li>
                    <div class="image-box"><a href="#"><img src="<?= $web_url ?>/front/images/clients/5.png" alt=""></a></div>
                </li>
                <li>
                    <div class="image-box"><a href="#"><img src="<?= $web_url ?>/front/images/clients/1.png" alt=""></a></div>
                </li>
                <li>
                    <div class="image-box"><a href="#"><img src="<?= $web_url ?>/front/images/clients/2.png" alt=""></a></div>
                </li>
            </ul>
        </div>

    </div>
</div>		<!--End Sponsors Section-->

		<!-- Reputation Section Two -->
		<div class="reputation-section-two style-two">
			<div class="auto-container">
				<div class="row clearfix">

					<!-- Form Column -->
					<div class="form-column col-lg-5 col-md-12 col-sm-12">
						<div class="inner-column">
							<div class="form-boxed">
								<!-- <h5>Our Values</h5> -->
								<div class="text">The mission of <?= $pageTitle ?> is to contribute to the sustainable development of the international banking sector by
									providing. Below are our core values</div>

								<div class="consult-form">
									<div class="sidebar-widget categories-blog">
    <div class="sidebar-title">
        <h4>Our Values</h4>
    </div>
    <ul>
        <li><a>Integrity and honesty </a></li>
        <li><a>Loyalty </a></li>
        <li><a>Teamwork</a></li>
        <li><a>Community </a></li>
        <li><a>Accountability </a></li>
        <li><a>Excellence </a></li>
        <li><a>Confidentiality </a></li>
        <li><a>Relationship </a></li>
    </ul>
</div>
								</div>
							</div>

						</div>
					</div>

					<!-- Content Column -->
					<div class="content-column col-lg-7 col-md-12 col-sm-12">
						<div class="inner-column">
							<div class="sec-title">
								<div class="title">why choose us</div>
								<h2><span>Your Success </span>Means<br> a lot To Us</h2>
							</div>
							<div class="blocks-outer">

								<!-- Reputation Block -->
								<div class="reputation-block">
									<div class="inner-box">
										<h5>Our Vision</h5>
										<div class="text">To be a leading bank in the World, supporting the development of small businesses and financial inclusion around the world.</div>
									</div>
								</div>

								<!-- Reputation Block -->
								<div class="reputation-block">
									<div class="inner-box">
										<h5>Our Mission</h5>
										<div class="text">The mission of <?= $pageTitle ?> is to contribute to the sustainable development of the international banking sector by
											providing responsible financial services and solutions to households and micro, small and medium enterprises, using internationally
											recognized best banking practices. We are committed to delivering value for our clients, shareholders, employees, and society at large.
											The mission is based on our values: integrity and openness, professionalism, commitment to customers, team work, and social and
											environmental responsibility.</div>
									</div>
								</div>



							</div>
						</div>
					</div>

				</div>
			</div>
		</div>
		<!-- End Reputation Section -->



		<!-- Blog Section -->
		<div class="blog-section">
			<div class="auto-container">
				<!-- Sec Title -->

				<div class="inner-container">
					<div class="clearfix row g-0">
						<!-- Column -->
						<div class="column col-lg-8 col-md-12 col-sm-12">

							<!-- News Block -->
							<div class="news-block">
								<div class="inner-box">
									<div class="clearfix">
										<!-- Image Column -->
										<div class="image-column col-lg-6 col-md-6 col-sm-12">
											<div class="inner-column">
												<div class="image">
													<a><img src="<?= $web_url ?>/front/images/resource/news-1.jpg" alt="" /></a>
												</div>
											</div>
										</div>
										<!-- Content Column -->
										<div class="content-column col-lg-6 col-md-6 col-sm-12">
											<div class="inner-column">
												<div class="arrow-one"></div>
												<div class="title">Home Mortgage Loans</div>
												<h4><a>The keys to your dream home are within reach — and we can help you get them with affordable and flexible rates.</a></h4>
												<div class="post-date">December 14th, 2020 by <span>Admin</span></div>
											</div>
										</div>
									</div>
								</div>
							</div>

							<!-- News Block -->
							<div class="news-block">
								<div class="inner-box">
									<div class="clearfix row g-0">
										<!-- Content Column -->
										<div class="content-column col-lg-6 col-md-6 col-sm-12 order-lg-1 order-2">
											<div class="inner-column">
												<div class="arrow-two"></div>
												<div class="title">Personal Needs</div>
												<h4><a>Whether it's a sudden expense or just vacation to get away from everything, <?= $pageTitle ?> can make it happen.</a></h4>
												<div class="post-date">June 6th, 2003 by <span>Admin</span></div>
											</div>
										</div>
										<!-- Image Column -->
										<div class="image-column col-lg-6 col-md-6 col-sm-12 order-lg-2 order-1">
											<div class="inner-column">
												<div class="image">
													<a><img src="<?= $web_url ?>/front/images/resource/news-2.jpg" alt="" /></a>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>

						</div>

						<!-- Column -->
						<div class="column col-lg-4 col-md-12 col-sm-12">
							<!-- News Block Two -->
							<div class="news-block-two">
								<div class="inner-box">
									<div class="image">
										<a><img src="<?= $web_url ?>/front/images/resource/news-3.jpg" alt="" /></a>
										<div class="arrow"></div>
									</div>
									<div class="lower-content">
										<div class="title">HSA</div>
										<h4><a>An HSA (Health Saving Account) helps employees save in advance for future medical expenses.</a></h4>
										<div class="post-date">May 13th, 2013 by <span>Admin</span></div>
									</div>
								</div>
							</div>
						</div>

					</div>
				</div>
			</div>
		</div>
		<!-- End Blog Section -->

		

<?php
include_once("front/footer.php");

?>