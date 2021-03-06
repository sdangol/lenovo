<header class="fl-page-header fl-page-header-primary<?php FLTheme::header_classes(); ?>"<?php FLTheme::header_data_attrs(); ?> itemscope="itemscope" itemtype="http://schema.org/WPHeader">
	<div class="fl-page-header-wrap">
		<div class="fl-page-header-container container">
			<div class="fl-page-header-row row">
				<div class="col-md-12 fl-page-header-logo-col">
					<div class="fl-page-header-logo" itemscope="itemscope" itemtype="http://schema.org/Organization">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" itemprop="url"><?php FLTheme::logo(); ?></a>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="fl-page-nav-wrap">
		<div class="fl-page-nav-container container">
			<nav class="fl-page-nav fl-nav navbar navbar-default" itemscope="itemscope" itemtype="http://schema.org/SiteNavigationElement">
				<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".fl-page-nav-collapse">
					<span><?php FLTheme::nav_toggle_text(); ?></span>
				</button>
				<div class="fl-page-nav-collapse collapse navbar-collapse">
					<?php

					wp_nav_menu(array(
						'theme_location' => 'header',
						'items_wrap' => '<ul id="%1$s" class="nav navbar-nav %2$s">%3$s</ul>',
						'container' => false,
						'fallback_cb' => 'FLTheme::nav_menu_fallback'
					));

					FLTheme::nav_search();

					?>
				</div>
			</nav>
		</div>
	</div>
</header><!-- .fl-page-header -->