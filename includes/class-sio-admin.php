<?php
/**
 * Admin controller.
 *
 * @package Simple_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin UI.
 */
class SIO_Admin {
	/** @var string */
	private $hook_suffix = '';

	/** @var SIO_Options */
	private $options;

	/** @var SIO_Server_Capabilities */
	private $capabilities;

	/**
	 * Constructor.
	 *
	 * @param SIO_Options             $options Options.
	 * @param SIO_Server_Capabilities $capabilities Capabilities.
	 */
	public function __construct( SIO_Options $options, SIO_Server_Capabilities $capabilities ) {
		$this->options      = $options;
		$this->capabilities = $capabilities;
	}

	/** Register admin hooks. */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_tools_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_body_class', array( $this, 'add_admin_body_class' ) );
		add_filter( 'manage_media_columns', array( $this, 'add_media_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_media_column' ), 10, 2 );
	}

	/** Add page under Tools. */
	public function add_tools_page() {
		$this->hook_suffix = add_management_page(
			__( 'Simple Image Optimizer', 'simple-image-optimizer' ),
			__( 'Simple Image Optimizer', 'simple-image-optimizer' ),
			'manage_options',
			'simple-image-optimizer',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue assets on plugin page and media library.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->hook_suffix && 'upload.php' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'simple-image-optimizer-admin', SIO_URL . 'assets/css/admin.css', array(), SIO_VERSION );
		wp_enqueue_script( 'simple-image-optimizer-admin', SIO_URL . 'assets/js/admin.js', array(), SIO_VERSION, true );

		wp_localize_script(
			'simple-image-optimizer-admin',
			'sioAdmin',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( SIO_Ajax::NONCE_ACTION ),
				'batchSize'        => (int) $this->options->get()['batch_size'],
				'scanning'         => __( 'Scanning media library...', 'simple-image-optimizer' ),
				'scanComplete'     => __( 'Scan complete.', 'simple-image-optimizer' ),
				'optimizing'       => __( 'Optimizing images...', 'simple-image-optimizer' ),
				'complete'         => __( 'Optimization complete.', 'simple-image-optimizer' ),
				'noImages'         => __( 'No candidate images found.', 'simple-image-optimizer' ),
				'confirmOptimize'  => __( 'Start optimizing the selected images? Backups are created when enabled in settings.', 'simple-image-optimizer' ),
				'confirmRestore'   => __( 'Restore this image from the local backup? Generated WebP files created by this plugin will be removed.', 'simple-image-optimizer' ),
				'restored'         => __( 'Restored from backup.', 'simple-image-optimizer' ),
				'copied'           => __( 'Copied.', 'simple-image-optimizer' ),
				'copyFailed'       => __( 'Could not copy the URL automatically. Please copy it manually.', 'simple-image-optimizer' ),
				'genericError'     => __( 'Something went wrong. Please try again.', 'simple-image-optimizer' ),
				'bytesSavedLabel'  => __( 'estimated saved', 'simple-image-optimizer' ),
				'labels'           => array(
					'optimized' => __( 'Optimized', 'simple-image-optimizer' ),
					'skipped'   => __( 'Skipped', 'simple-image-optimizer' ),
					'error'     => __( 'Error', 'simple-image-optimizer' ),
					'restored'  => __( 'Restored', 'simple-image-optimizer' ),
					'before'    => __( 'Before:', 'simple-image-optimizer' ),
					'after'     => __( 'After:', 'simple-image-optimizer' ),
					'saved'     => __( 'Saved:', 'simple-image-optimizer' ),
					'webp'      => __( 'WebP', 'simple-image-optimizer' ),
					'backup'    => __( 'Backup', 'simple-image-optimizer' ),
					'sizes'     => __( 'Sizes:', 'simple-image-optimizer' ),
					'kept'      => __( 'Kept original', 'simple-image-optimizer' ),
				),
			)
		);
	}

	/**
	 * Add page-specific body class.
	 *
	 * @param string $classes Current classes.
	 * @return string
	 */
	public function add_admin_body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'tools_page_simple-image-optimizer' === $screen->id ) {
			$classes .= ' simple-image-optimizer-admin-page';
		}
		return $classes;
	}

	/** Render admin page. */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'simple-image-optimizer' ) );
		}

		$saved = false;
		if ( isset( $_POST['sio_action'] ) ) {
			check_admin_referer( 'sio_save_settings', 'sio_nonce' );
			$this->save_settings();
			$saved = true;
		}

		$options      = $this->options->get();
		$stats        = $this->options->get_stats();
		$recent       = $this->options->get_recent_results();
		$capabilities = $this->capabilities->get();
		$ready        = ! empty( $capabilities['can_process_local'] ) && ! empty( $capabilities['uploads_writable'] );
		$active_tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'optimization';
		$active_tab   = in_array( $active_tab, array( 'optimization', 'diagnostics' ), true ) ? $active_tab : 'optimization';
		?>
		<div class="wrap">
			<div class="wphubb-admin sio-admin">
				<div class="wphubb-header">
					<div>
						<span class="wphubb-eyebrow"><?php echo esc_html__( 'WPHubb Plugin', 'simple-image-optimizer' ); ?></span>
						<h1><?php echo esc_html__( 'Simple Image Optimizer', 'simple-image-optimizer' ); ?></h1>
						<p><?php echo esc_html__( 'Optimize existing WordPress media library images locally, in safe batches, without external services.', 'simple-image-optimizer' ); ?></p>
					</div>
					<span class="wphubb-badge <?php echo esc_attr( $ready ? 'wphubb-badge-active' : 'wphubb-badge-warning' ); ?>">
						<?php echo esc_html( $ready ? __( 'Ready', 'simple-image-optimizer' ) : __( 'Needs attention', 'simple-image-optimizer' ) ); ?>
					</span>
				</div>

				<?php $this->render_tabs( $active_tab ); ?>

				<?php if ( $saved ) : ?>
					<div class="wphubb-notice wphubb-notice-success"><strong><?php echo esc_html__( 'Settings saved.', 'simple-image-optimizer' ); ?></strong></div>
				<?php endif; ?>

				<?php if ( ! $ready && 'optimization' === $active_tab ) : ?>
					<div class="wphubb-notice wphubb-notice-warning"><strong><?php echo esc_html__( 'Local optimization requires GD or Imagick and a writable uploads folder.', 'simple-image-optimizer' ); ?></strong></div>
				<?php endif; ?>

				<?php if ( 'diagnostics' === $active_tab ) : ?>
					<?php $this->render_diagnostics_tab( $options, $stats, $recent, $capabilities ); ?>
				<?php else : ?>
					<div class="wphubb-grid wphubb-grid-2 sio-main-grid">
						<div class="sio-main-column">
							<?php $this->render_server_card( $capabilities ); ?>
							<?php $this->render_optimizer_card( $ready ); ?>
							<?php $this->render_stats_card( $stats ); ?>
						</div>
						<div class="sio-settings-column">
							<?php $this->render_settings_form( $options ); ?>
						</div>
					</div>
					<?php $this->render_recent_results_card( $recent ); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render internal admin tabs.
	 *
	 * @param string $active_tab Active tab key.
	 * @return void
	 */
	private function render_tabs( $active_tab ) {
		$base_url = menu_page_url( 'simple-image-optimizer', false );
		$tabs     = array(
			'optimization' => __( 'Optimization', 'simple-image-optimizer' ),
			'diagnostics'  => __( 'Diagnostics', 'simple-image-optimizer' ),
		);
		?>
		<nav class="sio-tabs" aria-label="<?php echo esc_attr__( 'Simple Image Optimizer sections', 'simple-image-optimizer' ); ?>">
			<?php foreach ( $tabs as $tab => $label ) : ?>
				<?php
				$url = 'optimization' === $tab ? remove_query_arg( 'tab', $base_url ) : add_query_arg( 'tab', $tab, $base_url );
				?>
				<a class="sio-tab <?php echo esc_attr( $active_tab === $tab ? 'sio-tab-active' : '' ); ?>" href="<?php echo esc_url( $url ); ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * Render diagnostics tab.
	 *
	 * @param array $options Plugin options.
	 * @param array $stats Stats.
	 * @param array $recent Recent result rows.
	 * @param array $capabilities Server capabilities.
	 * @return void
	 */
	private function render_diagnostics_tab( array $options, array $stats, array $recent, array $capabilities ) {
		$report = $this->build_diagnostic_report( $options, $stats, $recent, $capabilities );
		?>
		<div class="sio-diagnostics">
			<div class="wphubb-card">
				<h2><?php echo esc_html__( 'Diagnostics', 'simple-image-optimizer' ); ?></h2>
				<p><?php echo esc_html__( 'Use this section when something does not behave as expected. It keeps technical details separate from the normal optimization flow.', 'simple-image-optimizer' ); ?></p>
				<div class="wphubb-notice wphubb-notice-info sio-inline-notice">
					<strong><?php echo esc_html__( 'Privacy note:', 'simple-image-optimizer' ); ?></strong>
					<span><?php echo esc_html__( 'The report avoids listing local file paths, but review it before sharing it publicly.', 'simple-image-optimizer' ); ?></span>
				</div>
			</div>

			<div class="wphubb-grid wphubb-grid-2 sio-diagnostics-grid">
				<div>
					<?php $this->render_diagnostic_environment_card( $capabilities ); ?>
					<?php $this->render_diagnostic_options_card( $options ); ?>
				</div>
				<div>
					<?php $this->render_diagnostic_stats_card( $stats ); ?>
					<?php $this->render_diagnostic_recent_events_card( $recent ); ?>
				</div>
			</div>

			<div class="wphubb-card">
				<h2><?php echo esc_html__( 'Copy diagnostic report', 'simple-image-optimizer' ); ?></h2>
				<p><?php echo esc_html__( 'Copy this report when you need to review a support case or compare environments.', 'simple-image-optimizer' ); ?></p>
				<textarea id="sio-diagnostic-report" class="wphubb-textarea sio-diagnostic-report" readonly><?php echo esc_textarea( $report ); ?></textarea>
				<div class="sio-actions sio-diagnostic-actions">
					<button type="button" class="wphubb-button wphubb-button-primary" data-sio-copy-report="#sio-diagnostic-report"><?php echo esc_html__( 'Copy report', 'simple-image-optimizer' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render server status card.
	 *
	 * @param array $capabilities Capabilities.
	 * @return void
	 */
	private function render_server_card( array $capabilities ) {
		?>
		<div class="wphubb-card">
			<h2><?php echo esc_html__( 'Server status', 'simple-image-optimizer' ); ?></h2>
			<p><?php echo esc_html__( 'The plugin processes images locally. These checks show what this server can handle.', 'simple-image-optimizer' ); ?></p>
			<div class="sio-status-grid">
				<?php $this->render_status_item( __( 'GD', 'simple-image-optimizer' ), ! empty( $capabilities['gd'] ) ); ?>
				<?php $this->render_status_item( __( 'Imagick', 'simple-image-optimizer' ), ! empty( $capabilities['imagick'] ) ); ?>
				<?php $this->render_status_item( __( 'WebP', 'simple-image-optimizer' ), ! empty( $capabilities['webp'] ) ); ?>
				<?php $this->render_status_item( __( 'Uploads writable', 'simple-image-optimizer' ), ! empty( $capabilities['uploads_writable'] ) ); ?>
			</div>
			<div class="sio-muted-line">
				<?php echo esc_html__( 'Preferred editor:', 'simple-image-optimizer' ); ?> <strong><?php echo esc_html( $capabilities['preferred_editor'] ); ?></strong>
			</div>
		</div>
		<?php
	}

	/** Render one status item. */
	private function render_status_item( $label, $enabled ) {
		?>
		<div class="sio-status-item">
			<span class="wphubb-badge <?php echo esc_attr( $enabled ? 'wphubb-badge-active' : 'wphubb-badge-warning' ); ?>"><?php echo esc_html( $enabled ? __( 'Yes', 'simple-image-optimizer' ) : __( 'No', 'simple-image-optimizer' ) ); ?></span>
			<strong><?php echo esc_html( $label ); ?></strong>
		</div>
		<?php
	}

	/** Render optimizer card. */
	private function render_optimizer_card( $ready ) {
		?>
		<div class="wphubb-card">
			<h2><?php echo esc_html__( 'Bulk optimization', 'simple-image-optimizer' ); ?></h2>
			<p><?php echo esc_html__( 'Scan the media library, then optimize candidate JPEG and PNG images in small batches.', 'simple-image-optimizer' ); ?></p>
			<div class="sio-actions">
				<button type="button" class="wphubb-button wphubb-button-secondary" data-sio-scan <?php disabled( ! $ready ); ?>><?php echo esc_html__( 'Scan media library', 'simple-image-optimizer' ); ?></button>
				<button type="button" class="wphubb-button wphubb-button-primary" data-sio-optimize disabled><?php echo esc_html__( 'Optimize found images', 'simple-image-optimizer' ); ?></button>
			</div>
			<div class="sio-progress" aria-live="polite">
				<div class="sio-progress-track"><span class="sio-progress-bar" data-sio-progress-bar></span></div>
				<div class="sio-progress-text" data-sio-progress-text><?php echo esc_html__( 'No scan started yet.', 'simple-image-optimizer' ); ?></div>
			</div>
			<ul class="sio-log" data-sio-log></ul>
		</div>
		<?php
	}

	/** Render stats card. */
	private function render_stats_card( array $stats ) {
		$saved = max( 0, (int) $stats['bytes_before'] - (int) $stats['bytes_after'] );
		?>
		<div class="wphubb-card">
			<h2><?php echo esc_html__( 'Stats', 'simple-image-optimizer' ); ?></h2>
			<div class="sio-metric-grid" data-sio-stats>
				<div class="sio-metric"><span><?php echo esc_html__( 'Processed', 'simple-image-optimizer' ); ?></span><strong data-sio-stat="processed"><?php echo esc_html( $stats['processed'] ); ?></strong></div>
				<div class="sio-metric"><span><?php echo esc_html__( 'Skipped', 'simple-image-optimizer' ); ?></span><strong data-sio-stat="skipped"><?php echo esc_html( $stats['skipped'] ); ?></strong></div>
				<div class="sio-metric"><span><?php echo esc_html__( 'Errors', 'simple-image-optimizer' ); ?></span><strong data-sio-stat="errors"><?php echo esc_html( $stats['errors'] ); ?></strong></div>
				<div class="sio-metric"><span><?php echo esc_html__( 'Saved', 'simple-image-optimizer' ); ?></span><strong data-sio-stat="saved"><?php echo esc_html( size_format( $saved, 1 ) ); ?></strong></div>
			</div>
			<p class="sio-muted-line"><?php echo esc_html__( 'Last run:', 'simple-image-optimizer' ); ?> <strong data-sio-stat="last_run"><?php echo esc_html( $stats['last_run'] ? $stats['last_run'] : __( 'Never', 'simple-image-optimizer' ) ); ?></strong></p>
		</div>
		<?php
	}

	/** Render settings form. */
	private function render_settings_form( array $options ) {
		?>
		<form method="post" action="" class="wphubb-card sio-settings-card">
			<?php wp_nonce_field( 'sio_save_settings', 'sio_nonce' ); ?>
			<input type="hidden" name="sio_action" value="save_settings" />
			<h2><?php echo esc_html__( 'Optimization settings', 'simple-image-optimizer' ); ?></h2>
			<p><?php echo esc_html__( 'Simple defaults focused on visible quality and safe batch processing.', 'simple-image-optimizer' ); ?></p>

			<div class="wphubb-field">
				<label for="sio-quality-preset"><?php echo esc_html__( 'Quality preset', 'simple-image-optimizer' ); ?></label>
				<select id="sio-quality-preset" class="wphubb-select" name="quality_preset">
					<option value="high" <?php selected( $options['quality_preset'], 'high' ); ?>><?php echo esc_html__( 'High quality', 'simple-image-optimizer' ); ?></option>
					<option value="balanced" <?php selected( $options['quality_preset'], 'balanced' ); ?>><?php echo esc_html__( 'Balanced', 'simple-image-optimizer' ); ?></option>
					<option value="compressed" <?php selected( $options['quality_preset'], 'compressed' ); ?>><?php echo esc_html__( 'More compression', 'simple-image-optimizer' ); ?></option>
				</select>
				<div class="wphubb-field-description"><?php echo esc_html__( 'High quality is recommended to avoid visible degradation.', 'simple-image-optimizer' ); ?></div>
			</div>

			<div class="sio-two-cols">
				<div class="wphubb-field">
					<label for="sio-max-width"><?php echo esc_html__( 'Max width', 'simple-image-optimizer' ); ?></label>
					<input id="sio-max-width" class="wphubb-input" type="number" name="max_width" value="<?php echo esc_attr( $options['max_width'] ); ?>" min="0" max="10000" />
				</div>
				<div class="wphubb-field">
					<label for="sio-max-height"><?php echo esc_html__( 'Max height', 'simple-image-optimizer' ); ?></label>
					<input id="sio-max-height" class="wphubb-input" type="number" name="max_height" value="<?php echo esc_attr( $options['max_height'] ); ?>" min="0" max="10000" />
				</div>
			</div>

			<div class="wphubb-field">
				<label for="sio-batch-size"><?php echo esc_html__( 'Batch size', 'simple-image-optimizer' ); ?></label>
				<input id="sio-batch-size" class="wphubb-input sio-small-input" type="number" name="batch_size" value="<?php echo esc_attr( $options['batch_size'] ); ?>" min="1" max="10" />
				<div class="wphubb-field-description"><?php echo esc_html__( 'Small batches reduce timeout risk on shared hosting.', 'simple-image-optimizer' ); ?></div>
			</div>

			<label class="wphubb-toggle sio-toggle-line">
				<input type="checkbox" name="keep_originals" value="1" <?php checked( $options['keep_originals'] ); ?> />
				<span class="wphubb-toggle-slider"></span>
				<span><?php echo esc_html__( 'Keep local backup of original files', 'simple-image-optimizer' ); ?></span>
			</label>

			<label class="wphubb-toggle sio-toggle-line">
				<input type="checkbox" name="generate_webp" value="1" <?php checked( $options['generate_webp'] ); ?> />
				<span class="wphubb-toggle-slider"></span>
				<span><?php echo esc_html__( 'Generate WebP files when supported', 'simple-image-optimizer' ); ?></span>
			</label>

			<label class="wphubb-toggle sio-toggle-line">
				<input type="checkbox" name="serve_webp_frontend" value="1" <?php checked( $options['serve_webp_frontend'] ); ?> />
				<span class="wphubb-toggle-slider"></span>
				<span><?php echo esc_html__( 'Serve generated WebP on the frontend for standard WordPress images', 'simple-image-optimizer' ); ?></span>
			</label>
			<div class="wphubb-field-description sio-toggle-description">
				<?php echo esc_html__( 'Safe opt-in mode. It replaces local JPEG/PNG uploads with existing WebP files in standard WordPress image output. Some page builder background images may not be covered.', 'simple-image-optimizer' ); ?>
			</div>

			<label class="wphubb-toggle sio-toggle-line">
				<input type="checkbox" name="optimize_sizes" value="1" <?php checked( $options['optimize_sizes'] ); ?> />
				<span class="wphubb-toggle-slider"></span>
				<span><?php echo esc_html__( 'Optimize generated WordPress sizes', 'simple-image-optimizer' ); ?></span>
			</label>

			<label class="wphubb-toggle sio-toggle-line">
				<input type="checkbox" name="auto_optimize" value="1" <?php checked( $options['auto_optimize'] ); ?> />
				<span class="wphubb-toggle-slider"></span>
				<span><?php echo esc_html__( 'Automatically optimize new uploads', 'simple-image-optimizer' ); ?></span>
			</label>

			<label class="wphubb-toggle sio-toggle-line">
				<input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( $options['delete_on_uninstall'] ); ?> />
				<span class="wphubb-toggle-slider"></span>
				<span><?php echo esc_html__( 'Delete plugin settings on uninstall', 'simple-image-optimizer' ); ?></span>
			</label>

			<div class="wphubb-notice wphubb-notice-info sio-inline-notice">
				<strong><?php echo esc_html__( 'Note:', 'simple-image-optimizer' ); ?></strong>
				<span><?php echo esc_html__( 'Optimization can reduce file size while keeping high visual quality, but it is not a perfect lossless process.', 'simple-image-optimizer' ); ?></span>
			</div>

			<p class="submit sio-submit"><button type="submit" class="wphubb-button wphubb-button-primary"><?php echo esc_html__( 'Save settings', 'simple-image-optimizer' ); ?></button></p>
		</form>
		<?php
	}

	/**
	 * Render recent results.
	 *
	 * @param array $recent Recent result rows.
	 * @return void
	 */
	private function render_recent_results_card( array $recent ) {
		?>
		<div class="wphubb-card">
			<h2><?php echo esc_html__( 'Latest results', 'simple-image-optimizer' ); ?></h2>
			<p><?php echo esc_html__( 'Review what happened during the latest optimizations without opening the uploads folder.', 'simple-image-optimizer' ); ?></p>

			<div class="sio-results-list" data-sio-results-list>
				<?php if ( empty( $recent ) ) : ?>
					<div class="sio-empty-state" data-sio-empty-results>
						<?php echo esc_html__( 'No optimization results yet. Run a scan and optimize images to see details here.', 'simple-image-optimizer' ); ?>
					</div>
				<?php else : ?>
					<?php foreach ( array_reverse( $recent ) as $result ) : ?>
						<?php $this->render_recent_result_row( $result ); ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one recent result.
	 *
	 * @param array $result Result row.
	 * @return void
	 */
	private function render_recent_result_row( array $result ) {
		$status = isset( $result['status'] ) ? $result['status'] : 'error';
		$badge  = 'optimized' === $status ? 'wphubb-badge-active' : ( 'skipped' === $status ? 'wphubb-badge-inactive' : 'wphubb-badge-warning' );
		$title  = ! empty( $result['title'] ) ? $result['title'] : $result['filename'];
		$saved  = isset( $result['bytes_saved'] ) ? (int) $result['bytes_saved'] : 0;
		?>
		<div class="sio-result-row">
			<div class="sio-result-main">
				<span class="wphubb-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $this->get_status_label( $status ) ); ?></span>
				<strong><?php echo esc_html( $title ); ?></strong>
				<span><?php echo esc_html( $result['message'] ); ?></span>
			</div>

			<div class="sio-result-meta">
				<span><?php echo esc_html__( 'Before:', 'simple-image-optimizer' ); ?> <strong><?php echo esc_html( size_format( (int) $result['bytes_before'], 1 ) ); ?></strong></span>
				<span><?php echo esc_html__( 'After:', 'simple-image-optimizer' ); ?> <strong><?php echo esc_html( size_format( (int) $result['bytes_after'], 1 ) ); ?></strong></span>
				<span><?php echo esc_html__( 'Saved:', 'simple-image-optimizer' ); ?> <strong><?php echo esc_html( size_format( $saved, 1 ) ); ?></strong></span>
				<span><?php echo esc_html__( 'Sizes:', 'simple-image-optimizer' ); ?> <strong><?php echo esc_html( (int) $result['sizes_processed'] ); ?></strong></span>
			</div>

			<div class="sio-result-flags">
				<span class="sio-flag <?php echo ! empty( $result['webp_created'] ) ? 'sio-flag-ok' : ''; ?>"><?php echo esc_html__( 'WebP', 'simple-image-optimizer' ); ?></span>
				<span class="sio-flag <?php echo ! empty( $result['backup_created'] ) ? 'sio-flag-ok' : ''; ?>"><?php echo esc_html__( 'Backup', 'simple-image-optimizer' ); ?></span>
				<?php if ( ! empty( $result['sizes_processed'] ) ) : ?>
					<span class="sio-flag sio-flag-ok">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: optimized generated image sizes. */
								__( '%d sizes', 'simple-image-optimizer' ),
								(int) $result['sizes_processed']
							)
						);
						?>
					</span>
				<?php endif; ?>
				<?php if ( ! empty( $result['kept_originals'] ) ) : ?>
					<span class="sio-flag">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: files kept unchanged because optimized output was larger. */
								__( '%d kept', 'simple-image-optimizer' ),
								(int) $result['kept_originals']
							)
						);
						?>
					</span>
				<?php endif; ?>
				<?php if ( ! empty( $result['time'] ) ) : ?>
					<span class="sio-result-time"><?php echo esc_html( $result['time'] ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get a readable result status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function get_status_label( $status ) {
		if ( 'optimized' === $status ) {
			return __( 'Optimized', 'simple-image-optimizer' );
		}

		if ( 'skipped' === $status ) {
			return __( 'Skipped', 'simple-image-optimizer' );
		}

		if ( 'restored' === $status ) {
			return __( 'Restored', 'simple-image-optimizer' );
		}

		return __( 'Error', 'simple-image-optimizer' );
	}

	/**
	 * Render environment diagnostics.
	 *
	 * @param array $capabilities Server capabilities.
	 * @return void
	 */
	private function render_diagnostic_environment_card( array $capabilities ) {
		$uploads = wp_get_upload_dir();
		$rows    = array(
			__( 'Plugin version', 'simple-image-optimizer' )     => SIO_VERSION,
			__( 'WordPress version', 'simple-image-optimizer' )  => get_bloginfo( 'version' ),
			__( 'PHP version', 'simple-image-optimizer' )        => PHP_VERSION,
			__( 'Environment type', 'simple-image-optimizer' )   => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : __( 'Unknown', 'simple-image-optimizer' ),
			__( 'WP_DEBUG', 'simple-image-optimizer' )           => $this->format_bool( defined( 'WP_DEBUG' ) && WP_DEBUG ),
			__( 'Memory limit', 'simple-image-optimizer' )       => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : ini_get( 'memory_limit' ),
			__( 'PHP memory limit', 'simple-image-optimizer' )   => ini_get( 'memory_limit' ),
			__( 'Max execution time', 'simple-image-optimizer' ) => ini_get( 'max_execution_time' ) . 's',
			__( 'Upload max filesize', 'simple-image-optimizer' ) => ini_get( 'upload_max_filesize' ),
			__( 'Post max size', 'simple-image-optimizer' )      => ini_get( 'post_max_size' ),
			__( 'GD', 'simple-image-optimizer' )                 => $this->format_bool( ! empty( $capabilities['gd'] ) ),
			__( 'Imagick', 'simple-image-optimizer' )            => $this->format_bool( ! empty( $capabilities['imagick'] ) ),
			__( 'WebP support', 'simple-image-optimizer' )       => $this->format_bool( ! empty( $capabilities['webp'] ) ),
			__( 'Uploads writable', 'simple-image-optimizer' )   => $this->format_bool( ! empty( $capabilities['uploads_writable'] ) ),
			__( 'Uploads URL available', 'simple-image-optimizer' ) => $this->format_bool( ! empty( $uploads['baseurl'] ) ),
			__( 'Preferred editor', 'simple-image-optimizer' )   => isset( $capabilities['preferred_editor'] ) ? $capabilities['preferred_editor'] : __( 'Unknown', 'simple-image-optimizer' ),
		);
		?>
		<div class="wphubb-card">
			<h2><?php echo esc_html__( 'Environment', 'simple-image-optimizer' ); ?></h2>
			<p><?php echo esc_html__( 'Technical context that affects local optimization and WebP generation.', 'simple-image-optimizer' ); ?></p>
			<?php $this->render_diagnostic_rows( $rows ); ?>
		</div>
		<?php
	}

	/**
	 * Render plugin option diagnostics.
	 *
	 * @param array $options Plugin options.
	 * @return void
	 */
	private function render_diagnostic_options_card( array $options ) {
		$rows = array(
			__( 'Quality preset', 'simple-image-optimizer' )          => $options['quality_preset'],
			__( 'JPEG quality', 'simple-image-optimizer' )            => (string) $options['jpeg_quality'],
			__( 'WebP quality', 'simple-image-optimizer' )            => (string) $options['webp_quality'],
			__( 'Max width', 'simple-image-optimizer' )               => (string) $options['max_width'],
			__( 'Max height', 'simple-image-optimizer' )              => (string) $options['max_height'],
			__( 'Batch size', 'simple-image-optimizer' )              => (string) $options['batch_size'],
			__( 'Keep backups', 'simple-image-optimizer' )            => $this->format_bool( ! empty( $options['keep_originals'] ) ),
			__( 'Generate WebP', 'simple-image-optimizer' )           => $this->format_bool( ! empty( $options['generate_webp'] ) ),
			__( 'Serve WebP on frontend', 'simple-image-optimizer' )  => $this->format_bool( ! empty( $options['serve_webp_frontend'] ) ),
			__( 'Optimize generated sizes', 'simple-image-optimizer' ) => $this->format_bool( ! empty( $options['optimize_sizes'] ) ),
			__( 'Auto optimize uploads', 'simple-image-optimizer' )   => $this->format_bool( ! empty( $options['auto_optimize'] ) ),
		);
		?>
		<div class="wphubb-card">
			<h2><?php echo esc_html__( 'Plugin settings', 'simple-image-optimizer' ); ?></h2>
			<p><?php echo esc_html__( 'Current configuration used by the optimizer.', 'simple-image-optimizer' ); ?></p>
			<?php $this->render_diagnostic_rows( $rows ); ?>
		</div>
		<?php
	}

	/**
	 * Render stats diagnostics.
	 *
	 * @param array $stats Stats.
	 * @return void
	 */
	private function render_diagnostic_stats_card( array $stats ) {
		$saved = max( 0, (int) $stats['bytes_before'] - (int) $stats['bytes_after'] );
		$rows  = array(
			__( 'Processed', 'simple-image-optimizer' )      => (string) $stats['processed'],
			__( 'Skipped', 'simple-image-optimizer' )        => (string) $stats['skipped'],
			__( 'Errors', 'simple-image-optimizer' )         => (string) $stats['errors'],
			__( 'Bytes before', 'simple-image-optimizer' )   => size_format( (int) $stats['bytes_before'], 1 ),
			__( 'Bytes after', 'simple-image-optimizer' )    => size_format( (int) $stats['bytes_after'], 1 ),
			__( 'Estimated saved', 'simple-image-optimizer' ) => size_format( $saved, 1 ),
			__( 'Last run', 'simple-image-optimizer' )       => $stats['last_run'] ? $stats['last_run'] : __( 'Never', 'simple-image-optimizer' ),
		);
		?>
		<div class="wphubb-card">
			<h2><?php echo esc_html__( 'Optimization stats', 'simple-image-optimizer' ); ?></h2>
			<p><?php echo esc_html__( 'Stored counters from previous optimization runs.', 'simple-image-optimizer' ); ?></p>
			<?php $this->render_diagnostic_rows( $rows ); ?>
		</div>
		<?php
	}

	/**
	 * Render recent diagnostic events.
	 *
	 * @param array $recent Recent result rows.
	 * @return void
	 */
	private function render_diagnostic_recent_events_card( array $recent ) {
		?>
		<div class="wphubb-card">
			<h2><?php echo esc_html__( 'Recent events', 'simple-image-optimizer' ); ?></h2>
			<p><?php echo esc_html__( 'Latest optimization outcomes that can explain skipped images or errors.', 'simple-image-optimizer' ); ?></p>
			<div class="sio-diagnostic-events">
				<?php if ( empty( $recent ) ) : ?>
					<div class="sio-empty-state"><?php echo esc_html__( 'No recent events stored yet.', 'simple-image-optimizer' ); ?></div>
				<?php else : ?>
					<?php foreach ( array_reverse( array_slice( $recent, -5 ) ) as $result ) : ?>
						<?php $this->render_diagnostic_event( $result ); ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one diagnostic event.
	 *
	 * @param array $result Recent result row.
	 * @return void
	 */
	private function render_diagnostic_event( array $result ) {
		$status = isset( $result['status'] ) ? $result['status'] : 'error';
		$badge  = 'optimized' === $status ? 'wphubb-badge-active' : ( 'skipped' === $status ? 'wphubb-badge-inactive' : 'wphubb-badge-warning' );
		$title  = ! empty( $result['title'] ) ? $result['title'] : $result['filename'];
		?>
		<div class="sio-diagnostic-event">
			<span class="wphubb-badge <?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $this->get_status_label( $status ) ); ?></span>
			<strong><?php echo esc_html( $title ); ?></strong>
			<span><?php echo esc_html( $result['message'] ); ?></span>
			<?php if ( ! empty( $result['time'] ) ) : ?>
				<em><?php echo esc_html( $result['time'] ); ?></em>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render key/value diagnostic rows.
	 *
	 * @param array $rows Rows.
	 * @return void
	 */
	private function render_diagnostic_rows( array $rows ) {
		?>
		<div class="sio-diagnostic-rows">
			<?php foreach ( $rows as $label => $value ) : ?>
				<div class="sio-diagnostic-row">
					<span><?php echo esc_html( $label ); ?></span>
					<strong><?php echo esc_html( (string) $value ); ?></strong>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Build a plain-text diagnostic report.
	 *
	 * @param array $options Plugin options.
	 * @param array $stats Stats.
	 * @param array $recent Recent result rows.
	 * @param array $capabilities Server capabilities.
	 * @return string
	 */
	private function build_diagnostic_report( array $options, array $stats, array $recent, array $capabilities ) {
		$saved       = max( 0, (int) $stats['bytes_before'] - (int) $stats['bytes_after'] );
		$recent_rows = array_slice( array_reverse( $recent ), 0, 5 );
		$lines       = array(
			'Simple Image Optimizer diagnostic report',
			'Generated: ' . current_time( 'mysql' ),
			'',
			'[Environment]',
			'Plugin version: ' . SIO_VERSION,
			'WordPress version: ' . get_bloginfo( 'version' ),
			'PHP version: ' . PHP_VERSION,
			'Environment type: ' . ( function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'unknown' ),
			'WP_DEBUG: ' . $this->format_bool( defined( 'WP_DEBUG' ) && WP_DEBUG ),
			'WP memory limit: ' . ( defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : ini_get( 'memory_limit' ) ),
			'PHP memory limit: ' . ini_get( 'memory_limit' ),
			'Max execution time: ' . ini_get( 'max_execution_time' ) . 's',
			'Upload max filesize: ' . ini_get( 'upload_max_filesize' ),
			'Post max size: ' . ini_get( 'post_max_size' ),
			'GD: ' . $this->format_bool( ! empty( $capabilities['gd'] ) ),
			'Imagick: ' . $this->format_bool( ! empty( $capabilities['imagick'] ) ),
			'WebP support: ' . $this->format_bool( ! empty( $capabilities['webp'] ) ),
			'Uploads writable: ' . $this->format_bool( ! empty( $capabilities['uploads_writable'] ) ),
			'Preferred editor: ' . ( isset( $capabilities['preferred_editor'] ) ? $capabilities['preferred_editor'] : 'unknown' ),
			'',
			'[Settings]',
			'Quality preset: ' . $options['quality_preset'],
			'JPEG quality: ' . $options['jpeg_quality'],
			'WebP quality: ' . $options['webp_quality'],
			'Max width: ' . $options['max_width'],
			'Max height: ' . $options['max_height'],
			'Batch size: ' . $options['batch_size'],
			'Keep backups: ' . $this->format_bool( ! empty( $options['keep_originals'] ) ),
			'Generate WebP: ' . $this->format_bool( ! empty( $options['generate_webp'] ) ),
			'Serve WebP on frontend: ' . $this->format_bool( ! empty( $options['serve_webp_frontend'] ) ),
			'Optimize generated sizes: ' . $this->format_bool( ! empty( $options['optimize_sizes'] ) ),
			'Auto optimize uploads: ' . $this->format_bool( ! empty( $options['auto_optimize'] ) ),
			'',
			'[Stats]',
			'Processed: ' . $stats['processed'],
			'Skipped: ' . $stats['skipped'],
			'Errors: ' . $stats['errors'],
			'Bytes before: ' . size_format( (int) $stats['bytes_before'], 1 ),
			'Bytes after: ' . size_format( (int) $stats['bytes_after'], 1 ),
			'Estimated saved: ' . size_format( $saved, 1 ),
			'Last run: ' . ( $stats['last_run'] ? $stats['last_run'] : 'never' ),
			'',
			'[Recent events]',
		);

		if ( empty( $recent_rows ) ) {
			$lines[] = 'No recent events stored.';
		} else {
			foreach ( $recent_rows as $result ) {
				$title   = ! empty( $result['title'] ) ? $result['title'] : $result['filename'];
				$lines[] = sprintf(
					'#%d | %s | %s | %s',
					(int) $result['id'],
					isset( $result['status'] ) ? $result['status'] : 'error',
					$title,
					isset( $result['message'] ) ? $result['message'] : ''
				);
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Format a boolean value.
	 *
	 * @param bool $value Value.
	 * @return string
	 */
	private function format_bool( $value ) {
		return $value ? __( 'Yes', 'simple-image-optimizer' ) : __( 'No', 'simple-image-optimizer' );
	}

	/**
	 * Add optimization column to Media Library list view.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_media_column( $columns ) {
		$columns['sio_optimization'] = __( 'Optimization', 'simple-image-optimizer' );
		return $columns;
	}

	/**
	 * Render media column content.
	 *
	 * @param string $column_name Column name.
	 * @param int    $attachment_id Attachment ID.
	 * @return void
	 */
	public function render_media_column( $column_name, $attachment_id ) {
		if ( 'sio_optimization' !== $column_name ) {
			return;
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, SIO_Media_Scanner::SUPPORTED_MIME_TYPES, true ) ) {
			echo '<span class="sio-media-muted">' . esc_html__( 'Not supported', 'simple-image-optimizer' ) . '</span>';
			return;
		}

		$is_optimized    = '1' === (string) get_post_meta( $attachment_id, '_sio_optimized', true );
		$last_error      = (string) get_post_meta( $attachment_id, '_sio_last_error', true );
		$bytes_before    = (int) get_post_meta( $attachment_id, '_sio_original_size', true );
		$bytes_after     = (int) get_post_meta( $attachment_id, '_sio_optimized_size', true );
		$backup_path     = (string) get_post_meta( $attachment_id, '_sio_backup_path', true );
		$webp_path       = (string) get_post_meta( $attachment_id, '_sio_webp_path', true );
		$sizes_processed = (int) get_post_meta( $attachment_id, '_sio_sizes_processed', true );
		$saved           = max( 0, $bytes_before - $bytes_after );

		if ( '' === $webp_path ) {
			$webp_files = get_post_meta( $attachment_id, '_sio_webp_files', true );
			if ( is_array( $webp_files ) && ! empty( $webp_files[0] ) && is_string( $webp_files[0] ) ) {
				$webp_path = $webp_files[0];
			}
		}

		$webp_file = $this->get_upload_file_data_from_path( $webp_path );

		echo '<div class="sio-media-status">';

		if ( $is_optimized ) {
			echo '<span class="sio-media-badge sio-media-badge-ok">' . esc_html__( 'Optimized', 'simple-image-optimizer' ) . '</span>';
			if ( $saved > 0 ) {
				echo '<span class="sio-media-line">' . esc_html__( 'Saved:', 'simple-image-optimizer' ) . ' <strong>' . esc_html( size_format( $saved, 1 ) ) . '</strong></span>';
			}
			if ( '' !== $webp_path ) {
				echo '<span class="sio-media-badge sio-media-badge-soft">' . esc_html__( 'WebP', 'simple-image-optimizer' ) . '</span>';
			}
			if ( $sizes_processed > 0 ) {
				echo '<span class="sio-media-badge sio-media-badge-soft">' . esc_html( sprintf( __( '%d sizes', 'simple-image-optimizer' ), $sizes_processed ) ) . '</span>';
			}
			if ( '' !== $backup_path ) {
				echo '<button type="button" class="button button-small sio-media-restore" data-sio-restore-media="' . esc_attr( $attachment_id ) . '">' . esc_html__( 'Restore', 'simple-image-optimizer' ) . '</button>';
			}

			if ( '' !== $webp_file['url'] ) {
				echo '<span class="sio-media-actions">';
				echo '<a class="button button-small" href="' . esc_url( $webp_file['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View WebP', 'simple-image-optimizer' ) . '</a>';
				echo '<button type="button" class="button button-small" data-sio-copy-webp="' . esc_attr( $webp_file['url'] ) . '">' . esc_html__( 'Copy WebP URL', 'simple-image-optimizer' ) . '</button>';
				echo '</span>';
				if ( ! $webp_file['exists'] ) {
					echo '<span class="sio-media-line sio-media-line-warning">' . esc_html__( 'WebP URL resolved, but the file was not found on disk.', 'simple-image-optimizer' ) . '</span>';
				}
			} elseif ( '' !== $webp_path ) {
				echo '<span class="sio-media-line sio-media-line-warning">' . esc_html__( 'WebP exists in metadata, but its uploads URL could not be resolved.', 'simple-image-optimizer' ) . '</span>';
			}
		} elseif ( '' !== $last_error ) {
			echo '<span class="sio-media-badge sio-media-badge-error">' . esc_html__( 'Error', 'simple-image-optimizer' ) . '</span>';
			echo '<span class="sio-media-line">' . esc_html( $last_error ) . '</span>';
		} else {
			echo '<span class="sio-media-badge sio-media-badge-pending">' . esc_html__( 'Pending', 'simple-image-optimizer' ) . '</span>';
		}

		echo '</div>';
	}

	/**
	 * Get public URL and disk status for an uploads file path.
	 *
	 * @param string $path File path.
	 * @return array
	 */
	private function get_upload_file_data_from_path( $path ) {
		$data = array(
			'url'      => '',
			'path'     => '',
			'relative' => '',
			'exists'   => false,
		);

		if ( ! is_string( $path ) || '' === trim( $path ) ) {
			return $data;
		}

		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return $data;
		}

		$base_dir = wp_normalize_path( untrailingslashit( $uploads['basedir'] ) );
		$file     = wp_normalize_path( trim( $path ) );
		$relative = '';

		if ( $base_dir === $file || 0 === strpos( $file, $base_dir . '/' ) ) {
			$relative = ltrim( substr( $file, strlen( $base_dir ) ), '/' );
		} elseif ( $this->is_absolute_path( $file ) ) {
			$uploads_marker = '/wp-content/uploads/';
			$marker_pos     = strpos( $file, $uploads_marker );
			if ( false !== $marker_pos ) {
				$relative = substr( $file, $marker_pos + strlen( $uploads_marker ) );
			}
		} else {
			$relative         = ltrim( $file, '/' );
			$uploads_basename = wp_basename( $base_dir );
			if ( '' !== $uploads_basename && 0 === strpos( $relative, $uploads_basename . '/' ) ) {
				$relative = substr( $relative, strlen( $uploads_basename ) + 1 );
			}
		}

		$relative = ltrim( wp_normalize_path( $relative ), '/' );
		$relative_uploads_marker = 'wp-content/uploads/';
		$relative_marker_pos     = strpos( $relative, $relative_uploads_marker );
		if ( false !== $relative_marker_pos ) {
			$relative = substr( $relative, $relative_marker_pos + strlen( $relative_uploads_marker ) );
		}

		$base_url_path = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
		if ( is_string( $base_url_path ) && '' !== $base_url_path ) {
			$base_url_path       = ltrim( wp_normalize_path( $base_url_path ), '/' );
			$base_url_marker_pos = strpos( $base_url_path, $relative_uploads_marker );
			if ( false !== $base_url_marker_pos ) {
				$base_url_relative = substr( $base_url_path, $base_url_marker_pos + strlen( $relative_uploads_marker ) );
				if ( '' !== $base_url_relative && 0 === strpos( $relative, trailingslashit( $base_url_relative ) ) ) {
					$relative = substr( $relative, strlen( trailingslashit( $base_url_relative ) ) );
				}
			}
		}

		if ( '' === $relative || false !== strpos( $relative, '../' ) || '..' === $relative || 0 === strpos( $relative, '../' ) ) {
			return $data;
		}

		$file_path = wp_normalize_path( trailingslashit( $base_dir ) . $relative );

		$data['path']     = $file_path;
		$data['relative'] = $relative;
		$data['exists']   = file_exists( $file_path );
		$data['url']      = trailingslashit( $uploads['baseurl'] ) . implode( '/', array_map( 'rawurlencode', explode( '/', $relative ) ) );

		return $data;
	}

	/**
	 * Determine whether a normalized path is absolute.
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	private function is_absolute_path( $path ) {
		return 0 === strpos( $path, '/' ) || 1 === strpos( $path, ':' );
	}

	/** Save settings. */
	private function save_settings() {
		$preset    = isset( $_POST['quality_preset'] ) ? sanitize_key( wp_unslash( $_POST['quality_preset'] ) ) : 'high';
		$qualities = SIO_Options::qualities_for_preset( $preset );

		$this->options->update(
			array(
				'quality_preset'      => $preset,
				'jpeg_quality'        => $qualities['jpeg'],
				'webp_quality'        => $qualities['webp'],
				'max_width'           => isset( $_POST['max_width'] ) ? absint( wp_unslash( $_POST['max_width'] ) ) : 1920,
				'max_height'          => isset( $_POST['max_height'] ) ? absint( wp_unslash( $_POST['max_height'] ) ) : 1920,
				'batch_size'          => isset( $_POST['batch_size'] ) ? absint( wp_unslash( $_POST['batch_size'] ) ) : 3,
				'keep_originals'      => ! empty( $_POST['keep_originals'] ),
				'generate_webp'       => ! empty( $_POST['generate_webp'] ),
				'serve_webp_frontend' => ! empty( $_POST['serve_webp_frontend'] ),
				'optimize_sizes'      => ! empty( $_POST['optimize_sizes'] ),
				'auto_optimize'       => ! empty( $_POST['auto_optimize'] ),
				'delete_on_uninstall' => ! empty( $_POST['delete_on_uninstall'] ),
			)
		);
	}
}


