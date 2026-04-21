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
	 * Enqueue assets only on plugin page.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
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
				'genericError'     => __( 'Something went wrong. Please try again.', 'simple-image-optimizer' ),
				'bytesSavedLabel'  => __( 'estimated saved', 'simple-image-optimizer' ),
				'labels'           => array(
					'optimized' => __( 'Optimized', 'simple-image-optimizer' ),
					'skipped'   => __( 'Skipped', 'simple-image-optimizer' ),
					'error'     => __( 'Error', 'simple-image-optimizer' ),
					'before'    => __( 'Before:', 'simple-image-optimizer' ),
					'after'     => __( 'After:', 'simple-image-optimizer' ),
					'saved'     => __( 'Saved:', 'simple-image-optimizer' ),
					'webp'      => __( 'WebP', 'simple-image-optimizer' ),
					'backup'    => __( 'Backup', 'simple-image-optimizer' ),
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

				<?php if ( $saved ) : ?>
					<div class="wphubb-notice wphubb-notice-success"><strong><?php echo esc_html__( 'Settings saved.', 'simple-image-optimizer' ); ?></strong></div>
				<?php endif; ?>

				<?php if ( ! $ready ) : ?>
					<div class="wphubb-notice wphubb-notice-warning"><strong><?php echo esc_html__( 'Local optimization requires GD or Imagick and a writable uploads folder.', 'simple-image-optimizer' ); ?></strong></div>
				<?php endif; ?>

				<div class="wphubb-grid wphubb-grid-2">
					<div>
						<?php $this->render_server_card( $capabilities ); ?>
						<?php $this->render_optimizer_card( $ready ); ?>
						<?php $this->render_stats_card( $stats ); ?>
						<?php $this->render_recent_results_card( $recent ); ?>
					</div>
					<div>
						<?php $this->render_settings_form( $options ); ?>
					</div>
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
			</div>

			<div class="sio-result-flags">
				<span class="sio-flag <?php echo ! empty( $result['webp_created'] ) ? 'sio-flag-ok' : ''; ?>"><?php echo esc_html__( 'WebP', 'simple-image-optimizer' ); ?></span>
				<span class="sio-flag <?php echo ! empty( $result['backup_created'] ) ? 'sio-flag-ok' : ''; ?>"><?php echo esc_html__( 'Backup', 'simple-image-optimizer' ); ?></span>
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

		return __( 'Error', 'simple-image-optimizer' );
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
				'delete_on_uninstall' => ! empty( $_POST['delete_on_uninstall'] ),
			)
		);
	}
}


